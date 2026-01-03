<?php

namespace Drupal\workflow_assignment;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Provides a list builder for workflow task entities.
 */
class WorkflowTaskListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Task');
    $header['asset'] = $this->t('Asset');
    $header['assigned'] = $this->t('Assigned To');
    $header['status'] = $this->t('Status');
    $header['weight'] = $this->t('Order');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\workflow_assignment\Entity\WorkflowTask $entity */
    $row['title'] = Link::createFromRoute(
      $entity->label(),
      'entity.workflow_task.canonical',
      ['workflow_task' => $entity->id()]
    );

    // Asset (node) reference.
    $node = $entity->getNode();
    $row['asset'] = $node ? Link::createFromRoute(
      $node->label(),
      'entity.node.canonical',
      ['node' => $node->id()]
    ) : '-';

    // Assigned to.
    $row['assigned'] = $entity->getAssignedLabel() ?: '-';

    // Status with badge styling.
    $status = $entity->getStatus();
    $status_labels = [
      'pending' => $this->t('Pending'),
      'in_progress' => $this->t('In Progress'),
      'completed' => $this->t('Completed'),
      'skipped' => $this->t('Skipped'),
    ];
    $row['status'] = $status_labels[$status] ?? $status;

    // Weight/order.
    $row['weight'] = $entity->getWeight();

    return $row + parent::buildRow($entity);
  }

}
