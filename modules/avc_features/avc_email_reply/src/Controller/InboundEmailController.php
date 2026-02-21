<?php

namespace Drupal\avc_email_reply\Controller;

use Drupal\avc_email_reply\Service\ReplyTokenService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for handling inbound email webhooks from SendGrid/Mailgun.
 */
class InboundEmailController extends ControllerBase {

  /**
   * The reply token service.
   *
   * @var \Drupal\avc_email_reply\Service\ReplyTokenService
   */
  protected $replyTokenService;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs an InboundEmailController object.
   *
   * @param \Drupal\avc_email_reply\Service\ReplyTokenService $reply_token_service
   *   The reply token service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(
    ReplyTokenService $reply_token_service,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    QueueFactory $queue_factory
  ) {
    $this->replyTokenService = $reply_token_service;
    $this->logger = $logger_factory->get('avc_email_reply');
    $this->configFactory = $config_factory;
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_email_reply.reply_token'),
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('queue')
    );
  }

  /**
   * Handles inbound email webhook from SendGrid/Mailgun.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   HTTP response.
   */
  public function receive(Request $request): Response {
    // Validate webhook signature based on configured provider.
    if (!$this->validateWebhookSignature($request)) {
      $this->logger->error('Invalid webhook signature from @ip', [
        '@ip' => $request->getClientIp(),
      ]);
      return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
    }

    // Parse POST data.
    $from = $request->request->get('from');
    $to = $request->request->get('to');
    $subject = $request->request->get('subject');
    $text = $request->request->get('text');
    $html = $request->request->get('html');
    $headers = $request->request->get('headers');
    $envelope = $request->request->get('envelope');
    $spam_score = $request->request->get('spam_score');
    $spf = $request->request->get('SPF');
    $dkim = $request->request->get('dkim');

    // Extract reply token from "to" address.
    $token = $this->extractToken($to);

    if (!$token) {
      $this->logger->warning('No reply token found in email to address: @to', [
        '@to' => $to,
      ]);
      return new Response('OK', Response::HTTP_OK);
    }

    // Prepare email data for queue.
    $email_data = [
      'token' => $token,
      'from' => $from,
      'to' => $to,
      'subject' => $subject,
      'text' => $text,
      'html' => $html,
      'headers' => $headers,
      'envelope' => $envelope,
      'spam_score' => $spam_score,
      'spf' => $spf,
      'dkim' => $dkim,
      'received_at' => time(),
    ];

    // Queue email for async processing.
    $queue = $this->queueFactory->get('avc_email_reply');
    $queue->createItem($email_data);

    $this->logger->info('Queued inbound email with token @token from @from', [
      '@token' => substr($token, 0, 10) . '...',
      '@from' => $from,
    ]);

    return new Response('OK', Response::HTTP_OK);
  }

  /**
   * Validates webhook signature based on configured email provider.
   *
   * Fails closed: rejects requests unless signature validation passes.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return bool
   *   TRUE if signature is valid, FALSE otherwise.
   */
  protected function validateWebhookSignature(Request $request): bool {
    $config = $this->configFactory->get('avc_email_reply.settings');
    $provider = $config->get('email_provider') ?? 'sendgrid';

    switch ($provider) {
      case 'sendgrid':
        return $this->validateSendGridSignature($request, $config);

      case 'mailgun':
        return $this->validateMailgunSignature($request, $config);

      case 'local':
        return $this->validateLocalRequest($request, $config);

      default:
        $this->logger->error('Unknown email provider: @provider', [
          '@provider' => $provider,
        ]);
        return FALSE;
    }
  }

  /**
   * Validates SendGrid webhook signature.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   *
   * @return bool
   *   TRUE if signature is valid, FALSE otherwise.
   */
  protected function validateSendGridSignature(Request $request, $config): bool {
    $signature = $request->headers->get('X-Twilio-Email-Event-Webhook-Signature');
    $timestamp = $request->headers->get('X-Twilio-Email-Event-Webhook-Timestamp');

    if (!$signature || !$timestamp) {
      $this->logger->warning('Missing SendGrid signature headers from @ip', [
        '@ip' => $request->getClientIp(),
      ]);
      return FALSE;
    }

    $webhook_secret = $config->get('webhook_secret');
    if (!$webhook_secret) {
      $this->logger->error('Webhook secret not configured for SendGrid');
      return FALSE;
    }

    $body = $request->getContent();
    $payload = $timestamp . $body;
    $expected_signature = base64_encode(hash_hmac('sha256', $payload, $webhook_secret, TRUE));

    return hash_equals($expected_signature, $signature);
  }

  /**
   * Validates Mailgun webhook signature.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   *
   * @return bool
   *   TRUE if signature is valid, FALSE otherwise.
   */
  protected function validateMailgunSignature(Request $request, $config): bool {
    $timestamp = $request->request->get('timestamp');
    $token = $request->request->get('token');
    $signature = $request->request->get('signature');

    if (!$timestamp || !$token || !$signature) {
      $this->logger->warning('Missing Mailgun signature fields from @ip', [
        '@ip' => $request->getClientIp(),
      ]);
      return FALSE;
    }

    $webhook_secret = $config->get('webhook_secret');
    if (!$webhook_secret) {
      $this->logger->error('Webhook secret not configured for Mailgun');
      return FALSE;
    }

    $expected_signature = hash_hmac('sha256', $timestamp . $token, $webhook_secret);

    return hash_equals($expected_signature, $signature);
  }

  /**
   * Validates a local request from Postfix pipe delivery.
   *
   * Accepts requests from localhost with a valid webhook secret passed
   * as the X-Webhook-Secret header or 'webhook_secret' POST parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   *
   * @return bool
   *   TRUE if request is valid, FALSE otherwise.
   */
  protected function validateLocalRequest(Request $request, $config): bool {
    // Only accept from loopback addresses.
    $client_ip = $request->getClientIp();
    $allowed_ips = ['127.0.0.1', '::1'];
    if (!in_array($client_ip, $allowed_ips)) {
      $this->logger->warning('Local provider request from non-loopback IP: @ip', [
        '@ip' => $client_ip,
      ]);
      return FALSE;
    }

    $webhook_secret = $config->get('webhook_secret');
    if (!$webhook_secret) {
      $this->logger->error('Webhook secret not configured for local provider');
      return FALSE;
    }

    // Accept secret from header or POST parameter.
    $provided_secret = $request->headers->get('X-Webhook-Secret')
      ?: $request->request->get('webhook_secret');

    if (!$provided_secret) {
      $this->logger->warning('Missing webhook secret in local request');
      return FALSE;
    }

    return hash_equals($webhook_secret, $provided_secret);
  }

  /**
   * Extracts reply token from email to address.
   *
   * @param string $to_address
   *   The recipient email address.
   *
   * @return string|null
   *   The extracted token, or NULL if not found.
   */
  protected function extractToken(string $to_address): ?string {
    // Extract token using regex: reply+{token}@domain.com.
    if (preg_match('/reply\+([a-zA-Z0-9+\/=]+)@/', $to_address, $matches)) {
      return $matches[1];
    }

    return NULL;
  }

}
