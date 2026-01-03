<?php

namespace Drupal\avc_devel\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for cleaning up AVC test content.
 */
class CleanupContentForm extends ConfirmFormBase {

  /**
   * The test content generator.
   *
   * @var \Drupal\avc_devel\Generator\TestContentGenerator
   */
  protected $generator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->generator = $container->get('avc_devel.generator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_devel_cleanup_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete all AVC test content?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $counts = $this->getTestContentCounts();
    $items = [];

    foreach ($counts as $type => $count) {
      if ($count > 0) {
        $items[] = $this->t('@count @type', ['@count' => $count, '@type' => $type]);
      }
    }

    if (empty($items)) {
      return $this->t('No test content found to delete.');
    }

    return $this->t('This will permanently delete: @items. This action cannot be undone.', [
      '@items' => implode(', ', $items),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('avc_devel.generate');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete All Test Content');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $summary = $this->generator->cleanupAll();

    $messages = [];
    foreach ($summary as $type => $count) {
      if ($count > 0) {
        $messages[] = $this->t('@count @type', ['@count' => $count, '@type' => $type]);
      }
    }

    if (!empty($messages)) {
      $this->messenger()->addStatus($this->t('Deleted test content: @items', [
        '@items' => implode(', ', $messages),
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('No test content was found to delete.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Gets counts of test content.
   *
   * @return array
   *   Counts by type.
   */
  protected function getTestContentCounts() {
    $counts = [
      'users' => 0,
      'nodes' => 0,
      'workflow_assignments' => 0,
    ];

    $entity_type_manager = \Drupal::entityTypeManager();

    // Count test users.
    $counts['users'] = $entity_type_manager->getStorage('user')
      ->getQuery()
      ->condition('name', 'avc_test_user_%', 'LIKE')
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    // Count test nodes.
    $counts['nodes'] = $entity_type_manager->getStorage('node')
      ->getQuery()
      ->condition('title', '[TEST]%', 'LIKE')
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    // Count workflow assignments.
    if ($entity_type_manager->hasDefinition('workflow_assignment')) {
      $counts['workflow_assignments'] = $entity_type_manager->getStorage('workflow_assignment')
        ->getQuery()
        ->condition('title', '[TEST]%', 'LIKE')
        ->accessCheck(FALSE)
        ->count()
        ->execute();
    }

    return $counts;
  }

}
