<?php

namespace Drupal\avc_group\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Service for managing group notifications.
 */
class GroupNotificationService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a GroupNotificationService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Sends notification to group members about a new assignment.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param mixed $assignment
   *   The workflow assignment entity.
   */
  public function notifyNewAssignment(GroupInterface $group, $assignment) {
    $members = $this->getNotifiableMembers($group);

    foreach ($members as $member) {
      $this->sendAssignmentNotification($member, $group, $assignment, 'new');
    }
  }

  /**
   * Sends notification about assignment status change.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param mixed $assignment
   *   The workflow assignment entity.
   * @param string $old_status
   *   The previous status.
   * @param string $new_status
   *   The new status.
   */
  public function notifyStatusChange(GroupInterface $group, $assignment, $old_status, $new_status) {
    // Notify the assigned user if there is one.
    $assigned_user = $assignment->get('assigned_user')->entity ?? NULL;

    if ($assigned_user) {
      $preference = $this->getUserNotificationPreference($assigned_user, $group);
      if ($preference !== 'x') {
        $this->queueNotification($assigned_user, $group, $assignment, 'status_change', [
          'old_status' => $old_status,
          'new_status' => $new_status,
        ]);
      }
    }

    // Notify group managers.
    $managers = $this->getGroupManagers($group);
    foreach ($managers as $manager) {
      if (!$assigned_user || $manager->id() !== $assigned_user->id()) {
        $preference = $this->getUserNotificationPreference($manager, $group);
        if ($preference !== 'x') {
          $this->queueNotification($manager, $group, $assignment, 'status_change', [
            'old_status' => $old_status,
            'new_status' => $new_status,
          ]);
        }
      }
    }
  }

  /**
   * Gets group members who should receive notifications.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return array
   *   Array of user entities.
   */
  protected function getNotifiableMembers(GroupInterface $group) {
    $members = [];

    foreach ($group->getMembers() as $membership) {
      $user = $membership->getUser();
      if ($user) {
        $preference = $this->getUserNotificationPreference($user, $group);
        if ($preference !== 'x') {
          $members[] = $user;
        }
      }
    }

    return $members;
  }

  /**
   * Gets group managers.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return array
   *   Array of manager user entities.
   */
  protected function getGroupManagers(GroupInterface $group) {
    $managers = [];

    foreach ($group->getMembers() as $membership) {
      foreach ($membership->getRoles() as $role) {
        $role_id = $role->id();
        if (strpos($role_id, 'admin') !== FALSE ||
            strpos($role_id, 'manager') !== FALSE) {
          $managers[] = $membership->getUser();
          break;
        }
      }
    }

    return $managers;
  }

  /**
   * Gets user's notification preference for a group.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return string
   *   Preference: 'n' (immediate), 'd' (daily), 'w' (weekly), 'x' (none).
   */
  protected function getUserNotificationPreference($user, GroupInterface $group) {
    // Check for group-specific override first.
    if ($user->hasField('field_notification_groups')) {
      $overrides = $user->get('field_notification_groups')->getValue();
      foreach ($overrides as $override) {
        if (isset($override['group_id']) && $override['group_id'] == $group->id()) {
          return $override['preference'] ?? 'x';
        }
      }
    }

    // Fall back to default preference.
    if ($user->hasField('field_notification_default')) {
      return $user->get('field_notification_default')->value ?? 'x';
    }

    return 'x';
  }

  /**
   * Queues a notification for later sending (for digest mode).
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param mixed $assignment
   *   The workflow assignment entity.
   * @param string $type
   *   The notification type.
   * @param array $context
   *   Additional context data.
   */
  protected function queueNotification($user, GroupInterface $group, $assignment, $type, array $context = []) {
    $preference = $this->getUserNotificationPreference($user, $group);

    if ($preference === 'n') {
      // Immediate notification.
      $this->sendAssignmentNotification($user, $group, $assignment, $type, $context);
    }
    else {
      // Queue for digest.
      $this->addToDigestQueue($user, $group, $assignment, $type, $context, $preference);
    }
  }

  /**
   * Sends an immediate notification.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param mixed $assignment
   *   The workflow assignment entity.
   * @param string $type
   *   The notification type.
   * @param array $context
   *   Additional context data.
   */
  protected function sendAssignmentNotification($user, GroupInterface $group, $assignment, $type, array $context = []) {
    $params = [
      'user' => $user,
      'group' => $group,
      'assignment' => $assignment,
      'type' => $type,
      'context' => $context,
    ];

    $this->mailManager->mail(
      'avc_group',
      'workflow_notification',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      $params
    );
  }

  /**
   * Adds a notification to the digest queue.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param mixed $assignment
   *   The workflow assignment entity.
   * @param string $type
   *   The notification type.
   * @param array $context
   *   Additional context data.
   * @param string $frequency
   *   The digest frequency ('d' or 'w').
   */
  protected function addToDigestQueue($user, GroupInterface $group, $assignment, $type, array $context, $frequency) {
    // Store in database queue for later processing.
    $queue = \Drupal::database()->insert('avc_notification_queue')
      ->fields([
        'uid' => $user->id(),
        'group_id' => $group->id(),
        'assignment_id' => $assignment->id(),
        'type' => $type,
        'context' => serialize($context),
        'frequency' => $frequency,
        'created' => \Drupal::time()->getRequestTime(),
      ]);

    try {
      $queue->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_group')->error(
        'Error queuing notification: @message',
        ['@message' => $e->getMessage()]
      );
    }
  }

  /**
   * Processes the digest queue and sends batched notifications.
   *
   * @param string $frequency
   *   The frequency to process ('d' for daily, 'w' for weekly).
   */
  public function processDigestQueue($frequency) {
    try {
      $database = \Drupal::database();

      // Get all pending notifications for this frequency.
      $query = $database->select('avc_notification_queue', 'q')
        ->fields('q')
        ->condition('frequency', $frequency)
        ->orderBy('uid')
        ->orderBy('group_id');

      $results = $query->execute()->fetchAll();

      // Group by user.
      $by_user = [];
      foreach ($results as $row) {
        $by_user[$row->uid][] = $row;
      }

      // Send digest for each user.
      foreach ($by_user as $uid => $notifications) {
        $this->sendDigest($uid, $notifications);

        // Clear processed notifications.
        $ids = array_column($notifications, 'id');
        $database->delete('avc_notification_queue')
          ->condition('id', $ids, 'IN')
          ->execute();
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_group')->error(
        'Error processing digest queue: @message',
        ['@message' => $e->getMessage()]
      );
    }
  }

  /**
   * Sends a digest email to a user.
   *
   * @param int $uid
   *   The user ID.
   * @param array $notifications
   *   Array of notification queue items.
   */
  protected function sendDigest($uid, array $notifications) {
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$user) {
      return;
    }

    $params = [
      'user' => $user,
      'notifications' => $notifications,
      'type' => 'digest',
    ];

    $this->mailManager->mail(
      'avc_group',
      'workflow_digest',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      $params
    );
  }

}
