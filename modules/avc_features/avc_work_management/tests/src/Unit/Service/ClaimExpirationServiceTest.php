<?php

namespace Drupal\Tests\avc_work_management\Unit\Service;

use Drupal\avc_work_management\Service\ClaimExpirationService;
use Drupal\avc_work_management\Service\WorkTaskActionService;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for ClaimExpirationService.
 *
 * @group avc_work_management
 * @coversDefaultClass \Drupal\avc_work_management\Service\ClaimExpirationService
 */
class ClaimExpirationServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected WorkTaskActionService $taskAction;
  protected TimeInterface $time;
  protected ClaimExpirationService $service;
  protected EntityStorageInterface $taskStorage;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->taskAction = $this->createMock(WorkTaskActionService::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->time->method('getCurrentTime')->willReturn(1000000);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(function ($key) {
      return match ($key) {
        'claim_settings.warning_threshold' => 4,
        default => NULL,
      };
    });

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $this->taskStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')
      ->with('workflow_task')
      ->willReturn($this->taskStorage);

    $this->service = new ClaimExpirationService(
      $this->entityTypeManager,
      $this->taskAction,
      $this->time,
      $loggerFactory,
      $configFactory
    );
  }

  /**
   * Tests processExpiredClaims with no expired claims.
   *
   * @covers ::processExpiredClaims
   */
  public function testProcessExpiredClaimsNone(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $this->taskStorage->method('getQuery')->willReturn($query);

    $released = $this->service->processExpiredClaims();

    $this->assertEquals(0, $released);
  }

  /**
   * Tests processExpiredClaims releases expired tasks.
   *
   * @covers ::processExpiredClaims
   */
  public function testProcessExpiredClaimsReleasesExpired(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([1, 2]);

    $this->taskStorage->method('getQuery')->willReturn($query);

    $task1 = $this->createMock(\stdClass::class);
    $task2 = $this->createMock(\stdClass::class);

    $this->taskStorage->method('load')
      ->willReturnCallback(function ($id) use ($task1, $task2) {
        return match ($id) {
          1 => $task1,
          2 => $task2,
          default => NULL,
        };
      });

    $this->taskAction->method('releaseTask')
      ->willReturn(TRUE);

    $released = $this->service->processExpiredClaims();

    $this->assertEquals(2, $released);
  }

  /**
   * Tests sendExpiryWarnings with no warnings needed.
   *
   * @covers ::sendExpiryWarnings
   */
  public function testSendExpiryWarningsNone(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $this->taskStorage->method('getQuery')->willReturn($query);

    $warned = $this->service->sendExpiryWarnings();

    $this->assertEquals(0, $warned);
  }

}
