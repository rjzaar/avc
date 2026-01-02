<?php

namespace Drupal\avc_notification\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for processing the notification queue.
 */
class NotificationProcessor {

  /**
   * The notification aggregator.
   *
   * @var \Drupal\avc_notification\Service\NotificationAggregator
   */
  protected $aggregator;

  /**
   * The notification sender.
   *
   * @var \Drupal\avc_notification\Service\NotificationSender
   */
  protected $sender;

  /**
   * The notification preferences service.
   *
   * @var \Drupal\avc_notification\Service\NotificationPreferences
   */
  protected $preferences;

  /**
   * The notification service.
   *
   * @var \Drupal\avc_notification\Service\NotificationService
   */
  protected $notificationService;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

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
   * Constructs a NotificationProcessor.
   *
   * @param \Drupal\avc_notification\Service\NotificationAggregator $aggregator
   *   The notification aggregator.
   * @param \Drupal\avc_notification\Service\NotificationSender $sender
   *   The notification sender.
   * @param \Drupal\avc_notification\Service\NotificationPreferences $preferences
   *   The notification preferences service.
   * @param \Drupal\avc_notification\Service\NotificationService $notification_service
   *   The notification service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    NotificationAggregator $aggregator,
    NotificationSender $sender,
    NotificationPreferences $preferences,
    NotificationService $notification_service,
    StateInterface $state,
    ConfigFactoryInterface $config_factory,
    LoggerInterface $logger
  ) {
    $this->aggregator = $aggregator;
    $this->sender = $sender;
    $this->preferences = $preferences;
    $this->notificationService = $notification_service;
    $this->state = $state;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * Process the notification queue.
   *
   * This is called by cron.
   */
  public function processQueue() {
    // Process immediate notifications.
    $this->processImmediate();

    // Check if it's time for daily digest.
    if ($this->shouldRunDailyDigest()) {
      $this->processDailyDigest();
    }

    // Check if it's time for weekly digest.
    if ($this->shouldRunWeeklyDigest()) {
      $this->processWeeklyDigest();
    }

    // Cleanup old notifications.
    $this->cleanup();
  }

  /**
   * Process immediate notifications.
   */
  public function processImmediate() {
    $notifications = $this->aggregator->getImmediateNotifications();

    $sent = 0;
    $failed = 0;

    foreach ($notifications as $notification) {
      try {
        if ($this->sender->sendImmediate($notification)) {
          $sent++;
        }
        else {
          $failed++;
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Error sending notification @id: @message', [
          '@id' => $notification->id(),
          '@message' => $e->getMessage(),
        ]);
        $failed++;
      }
    }

    if ($sent > 0 || $failed > 0) {
      $this->logger->info('Processed immediate notifications: @sent sent, @failed failed.', [
        '@sent' => $sent,
        '@failed' => $failed,
      ]);
    }
  }

  /**
   * Process daily digest.
   */
  public function processDailyDigest() {
    $digest_data = $this->aggregator->getDailyDigestData();

    $sent = 0;
    $failed = 0;

    foreach ($digest_data as $user_id => $data) {
      try {
        if ($this->sender->sendDailyDigest($data['user'], $data)) {
          $sent++;
          // Update last run timestamp.
          $this->preferences->setLastNotificationRun($data['user'], time());
        }
        else {
          $failed++;
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Error sending daily digest to user @id: @message', [
          '@id' => $user_id,
          '@message' => $e->getMessage(),
        ]);
        $failed++;
      }
    }

    $this->state->set('avc_notification.daily_digest_last_run', time());

    if ($sent > 0 || $failed > 0) {
      $this->logger->info('Processed daily digests: @sent sent, @failed failed.', [
        '@sent' => $sent,
        '@failed' => $failed,
      ]);
    }
  }

  /**
   * Process weekly digest.
   */
  public function processWeeklyDigest() {
    $digest_data = $this->aggregator->getWeeklyDigestData();

    $sent = 0;
    $failed = 0;

    foreach ($digest_data as $user_id => $data) {
      try {
        if ($this->sender->sendWeeklyDigest($data['user'], $data)) {
          $sent++;
          // Update last run timestamp.
          $this->preferences->setLastNotificationRun($data['user'], time());
        }
        else {
          $failed++;
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Error sending weekly digest to user @id: @message', [
          '@id' => $user_id,
          '@message' => $e->getMessage(),
        ]);
        $failed++;
      }
    }

    $this->state->set('avc_notification.weekly_digest_last_run', time());

    if ($sent > 0 || $failed > 0) {
      $this->logger->info('Processed weekly digests: @sent sent, @failed failed.', [
        '@sent' => $sent,
        '@failed' => $failed,
      ]);
    }
  }

  /**
   * Check if daily digest should run.
   *
   * @return bool
   *   TRUE if daily digest should run.
   */
  protected function shouldRunDailyDigest() {
    $config = $this->configFactory->get('avc_notification.settings');
    $daily_hour = $config->get('daily_digest_hour') ?? 8;

    $last_run = $this->state->get('avc_notification.daily_digest_last_run', 0);
    $current_hour = (int) date('G');

    // Run if it's the configured hour and we haven't run today.
    if ($current_hour >= $daily_hour) {
      $today_start = strtotime('today');
      return $last_run < $today_start;
    }

    return FALSE;
  }

  /**
   * Check if weekly digest should run.
   *
   * @return bool
   *   TRUE if weekly digest should run.
   */
  protected function shouldRunWeeklyDigest() {
    $config = $this->configFactory->get('avc_notification.settings');
    $weekly_day = $config->get('weekly_digest_day') ?? 1; // Monday = 1.
    $weekly_hour = $config->get('weekly_digest_hour') ?? 8;

    $last_run = $this->state->get('avc_notification.weekly_digest_last_run', 0);
    $current_day = (int) date('N');
    $current_hour = (int) date('G');

    // Run if it's the configured day/hour and we haven't run this week.
    if ($current_day == $weekly_day && $current_hour >= $weekly_hour) {
      $week_start = strtotime('monday this week');
      return $last_run < $week_start;
    }

    return FALSE;
  }

  /**
   * Cleanup old sent notifications.
   */
  protected function cleanup() {
    $config = $this->configFactory->get('avc_notification.settings');
    $retention_days = $config->get('retention_days') ?? 7;

    $this->notificationService->cleanupOldNotifications($retention_days);
  }

  /**
   * Force run daily digest (for testing/admin use).
   */
  public function forceRunDailyDigest() {
    $this->processDailyDigest();
  }

  /**
   * Force run weekly digest (for testing/admin use).
   */
  public function forceRunWeeklyDigest() {
    $this->processWeeklyDigest();
  }

}
