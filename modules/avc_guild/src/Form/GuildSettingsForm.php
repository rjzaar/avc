<?php

namespace Drupal\avc_guild\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for guild settings.
 */
class GuildSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_guild_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['avc_guild.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('avc_guild.settings');

    $form['scoring'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Scoring Settings'),
    ];

    $form['scoring']['points_task_completed'] = [
      '#type' => 'number',
      '#title' => $this->t('Points for Task Completed'),
      '#default_value' => $config->get('points_task_completed') ?? 10,
      '#min' => 0,
    ];

    $form['scoring']['points_task_ratified'] = [
      '#type' => 'number',
      '#title' => $this->t('Points for Task Ratified (bonus)'),
      '#default_value' => $config->get('points_task_ratified') ?? 15,
      '#min' => 0,
    ];

    $form['scoring']['points_ratification_given'] = [
      '#type' => 'number',
      '#title' => $this->t('Points for Giving Ratification'),
      '#default_value' => $config->get('points_ratification_given') ?? 5,
      '#min' => 0,
    ];

    $form['scoring']['points_endorsement_received'] = [
      '#type' => 'number',
      '#title' => $this->t('Points for Endorsement Received'),
      '#default_value' => $config->get('points_endorsement_received') ?? 20,
      '#min' => 0,
    ];

    $form['scoring']['points_endorsement_given'] = [
      '#type' => 'number',
      '#title' => $this->t('Points for Endorsement Given'),
      '#default_value' => $config->get('points_endorsement_given') ?? 5,
      '#min' => 0,
    ];

    $form['promotion'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Promotion Settings'),
    ];

    $form['promotion']['auto_promote'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automatic promotion'),
      '#description' => $this->t('Automatically promote juniors to endorsed when they reach the threshold.'),
      '#default_value' => $config->get('auto_promote') ?? FALSE,
    ];

    $form['promotion']['default_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Default Promotion Threshold'),
      '#description' => $this->t('Default score threshold for promotion (can be overridden per guild).'),
      '#default_value' => $config->get('default_threshold') ?? 100,
      '#min' => 0,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('avc_guild.settings')
      ->set('points_task_completed', $form_state->getValue('points_task_completed'))
      ->set('points_task_ratified', $form_state->getValue('points_task_ratified'))
      ->set('points_ratification_given', $form_state->getValue('points_ratification_given'))
      ->set('points_endorsement_received', $form_state->getValue('points_endorsement_received'))
      ->set('points_endorsement_given', $form_state->getValue('points_endorsement_given'))
      ->set('auto_promote', $form_state->getValue('auto_promote'))
      ->set('default_threshold', $form_state->getValue('default_threshold'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
