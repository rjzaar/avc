<?php

namespace Drupal\avc_member\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Service for managing member worklists.
 */
class MemberWorklistService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MemberWorklistService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets the worklist for a user.
   *
   * Returns all assets where the user is assigned in the workflow
   * and the workflow is at or approaching their stage.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return array
   *   Array of worklist items with status.
   */
  public function getUserWorklist(UserInterface $user) {
    $worklist = [];

    // Query workflow_task entities where user is assigned.
    try {
      if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
        return $worklist;
      }

      $storage = $this->entityTypeManager->getStorage('workflow_task');
      $query = $storage->getQuery()
        ->condition('assigned_type', 'user')
        ->condition('assigned_user', $user->id())
        ->accessCheck(TRUE);
      $ids = $query->execute();

      if (!empty($ids)) {
        $tasks = $storage->loadMultiple($ids);
        foreach ($tasks as $task) {
          $worklist[] = [
            'id' => $task->id(),
            'label' => $task->label(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'node' => $task->getNode(),
          ];
        }
      }
    }
    catch (\Exception $e) {
      // Log error but don't break the page.
      \Drupal::logger('avc_member')->error('Error loading worklist: @message', ['@message' => $e->getMessage()]);
    }

    return $worklist;
  }

  /**
   * Gets worklists for all groups a user belongs to.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return array
   *   Array of group worklists keyed by group ID.
   */
  public function getUserGroupWorklists(UserInterface $user) {
    $group_worklists = [];

    // Get groups the user belongs to.
    try {
      if ($this->entityTypeManager->hasDefinition('group')) {
        $group_storage = $this->entityTypeManager->getStorage('group');

        // Query group_content for user memberships.
        $membership_storage = $this->entityTypeManager->getStorage('group_content');
        $membership_ids = $membership_storage->getQuery()
          ->condition('entity_id', $user->id())
          ->condition('type', '%group_membership', 'LIKE')
          ->accessCheck(TRUE)
          ->execute();

        if (!empty($membership_ids)) {
          $memberships = $membership_storage->loadMultiple($membership_ids);
          foreach ($memberships as $membership) {
            $group = $membership->getGroup();
            if ($group) {
              $group_worklists[$group->id()] = [
                'group_id' => $group->id(),
                'group_label' => $group->label(),
                'items' => $this->getGroupWorklist($group),
              ];
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_member')->error('Error loading group worklists: @message', ['@message' => $e->getMessage()]);
    }

    return $group_worklists;
  }

  /**
   * Gets the worklist for a specific group.
   *
   * @param mixed $group
   *   The group entity.
   *
   * @return array
   *   Array of worklist items.
   */
  public function getGroupWorklist($group) {
    $worklist = [];

    try {
      if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
        return $worklist;
      }

      $storage = $this->entityTypeManager->getStorage('workflow_task');
      $query = $storage->getQuery()
        ->condition('assigned_type', 'group')
        ->condition('assigned_group', $group->id())
        ->accessCheck(TRUE);
      $ids = $query->execute();

      if (!empty($ids)) {
        $tasks = $storage->loadMultiple($ids);
        foreach ($tasks as $task) {
          $worklist[] = [
            'id' => $task->id(),
            'label' => $task->label(),
            'status' => $task->getStatus(),
            'node' => $task->getNode(),
          ];
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_member')->error('Error loading group worklist: @message', ['@message' => $e->getMessage()]);
    }

    return $worklist;
  }

  /**
   * Gets notification settings for a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return array
   *   Notification settings.
   */
  public function getUserNotificationSettings(UserInterface $user) {
    $settings = [
      'default' => 'x', // No notifications by default.
      'last_run' => NULL,
      'groups' => [],
    ];

    // Load from user profile fields if they exist.
    try {
      if ($user->hasField('field_notification_default')) {
        $settings['default'] = $user->get('field_notification_default')->value ?? 'x';
      }
      if ($user->hasField('field_notification_last_run')) {
        $settings['last_run'] = $user->get('field_notification_last_run')->value;
      }
    }
    catch (\Exception $e) {
      // Fields may not exist yet.
    }

    return $settings;
  }

  /**
   * Determines the status of a worklist item for a user.
   *
   * @param mixed $assignment
   *   The workflow assignment entity.
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return string
   *   Status: 'current' (green), 'upcoming', or 'completed'.
   */
  protected function determineWorklistStatus($assignment, UserInterface $user) {
    $completion = $assignment->get('completion')->value ?? 'proposed';

    switch ($completion) {
      case 'completed':
        return 'completed';

      case 'accepted':
        return 'current';

      default:
        return 'upcoming';
    }
  }

}
