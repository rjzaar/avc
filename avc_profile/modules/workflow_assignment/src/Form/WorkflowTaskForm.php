<?php

namespace Drupal\workflow_assignment\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Workflow Task entities.
 */
class WorkflowTaskForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Add states to show/hide assignment fields based on type.
    $form['assigned_user']['#states'] = [
      'visible' => [
        ':input[name="assigned_type"]' => ['value' => 'user'],
      ],
    ];
    $form['assigned_group']['#states'] = [
      'visible' => [
        ':input[name="assigned_type"]' => ['value' => 'group'],
      ],
    ];
    $form['assigned_destination']['#states'] = [
      'visible' => [
        ':input[name="assigned_type"]' => ['value' => 'destination'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label workflow task.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label workflow task.', [
          '%label' => $entity->label(),
        ]));
    }

    // Redirect to the node's workflow tab if we have a node reference.
    $node = $entity->getNode();
    if ($node) {
      $form_state->setRedirect('workflow_assignment.node_workflow_tab', [
        'node' => $node->id(),
      ]);
    }
    else {
      $form_state->setRedirect('entity.workflow_task.canonical', [
        'workflow_task' => $entity->id(),
      ]);
    }
  }

}
