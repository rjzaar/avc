<?php

namespace Drupal\avc_error_report\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Service to handle rate limiting for error report submissions.
 */
class RateLimitService {

  /**
   * The key-value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $store;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a RateLimitService object.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   The key-value factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    KeyValueFactoryInterface $keyValueFactory,
    ConfigFactoryInterface $configFactory,
    TimeInterface $time
  ) {
    $this->store = $keyValueFactory->get('avc_error_report.rate_limit');
    $this->configFactory = $configFactory;
    $this->time = $time;
  }

  /**
   * Checks if a user is allowed to submit an error report.
   *
   * @param int $userId
   *   The user ID to check.
   *
   * @return bool
   *   TRUE if the user can submit, FALSE if rate limited.
   */
  public function isAllowed(int $userId): bool {
    $config = $this->configFactory->get('avc_error_report.settings');
    $maxSubmissions = $config->get('rate_limit_max') ?: 5;
    $window = $config->get('rate_limit_window') ?: 3600;

    $submissions = $this->getSubmissions($userId);
    $now = $this->time->getRequestTime();
    $windowStart = $now - $window;

    // Filter to submissions within the window.
    $recentSubmissions = array_filter($submissions, function ($timestamp) use ($windowStart) {
      return $timestamp >= $windowStart;
    });

    return count($recentSubmissions) < $maxSubmissions;
  }

  /**
   * Records a submission for a user.
   *
   * @param int $userId
   *   The user ID.
   */
  public function recordSubmission(int $userId): void {
    $submissions = $this->getSubmissions($userId);
    $now = $this->time->getRequestTime();

    // Add the new submission.
    $submissions[] = $now;

    // Clean up old submissions (older than 24 hours to be safe).
    $cutoff = $now - 86400;
    $submissions = array_filter($submissions, function ($timestamp) use ($cutoff) {
      return $timestamp >= $cutoff;
    });

    $this->store->set($this->getKey($userId), array_values($submissions));
  }

  /**
   * Gets the number of remaining submissions for a user.
   *
   * @param int $userId
   *   The user ID.
   *
   * @return int
   *   The number of remaining submissions in the current window.
   */
  public function getRemainingSubmissions(int $userId): int {
    $config = $this->configFactory->get('avc_error_report.settings');
    $maxSubmissions = $config->get('rate_limit_max') ?: 5;
    $window = $config->get('rate_limit_window') ?: 3600;

    $submissions = $this->getSubmissions($userId);
    $now = $this->time->getRequestTime();
    $windowStart = $now - $window;

    $recentSubmissions = array_filter($submissions, function ($timestamp) use ($windowStart) {
      return $timestamp >= $windowStart;
    });

    return max(0, $maxSubmissions - count($recentSubmissions));
  }

  /**
   * Gets the submissions for a user.
   *
   * @param int $userId
   *   The user ID.
   *
   * @return array
   *   An array of submission timestamps.
   */
  protected function getSubmissions(int $userId): array {
    return $this->store->get($this->getKey($userId), []);
  }

  /**
   * Gets the storage key for a user.
   *
   * @param int $userId
   *   The user ID.
   *
   * @return string
   *   The storage key.
   */
  protected function getKey(int $userId): string {
    return 'user_' . $userId;
  }

}
