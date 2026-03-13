<?php

namespace Drupal\workflow_assignment\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\workflow_assignment\Service\ReEditWorkflowService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for initiating a re-edit workflow on published content.
 */
class ReEditWorkflowForm extends FormBase {

  protected ReEditWorkflowService $reEditService;

  public function __construct(ReEditWorkflowService $re_edit_service) {
    $this->reEditService = $re_edit_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workflow_assignment.re_edit_workflow')
    );
  }

  public function getFormId(): string {
    return 'workflow_re_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node) {
      return $form;
    }

    $form['#node'] = $node;

    if (!$this->reEditService->canReEdit($node)) {
      $form['error'] = [
        '#markup' => '<p>' . $this->t('This content cannot be re-edited because it has an active workflow.') . '</p>',
      ];
      return $form;
    }

    $current_version = 'v' . ($node->hasField('field_version_major') ? $node->get('field_version_major')->value : '1') .
                       '.' . ($node->hasField('field_version_minor') ? $node->get('field_version_minor')->value : '0');
    $next_major = ($node->hasField('field_version_major') ? (int) $node->get('field_version_major')->value : 1) + 1;

    $form['version_info'] = [
      '#markup' => '<p>' . $this->t('Version transition: @current &rarr; v@next.0', [
        '@current' => $current_version,
        '@next' => $next_major,
      ]) . '</p>',
    ];

    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reason for Re-Edit'),
      '#description' => $this->t('Explain why this content needs to be re-edited.'),
      '#required' => TRUE,
      '#rows' => 3,
    ];

    $form['template'] = [
      '#type' => 'radios',
      '#title' => $this->t('Workflow Template'),
      '#options' => [
        'full' => $this->t('Full - Copy all tasks from previous cycle'),
        'abbreviated' => $this->t('Abbreviated - Review and publish only'),
      ],
      '#default_value' => 'full',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Re-Edit'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $node->toUrl(),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $form['#node'];
    $reason = $form_state->getValue('reason');
    $template = $form_state->getValue('template');

    $success = $this->reEditService->initiateReEdit($node, $reason, $template);

    if ($success) {
      $this->messenger()->addStatus($this->t('Re-edit workflow started for %title.', [
        '%title' => $node->getTitle(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to start re-edit workflow.'));
    }

    $form_state->setRedirectUrl($node->toUrl());
  }

}
