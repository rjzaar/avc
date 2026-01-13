<?php

namespace Drupal\Tests\avc_email_reply\Unit\Service;

use Drupal\avc_email_reply\Service\EmailRateLimiter;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for EmailRateLimiter.
 *
 * @group avc_email_reply
 * @coversDefaultClass \Drupal\avc_email_reply\Service\EmailRateLimiter
 */
class EmailRateLimiterTest extends UnitTestCase {

  /**
   * The state service mock.
   *
   * @var \Drupal\Core\State\StateInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $state;

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The config object mock.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_email_reply\Service\EmailRateLimiter
   */
  protected $service;

  /**
   * State storage for testing.
   *
   * @var array
   */
  protected $stateStorage = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Reset state storage.
    $this->stateStorage = [];

    // Mock the state service.
    $this->state = $this->createMock(StateInterface::class);
    $this->state->method('get')
      ->willReturnCallback(function ($key, $default = NULL) {
        return $this->stateStorage[$key] ?? $default;
      });
    $this->state->method('set')
      ->willReturnCallback(function ($key, $value) {
        $this->stateStorage[$key] = $value;
      });

    // Mock the config.
    $this->config = $this->createMock(ImmutableConfig::class);
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limits.per_user_per_hour', 10],
        ['rate_limits.per_user_per_day', 50],
        ['rate_limits.per_group_per_hour', 100],
      ]);

    // Mock the config factory.
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('avc_email_reply.settings')
      ->willReturn($this->config);

    $this->service = new EmailRateLimiter($this->state, $this->configFactory);
  }

  /**
   * Tests isLimited returns false when under limit.
   *
   * @covers ::isLimited
   */
  public function testIsLimitedReturnsFalseWhenUnderLimit() {
    $result = $this->service->isLimited(1);
    $this->assertFalse($result);
  }

  /**
   * Tests isLimited returns true when over hourly limit.
   *
   * @covers ::isLimited
   */
  public function testIsLimitedReturnsTrueWhenOverHourlyLimit() {
    $hour_key = date('Y-m-d-H');
    $user_hourly_key = "avc_email_reply.rate.user.1.hour.{$hour_key}";

    // Set user hourly count to limit.
    $this->stateStorage[$user_hourly_key] = 10;

    $result = $this->service->isLimited(1);
    $this->assertTrue($result);
  }

  /**
   * Tests isLimited returns true when over daily limit.
   *
   * @covers ::isLimited
   */
  public function testIsLimitedReturnsTrueWhenOverDailyLimit() {
    $day_key = date('Y-m-d');
    $user_daily_key = "avc_email_reply.rate.user.1.day.{$day_key}";

    // Set user daily count to limit.
    $this->stateStorage[$user_daily_key] = 50;

    $result = $this->service->isLimited(1);
    $this->assertTrue($result);
  }

  /**
   * Tests isLimited returns true when over group hourly limit.
   *
   * @covers ::isLimited
   */
  public function testIsLimitedReturnsTrueWhenOverGroupHourlyLimit() {
    $hour_key = date('Y-m-d-H');
    $group_hourly_key = "avc_email_reply.rate.group.5.hour.{$hour_key}";

    // User is under limit, but group is at limit.
    $this->stateStorage[$group_hourly_key] = 100;

    $result = $this->service->isLimited(1, 5);
    $this->assertTrue($result);
  }

  /**
   * Tests isLimited does not check group limit when group_id is NULL.
   *
   * @covers ::isLimited
   */
  public function testIsLimitedSkipsGroupCheckWhenGroupIdIsNull() {
    $hour_key = date('Y-m-d-H');
    $group_hourly_key = "avc_email_reply.rate.group.5.hour.{$hour_key}";

    // Group is at limit.
    $this->stateStorage[$group_hourly_key] = 100;

    // But we're not checking group, so should return false.
    $result = $this->service->isLimited(1, NULL);
    $this->assertFalse($result);
  }

  /**
   * Tests isLimited with user at hourly limit but under daily limit.
   *
   * @covers ::isLimited
   */
  public function testIsLimitedHourlyLimitTakesPrecedence() {
    $hour_key = date('Y-m-d-H');
    $day_key = date('Y-m-d');
    $user_hourly_key = "avc_email_reply.rate.user.1.hour.{$hour_key}";
    $user_daily_key = "avc_email_reply.rate.user.1.day.{$day_key}";

    // Set hourly to limit, daily under limit.
    $this->stateStorage[$user_hourly_key] = 10;
    $this->stateStorage[$user_daily_key] = 20;

    $result = $this->service->isLimited(1);
    $this->assertTrue($result);
  }

  /**
   * Tests recordReply increments counters.
   *
   * @covers ::recordReply
   */
  public function testRecordReplyIncrementsCounters() {
    $hour_key = date('Y-m-d-H');
    $day_key = date('Y-m-d');
    $user_hourly_key = "avc_email_reply.rate.user.1.hour.{$hour_key}";
    $user_daily_key = "avc_email_reply.rate.user.1.day.{$day_key}";

    // Initially should be 0.
    $this->assertEquals(0, $this->stateStorage[$user_hourly_key] ?? 0);
    $this->assertEquals(0, $this->stateStorage[$user_daily_key] ?? 0);

    // Record a reply.
    $this->service->recordReply(1);

    // Should be incremented to 1.
    $this->assertEquals(1, $this->stateStorage[$user_hourly_key]);
    $this->assertEquals(1, $this->stateStorage[$user_daily_key]);

    // Record another reply.
    $this->service->recordReply(1);

    // Should be incremented to 2.
    $this->assertEquals(2, $this->stateStorage[$user_hourly_key]);
    $this->assertEquals(2, $this->stateStorage[$user_daily_key]);
  }

  /**
   * Tests recordReply increments group counter when group_id provided.
   *
   * @covers ::recordReply
   */
  public function testRecordReplyIncrementsGroupCounter() {
    $hour_key = date('Y-m-d-H');
    $group_hourly_key = "avc_email_reply.rate.group.5.hour.{$hour_key}";

    // Initially should be 0.
    $this->assertEquals(0, $this->stateStorage[$group_hourly_key] ?? 0);

    // Record a reply with group_id.
    $this->service->recordReply(1, 5);

    // Should be incremented to 1.
    $this->assertEquals(1, $this->stateStorage[$group_hourly_key]);
  }

  /**
   * Tests recordReply does not increment group counter when group_id is NULL.
   *
   * @covers ::recordReply
   */
  public function testRecordReplySkipsGroupCounterWhenGroupIdIsNull() {
    $hour_key = date('Y-m-d-H');
    $group_hourly_key = "avc_email_reply.rate.group.5.hour.{$hour_key}";

    // Record a reply without group_id.
    $this->service->recordReply(1, NULL);

    // Group counter should not exist.
    $this->assertArrayNotHasKey($group_hourly_key, $this->stateStorage);
  }

  /**
   * Tests getRemainingQuota returns correct values.
   *
   * @covers ::getRemainingQuota
   */
  public function testGetRemainingQuotaReturnsCorrectValues() {
    $hour_key = date('Y-m-d-H');
    $day_key = date('Y-m-d');
    $user_hourly_key = "avc_email_reply.rate.user.1.hour.{$hour_key}";
    $user_daily_key = "avc_email_reply.rate.user.1.day.{$day_key}";

    // Set current usage.
    $this->stateStorage[$user_hourly_key] = 3;
    $this->stateStorage[$user_daily_key] = 15;

    $result = $this->service->getRemainingQuota(1);

    // Hourly limit is 10, used 3, should have 7 remaining.
    $this->assertEquals(7, $result['hourly']);

    // Daily limit is 50, used 15, should have 35 remaining.
    $this->assertEquals(35, $result['daily']);
  }

  /**
   * Tests getRemainingQuota returns 0 when at limit.
   *
   * @covers ::getRemainingQuota
   */
  public function testGetRemainingQuotaReturnsZeroWhenAtLimit() {
    $hour_key = date('Y-m-d-H');
    $day_key = date('Y-m-d');
    $user_hourly_key = "avc_email_reply.rate.user.1.hour.{$hour_key}";
    $user_daily_key = "avc_email_reply.rate.user.1.day.{$day_key}";

    // Set current usage to limits.
    $this->stateStorage[$user_hourly_key] = 10;
    $this->stateStorage[$user_daily_key] = 50;

    $result = $this->service->getRemainingQuota(1);

    $this->assertEquals(0, $result['hourly']);
    $this->assertEquals(0, $result['daily']);
  }

  /**
   * Tests getRemainingQuota returns 0 when over limit.
   *
   * @covers ::getRemainingQuota
   */
  public function testGetRemainingQuotaReturnsZeroWhenOverLimit() {
    $hour_key = date('Y-m-d-H');
    $day_key = date('Y-m-d');
    $user_hourly_key = "avc_email_reply.rate.user.1.hour.{$hour_key}";
    $user_daily_key = "avc_email_reply.rate.user.1.day.{$day_key}";

    // Set current usage over limits.
    $this->stateStorage[$user_hourly_key] = 15;
    $this->stateStorage[$user_daily_key] = 60;

    $result = $this->service->getRemainingQuota(1);

    // Should return 0, not negative values.
    $this->assertEquals(0, $result['hourly']);
    $this->assertEquals(0, $result['daily']);
  }

  /**
   * Tests getRemainingQuota returns full quota when no usage.
   *
   * @covers ::getRemainingQuota
   */
  public function testGetRemainingQuotaReturnsFullQuotaWhenNoUsage() {
    $result = $this->service->getRemainingQuota(1);

    // Should return full limits.
    $this->assertEquals(10, $result['hourly']);
    $this->assertEquals(50, $result['daily']);
  }

  /**
   * Tests rate limiting for different users is independent.
   *
   * @covers ::isLimited
   * @covers ::recordReply
   */
  public function testRateLimitingIsIndependentPerUser() {
    // Record 10 replies for user 1 (at hourly limit).
    for ($i = 0; $i < 10; $i++) {
      $this->service->recordReply(1);
    }

    // User 1 should be limited.
    $this->assertTrue($this->service->isLimited(1));

    // User 2 should not be limited.
    $this->assertFalse($this->service->isLimited(2));
  }

  /**
   * Tests custom rate limits from config.
   *
   * @covers ::isLimited
   */
  public function testCustomRateLimitsFromConfig() {
    // Mock config with custom limits.
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnMap([
        ['rate_limits.per_user_per_hour', 5],  // Custom: 5 per hour.
        ['rate_limits.per_user_per_day', 25],  // Custom: 25 per day.
        ['rate_limits.per_group_per_hour', 50], // Custom: 50 per hour.
      ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('avc_email_reply.settings')
      ->willReturn($config);

    $service = new EmailRateLimiter($this->state, $configFactory);

    $hour_key = date('Y-m-d-H');
    $user_hourly_key = "avc_email_reply.rate.user.1.hour.{$hour_key}";

    // Set to custom limit.
    $this->stateStorage[$user_hourly_key] = 5;

    // Should be limited at 5 (not default 10).
    $this->assertTrue($service->isLimited(1));
  }

}
