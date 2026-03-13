<?php

namespace Drupal\Tests\avc_notification\Unit\Service;

use Drupal\avc_notification\Entity\NotificationQueue;
use Drupal\avc_notification\Service\NotificationAggregator;
use Drupal\avc_notification\Service\NotificationProcessor;
use Drupal\avc_notification\Service\NotificationPreferences;
use Drupal\avc_notification\Service\NotificationSender;
use Drupal\avc_notification\Service\NotificationService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for NotificationProcessor.
 *
 * @group avc_notification
 * @coversDefaultClass \Drupal\avc_notification\Service\NotificationProcessor
 */
class NotificationProcessorTest extends UnitTestCase {

  /**
   * The notification aggregator mock.
   *
   * @var \Drupal\avc_notification\Service\NotificationAggregator|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $aggregator;

  /**
   * The notification sender mock.
   *
   * @var \Drupal\avc_notification\Service\NotificationSender|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $sender;

  /**
   * The notification preferences mock.
   *
   * @var \Drupal\avc_notification\Service\NotificationPreferences|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $preferences;

  /**
   * The notification service mock.
   *
   * @var \Drupal\avc_notification\Service\NotificationService|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $notificationService;

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
   * The logger mock.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_notification\Service\NotificationProcessor
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->aggregator = $this->createMock(NotificationAggregator::class);
    $this->sender = $this->createMock(NotificationSender::class);
    $this->preferences = $this->createMock(NotificationPreferences::class);
    $this->notificationService = $this->createMock(NotificationService::class);
    $this->state = $this->createMock(StateInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->processor = new NotificationProcessor(
      $this->aggregator,
      $this->sender,
      $this->preferences,
      $this->notificationService,
      $this->state,
      $this->configFactory,
      $this->logger
    );
  }

  /**
   * Creates a mock notification queue entity.
   *
   * @param int $id
   *   The notification ID.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mock notification.
   */
  protected function createMockNotification(int $id) {
    $notification = $this->createMock(NotificationQueue::class);

    $notification->method('id')->willReturn($id);

    return $notification;
  }

  /**
   * Tests processImmediate sends notifications via sender.
   *
   * @covers ::processImmediate
   */
  public function testProcessImmediate(): void {
    $notification1 = $this->createMockNotification(1);
    $notification2 = $this->createMockNotification(2);

    $this->aggregator->expects($this->once())
      ->method('getImmediateNotifications')
      ->willReturn([$notification1, $notification2]);

    $this->sender->expects($this->exactly(2))
      ->method('sendImmediate')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        'Processed immediate notifications: @sent sent, @failed failed.',
        ['@sent' => 2, '@failed' => 0]
      );

    $this->processor->processImmediate();
  }

  /**
   * Tests processQueue calls processImmediate.
   *
   * @covers ::processQueue
   */
  public function testProcessQueueCallsImmediate(): void {
    // The aggregator should be called for immediate notifications
    // as part of processQueue calling processImmediate.
    $this->aggregator->expects($this->once())
      ->method('getImmediateNotifications')
      ->willReturn([]);

    // Set up config mock for shouldRunDailyDigest and shouldRunWeeklyDigest.
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnMap([
        ['daily_digest_hour', 8],
        ['weekly_digest_day', 1],
        ['weekly_digest_hour', 8],
        ['retention_days', 7],
      ]);

    $this->configFactory->method('get')
      ->with('avc_notification.settings')
      ->willReturn($config);

    // State returns a recent timestamp so digest checks won't trigger.
    $this->state->method('get')
      ->willReturnMap([
        ['avc_notification.daily_digest_last_run', 0, time()],
        ['avc_notification.weekly_digest_last_run', 0, time()],
      ]);

    // Cleanup will call notificationService.
    $this->notificationService->expects($this->once())
      ->method('cleanupOldNotifications')
      ->with(7);

    $this->processor->processQueue();
  }

  /**
   * Tests processDailyDigest checks state for last run time.
   *
   * @covers ::processDailyDigest
   */
  public function testProcessDailyDigestChecksState(): void {
    $user = $this->createMock(AccountInterface::class);
    $user->method('id')->willReturn(42);

    $digestData = [
      42 => [
        'user' => $user,
        'notifications' => [],
        'by_group' => [],
      ],
    ];

    $this->aggregator->expects($this->once())
      ->method('getDailyDigestData')
      ->willReturn($digestData);

    $this->sender->expects($this->once())
      ->method('sendDailyDigest')
      ->with($user, $digestData[42])
      ->willReturn(TRUE);

    $this->preferences->expects($this->once())
      ->method('setLastNotificationRun')
      ->with($user, $this->anything());

    // Verify state is updated with the last run timestamp.
    $this->state->expects($this->once())
      ->method('set')
      ->with('avc_notification.daily_digest_last_run', $this->anything());

    $this->processor->processDailyDigest();
  }

  /**
   * Tests processWeeklyDigest checks state for last run time.
   *
   * @covers ::processWeeklyDigest
   */
  public function testProcessWeeklyDigestChecksState(): void {
    $user = $this->createMock(AccountInterface::class);
    $user->method('id')->willReturn(7);

    $digestData = [
      7 => [
        'user' => $user,
        'notifications' => [],
        'by_group' => [],
      ],
    ];

    $this->aggregator->expects($this->once())
      ->method('getWeeklyDigestData')
      ->willReturn($digestData);

    $this->sender->expects($this->once())
      ->method('sendWeeklyDigest')
      ->with($user, $digestData[7])
      ->willReturn(TRUE);

    $this->preferences->expects($this->once())
      ->method('setLastNotificationRun')
      ->with($user, $this->anything());

    // Verify state is updated with the last run timestamp.
    $this->state->expects($this->once())
      ->method('set')
      ->with('avc_notification.weekly_digest_last_run', $this->anything());

    $this->processor->processWeeklyDigest();
  }

}
