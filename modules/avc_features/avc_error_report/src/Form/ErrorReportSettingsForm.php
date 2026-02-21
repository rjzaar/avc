<?php

namespace Drupal\avc_error_report\Form;

use Drupal\avc_error_report\Service\GitLabService;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for AVC Error Report settings.
 */
class ErrorReportSettingsForm extends ConfigFormBase {

  /**
   * The GitLab service.
   *
   * @var \Drupal\avc_error_report\Service\GitLabService
   */
  protected $gitLabService;

  /**
   * Constructs an ErrorReportSettingsForm object.
   *
   * @param \Drupal\avc_error_report\Service\GitLabService $gitLabService
   *   The GitLab service.
   */
  public function __construct(GitLabService $gitLabService) {
    $this->gitLabService = $gitLabService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_error_report.gitlab')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['avc_error_report.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_error_report_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('avc_error_report.settings');

    $form['gitlab'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('GitLab Settings'),
    ];

    $form['gitlab']['gitlab_url'] = [
      '#type' => 'url',
      '#title' => $this->t('GitLab URL'),
      '#default_value' => $config->get('gitlab_url'),
      '#description' => $this->t('The base URL of your GitLab instance (e.g., https://git.nwpcode.org).'),
      '#required' => TRUE,
    ];

    $form['gitlab']['gitlab_project'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GitLab Project'),
      '#default_value' => $config->get('gitlab_project'),
      '#description' => $this->t('The project path (e.g., nwp/avc or group/project).'),
      '#required' => TRUE,
    ];

    $form['gitlab']['gitlab_token'] = [
      '#type' => 'password',
      '#title' => $this->t('GitLab API Token'),
      '#description' => $this->t('A GitLab personal or project access token with "api" scope. Leave blank to keep the existing token.'),
      '#attributes' => [
        'autocomplete' => 'new-password',
      ],
    ];

    if ($config->get('gitlab_token')) {
      $form['gitlab']['gitlab_token']['#description'] .= ' ' . $this->t('<strong>A token is currently configured.</strong>');
    }

    // Connection status display area.
    $form['gitlab']['connection_status'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'connection-status',
        'class' => ['connection-status'],
      ],
    ];

    $form['gitlab']['test_connection'] = [
      '#type' => 'button',
      '#value' => $this->t('Test Connection'),
      '#ajax' => [
        'callback' => '::testConnectionAjax',
        'wrapper' => 'connection-status',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Testing connection...'),
        ],
      ],
    ];

    $form['gitlab']['labels'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Issue Labels'),
      '#default_value' => implode(', ', $config->get('labels') ?: []),
      '#description' => $this->t('Comma-separated list of labels to add to created issues (e.g., bug, user-reported).'),
    ];

    $form['rate_limiting'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Rate Limiting'),
    ];

    $form['rate_limiting']['rate_limit_max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum reports per window'),
      '#default_value' => $config->get('rate_limit_max') ?: 5,
      '#min' => 1,
      '#max' => 100,
      '#description' => $this->t('Maximum number of error reports a user can submit within the time window.'),
      '#required' => TRUE,
    ];

    $form['rate_limiting']['rate_limit_window'] = [
      '#type' => 'number',
      '#title' => $this->t('Time window (seconds)'),
      '#default_value' => $config->get('rate_limit_window') ?: 3600,
      '#min' => 60,
      '#max' => 86400,
      '#description' => $this->t('The time window in seconds for rate limiting (e.g., 3600 = 1 hour).'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback for testing the GitLab connection.
   */
  public function testConnectionAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    $result = $this->gitLabService->testConnection();

    if ($result['success']) {
      $message = '<div class="messages messages--status">' .
        $this->t('<strong>Connection successful:</strong> @message', [
          '@message' => $result['message'],
        ]) . '</div>';
    }
    else {
      $message = '<div class="messages messages--error">' .
        $this->t('<strong>Connection failed:</strong> @message', [
          '@message' => $result['message'],
        ]) . '</div>';
    }

    $response->addCommand(new HtmlCommand('#connection-status', $message));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('avc_error_report.settings');

    $config->set('gitlab_url', rtrim($form_state->getValue('gitlab_url'), '/'));
    $config->set('gitlab_project', $form_state->getValue('gitlab_project'));

    // Only update token if a new one is provided.
    $newToken = $form_state->getValue('gitlab_token');
    if (!empty($newToken)) {
      $config->set('gitlab_token', $newToken);
    }

    // Parse labels from comma-separated string.
    $labelsString = $form_state->getValue('labels');
    $labels = array_map('trim', explode(',', $labelsString));
    $labels = array_filter($labels);
    $config->set('labels', $labels);

    $config->set('rate_limit_max', (int) $form_state->getValue('rate_limit_max'));
    $config->set('rate_limit_window', (int) $form_state->getValue('rate_limit_window'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
