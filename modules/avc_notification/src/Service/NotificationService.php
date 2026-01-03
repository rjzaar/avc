<?php

namespace Drupal\avc_notification\Service;

use Drupal\avc_notification\Entity\NotificationQueue;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;

/**
 * Service for creating and managing notifications.
 */
class NotificationService {

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
   * Constructs a NotificationService.
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
   * Queue a workflow advance notification.
   *
   * @param \Drupal\Core\Session\AccountInterface $target_user
   *   The user to notify.
   * @param \Drupal\node\NodeInterface $asset
   *   The asset that advanced.
   * @param string $check_type
   *   The type of check required.
   * @param \Drupal\Core\Session\AccountInterface|null $previous_user
   *   The previous user who completed their step.
   * @param string $comment
   *   Any comment from the previous step.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   The group context (optional).
   *
   * @return \Drupal\avc_notification\Entity\NotificationQueue|null
   *   The created notification entity or NULL if skipped.
   */
  public function queueWorkflowAdvance(
    AccountInterface $target_user,
    NodeInterface $asset,
    string $check_type,
    ?AccountInterface $previous_user = NULL,
    string $comment = '',
    ?GroupInterface $group = NULL
  ) {
    // Check if user wants notifications.
    $preference = $this->preferences->getUserPreference($target_user, $group);
    if ($preference === 'x') {
      return NULL;
    }

    $data = [
      'check_type' => $check_type,
      'previous_user_id' => $previous_user ? $previous_user->id() : NULL,
      'previous_user_name' => $previous_user ? $previous_user->getDisplayName() : '',
      'comment' => $comment,
    ];

    return $this->createNotification(
      NotificationQueue::EVENT_WORKFLOW_ADVANCE,
      $target_user,
      $asset,
      t('Asset "@asset" is ready for @check_type.', [
        '@asset' => $asset->label(),
        '@check_type' => $check_type,
      ]),
      $data,
      $group
    );
  }

  /**
   * Queue an assignment notification.
   *
   * @param \Drupal\Core\Session\AccountInterface $target_user
   *   The user to notify.
   * @param \Drupal\node\NodeInterface $asset
   *   The asset being assigned.
   * @param string $assignment_type
   *   The type of assignment.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   The group context (optional).
   *
   * @return \Drupal\avc_notification\Entity\NotificationQueue|null
   *   The created notification entity or NULL if skipped.
   */
  public function queueAssignment(
    AccountInterface $target_user,
    NodeInterface $asset,
    string $assignment_type,
    ?GroupInterface $group = NULL
  ) {
    $preference = $this->preferences->getUserPreference($target_user, $group);
    if ($preference === 'x') {
      return NULL;
    }

    $data = [
      'assignment_type' => $assignment_type,
    ];

    return $this->createNotification(
      NotificationQueue::EVENT_ASSIGNMENT,
      $target_user,
      $asset,
      t('You have been assigned to asset "@asset".', [
        '@asset' => $asset->label(),
      ]),
      $data,
      $group
    );
  }

  /**
   * Queue a ratification needed notification.
   *
   * @param \Drupal\Core\Session\AccountInterface $mentor
   *   The mentor to notify.
   * @param \Drupal\Core\Session\AccountInterface $junior
   *   The junior who completed work.
   * @param \Drupal\node\NodeInterface $asset
   *   The asset needing ratification.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild context.
   *
   * @return \Drupal\avc_notification\Entity\NotificationQueue|null
   *   The created notification entity or NULL if skipped.
   */
  public function queueRatificationNeeded(
    AccountInterface $mentor,
    AccountInterface $junior,
    NodeInterface $asset,
    GroupInterface $guild
  ) {
    $preference = $this->preferences->getUserPreference($mentor, $guild);
    if ($preference === 'x') {
      return NULL;
    }

    $data = [
      'junior_id' => $junior->id(),
      'junior_name' => $junior->getDisplayName(),
    ];

    return $this->createNotification(
      NotificationQueue::EVENT_RATIFICATION_NEEDED,
      $mentor,
      $asset,
      t('@junior has completed work on "@asset" and needs ratification.', [
        '@junior' => $junior->getDisplayName(),
        '@asset' => $asset->label(),
      ]),
      $data,
      $guild
    );
  }

  /**
   * Queue a ratification complete notification.
   *
   * @param \Drupal\Core\Session\AccountInterface $junior
   *   The junior to notify.
   * @param \Drupal\Core\Session\AccountInterface $mentor
   *   The mentor who ratified.
   * @param \Drupal\node\NodeInterface $asset
   *   The asset that was ratified.
   * @param bool $approved
   *   Whether the work was approved.
   * @param string $feedback
   *   Mentor feedback.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild context.
   *
   * @return \Drupal\avc_notification\Entity\NotificationQueue|null
   *   The created notification entity or NULL if skipped.
   */
  public function queueRatificationComplete(
    AccountInterface $junior,
    AccountInterface $mentor,
    NodeInterface $asset,
    bool $approved,
    string $feedback,
    GroupInterface $guild
  ) {
    $preference = $this->preferences->getUserPreference($junior, $guild);
    if ($preference === 'x') {
      return NULL;
    }

    $data = [
      'mentor_id' => $mentor->id(),
      'mentor_name' => $mentor->getDisplayName(),
      'approved' => $approved,
      'feedback' => $feedback,
    ];

    $message = $approved
      ? t('@mentor has approved your work on "@asset".', [
          '@mentor' => $mentor->getDisplayName(),
          '@asset' => $asset->label(),
        ])
      : t('@mentor has requested changes to your work on "@asset".', [
          '@mentor' => $mentor->getDisplayName(),
          '@asset' => $asset->label(),
        ]);

    return $this->createNotification(
      NotificationQueue::EVENT_RATIFICATION_COMPLETE,
      $junior,
      $asset,
      $message,
      $data,
      $guild
    );
  }

  /**
   * Queue an endorsement notification.
   *
   * @param \Drupal\Core\Session\AccountInterface $endorsed_user
   *   The user who was endorsed.
   * @param \Drupal\Core\Session\AccountInterface $endorser
   *   The user who gave the endorsement.
   * @param string $skill_name
   *   The skill that was endorsed.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild context.
   *
   * @return \Drupal\avc_notification\Entity\NotificationQueue|null
   *   The created notification entity or NULL if skipped.
   */
  public function queueEndorsement(
    AccountInterface $endorsed_user,
    AccountInterface $endorser,
    string $skill_name,
    GroupInterface $guild
  ) {
    $preference = $this->preferences->getUserPreference($endorsed_user, $guild);
    if ($preference === 'x') {
      return NULL;
    }

    $data = [
      'endorser_id' => $endorser->id(),
      'endorser_name' => $endorser->getDisplayName(),
      'skill_name' => $skill_name,
    ];

    return $this->createNotification(
      NotificationQueue::EVENT_ENDORSEMENT,
      $endorsed_user,
      NULL,
      t('@endorser has endorsed your @skill skill.', [
        '@endorser' => $endorser->getDisplayName(),
        '@skill' => $skill_name,
      ]),
      $data,
      $guild
    );
  }

  /**
   * Queue a guild promotion notification.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user who was promoted.
   * @param string $new_role
   *   The new role name.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild context.
   *
   * @return \Drupal\avc_notification\Entity\NotificationQueue|null
   *   The created notification entity or NULL if skipped.
   */
  public function queueGuildPromotion(
    AccountInterface $user,
    string $new_role,
    GroupInterface $guild
  ) {
    $preference = $this->preferences->getUserPreference($user, $guild);
    if ($preference === 'x') {
      return NULL;
    }

    $data = [
      'new_role' => $new_role,
      'guild_name' => $guild->label(),
    ];

    return $this->createNotification(
      NotificationQueue::EVENT_GUILD_PROMOTION,
      $user,
      NULL,
      t('Congratulations! You have been promoted to @role in @guild.', [
        '@role' => $new_role,
        '@guild' => $guild->label(),
      ]),
      $data,
      $guild
    );
  }

  /**
   * Creates a notification queue entity.
   *
   * @param string $event_type
   *   The event type.
   * @param \Drupal\Core\Session\AccountInterface $target_user
   *   The target user.
   * @param \Drupal\node\NodeInterface|null $asset
   *   The related asset (optional).
   * @param string $message
   *   The notification message.
   * @param array $data
   *   Additional data.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   The group context (optional).
   *
   * @return \Drupal\avc_notification\Entity\NotificationQueue
   *   The created notification entity.
   */
  protected function createNotification(
    string $event_type,
    AccountInterface $target_user,
    ?NodeInterface $asset,
    string $message,
    array $data = [],
    ?GroupInterface $group = NULL
  ) {
    $values = [
      'event_type' => $event_type,
      'target_user' => $target_user->id(),
      'message' => $message,
      'status' => NotificationQueue::STATUS_PENDING,
    ];

    if ($asset) {
      $values['asset_id'] = $asset->id();
    }

    if ($group) {
      $values['target_group'] = $group->id();
    }

    /** @var \Drupal\avc_notification\Entity\NotificationQueue $notification */
    $notification = $this->entityTypeManager
      ->getStorage('notification_queue')
      ->create($values);

    $notification->setData($data);
    $notification->save();

    // Check if immediate notification is needed.
    $preference = $this->preferences->getUserPreference($target_user, $group);
    if ($preference === 'n') {
      // Mark for immediate processing.
      $data['immediate'] = TRUE;
      $notification->setData($data);
      $notification->save();
    }

    return $notification;
  }

  /**
   * Get pending notifications for a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param string|null $event_type
   *   Filter by event type (optional).
   *
   * @return \Drupal\avc_notification\Entity\NotificationQueue[]
   *   Array of pending notifications.
   */
  public function getPendingNotifications(AccountInterface $user, ?string $event_type = NULL) {
    $query = $this->entityTypeManager
      ->getStorage('notification_queue')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_user', $user->id())
      ->condition('status', NotificationQueue::STATUS_PENDING);

    if ($event_type) {
      $query->condition('event_type', $event_type);
    }

    $ids = $query->execute();
    return $this->entityTypeManager
      ->getStorage('notification_queue')
      ->loadMultiple($ids);
  }

  /**
   * Clean up old sent notifications.
   *
   * @param int $days
   *   Number of days to retain.
   */
  public function cleanupOldNotifications(int $days = 7) {
    $cutoff = strtotime("-{$days} days");

    $ids = $this->entityTypeManager
      ->getStorage('notification_queue')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', NotificationQueue::STATUS_SENT)
      ->condition('sent', $cutoff, '<')
      ->execute();

    if ($ids) {
      $notifications = $this->entityTypeManager
        ->getStorage('notification_queue')
        ->loadMultiple($ids);
      $this->entityTypeManager
        ->getStorage('notification_queue')
        ->delete($notifications);
    }
  }

}
