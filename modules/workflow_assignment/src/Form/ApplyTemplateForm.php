<?php

namespace Drupal\workflow_assignment\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for applying a workflow template to a node.
 */
class ApplyTemplateForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The node to apply templates to.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Constructs an ApplyTemplateForm object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workflow_assignment_apply_template_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $this->node = $node;

    // Load all templates.
    $templates = $this->entityTypeManager->getStorage('workflow_template')->loadMultiple();

    if (empty($templates)) {
      $form['empty'] = [
        '#markup' => $this->t('No workflow templates available. <a href="@url">Create a template</a> first.', [
          '@url' => '/admin/structure/workflow-template/add',
        ]),
      ];
      return $form;
    }

    $options = [];
    foreach ($templates as $template) {
      $workflow_count = 0;
      if ($template->hasField('template_workflows')) {
        $workflow_count = count($template->get('template_workflows'));
      }
      $options[$template->id()] = $this->t('@name (@count workflows)', [
        '@name' => $template->label(),
        '@count' => $workflow_count,
      ]);
    }

    $form['template'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select Template'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Application Mode'),
      '#options' => [
        'add' => $this->t('Add to existing workflows'),
        'replace' => $this->t('Replace existing workflows'),
      ],
      '#default_value' => 'add',
      '#description' => $this->t('Choose whether to add template workflows to existing ones or replace them entirely.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply Template'),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $node->toUrl('canonical'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $template_id = $form_state->getValue('template');
    $mode = $form_state->getValue('mode');

    $template = $this->entityTypeManager->getStorage('workflow_template')->load($template_id);

    if (!$template) {
      $this->messenger()->addError($this->t('Template not found.'));
      return;
    }

    // Get template workflows.
    $template_workflows = [];
    if ($template->hasField('template_workflows')) {
      foreach ($template->get('template_workflows') as $item) {
        if ($item->target_id) {
          $template_workflows[] = $item->target_id;
        }
      }
    }

    if (empty($template_workflows)) {
      $this->messenger()->addWarning($this->t('Template has no workflows to apply.'));
      return;
    }

    // Get current workflows if adding.
    $current_workflows = [];
    if ($mode === 'add' && $this->node->hasField('field_workflow_list')) {
      foreach ($this->node->get('field_workflow_list') as $item) {
        if ($item->target_id) {
          $current_workflows[] = $item->target_id;
        }
      }
    }

    // Combine workflows.
    $final_workflows = array_unique(array_merge($current_workflows, $template_workflows));

    // Apply to node.
    $this->node->set('field_workflow_list', $final_workflows);
    $this->node->save();

    $this->messenger()->addStatus($this->t('Applied template "@template" to this content.', [
      '@template' => $template->label(),
    ]));

    $form_state->setRedirect('workflow_assignment.node_workflow_tab', ['node' => $this->node->id()]);
  }

}
