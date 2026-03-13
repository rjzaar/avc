<?php

namespace Drupal\workflow_assignment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying version history of workflow-managed content.
 */
class VersionHistoryController extends ControllerBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Display version history for a node.
   */
  public function history(NodeInterface $node): array {
    $cycles = $this->getWorkflowCycles($node);
    $revisions = $this->getNodeRevisions($node);

    $rows = [];
    foreach ($revisions as $revision) {
      $version = 'v' . ($revision->get('field_version_major')->value ?? '1') .
                 '.' . ($revision->get('field_version_minor')->value ?? '0');

      $rows[] = [
        'version' => $version,
        'revision_id' => $revision->getRevisionId(),
        'date' => \Drupal::service('date.formatter')->format($revision->getChangedTime(), 'short'),
        'author' => $revision->getRevisionUser() ? $revision->getRevisionUser()->getDisplayName() : '',
        'log' => $revision->getRevisionLogMessage() ?? '',
        'current' => $revision->isDefaultRevision(),
      ];
    }

    return [
      '#theme' => 'workflow_version_history',
      '#node' => $node,
      '#versions' => $rows,
      '#cycles' => $cycles,
      '#cache' => [
        'tags' => $node->getCacheTags(),
      ],
    ];
  }

  /**
   * Page title callback.
   */
  public function title(NodeInterface $node): string {
    return $this->t('Version History: @title', ['@title' => $node->getTitle()]);
  }

  /**
   * Get workflow cycles for a node.
   */
  protected function getWorkflowCycles(NodeInterface $node): array {
    $storage = $this->entityTypeManager->getStorage('workflow_task');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('node_id', $node->id())
      ->sort('workflow_cycle', 'ASC')
      ->sort('weight', 'ASC')
      ->execute();

    $tasks = $storage->loadMultiple($ids);
    $cycles = [];

    foreach ($tasks as $task) {
      $cycle_num = (int) ($task->get('workflow_cycle')->value ?? 1);
      $cycles[$cycle_num][] = [
        'title' => $task->get('title')->value,
        'status' => $task->get('status')->value,
        'assigned_type' => $task->get('assigned_type')->value,
        'assigned_label' => $task->getAssignedLabel(),
        'weight' => (int) $task->get('weight')->value,
      ];
    }

    return $cycles;
  }

  /**
   * Get all revisions of a node.
   */
  protected function getNodeRevisions(NodeInterface $node): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $vids = $storage->revisionIds($node);

    $revisions = [];
    foreach (array_reverse($vids) as $vid) {
      $revisions[] = $storage->loadRevision($vid);
    }

    return $revisions;
  }

}
