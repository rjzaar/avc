<?php

namespace Drupal\avc_notification\Service;

use Drupal\avc_notification\Entity\NotificationQueue;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Service for aggregating notifications into digests.
 */
class NotificationAggregator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The notification preferences service.
   *
   * @var \Drupal\avc_notification\Service\NotificationPreferences
   */
  protected $preferences;

  /**
   * Constructs a NotificationAggregator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\avc_notification\Service\NotificationPreferences $preferences
   *   The notification preferences service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    NotificationPreferences $preferences
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->preferences = $preferences;
  }

  /**
   * Get notifications for immediate processing.
   *
   * @return \Drupal\avc_notification\Entity\NotificationQueue[]
   *   Array of notifications to send immediately.
   */
  public function getImmediateNotifications() {
    $notifications = [];
    $storage = $this->entityTypeManager->getStorage('notification_queue');

    // Get all pending notifications.
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', NotificationQueue::STATUS_PENDING)
      ->execute();

    if (empty($ids)) {
      return $notifications;
    }

    /** @var \Drupal\avc_notification\Entity\NotificationQueue[] $all_notifications */
    $all_notifications = $storage->loadMultiple($ids);

    foreach ($all_notifications as $notification) {
      $user = $notification->getTargetUser();
      if (!$user) {
        continue;
      }

      $group = $notification->getTargetGroup();
      $preference = $this->preferences->getUserPreference($user, $group);

      // Check if this should be sent immediately.
      if ($preference === NotificationPreferences::PREF_IMMEDIATE) {
        $notifications[] = $notification;
      }
    }

    return $notifications;
  }

  /**
   * Get users who need daily digest.
   *
   * @return array
   *   Array keyed by user ID containing aggregated notifications.
   */
  public function getDailyDigestData() {
    return $this->getDigestData(NotificationPreferences::PREF_DAILY);
  }

  /**
   * Get users who need weekly digest.
   *
   * @return array
   *   Array keyed by user ID containing aggregated notifications.
   */
  public function getWeeklyDigestData() {
    return $this->getDigestData(NotificationPreferences::PREF_WEEKLY);
  }

  /**
   * Get digest data for a specific preference type.
   *
   * @param string $preference_type
   *   The preference type (d or w).
   *
   * @return array
   *   Array keyed by user ID containing aggregated notifications.
   */
  protected function getDigestData(string $preference_type) {
    $digest_data = [];
    $storage = $this->entityTypeManager->getStorage('notification_queue');

    // Get all pending notifications.
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', NotificationQueue::STATUS_PENDING)
      ->execute();

    if (empty($ids)) {
      return $digest_data;
    }

    /** @var \Drupal\avc_notification\Entity\NotificationQueue[] $all_notifications */
    $all_notifications = $storage->loadMultiple($ids);

    foreach ($all_notifications as $notification) {
      $user = $notification->getTargetUser();
      if (!$user) {
        continue;
      }

      $group = $notification->getTargetGroup();
      $preference = $this->preferences->getUserPreference($user, $group);

      if ($preference === $preference_type) {
        $user_id = $user->id();

        if (!isset($digest_data[$user_id])) {
          $digest_data[$user_id] = [
            'user' => $user,
            'notifications' => [],
            'by_group' => [],
          ];
        }

        $digest_data[$user_id]['notifications'][] = $notification;

        // Also group by group for the digest.
        $group_id = $group ? $group->id() : 0;
        $group_label = $group ? $group->label() : t('General');

        if (!isset($digest_data[$user_id]['by_group'][$group_id])) {
          $digest_data[$user_id]['by_group'][$group_id] = [
            'label' => $group_label,
            'notifications' => [],
          ];
        }

        $digest_data[$user_id]['by_group'][$group_id]['notifications'][] = $notification;
      }
    }

    return $digest_data;
  }

  /**
   * Aggregate notifications by event type for a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\avc_notification\Entity\NotificationQueue[] $notifications
   *   The notifications to aggregate.
   *
   * @return array
   *   Aggregated notification data.
   */
  public function aggregateByEventType(AccountInterface $user, array $notifications) {
    $aggregated = [];

    foreach ($notifications as $notification) {
      $event_type = $notification->getEventType();

      if (!isset($aggregated[$event_type])) {
        $aggregated[$event_type] = [
          'label' => $this->getEventTypeLabel($event_type),
          'count' => 0,
          'notifications' => [],
        ];
      }

      $aggregated[$event_type]['count']++;
      $aggregated[$event_type]['notifications'][] = [
        'id' => $notification->id(),
        'message' => $notification->getMessage(),
        'asset' => $notification->getAsset(),
        'group' => $notification->getTargetGroup(),
        'created' => $notification->get('created')->value,
        'data' => $notification->getData(),
      ];
    }

    return $aggregated;
  }

  /**
   * Get a human-readable label for an event type.
   *
   * @param string $event_type
   *   The event type.
   *
   * @return string
   *   The label.
   */
  protected function getEventTypeLabel(string $event_type) {
    $labels = [
      NotificationQueue::EVENT_WORKFLOW_ADVANCE => t('Workflow Tasks'),
      NotificationQueue::EVENT_ASSIGNMENT => t('Assignments'),
      NotificationQueue::EVENT_RATIFICATION_NEEDED => t('Ratification Requests'),
      NotificationQueue::EVENT_RATIFICATION_COMPLETE => t('Ratification Results'),
      NotificationQueue::EVENT_ENDORSEMENT => t('Endorsements'),
      NotificationQueue::EVENT_GUILD_PROMOTION => t('Promotions'),
    ];

    return $labels[$event_type] ?? $event_type;
  }

}
