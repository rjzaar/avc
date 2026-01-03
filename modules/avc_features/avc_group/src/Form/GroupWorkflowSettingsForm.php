<?php

namespace Drupal\avc_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for configuring group workflow settings.
 */
class GroupWorkflowSettingsForm extends FormBase {

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
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_group_workflow_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, GroupInterface $group = NULL) {
    $form_state->set('group', $group);

    // Load existing settings.
    $settings = $this->getGroupWorkflowSettings($group);

    $form['workflow_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable workflow tracking'),
      '#default_value' => $settings['enabled'] ?? TRUE,
      '#description' => $this->t('Allow workflow assignments in this group.'),
    ];

    $form['default_template'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Workflow Template'),
      '#options' => ['' => $this->t('- None -')] + $this->getWorkflowTemplates(),
      '#default_value' => $settings['default_template'] ?? '',
      '#description' => $this->t('Default template for new group content.'),
    ];

    $form['notifications'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notification Settings'),
    ];

    $form['notifications']['notify_on_assignment'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify members when assigned'),
      '#default_value' => $settings['notify_on_assignment'] ?? TRUE,
    ];

    $form['notifications']['notify_on_completion'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify managers on completion'),
      '#default_value' => $settings['notify_on_completion'] ?? TRUE,
    ];

    $form['notifications']['notify_on_overdue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send overdue reminders'),
      '#default_value' => $settings['notify_on_overdue'] ?? FALSE,
    ];

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display Settings'),
    ];

    $form['display']['show_completed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show completed assignments'),
      '#default_value' => $settings['show_completed'] ?? TRUE,
    ];

    $form['display']['completed_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to show completed'),
      '#min' => 1,
      '#max' => 365,
      '#default_value' => $settings['completed_days'] ?? 30,
      '#states' => [
        'visible' => [
          ':input[name="show_completed"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $group = $form_state->get('group');

    $settings = [
      'enabled' => $form_state->getValue('workflow_enabled'),
      'default_template' => $form_state->getValue('default_template'),
      'notify_on_assignment' => $form_state->getValue('notify_on_assignment'),
      'notify_on_completion' => $form_state->getValue('notify_on_completion'),
      'notify_on_overdue' => $form_state->getValue('notify_on_overdue'),
      'show_completed' => $form_state->getValue('show_completed'),
      'completed_days' => $form_state->getValue('completed_days'),
    ];

    $this->saveGroupWorkflowSettings($group, $settings);

    $this->messenger()->addStatus($this->t('Workflow settings saved.'));

    $form_state->setRedirect('avc_group.workflow', ['group' => $group->id()]);
  }

  /**
   * Gets workflow settings for a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return array
   *   The settings array.
   */
  protected function getGroupWorkflowSettings(GroupInterface $group) {
    // Store in group field if it exists.
    if ($group->hasField('field_workflow_settings')) {
      $value = $group->get('field_workflow_settings')->value;
      if ($value) {
        return json_decode($value, TRUE) ?: [];
      }
    }

    return [];
  }

  /**
   * Saves workflow settings for a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param array $settings
   *   The settings to save.
   */
  protected function saveGroupWorkflowSettings(GroupInterface $group, array $settings) {
    if ($group->hasField('field_workflow_settings')) {
      $group->set('field_workflow_settings', json_encode($settings));
      $group->save();
    }
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
