<?php

namespace Drupal\avc_notification\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for notification settings.
 */
class NotificationSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_notification_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['avc_notification.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('avc_notification.settings');

    $form['digest'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Digest Settings'),
    ];

    $form['digest']['daily_digest_hour'] = [
      '#type' => 'select',
      '#title' => $this->t('Daily Digest Hour'),
      '#description' => $this->t('Hour of the day (24-hour format) to send daily digests.'),
      '#options' => array_combine(range(0, 23), array_map(function ($h) {
        return sprintf('%02d:00', $h);
      }, range(0, 23))),
      '#default_value' => $config->get('daily_digest_hour') ?? 8,
    ];

    $form['digest']['weekly_digest_day'] = [
      '#type' => 'select',
      '#title' => $this->t('Weekly Digest Day'),
      '#description' => $this->t('Day of the week to send weekly digests.'),
      '#options' => [
        1 => $this->t('Monday'),
        2 => $this->t('Tuesday'),
        3 => $this->t('Wednesday'),
        4 => $this->t('Thursday'),
        5 => $this->t('Friday'),
        6 => $this->t('Saturday'),
        7 => $this->t('Sunday'),
      ],
      '#default_value' => $config->get('weekly_digest_day') ?? 1,
    ];

    $form['digest']['weekly_digest_hour'] = [
      '#type' => 'select',
      '#title' => $this->t('Weekly Digest Hour'),
      '#description' => $this->t('Hour of the day (24-hour format) to send weekly digests.'),
      '#options' => array_combine(range(0, 23), array_map(function ($h) {
        return sprintf('%02d:00', $h);
      }, range(0, 23))),
      '#default_value' => $config->get('weekly_digest_hour') ?? 8,
    ];

    $form['cleanup'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cleanup Settings'),
    ];

    $form['cleanup']['retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Retention Days'),
      '#description' => $this->t('Number of days to keep sent notifications before cleanup.'),
      '#min' => 1,
      '#max' => 90,
      '#default_value' => $config->get('retention_days') ?? 7,
    ];

    $form['admin'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Admin Settings'),
    ];

    $form['admin']['admin_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Admin Alert Email'),
      '#description' => $this->t('Email address for admin alerts. Leave empty to use site email.'),
      '#default_value' => $config->get('admin_email') ?? '',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('avc_notification.settings')
      ->set('daily_digest_hour', $form_state->getValue('daily_digest_hour'))
      ->set('weekly_digest_day', $form_state->getValue('weekly_digest_day'))
      ->set('weekly_digest_hour', $form_state->getValue('weekly_digest_hour'))
      ->set('retention_days', $form_state->getValue('retention_days'))
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
