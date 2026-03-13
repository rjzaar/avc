<?php

namespace Drupal\avc_work_management\Form;

use Drupal\avc_work_management\Service\WorkTaskActionService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for extending a claim on a workflow task.
 */
class ExtendClaimForm extends ConfirmFormBase {

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
    return 'avc_work_management_extend_claim_form';
  }

  public function getQuestion() {
    $settings = $this->taskAction->getClaimSettings();
    return $this->t('Extend your claim on "@task" by @hours hours?', [
      '@task' => $this->task ? $this->task->get('title')->value : '',
      '@hours' => $settings['extension_duration'],
    ]);
  }

  public function getDescription() {
    if (!$this->task) {
      return '';
    }
    $settings = $this->taskAction->getClaimSettings();
    $extensions = $this->task->hasField('extension_count')
      ? (int) $this->task->get('extension_count')->value
      : 0;
    $remaining_extensions = $settings['max_extensions'] - $extensions;
    $time_remaining = $this->taskAction->getClaimTimeRemaining($this->task);
    $hours_left = round($time_remaining / 3600, 1);

    return $this->t('Current time remaining: @hours hours. You have @remaining extension(s) left.', [
      '@hours' => $hours_left,
      '@remaining' => $remaining_extensions,
    ]);
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
    if ($this->task && $this->taskAction->extendClaim($this->task)) {
      $this->messenger()->addStatus($this->t('Your claim has been extended.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to extend claim. You may have reached the maximum number of extensions.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
