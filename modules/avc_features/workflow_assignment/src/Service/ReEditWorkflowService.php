<?php

namespace Drupal\workflow_assignment\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Service for initiating re-edit workflows on published content.
 *
 * When content has completed its workflow and reached a destination, this
 * service allows creating a new workflow cycle (incrementing the major version)
 * with new tasks based on a template.
 */
class ReEditWorkflowService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountInterface $currentUser;
  protected $logger;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->logger = $logger_factory->get('workflow_assignment');
  }

  /**
   * Check if a node can start a re-edit workflow.
   */
  public function canReEdit(NodeInterface $node): bool {
    // Must have no active workflow tasks.
    return !$this->hasActiveWorkflow($node);
  }

  /**
   * Initiate a re-edit workflow for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to re-edit.
   * @param string $reason
   *   The reason for re-editing.
   * @param string $template
   *   The template type: 'full', 'abbreviated', or 'custom'.
   * @param array $custom_tasks
   *   Task definitions for 'custom' template type.
   *
   * @return bool
   *   TRUE if re-edit was initiated successfully.
   */
  public function initiateReEdit(NodeInterface $node, string $reason, string $template = 'full', array $custom_tasks = []): bool {
    if (!$this->canReEdit($node)) {
      return FALSE;
    }

    try {
      // 1. Increment major version.
      $major = 1;
      $cycle = 1;
      if ($node->hasField('field_version_major')) {
        $major = ((int) $node->get('field_version_major')->value) + 1;
        $node->set('field_version_major', $major);
        $node->set('field_version_minor', 0);
      }

      // 2. Update workflow status.
      if ($node->hasField('field_workflow_status')) {
        $node->set('field_workflow_status', 'reedit');
      }

      // 3. Increment workflow cycle.
      if ($node->hasField('field_active_workflow_cycle')) {
        $cycle = ((int) $node->get('field_active_workflow_cycle')->value) + 1;
        $node->set('field_active_workflow_cycle', $cycle);
      }

      // 4. Unpublish for re-edit.
      $node->setUnpublished();
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage(sprintf(
        'Re-edit initiated (v%d.0): %s',
        $major,
        $reason
      ));
      $node->save();

      // 5. Create new workflow tasks.
      $tasks = $this->getTasksForTemplate($node, $template, $custom_tasks);
      $this->createWorkflowTasks($node, $tasks, $cycle);

      $this->logger->info('Re-edit initiated for node @nid (v@major.0, cycle @cycle): @reason', [
        '@nid' => $node->id(),
        '@major' => $major,
        '@cycle' => $cycle,
        '@reason' => $reason,
      ]);

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to initiate re-edit for node @nid: @message', [
        '@nid' => $node->id(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Check if a node has active (non-completed) workflow tasks.
   */
  protected function hasActiveWorkflow(NodeInterface $node): bool {
    $storage = $this->entityTypeManager->getStorage('workflow_task');
    $count = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('node_id', $node->id())
      ->condition('status', ['pending', 'in_progress'], 'IN')
      ->count()
      ->execute();

    return $count > 0;
  }

  /**
   * Get task definitions based on template type.
   */
  protected function getTasksForTemplate(NodeInterface $node, string $template, array $custom_tasks): array {
    switch ($template) {
      case 'abbreviated':
        return [
          ['title' => 'Review Changes', 'assigned_type' => 'user', 'weight' => 0],
          ['title' => 'Publish', 'assigned_type' => 'destination', 'weight' => 1],
        ];

      case 'custom':
        return $custom_tasks;

      case 'full':
      default:
        // Copy tasks from the most recently completed workflow cycle.
        return $this->getCompletedTaskTemplates($node);
    }
  }

  /**
   * Get task templates from the last completed workflow cycle.
   */
  protected function getCompletedTaskTemplates(NodeInterface $node): array {
    $storage = $this->entityTypeManager->getStorage('workflow_task');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('node_id', $node->id())
      ->condition('status', 'completed')
      ->sort('workflow_cycle', 'DESC')
      ->sort('weight', 'ASC')
      ->execute();

    if (empty($ids)) {
      return [
        ['title' => 'Review', 'assigned_type' => 'user', 'weight' => 0],
        ['title' => 'Approve', 'assigned_type' => 'user', 'weight' => 1],
        ['title' => 'Publish', 'assigned_type' => 'destination', 'weight' => 2],
      ];
    }

    $tasks = $storage->loadMultiple($ids);
    $templates = [];
    $last_cycle = NULL;

    foreach ($tasks as $task) {
      $task_cycle = (int) $task->get('workflow_cycle')->value;
      if ($last_cycle === NULL) {
        $last_cycle = $task_cycle;
      }
      // Only get tasks from the most recent cycle.
      if ($task_cycle !== $last_cycle) {
        break;
      }

      $templates[] = [
        'title' => $task->get('title')->value,
        'assigned_type' => $task->get('assigned_type')->value,
        'assigned_user' => $task->get('assigned_user')->target_id,
        'assigned_group' => $task->get('assigned_group')->target_id,
        'assigned_destination' => $task->get('assigned_destination')->target_id,
        'weight' => (int) $task->get('weight')->value,
      ];
    }

    return $templates;
  }

  /**
   * Create workflow tasks from task definitions.
   */
  protected function createWorkflowTasks(NodeInterface $node, array $task_defs, int $cycle): void {
    $storage = $this->entityTypeManager->getStorage('workflow_task');

    foreach ($task_defs as $index => $def) {
      $task_data = [
        'uid' => $this->currentUser->id(),
        'node_id' => $node->id(),
        'title' => $def['title'] ?? 'Task ' . ($index + 1),
        'assigned_type' => $def['assigned_type'] ?? 'user',
        'weight' => $def['weight'] ?? $index,
        'status' => $index === 0 ? 'pending' : 'pending',
        'workflow_cycle' => $cycle,
        'node_vid' => $node->getRevisionId(),
      ];

      // Set assignment reference.
      if (isset($def['assigned_user']) && $def['assigned_user']) {
        $task_data['assigned_user'] = $def['assigned_user'];
      }
      if (isset($def['assigned_group']) && $def['assigned_group']) {
        $task_data['assigned_group'] = $def['assigned_group'];
      }
      if (isset($def['assigned_destination']) && $def['assigned_destination']) {
        $task_data['assigned_destination'] = $def['assigned_destination'];
      }

      $task = $storage->create($task_data);
      $task->save();
    }
  }

}
