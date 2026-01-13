<?php

namespace Drupal\avc_email_reply\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;

/**
 * Configure email reply settings.
 */
class EmailReplySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['avc_email_reply.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_email_reply_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('avc_email_reply.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable email reply functionality'),
      '#description' => $this->t('Allow users to reply to content notifications via email.'),
      '#default_value' => $config->get('enabled') ?? FALSE,
    ];

    $form['basic_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic Settings'),
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['basic_settings']['reply_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reply email domain'),
      '#description' => $this->t('The domain for reply-to email addresses (e.g., reply.example.com). Users will reply to addresses like reply+TOKEN@reply.example.com'),
      '#default_value' => $config->get('reply_domain') ?? '',
      '#required' => FALSE,
      '#states' => [
        'required' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['basic_settings']['email_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Email provider'),
      '#description' => $this->t('Select the email service provider that will handle incoming replies.'),
      '#options' => [
        'sendgrid' => $this->t('SendGrid'),
        'mailgun' => $this->t('Mailgun'),
      ],
      '#default_value' => $config->get('email_provider') ?? 'sendgrid',
    ];

    $form['basic_settings']['webhook_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Webhook verification secret'),
      '#description' => $this->t('Secret key used to verify incoming webhooks from your email provider. Leave blank to keep existing value.'),
      '#default_value' => '',
      '#attributes' => [
        'placeholder' => $config->get('webhook_secret') ? '••••••••••••' : '',
      ],
    ];

    $form['basic_settings']['webhook_secret_current'] = [
      '#type' => 'item',
      '#title' => $this->t('Current webhook secret status'),
      '#markup' => $config->get('webhook_secret')
        ? $this->t('A webhook secret is currently set.')
        : $this->t('No webhook secret is currently set.'),
    ];

    $form['token_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Token Settings'),
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['token_settings']['token_expiry_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Token expiry (days)'),
      '#description' => $this->t('Number of days before reply tokens expire. After this period, users cannot reply via email.'),
      '#default_value' => $config->get('token_expiry_days') ?? 30,
      '#min' => 1,
      '#max' => 365,
    ];

    $form['rate_limiting'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Rate Limiting'),
      '#description' => $this->t('Prevent abuse by limiting the number of email replies per user.'),
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rate_limiting']['rate_limit_per_hour'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum replies per hour'),
      '#description' => $this->t('Maximum number of email replies a single user can send per hour.'),
      '#default_value' => $config->get('rate_limits.per_user_per_hour') ?? 10,
      '#min' => 1,
      '#max' => 1000,
    ];

    $form['rate_limiting']['rate_limit_per_day'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum replies per day'),
      '#description' => $this->t('Maximum number of email replies a single user can send per day.'),
      '#default_value' => $config->get('rate_limits.per_user_per_day') ?? 50,
      '#min' => 1,
      '#max' => 10000,
    ];

    $form['rate_limiting']['rate_limit_per_group'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum group replies per hour'),
      '#description' => $this->t('Maximum number of email replies per group per hour (across all users).'),
      '#default_value' => $config->get('rate_limits.per_group_per_hour') ?? 100,
      '#min' => 1,
      '#max' => 10000,
    ];

    $form['spam_protection'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Spam Protection'),
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['spam_protection']['spam_score_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Spam score threshold'),
      '#description' => $this->t('Reject email replies with a spam score above this value. Typical range is 0-10, where higher scores indicate more likely spam. Set to 10 to disable spam filtering.'),
      '#default_value' => $config->get('spam_score_threshold') ?? 5.0,
      '#min' => 0,
      '#max' => 10,
      '#step' => 0.1,
    ];

    $form['content_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Allowed Content Types'),
      '#description' => $this->t('Select which content types support email replies.'),
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Get all content types.
    $node_types = NodeType::loadMultiple();
    $content_type_options = [];
    foreach ($node_types as $type) {
      $content_type_options[$type->id()] = $type->label();
    }

    $form['content_types']['allowed_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable email replies for these content types'),
      '#description' => $this->t('Users will be able to reply via email to notifications about these content types.'),
      '#options' => $content_type_options,
      '#default_value' => $config->get('allowed_content_types') ?? [],
    ];

    $form['debugging'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Debugging'),
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['debugging']['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug mode'),
      '#description' => $this->t('Log detailed information about email reply processing. Warning: This may generate large log files. Only enable for troubleshooting.'),
      '#default_value' => $config->get('debug_mode') ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Only validate if enabled.
    if ($form_state->getValue('enabled')) {
      // Validate reply domain format.
      $reply_domain = $form_state->getValue('reply_domain');
      if (empty($reply_domain)) {
        $form_state->setErrorByName('reply_domain', $this->t('Reply domain is required when email reply is enabled.'));
      }
      elseif (!preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', $reply_domain)) {
        $form_state->setErrorByName('reply_domain', $this->t('Reply domain must be a valid domain name (e.g., reply.example.com).'));
      }

      // Validate rate limits.
      $per_hour = $form_state->getValue('rate_limit_per_hour');
      $per_day = $form_state->getValue('rate_limit_per_day');
      if ($per_hour > $per_day) {
        $form_state->setErrorByName('rate_limit_per_hour', $this->t('Hourly rate limit cannot exceed daily rate limit.'));
      }

      // Validate at least one content type is selected.
      $allowed_types = array_filter($form_state->getValue('allowed_content_types'));
      if (empty($allowed_types)) {
        $form_state->setErrorByName('allowed_content_types', $this->t('You must enable email replies for at least one content type.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('avc_email_reply.settings');

    // Save basic settings.
    $config->set('enabled', $form_state->getValue('enabled'));
    $config->set('reply_domain', $form_state->getValue('reply_domain'));
    $config->set('email_provider', $form_state->getValue('email_provider'));

    // Only update webhook secret if a new value was provided.
    $webhook_secret = $form_state->getValue('webhook_secret');
    if (!empty($webhook_secret)) {
      $config->set('webhook_secret', $webhook_secret);
    }

    // Save token settings.
    $config->set('token_expiry_days', $form_state->getValue('token_expiry_days'));

    // Save rate limiting to nested structure.
    $config->set('rate_limits.per_user_per_hour', (int) $form_state->getValue('rate_limit_per_hour'));
    $config->set('rate_limits.per_user_per_day', (int) $form_state->getValue('rate_limit_per_day'));
    $config->set('rate_limits.per_group_per_hour', (int) $form_state->getValue('rate_limit_per_group'));

    // Save spam protection.
    $config->set('spam_score_threshold', $form_state->getValue('spam_score_threshold'));

    // Save allowed content types (filter out unchecked items).
    $allowed_types = array_filter($form_state->getValue('allowed_content_types'));
    $config->set('allowed_content_types', array_values($allowed_types));

    // Save debugging.
    $config->set('debug_mode', $form_state->getValue('debug_mode'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
