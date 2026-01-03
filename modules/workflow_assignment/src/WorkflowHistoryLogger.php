<?php

namespace Drupal\workflow_assignment;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Service for logging workflow history.
 */
class WorkflowHistoryLogger {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a WorkflowHistoryLogger object.
   */
  public function __construct(Connection $database, AccountInterface $current_user, TimeInterface $time) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->time = $time;
  }

  /**
   * Logs a workflow change.
   *
   * @param string $workflow_id
   *   The workflow ID.
   * @param string $action
   *   The action (create, update, delete, assign, unassign).
   * @param int|null $node_id
   *   The node ID (if applicable).
   * @param string|null $field
   *   The field that was changed.
   * @param string|null $old_value
   *   The old value.
   * @param string|null $new_value
   *   The new value.
   */
  public function log($workflow_id, $action, $node_id = NULL, $field = NULL, $old_value = NULL, $new_value = NULL) {
    if (!$this->database->schema()->tableExists('workflow_assignment_history')) {
      return;
    }

    $this->database->insert('workflow_assignment_history')
      ->fields([
        'workflow_id' => $workflow_id,
        'node_id' => $node_id,
        'action' => $action,
        'field_name' => $field,
        'old_value' => is_string($old_value) ? substr($old_value, 0, 65535) : NULL,
        'new_value' => is_string($new_value) ? substr($new_value, 0, 65535) : NULL,
        'uid' => $this->currentUser->id(),
        'timestamp' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Logs workflow assignment to a node.
   *
   * @param string $workflow_id
   *   The workflow ID.
   * @param int $node_id
   *   The node ID.
   */
  public function logAssignment($workflow_id, $node_id) {
    $this->log($workflow_id, 'assign', $node_id);
  }

  /**
   * Logs workflow unassignment from a node.
   *
   * @param string $workflow_id
   *   The workflow ID.
   * @param int $node_id
   *   The node ID.
   */
  public function logUnassignment($workflow_id, $node_id) {
    $this->log($workflow_id, 'unassign', $node_id);
  }

}
