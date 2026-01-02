<?php

namespace Drupal\avc_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating workflow assignments within a group.
 */
class GroupAssignmentForm extends FormBase {

  /**
   * The group workflow service.
   *
   * @var \Drupal\avc_group\Service\GroupWorkflowService
   */
  protected $workflowService;

  /**
   * The group notification service.
   *
   * @var \Drupal\avc_group\Service\GroupNotificationService
   */
  protected $notificationService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->workflowService = $container->get('avc_group.workflow');
    $instance->notificationService = $container->get('avc_group.notification');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_group_assignment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, GroupInterface $group = NULL) {
    $form_state->set('group', $group);

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Assignment Title'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#rows' => 3,
    ];

    // Get group members for assignment.
    $members = $this->workflowService->getGroupMembers($group);
    $member_options = ['' => $this->t('- Unassigned -')];
    foreach ($members as $member) {
      $member_options[$member['uid']] = $member['name'];
    }

    $form['assigned_user'] = [
      '#type' => 'select',
      '#title' => $this->t('Assign to'),
      '#options' => $member_options,
    ];

    $form['due_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Due Date'),
    ];

    $form['priority'] = [
      '#type' => 'select',
      '#title' => $this->t('Priority'),
      '#options' => [
        'low' => $this->t('Low'),
        'normal' => $this->t('Normal'),
        'high' => $this->t('High'),
        'urgent' => $this->t('Urgent'),
      ],
      '#default_value' => 'normal',
    ];

    // Get workflow templates if available.
    $templates = $this->getWorkflowTemplates();
    if (!empty($templates)) {
      $form['template'] = [
        '#type' => 'select',
        '#title' => $this->t('Workflow Template'),
        '#options' => ['' => $this->t('- None -')] + $templates,
        '#description' => $this->t('Optionally apply a workflow template.'),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Assignment'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('avc_group.workflow', ['group' => $group->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $title = trim($form_state->getValue('title'));
    if (empty($title)) {
      $form_state->setErrorByName('title', $this->t('Title is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $group = $form_state->get('group');

    $values = [
      'label' => $form_state->getValue('title'),
      'description' => $form_state->getValue('description'),
      'due_date' => $form_state->getValue('due_date'),
      'priority' => $form_state->getValue('priority'),
      'completion' => 'proposed',
    ];

    // Set assigned user if specified.
    $assigned_user = $form_state->getValue('assigned_user');
    if (!empty($assigned_user)) {
      $values['assigned_type'] = 'user';
      $values['assigned_user'] = $assigned_user;
    }

    // Create the assignment.
    $assignment = $this->workflowService->createGroupAssignment($group, $values);

    if ($assignment) {
      $this->messenger()->addStatus($this->t('Workflow assignment "@title" created.', [
        '@title' => $values['label'],
      ]));

      // Send notifications.
      $this->notificationService->notifyNewAssignment($group, $assignment);
    }
    else {
      $this->messenger()->addError($this->t('Failed to create workflow assignment.'));
    }

    $form_state->setRedirect('avc_group.workflow', ['group' => $group->id()]);
  }

  /**
   * Gets available workflow templates.
   *
   * @return array
   *   Array of template options.
   */
  protected function getWorkflowTemplates() {
    $templates = [];

    try {
      if ($this->entityTypeManager->hasDefinition('workflow_template')) {
        $storage = $this->entityTypeManager->getStorage('workflow_template');
        $entities = $storage->loadMultiple();

        foreach ($entities as $template) {
          $templates[$template->id()] = $template->label();
        }
      }
    }
    catch (\Exception $e) {
      // Ignore.
    }

    return $templates;
  }

}
