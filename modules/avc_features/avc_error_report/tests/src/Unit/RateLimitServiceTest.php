<?php

namespace Drupal\Tests\avc_error_report\Unit;

use Drupal\avc_error_report\Service\RateLimitService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the RateLimitService.
 *
 * @group avc_error_report
 * @coversDefaultClass \Drupal\avc_error_report\Service\RateLimitService
 */
class RateLimitServiceTest extends UnitTestCase {

  /**
   * The mocked key-value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $store;

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The mocked time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $time;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_error_report\Service\RateLimitService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->store = $this->createMock(KeyValueStoreInterface::class);

    $keyValueFactory = $this->createMock(KeyValueFactoryInterface::class);
    $keyValueFactory->method('get')
      ->with('avc_error_report.rate_limit')
      ->willReturn($this->store);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnMap([
        ['rate_limit_max', 5],
        ['rate_limit_window', 3600],
      ]);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('avc_error_report.settings')
      ->willReturn($config);

    $this->time = $this->createMock(TimeInterface::class);

    $this->service = new RateLimitService(
      $keyValueFactory,
      $this->configFactory,
      $this->time
    );
  }

  /**
   * Tests isAllowed() when user has no submissions.
   *
   * @covers ::isAllowed
   */
  public function testIsAllowedWithNoSubmissions(): void {
    $this->time->method('getRequestTime')->willReturn(1000000);
    $this->store->method('get')->willReturn([]);

    $this->assertTrue($this->service->isAllowed(1));
  }

  /**
   * Tests isAllowed() when user is under the limit.
   *
   * @covers ::isAllowed
   */
  public function testIsAllowedUnderLimit(): void {
    $now = 1000000;
    $this->time->method('getRequestTime')->willReturn($now);

    // 3 submissions in the current window.
    $submissions = [$now - 100, $now - 200, $now - 300];
    $this->store->method('get')->willReturn($submissions);

    $this->assertTrue($this->service->isAllowed(1));
  }

  /**
   * Tests isAllowed() when user is at the limit.
   *
   * @covers ::isAllowed
   */
  public function testIsAllowedAtLimit(): void {
    $now = 1000000;
    $this->time->method('getRequestTime')->willReturn($now);

    // 5 submissions in the current window (at limit).
    $submissions = [$now - 100, $now - 200, $now - 300, $now - 400, $now - 500];
    $this->store->method('get')->willReturn($submissions);

    $this->assertFalse($this->service->isAllowed(1));
  }

  /**
   * Tests isAllowed() with expired submissions.
   *
   * @covers ::isAllowed
   */
  public function testIsAllowedWithExpiredSubmissions(): void {
    $now = 1000000;
    $this->time->method('getRequestTime')->willReturn($now);

    // 5 submissions but 3 are outside the window.
    $submissions = [
      $now - 100,   // In window.
      $now - 200,   // In window.
      $now - 4000,  // Outside window (>3600).
      $now - 5000,  // Outside window.
      $now - 6000,  // Outside window.
    ];
    $this->store->method('get')->willReturn($submissions);

    // Only 2 submissions in window, so should be allowed.
    $this->assertTrue($this->service->isAllowed(1));
  }

  /**
   * Tests getRemainingSubmissions().
   *
   * @covers ::getRemainingSubmissions
   */
  public function testGetRemainingSubmissions(): void {
    $now = 1000000;
    $this->time->method('getRequestTime')->willReturn($now);

    // 2 submissions in the current window.
    $submissions = [$now - 100, $now - 200];
    $this->store->method('get')->willReturn($submissions);

    // 5 max - 2 used = 3 remaining.
    $this->assertEquals(3, $this->service->getRemainingSubmissions(1));
  }

  /**
   * Tests recordSubmission().
   *
   * @covers ::recordSubmission
   */
  public function testRecordSubmission(): void {
    $now = 1000000;
    $this->time->method('getRequestTime')->willReturn($now);

    $this->store->method('get')->willReturn([]);
    $this->store->expects($this->once())
      ->method('set')
      ->with('user_1', [$now]);

    $this->service->recordSubmission(1);
  }

}
