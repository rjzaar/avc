<?php

namespace Drupal\workflow_assignment\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Workflow Template entities.
 */
class WorkflowTemplateForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Get all workflow lists for selection.
    $workflow_storage = $this->entityTypeManager->getStorage('workflow_list');
    $workflows = $workflow_storage->loadMultiple();

    $options = [];
    foreach ($workflows as $workflow) {
      $options[$workflow->id()] = $workflow->label();
    }

    $form['workflow_selection'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Workflows to include'),
      '#options' => $options,
      '#default_value' => $this->getSelectedWorkflows(),
      '#description' => $this->t('Select workflows to include in this template.'),
      '#weight' => 5,
    ];

    return $form;
  }

  /**
   * Gets the currently selected workflows.
   */
  protected function getSelectedWorkflows() {
    $entity = $this->entity;
    $selected = [];

    if ($entity->hasField('template_workflows')) {
      foreach ($entity->get('template_workflows') as $item) {
        if ($item->target_id) {
          $selected[] = $item->target_id;
        }
      }
    }

    return $selected;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    // Set the selected workflows.
    $selected = array_filter($form_state->getValue('workflow_selection', []));
    $entity->set('template_workflows', array_values($selected));

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label Workflow Template.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label Workflow Template.', [
          '%label' => $entity->label(),
        ]));
    }

    $form_state->setRedirect('entity.workflow_template.collection');
  }

}
