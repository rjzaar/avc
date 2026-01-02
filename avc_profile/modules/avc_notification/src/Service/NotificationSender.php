<?php

namespace Drupal\avc_notification\Service;

use Drupal\avc_notification\Entity\NotificationQueue;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;

/**
 * Service for sending notifications via email.
 */
class NotificationSender {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a NotificationSender.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    MailManagerInterface $mail_manager,
    RendererInterface $renderer,
    ConfigFactoryInterface $config_factory,
    LoggerInterface $logger
  ) {
    $this->mailManager = $mail_manager;
    $this->renderer = $renderer;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * Send an immediate notification.
   *
   * @param \Drupal\avc_notification\Entity\NotificationQueue $notification
   *   The notification to send.
   *
   * @return bool
   *   TRUE if sent successfully.
   */
  public function sendImmediate(NotificationQueue $notification) {
    $user = $notification->getTargetUser();
    if (!$user || !$user->getEmail()) {
      $notification->markSkipped();
      $notification->save();
      return FALSE;
    }

    $event_type = $notification->getEventType();

    switch ($event_type) {
      case NotificationQueue::EVENT_WORKFLOW_ADVANCE:
        return $this->sendWorkflowAdvance($notification);

      default:
        return $this->sendGeneric($notification);
    }
  }

  /**
   * Send a workflow advance notification.
   *
   * @param \Drupal\avc_notification\Entity\NotificationQueue $notification
   *   The notification.
   *
   * @return bool
   *   TRUE if sent successfully.
   */
  protected function sendWorkflowAdvance(NotificationQueue $notification) {
    $user = $notification->getTargetUser();
    $asset = $notification->getAsset();
    $data = $notification->getData();

    $build = [
      '#theme' => 'notification_email_workflow_advance',
      '#recipient_name' => $user->getDisplayName(),
      '#initiator_name' => $data['previous_user_name'] ?? '',
      '#resource_name' => $asset ? $asset->label() : '',
      '#check_type' => $data['check_type'] ?? '',
      '#resource_link' => $asset ? $asset->toUrl('canonical', ['absolute' => TRUE])->toString() : '',
      '#dashboard_link' => Url::fromRoute('avc_member.dashboard', ['user' => $user->id()], ['absolute' => TRUE])->toString(),
      '#previous_person' => $data['previous_user_name'] ?? '',
      '#comment' => $data['comment'] ?? '',
    ];

    return $this->sendMail($user, 'workflow_advance', [
      'resource_name' => $asset ? $asset->label() : 'Unknown',
      'body' => $build,
    ], $notification);
  }

  /**
   * Send a generic notification.
   *
   * @param \Drupal\avc_notification\Entity\NotificationQueue $notification
   *   The notification.
   *
   * @return bool
   *   TRUE if sent successfully.
   */
  protected function sendGeneric(NotificationQueue $notification) {
    $user = $notification->getTargetUser();

    $params = [
      'subject' => t('AV Commons Notification'),
      'body' => $notification->getMessage(),
    ];

    return $this->sendMail($user, 'generic', $params, $notification);
  }

  /**
   * Send a daily digest to a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param array $digest_data
   *   The digest data containing notifications.
   *
   * @return bool
   *   TRUE if sent successfully.
   */
  public function sendDailyDigest(AccountInterface $user, array $digest_data) {
    if (!$user->getEmail()) {
      return FALSE;
    }

    $items = $this->formatDigestItems($digest_data['notifications'] ?? []);
    $date = date('F j, Y');

    $build = [
      '#theme' => 'notification_email_daily_digest',
      '#recipient_name' => $user->getDisplayName(),
      '#items' => $items,
      '#dashboard_link' => Url::fromRoute('avc_member.dashboard', ['user' => $user->id()], ['absolute' => TRUE])->toString(),
      '#date' => $date,
    ];

    $result = $this->mailManager->mail(
      'avc_notification',
      'daily_digest',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      [
        'date' => $date,
        'body' => $build,
      ]
    );

    if ($result['result']) {
      // Mark all notifications as sent.
      foreach ($digest_data['notifications'] as $notification) {
        $notification->markSent();
        $notification->save();
      }
      return TRUE;
    }

    $this->logger->error('Failed to send daily digest to @user', [
      '@user' => $user->getEmail(),
    ]);

    return FALSE;
  }

  /**
   * Send a weekly digest to a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param array $digest_data
   *   The digest data containing notifications.
   *
   * @return bool
   *   TRUE if sent successfully.
   */
  public function sendWeeklyDigest(AccountInterface $user, array $digest_data) {
    if (!$user->getEmail()) {
      return FALSE;
    }

    $items = $this->formatDigestItems($digest_data['notifications'] ?? []);
    $week_start = date('F j', strtotime('last monday'));
    $week_end = date('F j, Y');

    $build = [
      '#theme' => 'notification_email_weekly_digest',
      '#recipient_name' => $user->getDisplayName(),
      '#items' => $items,
      '#dashboard_link' => Url::fromRoute('avc_member.dashboard', ['user' => $user->id()], ['absolute' => TRUE])->toString(),
      '#week_start' => $week_start,
      '#week_end' => $week_end,
    ];

    $result = $this->mailManager->mail(
      'avc_notification',
      'weekly_digest',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      [
        'week' => "$week_start - $week_end",
        'body' => $build,
      ]
    );

    if ($result['result']) {
      // Mark all notifications as sent.
      foreach ($digest_data['notifications'] as $notification) {
        $notification->markSent();
        $notification->save();
      }
      return TRUE;
    }

    $this->logger->error('Failed to send weekly digest to @user', [
      '@user' => $user->getEmail(),
    ]);

    return FALSE;
  }

  /**
   * Format notifications for digest display.
   *
   * @param \Drupal\avc_notification\Entity\NotificationQueue[] $notifications
   *   The notifications.
   *
   * @return array
   *   Formatted items for the digest template.
   */
  protected function formatDigestItems(array $notifications) {
    $items = [];

    foreach ($notifications as $notification) {
      $asset = $notification->getAsset();
      $group = $notification->getTargetGroup();

      $items[] = [
        'message' => $notification->getMessage(),
        'event_type' => $notification->getEventType(),
        'asset_title' => $asset ? $asset->label() : NULL,
        'asset_link' => $asset ? $asset->toUrl('canonical', ['absolute' => TRUE])->toString() : NULL,
        'group_name' => $group ? $group->label() : NULL,
        'created' => date('M j, g:i a', $notification->get('created')->value),
      ];
    }

    return $items;
  }

  /**
   * Send an admin alert.
   *
   * @param string $alert_type
   *   The type of alert.
   * @param string $message
   *   The alert message.
   *
   * @return bool
   *   TRUE if sent successfully.
   */
  public function sendAdminAlert(string $alert_type, string $message) {
    $config = $this->configFactory->get('avc_notification.settings');
    $admin_email = $config->get('admin_email') ?: $this->configFactory->get('system.site')->get('mail');

    if (!$admin_email) {
      return FALSE;
    }

    $result = $this->mailManager->mail(
      'avc_notification',
      'admin_alert',
      $admin_email,
      'en',
      [
        'alert_type' => $alert_type,
        'message' => $message,
      ]
    );

    return (bool) $result['result'];
  }

  /**
   * Send mail and update notification status.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The recipient.
   * @param string $key
   *   The mail key.
   * @param array $params
   *   The mail parameters.
   * @param \Drupal\avc_notification\Entity\NotificationQueue $notification
   *   The notification entity.
   *
   * @return bool
   *   TRUE if sent successfully.
   */
  protected function sendMail(
    AccountInterface $user,
    string $key,
    array $params,
    NotificationQueue $notification
  ) {
    $result = $this->mailManager->mail(
      'avc_notification',
      $key,
      $user->getEmail(),
      $user->getPreferredLangcode(),
      $params
    );

    if ($result['result']) {
      $notification->markSent();
      $notification->save();
      return TRUE;
    }

    $notification->markFailed();
    $notification->save();

    $this->logger->error('Failed to send notification @id to @user', [
      '@id' => $notification->id(),
      '@user' => $user->getEmail(),
    ]);

    return FALSE;
  }

}
