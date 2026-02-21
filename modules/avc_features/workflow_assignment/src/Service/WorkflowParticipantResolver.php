<?php

namespace Drupal\workflow_assignment\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves workflow participants for access control.
 */
class WorkflowParticipantResolver {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface|null
   */
  protected $groupMembershipLoader;

  /**
   * Constructs a WorkflowParticipantResolver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param object|null $group_membership_loader
   *   The group membership loader (optional).
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    $group_membership_loader = NULL
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->groupMembershipLoader = $group_membership_loader;
  }

  /**
   * Get all active workflow tasks for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return array
   *   Array of active workflow tasks.
   */
  public function getActiveWorkflowTasks(NodeInterface $node): array {
    try {
      $storage = $this->entityTypeManager->getStorage('workflow_task');
    }
    catch (\Exception $e) {
      return [];
    }

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('node_id', $node->id())
      ->condition('status', ['pending', 'in_progress'], 'IN')
      ->sort('weight', 'ASC');

    $ids = $query->execute();

    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * Get the current (lowest weight pending/in_progress) task.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return object|null
   *   The current task or NULL.
   */
  public function getCurrentTask(NodeInterface $node) {
    $tasks = $this->getActiveWorkflowTasks($node);
    return reset($tasks) ?: NULL;
  }

  /**
   * Get all workflow tasks (including completed) for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return array
   *   Array of all workflow tasks.
   */
  public function getAllWorkflowTasks(NodeInterface $node): array {
    try {
      $storage = $this->entityTypeManager->getStorage('workflow_task');
    }
    catch (\Exception $e) {
      return [];
    }

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('node_id', $node->id())
      ->sort('weight', 'ASC');

    $ids = $query->execute();

    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * Check if user is a participant in any workflow task.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param bool $active_only
   *   If TRUE, only check active tasks. If FALSE, include completed.
   *
   * @return bool
   *   TRUE if user is a participant.
   */
  public function isParticipant(NodeInterface $node, AccountInterface $account, bool $active_only = FALSE): bool {
    $tasks = $active_only
      ? $this->getActiveWorkflowTasks($node)
      : $this->getAllWorkflowTasks($node);

    foreach ($tasks as $task) {
      if ($this->isAssignedToTask($task, $account)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if user is assigned to a specific task.
   *
   * @param object $task
   *   The workflow task.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE if user is assigned to this task.
   */
  public function isAssignedToTask($task, AccountInterface $account): bool {
    $assigned_type = $task->get('assigned_type')->value;

    switch ($assigned_type) {
      case 'user':
        $assigned_user = $task->get('assigned_user')->target_id;
        return $assigned_user && (int) $assigned_user === (int) $account->id();

      case 'group':
        return $this->isUserInAssignedGroup($task, $account);

      case 'destination':
        return FALSE;
    }

    return FALSE;
  }

  /**
   * Check if user is a member of the assigned group.
   *
   * @param object $task
   *   The workflow task.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE if user is in the assigned group.
   */
  protected function isUserInAssignedGroup($task, AccountInterface $account): bool {
    $group_id = $task->get('assigned_group')->target_id;

    if (!$group_id || !$this->groupMembershipLoader) {
      return FALSE;
    }

    try {
      $group = $this->entityTypeManager->getStorage('group')->load($group_id);
      if (!$group) {
        return FALSE;
      }

      $membership = $this->groupMembershipLoader->load($group, $account);
      return $membership !== NULL;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Check if user has completed a task for this node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE if user completed a task.
   */
  public function hasCompletedTask(NodeInterface $node, AccountInterface $account): bool {
    try {
      $storage = $this->entityTypeManager->getStorage('workflow_task');
    }
    catch (\Exception $e) {
      return FALSE;
    }

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('node_id', $node->id())
      ->condition('status', 'completed')
      ->condition('assigned_type', 'user')
      ->condition('assigned_user', $account->id());

    return (bool) $query->count()->execute();
  }

  /**
   * Get cache tags for workflow access.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return array
   *   Array of cache tags.
   */
  public function getAccessCacheTags(NodeInterface $node): array {
    $tags = ['workflow_task_list:' . $node->id()];

    foreach ($this->getAllWorkflowTasks($node) as $task) {
      $tags[] = 'workflow_task:' . $task->id();
    }

    return $tags;
  }

}
