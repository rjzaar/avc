<?php

namespace Drupal\avc_work_management\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Service for workflow task actions (claim, complete, release).
 */
class WorkTaskActionService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountInterface $currentUser;
  protected TimeInterface $time;
  protected $logger;

  /**
   * Constructs a WorkTaskActionService.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    TimeInterface $time,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->time = $time;
    $this->logger = $logger_factory->get('avc_work_management');
  }

  /**
   * Check if user can claim a task.
   */
  public function canClaim(object $task, ?AccountInterface $user = NULL): bool {
    $user = $user ?? $this->currentUser;

    // Must be group-assigned.
    if ($task->get('assigned_type')->value !== 'group') {
      return FALSE;
    }

    // Must be pending.
    if ($task->get('status')->value !== 'pending') {
      return FALSE;
    }

    // User must be in the assigned group.
    $group_id = $task->get('assigned_group')->target_id;
    return $this->userInGroup($user, $group_id);
  }

  /**
   * Claim a task for the current user.
   */
  public function claimTask(object $task, ?AccountInterface $user = NULL): bool {
    $user = $user ?? $this->currentUser;

    if (!$this->canClaim($task, $user)) {
      return FALSE;
    }

    try {
      // Store original group for potential release.
      $original_group = $task->get('assigned_group')->target_id;

      // Update assignment.
      $task->set('assigned_type', 'user');
      $task->set('assigned_user', $user->id());
      $task->set('assigned_group', NULL);
      $task->set('status', 'in_progress');

      // Add revision log.
      if ($task->hasField('revision_log')) {
        $task->setRevisionLogMessage(sprintf(
          'Task claimed by %s (was assigned to group %d)',
          $user->getDisplayName(),
          $original_group
        ));
      }
      $task->setNewRevision(TRUE);
      $task->save();

      $this->logger->info('Task @id claimed by user @user', [
        '@id' => $task->id(),
        '@user' => $user->id(),
      ]);

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to claim task @id: @message', [
        '@id' => $task->id(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Mark a task as complete.
   */
  public function completeTask(object $task, ?AccountInterface $user = NULL): bool {
    $user = $user ?? $this->currentUser;

    // Must be assigned to user.
    if ($task->get('assigned_type')->value !== 'user') {
      return FALSE;
    }

    // Must be current assignee or admin.
    $assigned_user = $task->get('assigned_user')->target_id;
    if ((int) $assigned_user !== (int) $user->id() && !$user->hasPermission('administer workflow tasks')) {
      return FALSE;
    }

    try {
      $task->set('status', 'completed');

      if ($task->hasField('revision_log')) {
        $task->setRevisionLogMessage(sprintf(
          'Task completed by %s',
          $user->getDisplayName()
        ));
      }
      $task->setNewRevision(TRUE);
      $task->save();

      $this->logger->info('Task @id completed by user @user', [
        '@id' => $task->id(),
        '@user' => $user->id(),
      ]);

      // Activate next task in sequence if exists.
      $this->activateNextTask($task);

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to complete task @id: @message', [
        '@id' => $task->id(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Release a claimed task back to the group.
   *
   * Note: This requires storing original group somewhere.
   * For now, this is a placeholder.
   */
  public function releaseTask(object $task, int $group_id): bool {
    try {
      $task->set('assigned_type', 'group');
      $task->set('assigned_group', $group_id);
      $task->set('assigned_user', NULL);
      $task->set('status', 'pending');

      $task->setNewRevision(TRUE);
      $task->save();

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to release task @id: @message', [
        '@id' => $task->id(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Activate the next task in the workflow sequence.
   */
  protected function activateNextTask(object $completed_task): void {
    $node_id = $completed_task->get('node_id')->target_id;
    $current_weight = $completed_task->get('weight')->value;

    // Find next pending task.
    $storage = $this->entityTypeManager->getStorage('workflow_task');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('node_id', $node_id)
      ->condition('status', 'pending')
      ->condition('weight', $current_weight, '>')
      ->sort('weight', 'ASC')
      ->range(0, 1);

    $ids = $query->execute();

    if (!empty($ids)) {
      $next_task = $storage->load(reset($ids));
      if ($next_task && $next_task->get('assigned_type')->value === 'user') {
        $next_task->set('status', 'in_progress');
        $next_task->save();

        // TODO: Send notification to next assignee.
      }
    }
  }

  /**
   * Check if user is in a group.
   */
  protected function userInGroup(AccountInterface $user, int $group_id): bool {
    try {
      $group = $this->entityTypeManager->getStorage('group')->load($group_id);
      if (!$group) {
        return FALSE;
      }

      $membership_loader = \Drupal::service('group.membership_loader');
      $membership = $membership_loader->load($group, $user);

      return $membership !== NULL;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}
