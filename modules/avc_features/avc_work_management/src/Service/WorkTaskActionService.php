<?php

namespace Drupal\avc_work_management\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
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
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a WorkTaskActionService.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    TimeInterface $time,
    LoggerChannelFactoryInterface $logger_factory,
    ?ConfigFactoryInterface $config_factory = NULL,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->time = $time;
    $this->logger = $logger_factory->get('avc_work_management');
    $this->configFactory = $config_factory ?? \Drupal::configFactory();
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
   * Claim a task for the current user with time limit.
   */
  public function claimTask(object $task, ?AccountInterface $user = NULL): bool {
    $user = $user ?? $this->currentUser;

    if (!$this->canClaim($task, $user)) {
      return FALSE;
    }

    try {
      // Store original group for release.
      $original_group = $task->get('assigned_group')->target_id;

      // Calculate claim expiration.
      $claim_settings = $this->getClaimSettings();
      $duration_hours = $claim_settings['default_claim_duration'];
      $now = $this->time->getCurrentTime();
      $expires = $now + ($duration_hours * 3600);

      // Update assignment.
      $task->set('assigned_type', 'user');
      $task->set('assigned_user', $user->id());
      $task->set('assigned_group', NULL);
      $task->set('status', 'in_progress');

      // Set time-limited claim fields.
      if ($task->hasField('claimed_at')) {
        $task->set('claimed_at', $now);
      }
      if ($task->hasField('claim_expires')) {
        $task->set('claim_expires', $expires);
      }
      if ($task->hasField('original_group')) {
        $task->set('original_group', $original_group);
      }
      if ($task->hasField('extension_count')) {
        $task->set('extension_count', 0);
      }
      if ($task->hasField('expiry_warning_sent')) {
        $task->set('expiry_warning_sent', FALSE);
      }

      // Add revision log.
      if ($task->hasField('revision_log')) {
        $task->setRevisionLogMessage(sprintf(
          'Task claimed by %s (was assigned to group %d, expires in %d hours)',
          $user->getDisplayName(),
          $original_group,
          $duration_hours
        ));
      }
      $task->setNewRevision(TRUE);
      $task->save();

      $this->logger->info('Task @id claimed by user @user (expires @expires)', [
        '@id' => $task->id(),
        '@user' => $user->id(),
        '@expires' => date('Y-m-d H:i:s', $expires),
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
   * Release a claimed task back to its original group.
   *
   * @param object $task
   *   The task to release.
   * @param int|null $group_id
   *   The group ID to release to. If NULL, uses original_group field.
   * @param string $reason
   *   The reason for release.
   */
  public function releaseTask(object $task, ?int $group_id = NULL, string $reason = 'voluntary'): bool {
    try {
      // Use original_group field if no group_id provided.
      if ($group_id === NULL && $task->hasField('original_group')) {
        $group_id = $task->get('original_group')->target_id;
      }

      if (!$group_id) {
        $this->logger->error('Cannot release task @id: no group ID available.', [
          '@id' => $task->id(),
        ]);
        return FALSE;
      }

      $task->set('assigned_type', 'group');
      $task->set('assigned_group', $group_id);
      $task->set('assigned_user', NULL);
      $task->set('status', 'pending');

      // Clear claim fields.
      if ($task->hasField('claimed_at')) {
        $task->set('claimed_at', NULL);
      }
      if ($task->hasField('claim_expires')) {
        $task->set('claim_expires', NULL);
      }
      if ($task->hasField('expiry_warning_sent')) {
        $task->set('expiry_warning_sent', FALSE);
      }

      if ($task->hasField('revision_log')) {
        $task->setRevisionLogMessage(sprintf(
          'Task released back to group %d (reason: %s)',
          $group_id,
          $reason
        ));
      }
      $task->setNewRevision(TRUE);
      $task->save();

      $this->logger->info('Task @id released to group @group (reason: @reason)', [
        '@id' => $task->id(),
        '@group' => $group_id,
        '@reason' => $reason,
      ]);

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
   * Extend a claim on a task.
   */
  public function extendClaim(object $task, ?AccountInterface $user = NULL): bool {
    $user = $user ?? $this->currentUser;
    $claim_settings = $this->getClaimSettings();

    // Check if task is claimed by this user.
    if ($task->get('assigned_type')->value !== 'user') {
      return FALSE;
    }
    if ((int) $task->get('assigned_user')->target_id !== (int) $user->id()) {
      return FALSE;
    }

    // Check max extensions.
    $extensions = 0;
    if ($task->hasField('extension_count')) {
      $extensions = (int) $task->get('extension_count')->value;
    }

    if ($extensions >= $claim_settings['max_extensions']) {
      return FALSE;
    }

    // Check if self-extension is allowed.
    if (!$claim_settings['allow_self_extension'] && !$user->hasPermission('administer workflow tasks')) {
      return FALSE;
    }

    try {
      $extension_hours = $claim_settings['extension_duration'];
      $current_expires = $task->hasField('claim_expires') ? (int) $task->get('claim_expires')->value : 0;
      $now = $this->time->getCurrentTime();

      // Extend from current expiry or now, whichever is later.
      $base = max($current_expires, $now);
      $new_expires = $base + ($extension_hours * 3600);

      $task->set('claim_expires', $new_expires);
      $task->set('extension_count', $extensions + 1);
      $task->set('expiry_warning_sent', FALSE);

      if ($task->hasField('revision_log')) {
        $task->setRevisionLogMessage(sprintf(
          'Claim extended by %s (extension %d/%d, new expiry: %s)',
          $user->getDisplayName(),
          $extensions + 1,
          $claim_settings['max_extensions'],
          date('Y-m-d H:i:s', $new_expires)
        ));
      }
      $task->setNewRevision(TRUE);
      $task->save();

      $this->logger->info('Task @id claim extended by user @user (extension @count/@max)', [
        '@id' => $task->id(),
        '@user' => $user->id(),
        '@count' => $extensions + 1,
        '@max' => $claim_settings['max_extensions'],
      ]);

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to extend claim on task @id: @message', [
        '@id' => $task->id(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Force-release a task (admin action).
   */
  public function forceRelease(object $task, ?AccountInterface $admin = NULL): bool {
    $admin = $admin ?? $this->currentUser;

    if (!$admin->hasPermission('administer workflow tasks')) {
      return FALSE;
    }

    return $this->releaseTask($task, NULL, 'admin_force_release');
  }

  /**
   * Get remaining time on a claim in seconds.
   */
  public function getClaimTimeRemaining(object $task): int {
    if (!$task->hasField('claim_expires')) {
      return 0;
    }
    $expires = (int) $task->get('claim_expires')->value;
    if (!$expires) {
      return 0;
    }
    return max(0, $expires - $this->time->getCurrentTime());
  }

  /**
   * Check if a claim has expired.
   */
  public function isClaimExpired(object $task): bool {
    if (!$task->hasField('claim_expires')) {
      return FALSE;
    }
    $expires = (int) $task->get('claim_expires')->value;
    if (!$expires) {
      return FALSE;
    }
    return $this->time->getCurrentTime() >= $expires;
  }

  /**
   * Get claim configuration settings.
   */
  public function getClaimSettings(): array {
    $config = $this->configFactory->get('avc_work_management.settings');
    return [
      'default_claim_duration' => $config->get('claim_settings.default_claim_duration') ?? 24,
      'max_extensions' => $config->get('claim_settings.max_extensions') ?? 2,
      'extension_duration' => $config->get('claim_settings.extension_duration') ?? 24,
      'warning_threshold' => $config->get('claim_settings.warning_threshold') ?? 4,
      'allow_self_extension' => $config->get('claim_settings.allow_self_extension') ?? TRUE,
    ];
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
