<?php

namespace Drupal\avc_notification\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Service for managing notification preferences.
 */
class NotificationPreferences {

  /**
   * Notification preference options.
   */
  const PREF_IMMEDIATE = 'n';
  const PREF_DAILY = 'd';
  const PREF_WEEKLY = 'w';
  const PREF_NONE = 'x';
  const PREF_PERSONAL = 'p';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a NotificationPreferences service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get notification preference options.
   *
   * @param bool $include_personal
   *   Whether to include the 'personal' option (for group overrides).
   *
   * @return array
   *   Array of preference options.
   */
  public static function getOptions(bool $include_personal = FALSE) {
    $options = [
      self::PREF_IMMEDIATE => t('Immediate (as they occur)'),
      self::PREF_DAILY => t('Daily digest'),
      self::PREF_WEEKLY => t('Weekly digest'),
      self::PREF_NONE => t('No notifications'),
    ];

    if ($include_personal) {
      $options = [self::PREF_PERSONAL => t('Use personal default')] + $options;
    }

    return $options;
  }

  /**
   * Get a user's notification preference.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   The group context (optional).
   *
   * @return string
   *   The notification preference (n, d, w, or x).
   */
  public function getUserPreference(AccountInterface $user, ?GroupInterface $group = NULL) {
    // First check for group-specific override.
    if ($group) {
      $group_pref = $this->getGroupOverride($user, $group);
      if ($group_pref && $group_pref !== self::PREF_PERSONAL) {
        return $group_pref;
      }
    }

    // Fall back to user's default preference.
    return $this->getUserDefault($user);
  }

  /**
   * Get a user's default notification preference.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   *
   * @return string
   *   The default preference.
   */
  public function getUserDefault(AccountInterface $user) {
    // Load full user entity if needed.
    if (!$user instanceof \Drupal\user\UserInterface) {
      $user = $this->entityTypeManager->getStorage('user')->load($user->id());
    }

    // Check for notification preference field.
    if ($user && $user->hasField('field_notification_default')) {
      $value = $user->get('field_notification_default')->value;
      if ($value) {
        return $value;
      }
    }

    // Default to immediate notifications.
    return self::PREF_IMMEDIATE;
  }

  /**
   * Set a user's default notification preference.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param string $preference
   *   The preference value.
   */
  public function setUserDefault(AccountInterface $user, string $preference) {
    if (!$user instanceof \Drupal\user\UserInterface) {
      $user = $this->entityTypeManager->getStorage('user')->load($user->id());
    }

    if ($user && $user->hasField('field_notification_default')) {
      $user->set('field_notification_default', $preference);
      $user->save();
    }
  }

  /**
   * Get a user's notification override for a specific group.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return string|null
   *   The override preference or NULL if not set.
   */
  public function getGroupOverride(AccountInterface $user, GroupInterface $group) {
    // Check user.data first (used by behat tests).
    $user_data = \Drupal::service('user.data');
    $value = $user_data->get('avc_notification', $user->id(), 'group_' . $group->id());
    if ($value) {
      return $value;
    }

    // Fall back to checking the user's membership in this group.
    $membership = $group->getMember($user);

    if ($membership) {
      $group_content = $membership->getGroupRelationship();
      if ($group_content->hasField('field_notification_override')) {
        $value = $group_content->get('field_notification_override')->value;
        if ($value) {
          return $value;
        }
      }
    }

    return NULL;
  }

  /**
   * Set a user's notification override for a specific group.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   * @param string $preference
   *   The preference value.
   */
  public function setGroupOverride(AccountInterface $user, GroupInterface $group, string $preference) {
    $membership = $group->getMember($user);

    if ($membership) {
      $group_content = $membership->getGroupRelationship();
      if ($group_content->hasField('field_notification_override')) {
        $group_content->set('field_notification_override', $preference);
        $group_content->save();
      }
    }

    // Also store in user.data for easy access.
    $user_data = \Drupal::service('user.data');
    $user_data->set('avc_notification', $user->id(), 'group_' . $group->id(), $preference);
  }

  /**
   * Get users who should receive immediate notifications.
   *
   * @return \Drupal\user\UserInterface[]
   *   Array of users.
   */
  public function getImmediateNotificationUsers() {
    $query = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1);

    // If the field exists, filter by it.
    // Otherwise return all active users (they default to immediate).
    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('user', 'user');

    if (isset($field_definitions['field_notification_default'])) {
      $query->condition('field_notification_default', self::PREF_IMMEDIATE);
    }

    $ids = $query->execute();
    return $this->entityTypeManager->getStorage('user')->loadMultiple($ids);
  }

  /**
   * Get a user's last notification run timestamp.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   *
   * @return int
   *   The timestamp or 0 if never run.
   */
  public function getLastNotificationRun(AccountInterface $user) {
    if (!$user instanceof \Drupal\user\UserInterface) {
      $user = $this->entityTypeManager->getStorage('user')->load($user->id());
    }

    if ($user && $user->hasField('field_notification_last_run')) {
      return (int) $user->get('field_notification_last_run')->value;
    }

    return 0;
  }

  /**
   * Set a user's last notification run timestamp.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param int $timestamp
   *   The timestamp.
   */
  public function setLastNotificationRun(AccountInterface $user, int $timestamp) {
    if (!$user instanceof \Drupal\user\UserInterface) {
      $user = $this->entityTypeManager->getStorage('user')->load($user->id());
    }

    if ($user && $user->hasField('field_notification_last_run')) {
      $user->set('field_notification_last_run', $timestamp);
      $user->save();
    }
  }

}
