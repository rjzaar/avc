<?php

namespace Drupal\avc_work_management\Form;

use Drupal\avc_work_management\Service\WorkTaskActionService;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to confirm claiming a task.
 */
class ClaimTaskForm extends ConfirmFormBase {

  protected WorkTaskActionService $taskAction;
  protected ?ContentEntityInterface $task = NULL;

  /**
   * Constructs a ClaimTaskForm.
   */
  public function __construct(WorkTaskActionService $task_action) {
    $this->taskAction = $task_action;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_work_management.task_action')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_work_management_claim_task_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Claim this task?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('You will become the assignee for this task and it will appear in your Action Needed list.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('avc_work_management.my_work');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Claim Task');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $workflow_task = NULL) {
    $this->task = $workflow_task;

    if (!$this->taskAction->canClaim($workflow_task)) {
      $this->messenger()->addError($this->t('You cannot claim this task.'));
      return $this->redirect('avc_work_management.my_work');
    }

    // Show task details.
    $form['task_info'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['claim-task-info']],
    ];

    $form['task_info']['title'] = [
      '#markup' => '<h3>' . $workflow_task->label() . '</h3>',
    ];

    if ($workflow_task->hasField('description') && !$workflow_task->get('description')->isEmpty()) {
      $form['task_info']['description'] = [
        '#markup' => '<p>' . $workflow_task->get('description')->value . '</p>',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->taskAction->claimTask($this->task)) {
      $this->messenger()->addStatus($this->t('Task claimed successfully. It now appears in your Action Needed list.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to claim task. Please try again.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
