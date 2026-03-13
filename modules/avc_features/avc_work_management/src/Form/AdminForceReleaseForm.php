<?php

namespace Drupal\avc_work_management\Form;

use Drupal\avc_work_management\Service\WorkTaskActionService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form for force-releasing a claimed workflow task.
 */
class AdminForceReleaseForm extends ConfirmFormBase {

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
    return 'avc_work_management_admin_force_release_form';
  }

  public function getQuestion() {
    $assigned = '';
    if ($this->task) {
      $user_id = $this->task->get('assigned_user')->target_id;
      if ($user_id) {
        $user = \Drupal::entityTypeManager()->getStorage('user')->load($user_id);
        $assigned = $user ? $user->getDisplayName() : 'User #' . $user_id;
      }
    }
    return $this->t('Force-release "@task" currently claimed by @user?', [
      '@task' => $this->task ? $this->task->get('title')->value : '',
      '@user' => $assigned,
    ]);
  }

  public function getDescription() {
    return $this->t('This will immediately release the task back to its original group. The user will be notified.');
  }

  public function getCancelUrl() {
    return Url::fromRoute('entity.workflow_task.collection');
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
    if ($this->task && $this->taskAction->forceRelease($this->task)) {
      $this->messenger()->addStatus($this->t('Task has been force-released.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to force-release task.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
