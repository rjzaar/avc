<?php

namespace Drupal\workflow_assignment;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for workflow template entities.
 */
class WorkflowTemplateListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    // Add page title for Behat tests.
    $build['title'] = [
      '#markup' => '<h1>' . $this->t('Workflow Templates') . '</h1>',
      '#weight' => -20,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['description'] = $this->t('Description');
    $header['workflows'] = $this->t('Workflows');
    $header['author'] = $this->t('Author');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->label();
    $row['description'] = $entity->getDescription() ? substr($entity->getDescription(), 0, 50) . '...' : '-';

    // Count workflows.
    $workflow_count = 0;
    if ($entity->hasField('template_workflows')) {
      $workflow_count = count($entity->get('template_workflows'));
    }
    $row['workflows'] = $this->t('@count workflows', ['@count' => $workflow_count]);

    $row['author'] = $entity->getOwner() ? $entity->getOwner()->getDisplayName() : '-';

    return $row + parent::buildRow($entity);
  }

}
