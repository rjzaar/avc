<?php

namespace Drupal\avc_asset\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Service for checking and validating asset workflows.
 *
 * Ports the checkDoc functionality from the Google Apps Script prototype.
 */
class WorkflowChecker {

  /**
   * Validation status constants.
   */
  const STATUS_VALID = 'valid';
  const STATUS_WARNING = 'warning';
  const STATUS_ERROR = 'error';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a WorkflowChecker object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks all workflow entries for an asset.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   *
   * @return array
   *   Validation results with status, messages, and fixes.
   */
  public function check(NodeInterface $node) {
    $result = [
      'valid' => TRUE,
      'status' => self::STATUS_VALID,
      'messages' => [],
      'entries' => [],
      'fixes_applied' => [],
    ];

    try {
      $assignments = $this->getNodeWorkflowAssignments($node);

      if (empty($assignments)) {
        $result['messages'][] = [
          'type' => 'warning',
          'text' => 'No workflow assignments found.',
        ];
        $result['status'] = self::STATUS_WARNING;
        return $result;
      }

      // Check each assignment.
      foreach ($assignments as $index => $assignment) {
        $entry_result = $this->checkEntry($assignment, $index);
        $result['entries'][$index] = $entry_result;

        if ($entry_result['status'] === self::STATUS_ERROR) {
          $result['valid'] = FALSE;
          $result['status'] = self::STATUS_ERROR;
        }
        elseif ($entry_result['status'] === self::STATUS_WARNING &&
                $result['status'] !== self::STATUS_ERROR) {
          $result['status'] = self::STATUS_WARNING;
        }

        // Apply automatic fixes.
        if (!empty($entry_result['fix_available'])) {
          $fix_result = $this->applyFix($assignment, $entry_result['fix_available']);
          if ($fix_result['applied']) {
            $result['fixes_applied'][] = $fix_result;
          }
        }
      }

      // Check workflow structure.
      $structure_result = $this->checkStructure($assignments);
      $result['messages'] = array_merge($result['messages'], $structure_result['messages']);

      if ($structure_result['status'] === self::STATUS_ERROR) {
        $result['valid'] = FALSE;
        $result['status'] = self::STATUS_ERROR;
      }
    }
    catch (\Exception $e) {
      $result['valid'] = FALSE;
      $result['status'] = self::STATUS_ERROR;
      $result['messages'][] = [
        'type' => 'error',
        'text' => 'Error checking workflow: ' . $e->getMessage(),
      ];
    }

    return $result;
  }

  /**
   * Checks a single workflow task.
   *
   * @param mixed $task
   *   The workflow task entity (content entity).
   * @param int $index
   *   The position in the workflow.
   *
   * @return array
   *   Entry validation result.
   */
  protected function checkEntry($task, $index) {
    $result = [
      'index' => $index,
      'label' => $task->label(),
      'status' => self::STATUS_VALID,
      'messages' => [],
      'fix_available' => NULL,
    ];

    // workflow_task is a content entity, use field access.
    $assigned_type = $task->get('assigned_type')->value ?? '';

    // Check assignment type is set.
    if (empty($assigned_type)) {
      $result['status'] = self::STATUS_ERROR;
      $result['messages'][] = 'No assignment type set.';
      return $result;
    }

    // Validate assignee exists based on type.
    switch ($assigned_type) {
      case 'user':
        $user_id = $task->get('assigned_user')->target_id ?? NULL;
        if (empty($user_id)) {
          $result['status'] = self::STATUS_ERROR;
          $result['messages'][] = 'No user assigned.';
        }
        else {
          $user = $this->entityTypeManager->getStorage('user')->load($user_id);
          if (!$user) {
            $result['status'] = self::STATUS_ERROR;
            $result['messages'][] = 'Assigned user does not exist (ID: ' . $user_id . ').';
          }
          elseif (!$user->isActive()) {
            $result['status'] = self::STATUS_WARNING;
            $result['messages'][] = 'Assigned user is inactive.';
          }
        }
        break;

      case 'group':
        $group_id = $task->get('assigned_group')->target_id ?? NULL;
        if (empty($group_id)) {
          $result['status'] = self::STATUS_ERROR;
          $result['messages'][] = 'No group assigned.';
        }
        elseif ($this->entityTypeManager->hasDefinition('group')) {
          $group = $this->entityTypeManager->getStorage('group')->load($group_id);
          if (!$group) {
            $result['status'] = self::STATUS_ERROR;
            $result['messages'][] = 'Assigned group does not exist (ID: ' . $group_id . ').';
          }
          else {
            // Check group has members.
            $members = $group->getMembers();
            if (empty($members)) {
              $result['status'] = self::STATUS_WARNING;
              $result['messages'][] = 'Assigned group has no members.';
            }
          }
        }
        break;

      case 'destination':
        $dest_id = $task->get('assigned_destination')->target_id ?? NULL;
        if (empty($dest_id)) {
          $result['status'] = self::STATUS_ERROR;
          $result['messages'][] = 'No destination assigned.';
        }
        else {
          $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($dest_id);
          if (!$term) {
            $result['status'] = self::STATUS_ERROR;
            $result['messages'][] = 'Destination term does not exist.';
          }
        }
        break;

      default:
        $result['status'] = self::STATUS_ERROR;
        $result['messages'][] = 'Invalid assignment type: ' . $assigned_type;
    }

    return $result;
  }

  /**
   * Checks the overall workflow structure.
   *
   * @param array $workflows
   *   Array of workflow list entities.
   *
   * @return array
   *   Structure validation result.
   */
  protected function checkStructure(array $workflows) {
    $result = [
      'status' => self::STATUS_VALID,
      'messages' => [],
    ];

    if (count($workflows) < 2) {
      $result['status'] = self::STATUS_WARNING;
      $result['messages'][] = [
        'type' => 'warning',
        'text' => 'Workflow has fewer than 2 stages.',
      ];
    }

    // Check if destination is last.
    $last = end($workflows);
    if ($last) {
      // workflow_list is a config entity, use getter methods.
      $last_type = $last->getAssignedType() ?? '';
      if ($last_type !== 'destination') {
        $result['messages'][] = [
          'type' => 'info',
          'text' => 'Workflow does not end with a destination. This may be intentional.',
        ];
      }
    }

    // Note: workflow_list config entities don't have completion status.
    // That would be tracked on workflow_assignment content entities
    // when individual asset progress tracking is implemented.

    return $result;
  }

  /**
   * Attempts to apply an automatic fix.
   *
   * @param mixed $assignment
   *   The workflow assignment.
   * @param array $fix
   *   The fix to apply.
   *
   * @return array
   *   Fix result.
   */
  protected function applyFix($assignment, array $fix) {
    $result = [
      'applied' => FALSE,
      'description' => '',
    ];

    // Implement automatic fixes as needed.
    // For example: matching a member name to ID.

    return $result;
  }

  /**
   * Tries to resolve an assignee by name.
   *
   * @param string $name
   *   The assignee name to search for.
   * @param string $type
   *   The type: 'user', 'group', or 'destination'.
   *
   * @return int|null
   *   The entity ID if found.
   */
  public function resolveAssigneeByName($name, $type) {
    $name = trim($name);
    if (empty($name)) {
      return NULL;
    }

    try {
      switch ($type) {
        case 'user':
          $users = $this->entityTypeManager->getStorage('user')
            ->loadByProperties(['name' => $name]);
          if (!empty($users)) {
            return reset($users)->id();
          }
          // Try display name.
          $query = $this->entityTypeManager->getStorage('user')->getQuery()
            ->condition('field_profile_first_name', $name, 'CONTAINS')
            ->accessCheck(TRUE)
            ->range(0, 1);
          $ids = $query->execute();
          return !empty($ids) ? reset($ids) : NULL;

        case 'group':
          $groups = $this->entityTypeManager->getStorage('group')
            ->loadByProperties(['label' => $name]);
          return !empty($groups) ? reset($groups)->id() : NULL;

        case 'destination':
          $terms = $this->entityTypeManager->getStorage('taxonomy_term')
            ->loadByProperties([
              'vid' => 'destination_locations',
              'name' => $name,
            ]);
          return !empty($terms) ? reset($terms)->id() : NULL;
      }
    }
    catch (\Exception $e) {
      // Ignore.
    }

    return NULL;
  }

  /**
   * Gets workflow tasks for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   Array of workflow task entities.
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

}
