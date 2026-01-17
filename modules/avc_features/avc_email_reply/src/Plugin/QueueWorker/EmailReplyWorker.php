<?php

namespace Drupal\avc_email_reply\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\avc_email_reply\Service\EmailReplyProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes inbound email replies.
 *
 * @QueueWorker(
 *   id = "avc_email_reply",
 *   title = @Translation("Email Reply Processor"),
 *   cron = {"time" = 60}
 * )
 */
class EmailReplyWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The email reply processor service.
   *
   * @var \Drupal\avc_email_reply\Service\EmailReplyProcessor
   */
  protected $emailProcessor;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs an EmailReplyWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\avc_email_reply\Service\EmailReplyProcessor $email_processor
   *   The email reply processor service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EmailReplyProcessor $email_processor,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->emailProcessor = $email_processor;
    $this->logger = $logger_factory->get('avc_email_reply');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('avc_email_reply.email_processor'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Validate required data fields.
    if (empty($data['token']) || empty($data['from'])) {
      $this->logger->error('Invalid queue item: missing token or from address');
      // Don't requeue - this is a permanent failure.
      return;
    }

    try {
      // Map queue data fields to processor expected fields.
      $processor_data = [
        'token' => $data['token'],
        'from' => $data['from'],
        'text_content' => $data['text'] ?? '',
        'spam_score' => isset($data['spam_score']) ? (float) $data['spam_score'] : 0.0,
        'spf_result' => $data['spf'] ?? NULL,
        'dkim_result' => $data['dkim'] ?? NULL,
      ];

      // Process the email reply.
      $result = $this->emailProcessor->process($processor_data);

      // Log the result based on success/failure.
      if ($result->isSuccess()) {
        $this->logger->info('Successfully processed email reply from @from for token @token', [
          '@from' => $data['from'],
          '@token' => substr($data['token'], 0, 10) . '...',
        ]);
      }
      else {
        // Permanent failure - log and don't requeue.
        $this->logger->error('Failed to process email from @from: @error', [
          '@from' => $data['from'],
          '@error' => $result->getMessage(),
        ]);
      }
    }
    catch (RequeueException $e) {
      // Re-throw RequeueException to allow retry.
      throw $e;
    }
    catch (\Exception $e) {
      // Unexpected exception - log and don't requeue (treat as permanent failure).
      $this->logger->error('Exception processing email from @from: @message', [
        '@from' => $data['from'],
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
