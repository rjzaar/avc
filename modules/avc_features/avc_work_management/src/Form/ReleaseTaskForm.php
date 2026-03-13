<?php

namespace Drupal\avc_work_management\Form;

use Drupal\avc_work_management\Service\WorkTaskActionService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for voluntarily releasing a claimed workflow task.
 */
class ReleaseTaskForm extends ConfirmFormBase {

  protected WorkTaskActionService $taskAction;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected ?object $task = NULL;

  public function __construct(
    WorkTaskActionService $task_action,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->taskAction = $task_action;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_work_management.task_action'),
      $container->get('entity_type.manager')
    );
  }

  public function getFormId(): string {
    return 'avc_work_management_release_task_form';
  }

  public function getQuestion() {
    return $this->t('Release your claim on "@task"?', [
      '@task' => $this->task ? $this->task->get('title')->value : '',
    ]);
  }

  public function getDescription() {
    return $this->t('The task will be returned to the group pool and available for other members to claim.');
  }

  public function getCancelUrl() {
    return Url::fromRoute('avc_work_management.my_work');
  }

  public function buildForm(array $form, FormStateInterface $form_state, $workflow_task = NULL): array {
    if ($workflow_task) {
      $this->task = $this->entityTypeManager->getStorage('workflow_task')->load($workflow_task);
    }

    if (!$this->task) {
      $this->messenger()->addError($this->t('Task not found.'));
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->task && $this->taskAction->releaseTask($this->task, NULL, 'voluntary')) {
      $this->messenger()->addStatus($this->t('Task released back to group.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to release task.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
