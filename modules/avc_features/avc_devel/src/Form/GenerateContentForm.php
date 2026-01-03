<?php

namespace Drupal\avc_devel\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for generating AVC test content.
 */
class GenerateContentForm extends FormBase {

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
    return 'avc_devel_generate_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Generate test content to demonstrate AVC features. All generated content will be prefixed with "[TEST]" for easy identification.') . '</p>',
    ];

    $form['content_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content to Generate'),
    ];

    $form['content_types']['users'] = [
      '#type' => 'number',
      '#title' => $this->t('Test Users'),
      '#description' => $this->t('Number of test users to create (avc_test_user_N).'),
      '#default_value' => 10,
      '#min' => 0,
      '#max' => 50,
    ];

    $form['content_types']['groups'] = [
      '#type' => 'number',
      '#title' => $this->t('Test Groups'),
      '#description' => $this->t('Number of groups with random members.'),
      '#default_value' => 3,
      '#min' => 0,
      '#max' => 10,
    ];

    $form['content_types']['projects'] = [
      '#type' => 'number',
      '#title' => $this->t('Test Projects'),
      '#description' => $this->t('Number of project assets.'),
      '#default_value' => 2,
      '#min' => 0,
      '#max' => 10,
    ];

    $form['content_types']['documents'] = [
      '#type' => 'number',
      '#title' => $this->t('Test Documents'),
      '#description' => $this->t('Number of document assets.'),
      '#default_value' => 5,
      '#min' => 0,
      '#max' => 20,
    ];

    $form['content_types']['resources'] = [
      '#type' => 'number',
      '#title' => $this->t('Test Resources'),
      '#description' => $this->t('Number of resource assets.'),
      '#default_value' => 3,
      '#min' => 0,
      '#max' => 10,
    ];

    $form['content_types']['workflow_assignments'] = [
      '#type' => 'number',
      '#title' => $this->t('Workflow Assignments'),
      '#description' => $this->t('Number of workflow assignments to create.'),
      '#default_value' => 10,
      '#min' => 0,
      '#max' => 50,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Test Content'),
      '#button_type' => 'primary',
    ];

    $form['actions']['generate_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate All (Default Values)'),
      '#submit' => ['::generateAllSubmit'],
    ];

    // Show existing test content summary.
    $existing = $this->getExistingTestContent();
    if (!empty(array_filter($existing))) {
      $form['existing'] = [
        '#type' => 'details',
        '#title' => $this->t('Existing Test Content'),
        '#open' => TRUE,
      ];

      $items = [];
      foreach ($existing as $type => $count) {
        if ($count > 0) {
          $items[] = $this->t('@type: @count', ['@type' => ucfirst($type), '@count' => $count]);
        }
      }

      $form['existing']['list'] = [
        '#theme' => 'item_list',
        '#items' => $items,
      ];

      $form['existing']['cleanup'] = [
        '#type' => 'link',
        '#title' => $this->t('Cleanup Test Content'),
        '#url' => \Drupal\Core\Url::fromRoute('avc_devel.cleanup'),
        '#attributes' => ['class' => ['button']],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $options = [
      'users' => $form_state->getValue('users'),
      'groups' => $form_state->getValue('groups'),
      'projects' => $form_state->getValue('projects'),
      'documents' => $form_state->getValue('documents'),
      'resources' => $form_state->getValue('resources'),
      'workflow_assignments' => $form_state->getValue('workflow_assignments'),
    ];

    $summary = $this->generator->generateAll($options);

    $messages = [];
    foreach ($summary as $type => $count) {
      if ($count > 0) {
        $messages[] = $this->t('@count @type', ['@count' => $count, '@type' => $type]);
      }
    }

    if (!empty($messages)) {
      $this->messenger()->addStatus($this->t('Generated test content: @items', [
        '@items' => implode(', ', $messages),
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('No new content was generated. Content may already exist.'));
    }
  }

  /**
   * Submit handler for "Generate All" button.
   */
  public function generateAllSubmit(array &$form, FormStateInterface $form_state) {
    $summary = $this->generator->generateAll();

    $messages = [];
    foreach ($summary as $type => $count) {
      if ($count > 0) {
        $messages[] = $this->t('@count @type', ['@count' => $count, '@type' => $type]);
      }
    }

    if (!empty($messages)) {
      $this->messenger()->addStatus($this->t('Generated test content: @items', [
        '@items' => implode(', ', $messages),
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('No new content was generated. Content may already exist.'));
    }
  }

  /**
   * Gets summary of existing test content.
   *
   * @return array
   *   Counts by type.
   */
  protected function getExistingTestContent() {
    $counts = [
      'users' => 0,
      'groups' => 0,
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
