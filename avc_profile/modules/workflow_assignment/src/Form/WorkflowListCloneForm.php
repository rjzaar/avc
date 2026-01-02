<?php

namespace Drupal\workflow_assignment\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for cloning a workflow.
 */
class WorkflowListCloneForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workflow being cloned.
   *
   * @var \Drupal\workflow_assignment\Entity\WorkflowList
   */
  protected $workflow;

  /**
   * Constructs a WorkflowListCloneForm object.
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
    return 'workflow_list_clone_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $workflow_list = NULL) {
    $this->workflow = $this->entityTypeManager->getStorage('workflow_list')->load($workflow_list);

    if (!$this->workflow) {
      $this->messenger()->addError($this->t('Workflow not found.'));
      return $form;
    }

    $form['source'] = [
      '#type' => 'item',
      '#title' => $this->t('Source Workflow'),
      '#markup' => $this->workflow->label(),
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New Workflow Name'),
      '#default_value' => $this->t('@name (Copy)', ['@name' => $this->workflow->label()]),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => '',
      '#machine_name' => [
        'exists' => [$this, 'workflowExists'],
        'source' => ['label'],
      ],
      '#required' => TRUE,
    ];

    $form['copy_assignment'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Copy assignment'),
      '#description' => $this->t('Copy the user/group/destination assignment from the source workflow.'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clone Workflow'),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->workflow->toUrl('collection'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * Checks if a workflow ID already exists.
   */
  public function workflowExists($id) {
    return (bool) $this->entityTypeManager->getStorage('workflow_list')->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $this->entityTypeManager->getStorage('workflow_list');

    $values = [
      'id' => $form_state->getValue('id'),
      'label' => $form_state->getValue('label'),
      'description' => $this->workflow->getDescription(),
      'comments' => $this->workflow->getComments(),
    ];

    if ($form_state->getValue('copy_assignment')) {
      $values['assigned_type'] = $this->workflow->getAssignedType();
      $values['assigned_id'] = $this->workflow->getAssignedId();
    }

    $clone = $storage->create($values);
    $clone->save();

    // Log the clone action.
    $this->logWorkflowChange($clone->id(), 'create', NULL, 'Cloned from ' . $this->workflow->id());

    $this->messenger()->addStatus($this->t('Workflow %name has been cloned.', [
      '%name' => $clone->label(),
    ]));

    $form_state->setRedirect('entity.workflow_list.collection');
  }

  /**
   * Logs workflow changes.
   */
  protected function logWorkflowChange($workflow_id, $action, $field = NULL, $value = NULL) {
    $database = \Drupal::database();

    if (!$database->schema()->tableExists('workflow_assignment_history')) {
      return;
    }

    $database->insert('workflow_assignment_history')
      ->fields([
        'workflow_id' => $workflow_id,
        'action' => $action,
        'field_name' => $field,
        'new_value' => is_string($value) ? substr($value, 0, 255) : NULL,
        'uid' => $this->currentUser()->id(),
        'timestamp' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

}
