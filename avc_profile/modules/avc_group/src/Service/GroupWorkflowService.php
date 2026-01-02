<?php

namespace Drupal\avc_group\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Service for managing group workflows.
 */
class GroupWorkflowService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a GroupWorkflowService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Gets all workflow tasks for a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return array
   *   Array of workflow tasks with status.
   */
  public function getGroupAssignments(GroupInterface $group) {
    $assignments = [];

    try {
      // Check if workflow_task entity exists.
      if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
        return $assignments;
      }

      $storage = $this->entityTypeManager->getStorage('workflow_task');

      // Query tasks for this group.
      $query = $storage->getQuery()
        ->condition('assigned_type', 'group')
        ->condition('assigned_group', $group->id())
        ->accessCheck(TRUE)
        ->sort('created', 'DESC');

      $ids = $query->execute();

      if (!empty($ids)) {
        $entities = $storage->loadMultiple($ids);

        foreach ($entities as $task) {
          $assignments[] = [
            'task' => $task,
            'id' => $task->id(),
            'label' => $task->label(),
            'status' => $task->getStatus(),
            'assigned_user' => $this->getAssignedUser($task),
            'due_date' => $task->get('due_date')->value ?? NULL,
            'changed_date' => $task->get('changed')->value ?? NULL,
            'node' => $task->getNode(),
          ];
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_group')->error(
        'Error loading group tasks: @message',
        ['@message' => $e->getMessage()]
      );
    }

    return $assignments;
  }

  /**
   * Gets members of a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return array
   *   Array of group members with their roles.
   */
  public function getGroupMembers(GroupInterface $group) {
    $members = [];

    try {
      foreach ($group->getMembers() as $membership) {
        $user = $membership->getUser();
        if ($user) {
          $roles = [];
          foreach ($membership->getRoles() as $role) {
            $roles[] = $role->label();
          }

          $members[] = [
            'user' => $user,
            'uid' => $user->id(),
            'name' => $user->getDisplayName(),
            'roles' => $roles,
            'is_manager' => $this->isGroupManager($group, $user),
          ];
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_group')->error(
        'Error loading group members: @message',
        ['@message' => $e->getMessage()]
      );
    }

    return $members;
  }

  /**
   * Checks if a user is a group manager.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE if the user is a group manager.
   */
  public function isGroupManager(GroupInterface $group, AccountInterface $account) {
    $membership = $group->getMember($account);
    if (!$membership) {
      return FALSE;
    }

    foreach ($membership->getRoles() as $role) {
      // Check for admin or manager roles.
      $role_id = $role->id();
      if (strpos($role_id, 'admin') !== FALSE ||
          strpos($role_id, 'manager') !== FALSE ||
          $role->hasPermission('administer group')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Creates a workflow task for a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param array $values
   *   The task values.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The created task or NULL on failure.
   */
  public function createGroupAssignment(GroupInterface $group, array $values) {
    try {
      if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
        return NULL;
      }

      $storage = $this->entityTypeManager->getStorage('workflow_task');

      $values['assigned_type'] = 'group';
      $values['assigned_group'] = $group->id();

      $task = $storage->create($values);
      $task->save();

      return $task;
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_group')->error(
        'Error creating group task: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }
  }

  /**
   * Gets tasks for a specific user within a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   Array of tasks for the user in this group.
   */
  public function getUserGroupAssignments(GroupInterface $group, AccountInterface $account) {
    $assignments = [];

    try {
      if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
        return $assignments;
      }

      $storage = $this->entityTypeManager->getStorage('workflow_task');

      // Query tasks where user is assigned within this group's content.
      $query = $storage->getQuery()
        ->condition('assigned_type', 'user')
        ->condition('assigned_user', $account->id())
        ->accessCheck(TRUE);

      $ids = $query->execute();

      if (!empty($ids)) {
        $entities = $storage->loadMultiple($ids);

        foreach ($entities as $task) {
          // Check if this task's content belongs to the group.
          $node = $task->getNode();
          if ($node && $this->nodeInGroup($node, $group)) {
            $assignments[] = [
              'task' => $task,
              'id' => $task->id(),
              'label' => $task->label(),
              'status' => $task->getStatus(),
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_group')->error(
        'Error loading user group tasks: @message',
        ['@message' => $e->getMessage()]
      );
    }

    return $assignments;
  }

  /**
   * Gets the user assigned to a workflow task.
   *
   * @param mixed $task
   *   The workflow task entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   The assigned user or NULL.
   */
  protected function getAssignedUser($task) {
    try {
      $user_id = $task->get('assigned_user')->target_id ?? NULL;
      if ($user_id) {
        return $this->entityTypeManager->getStorage('user')->load($user_id);
      }
    }
    catch (\Exception $e) {
      // Ignore.
    }
    return NULL;
  }

  /**
   * Checks if a node belongs to a group.
   *
   * @param mixed $node
   *   The node entity.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return bool
   *   TRUE if the node belongs to the group.
   */
  protected function nodeInGroup($node, GroupInterface $group) {
    try {
      // Use Group Content to check relationship.
      $group_contents = $this->entityTypeManager
        ->getStorage('group_content')
        ->loadByEntity($node);

      foreach ($group_contents as $content) {
        if ($content->getGroup()->id() === $group->id()) {
          return TRUE;
        }
      }
    }
    catch (\Exception $e) {
      // Ignore.
    }
    return FALSE;
  }

}
