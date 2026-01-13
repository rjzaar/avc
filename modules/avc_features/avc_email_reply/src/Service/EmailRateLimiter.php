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
   * @param int $user_id
   *   The user ID who sent the reply.
   * @param int|null $group_id
   *   Optional group ID for the reply.
   */
  public function recordReply(int $user_id, ?int $group_id = NULL): void {
    $hour_key = date('Y-m-d-H');
    $day_key = date('Y-m-d');

    // Increment user hourly counter.
    $user_hourly_key = "avc_email_reply.rate.user.{$user_id}.hour.{$hour_key}";
    $user_hourly_count = $this->state->get($user_hourly_key, 0);
    $this->state->set($user_hourly_key, $user_hourly_count + 1);

    // Increment user daily counter.
    $user_daily_key = "avc_email_reply.rate.user.{$user_id}.day.{$day_key}";
    $user_daily_count = $this->state->get($user_daily_key, 0);
    $this->state->set($user_daily_key, $user_daily_count + 1);

    // Increment group hourly counter if group_id is provided.
    if ($group_id !== NULL) {
      $group_hourly_key = "avc_email_reply.rate.group.{$group_id}.hour.{$hour_key}";
      $group_hourly_count = $this->state->get($group_hourly_key, 0);
      $this->state->set($group_hourly_key, $group_hourly_count + 1);
    }
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
