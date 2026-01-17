<?php

namespace Drupal\avc_error_report\Form;

use Drupal\avc_error_report\Service\GitLabService;
use Drupal\avc_error_report\Service\RateLimitService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form for submitting error reports to GitLab.
 */
class ErrorReportForm extends FormBase {

  /**
   * The GitLab service.
   *
   * @var \Drupal\avc_error_report\Service\GitLabService
   */
  protected $gitLabService;

  /**
   * The rate limit service.
   *
   * @var \Drupal\avc_error_report\Service\RateLimitService
   */
  protected $rateLimitService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs an ErrorReportForm object.
   *
   * @param \Drupal\avc_error_report\Service\GitLabService $gitLabService
   *   The GitLab service.
   * @param \Drupal\avc_error_report\Service\RateLimitService $rateLimitService
   *   The rate limit service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    GitLabService $gitLabService,
    RateLimitService $rateLimitService,
    AccountProxyInterface $currentUser,
    RequestStack $requestStack
  ) {
    $this->gitLabService = $gitLabService;
    $this->rateLimitService = $rateLimitService;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_error_report.gitlab'),
      $container->get('avc_error_report.rate_limit'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_error_report_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->requestStack->getCurrentRequest();
    $referer = $request->headers->get('referer', '');

    // Try to get a meaningful page URL from referer.
    $pageUrl = '';
    if ($referer) {
      $parsedUrl = parse_url($referer);
      $pageUrl = $parsedUrl['path'] ?? '';
      if (!empty($parsedUrl['query'])) {
        $pageUrl .= '?' . $parsedUrl['query'];
      }
    }

    // Auto-captured information display.
    $environment = $this->getEnvironmentInfo();
    $form['auto_captured'] = [
      '#type' => 'details',
      '#title' => $this->t('Auto-captured information'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['auto-captured-info']],
    ];

    $envRows = [];
    foreach ($environment as $key => $value) {
      $envRows[] = [$key, $value];
    }

    $form['auto_captured']['info'] = [
      '#type' => 'table',
      '#rows' => $envRows,
      '#attributes' => ['class' => ['auto-captured-table']],
    ];

    // Page URL field.
    $form['page_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page where error occurred'),
      '#default_value' => $pageUrl,
      '#description' => $this->t('The page you were on when the error happened. This is auto-filled from your previous page, but you can edit it if needed.'),
      '#required' => TRUE,
      '#maxlength' => 2048,
    ];

    // What did you do?
    $form['action'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What did you do?'),
      '#description' => $this->t('Describe the action you took that caused the error (e.g., "I clicked Save after changing the group name").'),
      '#required' => TRUE,
      '#rows' => 3,
    ];

    // Error page content.
    $form['error_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Error page content'),
      '#description' => $this->t('<strong>Tip:</strong> If you saw an error page, copy its contents (Ctrl+A, Ctrl+C) before clicking Back, then paste here.'),
      '#rows' => 10,
      '#attributes' => [
        'placeholder' => $this->t('Paste the error page content here...'),
      ],
    ];

    // Reproducible?
    $form['reproducible'] = [
      '#type' => 'radios',
      '#title' => $this->t('Can you reproduce this error?'),
      '#options' => [
        '' => $this->t("Haven't tried"),
        'always' => $this->t('Always - happens every time'),
        'sometimes' => $this->t('Sometimes - happens intermittently'),
        'once' => $this->t('Only happened once'),
      ],
      '#default_value' => '',
    ];

    // Additional notes.
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional notes'),
      '#description' => $this->t('Any other information that might help us understand or fix this issue.'),
      '#rows' => 3,
    ];

    // Store environment data for submission.
    $form['environment_data'] = [
      '#type' => 'hidden',
      '#value' => json_encode($environment),
    ];

    // Submit button.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Error Report'),
      '#button_type' => 'primary',
    ];

    // Check rate limit and show remaining submissions.
    $remaining = $this->rateLimitService->getRemainingSubmissions($this->currentUser->id());
    if ($remaining <= 2) {
      $form['rate_limit_warning'] = [
        '#type' => 'markup',
        '#markup' => '<p class="messages messages--warning">' .
          $this->t('You have @count report(s) remaining in the next hour.', ['@count' => $remaining]) .
          '</p>',
        '#weight' => -100,
      ];
    }

    $form['#attributes']['class'][] = 'error-report-form';
    $form['#attached']['library'][] = 'avc_error_report/error-report';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Check rate limit.
    if (!$this->rateLimitService->isAllowed($this->currentUser->id())) {
      $form_state->setErrorByName('', $this->t('You have submitted too many error reports recently. Please wait before submitting another.'));
    }

    // Validate action field has meaningful content.
    $action = trim($form_state->getValue('action'));
    if (mb_strlen($action) < 10) {
      $form_state->setErrorByName('action', $this->t('Please provide more detail about what you did (at least 10 characters).'));
    }

    // Limit error content size.
    $errorContent = $form_state->getValue('error_content');
    if (mb_strlen($errorContent) > 50000) {
      $form_state->setErrorByName('error_content', $this->t('Error content is too large. Please trim it to the relevant portion (max 50,000 characters).'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $environment = json_decode($form_state->getValue('environment_data'), TRUE) ?: [];

    $data = [
      'page_url' => $form_state->getValue('page_url'),
      'action' => $form_state->getValue('action'),
      'error_content' => $form_state->getValue('error_content'),
      'reproducible' => $form_state->getValue('reproducible'),
      'notes' => $form_state->getValue('notes'),
      'environment' => $environment,
    ];

    // Create the GitLab issue.
    $issueUrl = $this->gitLabService->createIssue($data);

    if ($issueUrl) {
      // Record the submission for rate limiting.
      $this->rateLimitService->recordSubmission($this->currentUser->id());

      $this->messenger()->addStatus($this->t('Thank you! Your error report has been submitted. <a href="@url" target="_blank">View the issue on GitLab</a>.', [
        '@url' => $issueUrl,
      ]));

      $form_state->setRedirect('<front>');
    }
    else {
      $this->messenger()->addError($this->t('Sorry, we could not submit your error report at this time. Please try again later or contact support directly.'));
    }
  }

  /**
   * Gets auto-captured environment information.
   *
   * @return array
   *   An associative array of environment data.
   */
  protected function getEnvironmentInfo(): array {
    $request = $this->requestStack->getCurrentRequest();

    $info = [
      'User' => $this->currentUser->getAccountName(),
      'User ID' => $this->currentUser->id(),
      'Roles' => implode(', ', $this->currentUser->getRoles()),
      'Drupal' => \Drupal::VERSION,
      'PHP' => phpversion(),
    ];

    // Browser info from user agent.
    $userAgent = $request->headers->get('User-Agent', '');
    if ($userAgent) {
      // Simplify user agent for display.
      $info['Browser'] = $this->simplifyUserAgent($userAgent);
    }

    return $info;
  }

  /**
   * Simplifies a user agent string for display.
   *
   * @param string $userAgent
   *   The full user agent string.
   *
   * @return string
   *   A simplified browser/OS description.
   */
  protected function simplifyUserAgent(string $userAgent): string {
    $browser = 'Unknown';
    $os = 'Unknown';

    // Detect browser.
    if (preg_match('/Firefox\/(\d+)/', $userAgent, $m)) {
      $browser = 'Firefox ' . $m[1];
    }
    elseif (preg_match('/Edg\/(\d+)/', $userAgent, $m)) {
      $browser = 'Edge ' . $m[1];
    }
    elseif (preg_match('/Chrome\/(\d+)/', $userAgent, $m)) {
      $browser = 'Chrome ' . $m[1];
    }
    elseif (preg_match('/Safari\/(\d+)/', $userAgent, $m) && !str_contains($userAgent, 'Chrome')) {
      $browser = 'Safari';
    }

    // Detect OS.
    if (str_contains($userAgent, 'Windows')) {
      $os = 'Windows';
    }
    elseif (str_contains($userAgent, 'Mac OS')) {
      $os = 'macOS';
    }
    elseif (str_contains($userAgent, 'Linux')) {
      $os = 'Linux';
    }
    elseif (str_contains($userAgent, 'Android')) {
      $os = 'Android';
    }
    elseif (str_contains($userAgent, 'iOS') || str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
      $os = 'iOS';
    }

    return $browser . ' / ' . $os;
  }

}
