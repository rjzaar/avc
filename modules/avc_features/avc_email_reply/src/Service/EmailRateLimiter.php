<?php

namespace Drupal\avc_email_reply\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Service for rate limiting email replies to prevent abuse.
 */
class EmailRateLimiter {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an EmailRateLimiter object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(StateInterface $state, ConfigFactoryInterface $config_factory) {
    $this->state = $state;
    $this->configFactory = $config_factory;
  }

  /**
   * Checks if a user or group has exceeded rate limits.
   *
   * @param int $user_id
   *   The user ID to check.
   * @param int|null $group_id
   *   Optional group ID to check group limits.
   *
   * @return bool
   *   TRUE if the user/group is rate limited, FALSE otherwise.
   */
  public function isLimited(int $user_id, ?int $group_id = NULL): bool {
    $config = $this->configFactory->get('avc_email_reply.settings');
    $hour_key = date('Y-m-d-H');
    $day_key = date('Y-m-d');

    // Check per-user hourly limit.
    $user_hourly_limit = $config->get('rate_limits.per_user_per_hour') ?? 10;
    $user_hourly_key = "avc_email_reply.rate.user.{$user_id}.hour.{$hour_key}";
    $user_hourly_count = $this->state->get($user_hourly_key, 0);

    if ($user_hourly_count >= $user_hourly_limit) {
      return TRUE;
    }

    // Check per-user daily limit.
    $user_daily_limit = $config->get('rate_limits.per_user_per_day') ?? 50;
    $user_daily_key = "avc_email_reply.rate.user.{$user_id}.day.{$day_key}";
    $user_daily_count = $this->state->get($user_daily_key, 0);

    if ($user_daily_count >= $user_daily_limit) {
      return TRUE;
    }

    // Check per-group hourly limit if group_id is provided.
    if ($group_id !== NULL) {
      $group_hourly_limit = $config->get('rate_limits.per_group_per_hour') ?? 100;
      $group_hourly_key = "avc_email_reply.rate.group.{$group_id}.hour.{$hour_key}";
      $group_hourly_count = $this->state->get($group_hourly_key, 0);

      if ($group_hourly_count >= $group_hourly_limit) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Records an email reply for rate limiting purposes.
   *
   * Stores counter keys alongside a tracking list so stale keys can be
   * cleaned up later by cleanupStaleKeys().
   *
   * @param int $user_id
   *   The user ID who sent the reply.
   * @param int|null $group_id
   *   Optional group ID for the reply.
   */
  public function recordReply(int $user_id, ?int $group_id = NULL): void {
    $hour_key = date('Y-m-d-H');
    $day_key = date('Y-m-d');
    $keys_used = [];

    // Increment user hourly counter.
    $user_hourly_key = "avc_email_reply.rate.user.{$user_id}.hour.{$hour_key}";
    $user_hourly_count = $this->state->get($user_hourly_key, 0);
    $this->state->set($user_hourly_key, $user_hourly_count + 1);
    $keys_used[] = $user_hourly_key;

    // Increment user daily counter.
    $user_daily_key = "avc_email_reply.rate.user.{$user_id}.day.{$day_key}";
    $user_daily_count = $this->state->get($user_daily_key, 0);
    $this->state->set($user_daily_key, $user_daily_count + 1);
    $keys_used[] = $user_daily_key;

    // Increment group hourly counter if group_id is provided.
    if ($group_id !== NULL) {
      $group_hourly_key = "avc_email_reply.rate.group.{$group_id}.hour.{$hour_key}";
      $group_hourly_count = $this->state->get($group_hourly_key, 0);
      $this->state->set($group_hourly_key, $group_hourly_count + 1);
      $keys_used[] = $group_hourly_key;
    }

    // Track active keys for cleanup.
    $tracked = $this->state->get('avc_email_reply.rate.tracked_keys', []);
    $tracked = array_unique(array_merge($tracked, $keys_used));
    $this->state->set('avc_email_reply.rate.tracked_keys', $tracked);
  }

  /**
   * Removes stale rate limit keys from state.
   *
   * Should be called from hook_cron(). Removes hourly keys older than 2 hours
   * and daily keys older than 2 days.
   *
   * @return int
   *   The number of stale keys removed.
   */
  public function cleanupStaleKeys(): int {
    $tracked = $this->state->get('avc_email_reply.rate.tracked_keys', []);
    if (empty($tracked)) {
      return 0;
    }

    $current_hour = date('Y-m-d-H');
    $previous_hour = date('Y-m-d-H', strtotime('-1 hour'));
    $current_day = date('Y-m-d');
    $previous_day = date('Y-m-d', strtotime('-1 day'));

    $kept = [];
    $removed = 0;

    foreach ($tracked as $key) {
      $is_stale = FALSE;

      // Check hourly keys: keep current and previous hour only.
      if (preg_match('/\.hour\.(\d{4}-\d{2}-\d{2}-\d{2})$/', $key, $matches)) {
        $key_hour = $matches[1];
        if ($key_hour !== $current_hour && $key_hour !== $previous_hour) {
          $is_stale = TRUE;
        }
      }

      // Check daily keys: keep current and previous day only.
      if (preg_match('/\.day\.(\d{4}-\d{2}-\d{2})$/', $key, $matches)) {
        $key_day = $matches[1];
        if ($key_day !== $current_day && $key_day !== $previous_day) {
          $is_stale = TRUE;
        }
      }

      if ($is_stale) {
        $this->state->delete($key);
        $removed++;
      }
      else {
        $kept[] = $key;
      }
    }

    $this->state->set('avc_email_reply.rate.tracked_keys', $kept);

    return $removed;
  }

  /**
   * Gets the remaining quota for a user.
   *
   * @param int $user_id
   *   The user ID to check.
   *
   * @return array
   *   Array with 'hourly' and 'daily' keys showing remaining quota.
   */
  public function getRemainingQuota(int $user_id): array {
    $config = $this->configFactory->get('avc_email_reply.settings');
    $hour_key = date('Y-m-d-H');
    $day_key = date('Y-m-d');

    // Get hourly remaining quota.
    $user_hourly_limit = $config->get('rate_limits.per_user_per_hour') ?? 10;
    $user_hourly_key = "avc_email_reply.rate.user.{$user_id}.hour.{$hour_key}";
    $user_hourly_count = $this->state->get($user_hourly_key, 0);
    $hourly_remaining = max(0, $user_hourly_limit - $user_hourly_count);

    // Get daily remaining quota.
    $user_daily_limit = $config->get('rate_limits.per_user_per_day') ?? 50;
    $user_daily_key = "avc_email_reply.rate.user.{$user_id}.day.{$day_key}";
    $user_daily_count = $this->state->get($user_daily_key, 0);
    $daily_remaining = max(0, $user_daily_limit - $user_daily_count);

    return [
      'hourly' => $hourly_remaining,
      'daily' => $daily_remaining,
    ];
  }

}
