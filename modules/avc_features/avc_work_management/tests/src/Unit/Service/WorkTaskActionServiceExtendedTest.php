<?php

namespace Drupal\Tests\avc_work_management\Unit\Service;

use Drupal\avc_work_management\Service\WorkTaskActionService;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Extended unit tests for WorkTaskActionService Phase 3 methods.
 *
 * @group avc_work_management
 * @coversDefaultClass \Drupal\avc_work_management\Service\WorkTaskActionService
 */
class WorkTaskActionServiceExtendedTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountInterface $currentUser;
  protected TimeInterface $time;
  protected LoggerChannelInterface $logger;
  protected ConfigFactoryInterface $configFactory;
  protected WorkTaskActionService $service;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->currentUser = $this->createMock(AccountInterface::class);
    $this->currentUser->method('id')->willReturn(1);
    $this->currentUser->method('getDisplayName')->willReturn('Test User');

    $this->time = $this->createMock(TimeInterface::class);
    $this->time->method('getCurrentTime')->willReturn(1000000);

    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($this->logger);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(function ($key) {
      return match ($key) {
        'claim_settings.default_claim_duration' => 24,
        'claim_settings.max_extensions' => 2,
        'claim_settings.extension_duration' => 24,
        'claim_settings.warning_threshold' => 4,
        'claim_settings.allow_self_extension' => TRUE,
        default => NULL,
      };
    });

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')->willReturn($config);

    $this->service = new WorkTaskActionService(
      $this->entityTypeManager,
      $this->currentUser,
      $this->time,
      $loggerFactory,
      $this->configFactory
    );
  }

  /**
   * Helper to create a mock task with time-limited claim fields.
   */
  protected function createMockTask(array $fields = []): object {
    $defaults = [
      'assigned_type' => 'user',
      'status' => 'in_progress',
      'assigned_user' => 1,
      'assigned_group' => NULL,
      'claimed_at' => 1000000,
      'claim_expires' => 1086400,
      'original_group' => 5,
      'extension_count' => 0,
      'expiry_warning_sent' => FALSE,
      'node_id' => 100,
      'weight' => 0,
    ];

    $values = array_merge($defaults, $fields);

    $task = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['get', 'id', 'hasField', 'set', 'setNewRevision', 'setRevisionLogMessage', 'save'])
      ->getMock();

    $task->method('id')->willReturn(1);
    $task->method('hasField')->willReturnCallback(function ($field) {
      return in_array($field, [
        'revision_log', 'claimed_at', 'claim_expires', 'original_group',
        'extension_count', 'expiry_warning_sent', 'node_id', 'weight',
        'assigned_type', 'assigned_user', 'assigned_group', 'status',
      ]);
    });

    $task->method('get')->willReturnCallback(function ($field) use ($values) {
      $obj = new \stdClass();
      if (in_array($field, ['assigned_user', 'assigned_group', 'original_group', 'node_id'])) {
        $obj->target_id = $values[$field] ?? NULL;
        $obj->value = $values[$field] ?? NULL;
      }
      else {
        $obj->value = $values[$field] ?? NULL;
        $obj->target_id = $values[$field] ?? NULL;
      }
      return $obj;
    });

    $task->method('set')->willReturnSelf();
    $task->method('setNewRevision')->willReturnSelf();
    $task->method('setRevisionLogMessage')->willReturnSelf();
    $task->method('save')->willReturn(1);

    return $task;
  }

  /**
   * Tests getClaimSettings returns correct defaults.
   *
   * @covers ::getClaimSettings
   */
  public function testGetClaimSettingsDefaults(): void {
    $settings = $this->service->getClaimSettings();

    $this->assertEquals(24, $settings['default_claim_duration']);
    $this->assertEquals(2, $settings['max_extensions']);
    $this->assertEquals(24, $settings['extension_duration']);
    $this->assertEquals(4, $settings['warning_threshold']);
    $this->assertTrue($settings['allow_self_extension']);
  }

  /**
   * Tests isClaimExpired returns false for unexpired claim.
   *
   * @covers ::isClaimExpired
   */
  public function testIsClaimExpiredFalse(): void {
    $task = $this->createMockTask([
      'claim_expires' => 2000000, // Far in future.
    ]);

    $this->assertFalse($this->service->isClaimExpired($task));
  }

  /**
   * Tests isClaimExpired returns true for expired claim.
   *
   * @covers ::isClaimExpired
   */
  public function testIsClaimExpiredTrue(): void {
    $task = $this->createMockTask([
      'claim_expires' => 500000, // In the past (current time is 1000000).
    ]);

    $this->assertTrue($this->service->isClaimExpired($task));
  }

  /**
   * Tests isClaimExpired returns false when no expiry set.
   *
   * @covers ::isClaimExpired
   */
  public function testIsClaimExpiredNoExpiry(): void {
    $task = $this->createMockTask([
      'claim_expires' => 0,
    ]);

    $this->assertFalse($this->service->isClaimExpired($task));
  }

  /**
   * Tests getClaimTimeRemaining calculates correctly.
   *
   * @covers ::getClaimTimeRemaining
   */
  public function testGetClaimTimeRemaining(): void {
    $task = $this->createMockTask([
      'claim_expires' => 1100000, // 100000 seconds from now.
    ]);

    $remaining = $this->service->getClaimTimeRemaining($task);
    $this->assertEquals(100000, $remaining);
  }

  /**
   * Tests getClaimTimeRemaining returns 0 for expired claims.
   *
   * @covers ::getClaimTimeRemaining
   */
  public function testGetClaimTimeRemainingExpired(): void {
    $task = $this->createMockTask([
      'claim_expires' => 500000,
    ]);

    $remaining = $this->service->getClaimTimeRemaining($task);
    $this->assertEquals(0, $remaining);
  }

  /**
   * Tests extendClaim succeeds.
   *
   * @covers ::extendClaim
   */
  public function testExtendClaimSucceeds(): void {
    $task = $this->createMockTask([
      'assigned_type' => 'user',
      'assigned_user' => 1,
      'claim_expires' => 1086400,
      'extension_count' => 0,
    ]);

    $result = $this->service->extendClaim($task, $this->currentUser);

    $this->assertTrue($result);
  }

  /**
   * Tests extendClaim fails when max extensions reached.
   *
   * @covers ::extendClaim
   */
  public function testExtendClaimFailsMaxExtensions(): void {
    $task = $this->createMockTask([
      'assigned_type' => 'user',
      'assigned_user' => 1,
      'extension_count' => 2, // Max is 2.
    ]);

    $result = $this->service->extendClaim($task, $this->currentUser);

    $this->assertFalse($result);
  }

  /**
   * Tests extendClaim fails for wrong user.
   *
   * @covers ::extendClaim
   */
  public function testExtendClaimFailsWrongUser(): void {
    $task = $this->createMockTask([
      'assigned_type' => 'user',
      'assigned_user' => 999, // Not current user.
    ]);

    $result = $this->service->extendClaim($task, $this->currentUser);

    $this->assertFalse($result);
  }

  /**
   * Tests extendClaim fails for group-assigned task.
   *
   * @covers ::extendClaim
   */
  public function testExtendClaimFailsGroupAssigned(): void {
    $task = $this->createMockTask([
      'assigned_type' => 'group',
    ]);

    $result = $this->service->extendClaim($task, $this->currentUser);

    $this->assertFalse($result);
  }

  /**
   * Tests releaseTask uses original_group field.
   *
   * @covers ::releaseTask
   */
  public function testReleaseTaskUsesOriginalGroup(): void {
    $task = $this->createMockTask([
      'assigned_type' => 'user',
      'assigned_user' => 1,
      'original_group' => 5,
    ]);

    $result = $this->service->releaseTask($task);

    $this->assertTrue($result);
  }

  /**
   * Tests releaseTask fails without group_id or original_group.
   *
   * @covers ::releaseTask
   */
  public function testReleaseTaskFailsNoGroup(): void {
    $task = $this->createMockTask([
      'original_group' => NULL,
    ]);

    $result = $this->service->releaseTask($task);

    $this->assertFalse($result);
  }

  /**
   * Tests releaseTask with explicit group_id parameter.
   *
   * @covers ::releaseTask
   */
  public function testReleaseTaskWithExplicitGroupId(): void {
    $task = $this->createMockTask([
      'original_group' => 5,
    ]);

    $result = $this->service->releaseTask($task, 10, 'voluntary');

    $this->assertTrue($result);
  }

  /**
   * Tests forceRelease requires admin permission.
   *
   * @covers ::forceRelease
   */
  public function testForceReleaseRequiresAdmin(): void {
    $task = $this->createMockTask(['original_group' => 5]);

    $non_admin = $this->createMock(AccountInterface::class);
    $non_admin->method('hasPermission')
      ->with('administer workflow tasks')
      ->willReturn(FALSE);

    $result = $this->service->forceRelease($task, $non_admin);

    $this->assertFalse($result);
  }

  /**
   * Tests forceRelease succeeds for admin.
   *
   * @covers ::forceRelease
   */
  public function testForceReleaseSucceedsForAdmin(): void {
    $task = $this->createMockTask(['original_group' => 5]);

    $admin = $this->createMock(AccountInterface::class);
    $admin->method('hasPermission')
      ->with('administer workflow tasks')
      ->willReturn(TRUE);

    $result = $this->service->forceRelease($task, $admin);

    $this->assertTrue($result);
  }

  /**
   * Tests completeTask clears claim data and activates next task.
   *
   * @covers ::completeTask
   */
  public function testCompleteTaskSucceeds(): void {
    $task = $this->createMockTask([
      'assigned_type' => 'user',
      'assigned_user' => 1,
      'status' => 'in_progress',
    ]);

    // Mock query for next task (none found).
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')
      ->with('workflow_task')
      ->willReturn($storage);

    $result = $this->service->completeTask($task, $this->currentUser);

    $this->assertTrue($result);
  }

}
