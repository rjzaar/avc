<?php

namespace Drupal\workflow_assignment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for workflow history/audit log.
 */
class WorkflowHistoryController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a WorkflowHistoryController object.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the workflow history list.
   *
   * @return array
   *   A render array.
   */
  public function list() {
    $header = [
      ['data' => $this->t('Date'), 'field' => 'timestamp', 'sort' => 'desc'],
      ['data' => $this->t('Workflow')],
      ['data' => $this->t('Node')],
      ['data' => $this->t('Action'), 'field' => 'action'],
      ['data' => $this->t('Field')],
      ['data' => $this->t('New Value')],
      ['data' => $this->t('User'), 'field' => 'uid'],
    ];

    // Check if table exists.
    if (!$this->database->schema()->tableExists('workflow_assignment_history')) {
      return [
        '#markup' => $this->t('Workflow history table does not exist. Please run database updates.'),
      ];
    }

    $query = $this->database->select('workflow_assignment_history', 'h')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->extend('Drupal\Core\Database\Query\TableSortExtender');

    $query->fields('h');
    $query->limit(50);
    $query->orderByHeader($header);

    $results = $query->execute()->fetchAll();

    $rows = [];
    foreach ($results as $record) {
      // Load workflow label.
      $workflow_label = $record->workflow_id;
      $workflow = $this->entityTypeManager->getStorage('workflow_list')->load($record->workflow_id);
      if ($workflow) {
        $workflow_label = $workflow->label();
      }

      // Load node title.
      $node_title = '-';
      if ($record->node_id) {
        $node = $this->entityTypeManager->getStorage('node')->load($record->node_id);
        if ($node) {
          $node_title = $node->getTitle();
        }
      }

      // Load user name.
      $user_name = $this->t('Unknown');
      $user = $this->entityTypeManager->getStorage('user')->load($record->uid);
      if ($user) {
        $user_name = $user->getDisplayName();
      }

      // Format action.
      $action_labels = [
        'create' => $this->t('Created'),
        'update' => $this->t('Updated'),
        'delete' => $this->t('Deleted'),
        'assign' => $this->t('Assigned'),
        'unassign' => $this->t('Unassigned'),
      ];
      $action = $action_labels[$record->action] ?? $record->action;

      $rows[] = [
        $this->dateFormatter->format($record->timestamp, 'short'),
        $workflow_label,
        $node_title,
        $action,
        $record->field_name ?: '-',
        $record->new_value ? (strlen($record->new_value) > 50 ? substr($record->new_value, 0, 50) . '...' : $record->new_value) : '-',
        $user_name,
      ];
    }

    // Add page title for Behat tests.
    $build['title'] = [
      '#markup' => '<h1>' . $this->t('Workflow History') . '</h1>',
      '#weight' => -20,
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No workflow history recorded yet.'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
