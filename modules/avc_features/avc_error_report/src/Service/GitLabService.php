<?php

namespace Drupal\avc_error_report\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service to handle GitLab API communication for error reports.
 */
class GitLabService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a GitLabService object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    ClientInterface $httpClient,
    ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerFactory,
    AccountProxyInterface $currentUser
  ) {
    $this->httpClient = $httpClient;
    $this->configFactory = $configFactory;
    $this->logger = $loggerFactory->get('avc_error_report');
    $this->currentUser = $currentUser;
  }

  /**
   * Creates a GitLab issue from error report data.
   *
   * @param array $data
   *   The error report data with keys:
   *   - page_url: The URL where the error occurred.
   *   - action: What the user did.
   *   - error_content: The pasted error page content.
   *   - reproducible: Whether it can be reproduced.
   *   - notes: Additional notes.
   *   - environment: Auto-captured environment data.
   *
   * @return string|null
   *   The URL of the created issue, or NULL on failure.
   */
  public function createIssue(array $data): ?string {
    $config = $this->configFactory->get('avc_error_report.settings');
    $gitlabUrl = rtrim($config->get('gitlab_url') ?: 'https://git.nwpcode.org', '/');
    $project = $config->get('gitlab_project') ?: 'avc/avc';
    $token = $this->getApiToken();

    if (empty($token)) {
      $this->logger->error('GitLab API token not configured. Cannot create issue.');
      return NULL;
    }

    $title = $this->formatTitle($data);
    $description = $this->formatDescription($data);
    $labels = $config->get('labels') ?: ['bug', 'user-reported'];

    $apiUrl = sprintf(
      '%s/api/v4/projects/%s/issues',
      $gitlabUrl,
      urlencode($project)
    );

    try {
      $response = $this->httpClient->request('POST', $apiUrl, [
        'headers' => [
          'PRIVATE-TOKEN' => $token,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'title' => $title,
          'description' => $description,
          'labels' => implode(',', $labels),
        ],
        'timeout' => 30,
      ]);

      $responseData = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($responseData['web_url'])) {
        $this->logger->info('Created GitLab issue: @url', [
          '@url' => $responseData['web_url'],
        ]);
        return $responseData['web_url'];
      }

      $this->logger->error('GitLab API response missing web_url: @response', [
        '@response' => print_r($responseData, TRUE),
      ]);
      return NULL;
    }
    catch (GuzzleException $e) {
      $this->logger->error('GitLab API request failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Tests the GitLab API connection.
   *
   * @return array
   *   An array with 'success' (bool) and 'message' (string).
   */
  public function testConnection(): array {
    $config = $this->configFactory->get('avc_error_report.settings');
    $gitlabUrl = rtrim($config->get('gitlab_url') ?: 'https://git.nwpcode.org', '/');
    $project = $config->get('gitlab_project') ?: 'avc/avc';
    $token = $this->getApiToken();

    if (empty($token)) {
      return [
        'success' => FALSE,
        'message' => 'GitLab API token is not configured.',
      ];
    }

    $apiUrl = sprintf(
      '%s/api/v4/projects/%s',
      $gitlabUrl,
      urlencode($project)
    );

    try {
      $response = $this->httpClient->request('GET', $apiUrl, [
        'headers' => [
          'PRIVATE-TOKEN' => $token,
        ],
        'timeout' => 10,
      ]);

      $responseData = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($responseData['name'])) {
        return [
          'success' => TRUE,
          'message' => sprintf('Connected to project: %s', $responseData['name_with_namespace'] ?? $responseData['name']),
        ];
      }

      return [
        'success' => FALSE,
        'message' => 'Unexpected response from GitLab API.',
      ];
    }
    catch (GuzzleException $e) {
      return [
        'success' => FALSE,
        'message' => 'Connection failed: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Gets the GitLab API token from config or secrets.
   *
   * @return string
   *   The API token, or empty string if not configured.
   */
  protected function getApiToken(): string {
    $config = $this->configFactory->get('avc_error_report.settings');
    $token = $config->get('gitlab_token');

    // If no token in config, could try to read from .secrets.yml
    // For now, just use config.
    return $token ?: '';
  }

  /**
   * Formats the issue title.
   *
   * @param array $data
   *   The error report data.
   *
   * @return string
   *   The issue title.
   */
  protected function formatTitle(array $data): string {
    $action = $data['action'] ?? 'Unknown action';
    // Truncate to first 80 characters of the action.
    $shortAction = mb_substr($action, 0, 80);
    if (mb_strlen($action) > 80) {
      $shortAction .= '...';
    }

    return sprintf('[User Report] %s', $shortAction);
  }

  /**
   * Formats the issue description in Markdown.
   *
   * @param array $data
   *   The error report data.
   *
   * @return string
   */
  protected function formatDescription(array $data): string {
    $username = $this->currentUser->getAccountName();
    $timestamp = date('Y-m-d H:i:s');

    $lines = [
      '## Error Report',
      '',
      sprintf('**Reported by:** @%s', $username),
      sprintf('**Page:** `%s`', $data['page_url'] ?? 'Unknown'),
      sprintf('**Date:** %s', $timestamp),
    ];

    if (!empty($data['reproducible'])) {
      $lines[] = sprintf('**Reproducible:** %s', $data['reproducible']);
    }

    $lines[] = '';
    $lines[] = '### What the user did';
    $lines[] = '';
    $lines[] = $data['action'] ?? 'Not specified';
    $lines[] = '';

    if (!empty($data['error_content'])) {
      $lines[] = '### Error page content';
      $lines[] = '';
      $lines[] = '```';
      // Limit error content to 10000 characters.
      $errorContent = $data['error_content'];
      if (mb_strlen($errorContent) > 10000) {
        $errorContent = mb_substr($errorContent, 0, 10000) . "\n\n[Truncated - content exceeded 10000 characters]";
      }
      $lines[] = $errorContent;
      $lines[] = '```';
      $lines[] = '';
    }

    if (!empty($data['notes'])) {
      $lines[] = '### Additional notes';
      $lines[] = '';
      $lines[] = $data['notes'];
      $lines[] = '';
    }

    if (!empty($data['environment'])) {
      $lines[] = '### Environment';
      $lines[] = '';
      $lines[] = '| | |';
      $lines[] = '|---|---|';
      foreach ($data['environment'] as $key => $value) {
        $lines[] = sprintf('| %s | %s |', $key, $value);
      }
      $lines[] = '';
    }

    $lines[] = '---';
    $lines[] = '*Submitted via AVC Error Report*';

    return implode("\n", $lines);
  }

}
