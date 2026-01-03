<?php

namespace Drupal\avc_asset\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for processing asset workflows.
 *
 * Ports the processaDoc functionality from the Google Apps Script prototype.
 */
class WorkflowProcessor {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a WorkflowProcessor object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EventDispatcherInterface $event_dispatcher,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger_factory->get('avc_asset');
  }

  /**
   * Processes an asset's workflow, advancing if ready.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   *
   * @return array
   *   Processing result with status and messages.
   */
  public function process(NodeInterface $node) {
    $result = [
      'success' => FALSE,
      'message' => '',
      'advanced' => FALSE,
      'current_stage' => NULL,
      'next_stage' => NULL,
    ];

    try {
      // Get workflow assignments for this node.
      $assignments = $this->getNodeWorkflowAssignments($node);

      if (empty($assignments)) {
        $result['message'] = 'No workflow assignments found.';
        return $result;
      }

      // Find current stage (first step without completion).
      $current_stage = NULL;
      $current_index = -1;

      foreach ($assignments as $index => $assignment) {
        $completion = $assignment->get('completion')->value ?? 'proposed';
        if ($completion !== 'completed') {
          $current_stage = $assignment;
          $current_index = $index;
          break;
        }
      }

      if (!$current_stage) {
        $result['message'] = 'All workflow stages completed.';
        $result['success'] = TRUE;
        return $result;
      }

      $result['current_stage'] = [
        'id' => $current_stage->id(),
        'label' => $current_stage->label(),
        'assigned_to' => $current_stage->getAssignedLabel(),
      ];

      // Check if current stage is ready to advance.
      $comment = $current_stage->get('comment')->value ?? '';
      if (empty(trim($comment))) {
        $result['message'] = 'Waiting for comment/action on current stage.';
        $result['success'] = TRUE;
        return $result;
      }

      // Advance to next stage.
      $next_index = $current_index + 1;
      if (isset($assignments[$next_index])) {
        $next_stage = $assignments[$next_index];

        // Mark current as completed.
        $current_stage->set('completion', 'completed');
        $current_stage->set('completed_date', \Drupal::time()->getRequestTime());
        $current_stage->save();

        // Activate next stage.
        $next_stage->set('completion', 'accepted');
        $next_stage->save();

        $result['advanced'] = TRUE;
        $result['next_stage'] = [
          'id' => $next_stage->id(),
          'label' => $next_stage->label(),
          'assigned_to' => $next_stage->getAssignedLabel(),
        ];
        $result['message'] = 'Workflow advanced to next stage.';

        // Send notification to next assignee.
        $this->sendStageNotification($node, $next_stage);

        // Log the transition.
        $this->logTransition($node, $current_stage, $next_stage);
      }
      else {
        // No more stages - workflow complete.
        $current_stage->set('completion', 'completed');
        $current_stage->set('completed_date', \Drupal::time()->getRequestTime());
        $current_stage->save();

        // Update node status.
        if ($node->hasField('field_process_status')) {
          $node->set('field_process_status', 'completed');
          $node->save();
        }

        $result['message'] = 'Workflow completed.';
        $this->logCompletion($node);
      }

      $result['success'] = TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Error processing workflow: @message', [
        '@message' => $e->getMessage(),
      ]);
      $result['message'] = 'Error processing workflow: ' . $e->getMessage();
    }

    return $result;
  }

  /**
   * Advances workflow to the next stage.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   * @param string $comment
   *   Comment for the current stage.
   *
   * @return array
   *   Result with status.
   */
  public function advance(NodeInterface $node, $comment = '') {
    $result = [
      'success' => FALSE,
      'message' => '',
    ];

    try {
      $assignments = $this->getNodeWorkflowAssignments($node);
      $current_stage = $this->getCurrentStage($assignments);

      if (!$current_stage) {
        $result['message'] = 'No active workflow stage found.';
        return $result;
      }

      // Add comment if provided.
      if (!empty($comment)) {
        $existing = $current_stage->get('comment')->value ?? '';
        $timestamp = date('Y-m-d H:i');
        $new_comment = $existing . "\n[{$timestamp}] {$comment}";
        $current_stage->set('comment', trim($new_comment));
        $current_stage->save();
      }

      // Process the workflow.
      return $this->process($node);
    }
    catch (\Exception $e) {
      $this->logger->error('Error advancing workflow: @message', [
        '@message' => $e->getMessage(),
      ]);
      $result['message'] = 'Error: ' . $e->getMessage();
    }

    return $result;
  }

  /**
   * Resends notification for current stage.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   *
   * @return array
   *   Result with status.
   */
  public function resendNotification(NodeInterface $node) {
    $result = [
      'success' => FALSE,
      'message' => '',
    ];

    try {
      $assignments = $this->getNodeWorkflowAssignments($node);
      $current_stage = $this->getCurrentStage($assignments);

      if (!$current_stage) {
        $result['message'] = 'No active workflow stage found.';
        return $result;
      }

      $this->sendStageNotification($node, $current_stage, TRUE);

      // Log resend.
      $this->logResend($node, $current_stage);

      $result['success'] = TRUE;
      $result['message'] = 'Notification resent.';
    }
    catch (\Exception $e) {
      $this->logger->error('Error resending notification: @message', [
        '@message' => $e->getMessage(),
      ]);
      $result['message'] = 'Error: ' . $e->getMessage();
    }

    return $result;
  }

  /**
   * Gets workflow tasks for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   Array of workflow task entities, ordered by weight.
   */
  protected function getNodeWorkflowAssignments(NodeInterface $node) {
    if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('workflow_task');
    $query = $storage->getQuery()
      ->condition('node_id', $node->id())
      ->sort('weight', 'ASC')
      ->accessCheck(TRUE);

    $ids = $query->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Gets the current active stage.
   *
   * @param array $tasks
   *   Array of workflow tasks.
   *
   * @return mixed|null
   *   The current task or NULL.
   */
  protected function getCurrentStage(array $tasks) {
    foreach ($tasks as $task) {
      $status = $task->getStatus();
      if ($status !== 'completed') {
        return $task;
      }
    }
    return NULL;
  }

  /**
   * Sends notification for a workflow stage.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   * @param mixed $stage
   *   The workflow assignment.
   * @param bool $is_resend
   *   Whether this is a resend.
   */
  protected function sendStageNotification(NodeInterface $node, $stage, $is_resend = FALSE) {
    $assigned_type = $stage->get('assigned_type')->value ?? '';
    $mail_manager = \Drupal::service('plugin.manager.mail');

    $params = [
      'node' => $node,
      'stage' => $stage,
      'is_resend' => $is_resend,
    ];

    if ($assigned_type === 'user') {
      $user_id = $stage->get('assigned_user')->target_id ?? NULL;
      if ($user_id) {
        $user = $this->entityTypeManager->getStorage('user')->load($user_id);
        if ($user && $user->getEmail()) {
          $mail_manager->mail(
            'avc_asset',
            'workflow_stage',
            $user->getEmail(),
            $user->getPreferredLangcode(),
            $params
          );
        }
      }
    }
    elseif ($assigned_type === 'group') {
      // Notify all group members.
      $group_id = $stage->get('assigned_group')->target_id ?? NULL;
      if ($group_id) {
        $group = $this->entityTypeManager->getStorage('group')->load($group_id);
        if ($group) {
          foreach ($group->getMembers() as $membership) {
            $user = $membership->getUser();
            if ($user && $user->getEmail()) {
              $mail_manager->mail(
                'avc_asset',
                'workflow_stage',
                $user->getEmail(),
                $user->getPreferredLangcode(),
                $params
              );
            }
          }
        }
      }
    }
  }

  /**
   * Logs a workflow transition.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   * @param mixed $from_stage
   *   The completed stage.
   * @param mixed $to_stage
   *   The new active stage.
   */
  protected function logTransition(NodeInterface $node, $from_stage, $to_stage) {
    $this->logger->info('Workflow advanced on @title: @from -> @to', [
      '@title' => $node->getTitle(),
      '@from' => $from_stage->label(),
      '@to' => $to_stage->label(),
    ]);
  }

  /**
   * Logs workflow completion.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   */
  protected function logCompletion(NodeInterface $node) {
    $this->logger->info('Workflow completed on @title', [
      '@title' => $node->getTitle(),
    ]);
  }

  /**
   * Logs notification resend.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   * @param mixed $stage
   *   The workflow assignment.
   */
  protected function logResend(NodeInterface $node, $stage) {
    $this->logger->info('Notification resent for @title stage @stage', [
      '@title' => $node->getTitle(),
      '@stage' => $stage->label(),
    ]);
  }

}
