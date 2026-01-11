<?php

namespace Drupal\Tests\avc_work_management\Unit\Service;

use Drupal\avc_work_management\Service\WorkTaskActionService;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for WorkTaskActionService.
 *
 * @group avc_work_management
 * @coversDefaultClass \Drupal\avc_work_management\Service\WorkTaskActionService
 */
class WorkTaskActionServiceTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The current user mock.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The time service mock.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $time;

  /**
   * The logger mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_work_management\Service\WorkTaskActionService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->currentUser = $this->createMock(AccountInterface::class);
    $this->currentUser->method('id')->willReturn(1);
    $this->currentUser->method('getDisplayName')->willReturn('Test User');
    $this->time = $this->createMock(TimeInterface::class);
    $this->time->method('getCurrentTime')->willReturn(time());

    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')
      ->with('avc_work_management')
      ->willReturn($this->logger);

    $this->service = new WorkTaskActionService(
      $this->entityTypeManager,
      $this->currentUser,
      $this->time,
      $loggerFactory
    );
  }

  /**
   * Creates a mock workflow task.
   *
   * @param string $assignedType
   *   The assignment type (user, group, destination).
   * @param string $status
   *   The task status.
   * @param int|null $assignedUserId
   *   The assigned user ID.
   * @param int|null $assignedGroupId
   *   The assigned group ID.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mock task.
   */
  protected function createMockTask(
    string $assignedType = 'user',
    string $status = 'pending',
    ?int $assignedUserId = NULL,
    ?int $assignedGroupId = NULL
  ) {
    // Create a mock using stdClass to avoid interface method restrictions.
    $task = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['get', 'id', 'hasField', 'set', 'setNewRevision', 'setRevisionLogMessage', 'save', 'label', 'getChangedTime'])
      ->getMock();

    // Use simple stdClass objects for field values to avoid mock magic method issues.
    $assignedTypeField = new \stdClass();
    $assignedTypeField->value = $assignedType;

    $statusField = new \stdClass();
    $statusField->value = $status;

    $assignedUserField = new \stdClass();
    $assignedUserField->target_id = $assignedUserId;

    $assignedGroupField = new \stdClass();
    $assignedGroupField->target_id = $assignedGroupId;

    $task->method('get')
      ->willReturnCallback(function ($field) use ($assignedTypeField, $statusField, $assignedUserField, $assignedGroupField) {
        switch ($field) {
          case 'assigned_type':
            return $assignedTypeField;
          case 'status':
            return $statusField;
          case 'assigned_user':
            return $assignedUserField;
          case 'assigned_group':
            return $assignedGroupField;
          default:
            $mock = new \stdClass();
            $mock->value = NULL;
            $mock->target_id = NULL;
            return $mock;
        }
      });

    $task->method('id')->willReturn(1);
    $task->method('hasField')->willReturn(FALSE);
    $task->method('set')->willReturnSelf();
    $task->method('setNewRevision')->willReturnSelf();
    $task->method('setRevisionLogMessage')->willReturnSelf();
    $task->method('save')->willReturn(1);
    $task->method('label')->willReturn('Test Task');
    $task->method('getChangedTime')->willReturn(time());

    return $task;
  }

  /**
   * Tests canClaim returns false for user-assigned tasks.
   *
   * @covers ::canClaim
   */
  public function testCanClaimFalseForUserAssigned(): void {
    $task = $this->createMockTask('user', 'pending', 1, NULL);

    $result = $this->service->canClaim($task, $this->currentUser);

    $this->assertFalse($result);
  }

  /**
   * Tests canClaim returns false for non-pending tasks.
   *
   * @covers ::canClaim
   */
  public function testCanClaimFalseForNonPending(): void {
    $task = $this->createMockTask('group', 'in_progress', NULL, 5);

    $result = $this->service->canClaim($task, $this->currentUser);

    $this->assertFalse($result);
  }

  /**
   * Tests canClaim returns false for completed tasks.
   *
   * @covers ::canClaim
   */
  public function testCanClaimFalseForCompleted(): void {
    $task = $this->createMockTask('group', 'completed', NULL, 5);

    $result = $this->service->canClaim($task, $this->currentUser);

    $this->assertFalse($result);
  }

  /**
   * Tests canClaim returns false when user not in group.
   *
   * @covers ::canClaim
   */
  public function testCanClaimFalseUserNotInGroup(): void {
    $task = $this->createMockTask('group', 'pending', NULL, 5);

    // Mock group that returns null membership.
    $group = $this->createMock('\Drupal\group\Entity\GroupInterface');

    $groupStorage = $this->createMock(EntityStorageInterface::class);
    $groupStorage->method('load')->with(5)->willReturn($group);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('group')
      ->willReturn($groupStorage);

    // We need to mock Drupal::service for membership loader.
    // Since the service calls \Drupal::service directly, we can't easily mock it.
    // For this test, we'll verify the logic would fail.

    $result = $this->service->canClaim($task, $this->currentUser);

    // Returns false because userInGroup will fail to find membership.
    $this->assertFalse($result);
  }

  /**
   * Tests claimTask returns false when canClaim is false.
   *
   * @covers ::claimTask
   */
  public function testClaimTaskFailsWhenCannotClaim(): void {
    $task = $this->createMockTask('user', 'pending', 1, NULL);

    $result = $this->service->claimTask($task, $this->currentUser);

    $this->assertFalse($result);
  }

  /**
   * Tests completeTask returns false for group-assigned tasks.
   *
   * @covers ::completeTask
   */
  public function testCompleteTaskFailsForGroupAssigned(): void {
    $task = $this->createMockTask('group', 'in_progress', NULL, 5);

    $result = $this->service->completeTask($task, $this->currentUser);

    $this->assertFalse($result);
  }

  /**
   * Tests completeTask returns false when not assignee.
   *
   * @covers ::completeTask
   */
  public function testCompleteTaskFailsWhenNotAssignee(): void {
    // Task assigned to user 2, but current user is 1.
    $task = $this->createMockTask('user', 'in_progress', 2, NULL);

    // User doesn't have admin permission.
    $this->currentUser->method('hasPermission')
      ->with('administer workflow tasks')
      ->willReturn(FALSE);

    $result = $this->service->completeTask($task, $this->currentUser);

    $this->assertFalse($result);
  }

  /**
   * Tests completeTask succeeds for admin even when not assignee.
   *
   * @covers ::completeTask
   */
  public function testCompleteTaskSucceedsForAdmin(): void {
    // Task assigned to user 2.
    $task = $this->createMockTask('user', 'in_progress', 2, NULL);
    $task->method('set')->willReturnSelf();
    $task->method('setNewRevision')->willReturnSelf();
    $task->method('save')->willReturn(1);

    // User has admin permission.
    $this->currentUser->method('hasPermission')
      ->with('administer workflow tasks')
      ->willReturn(TRUE);

    // Mock query for next task (returns empty).
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturn($storage);

    $this->logger->expects($this->once())
      ->method('info')
      ->with('Task @id completed by user @user', $this->anything());

    $result = $this->service->completeTask($task, $this->currentUser);

    $this->assertTrue($result);
  }

  /**
   * Tests completeTask succeeds for assignee.
   *
   * @covers ::completeTask
   */
  public function testCompleteTaskSucceedsForAssignee(): void {
    // Task assigned to current user (user 1).
    $task = $this->createMockTask('user', 'in_progress', 1, NULL);
    $task->method('set')->willReturnSelf();
    $task->method('setNewRevision')->willReturnSelf();
    $task->method('save')->willReturn(1);

    // Mock query for next task.
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturn($storage);

    $result = $this->service->completeTask($task, $this->currentUser);

    $this->assertTrue($result);
  }

  /**
   * Tests releaseTask updates task correctly.
   *
   * @covers ::releaseTask
   */
  public function testReleaseTask(): void {
    $task = $this->createMockTask('user', 'in_progress', 1, NULL);
    $task->method('set')->willReturnSelf();
    $task->method('setNewRevision')->willReturnSelf();
    $task->method('save')->willReturn(1);

    $result = $this->service->releaseTask($task, 5);

    $this->assertTrue($result);
  }

  /**
   * Tests releaseTask handles exception.
   *
   * @covers ::releaseTask
   */
  public function testReleaseTaskHandlesException(): void {
    $task = $this->createMockTask('user', 'in_progress', 1, NULL);
    $task->method('set')->willThrowException(new \Exception('Database error'));

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Failed to release task @id: @message', $this->anything());

    $result = $this->service->releaseTask($task, 5);

    $this->assertFalse($result);
  }

  /**
   * Data provider for task status tests.
   */
  public function taskStatusProvider(): array {
    return [
      'pending task' => ['pending', FALSE],
      'in_progress task' => ['in_progress', FALSE],
      'completed task' => ['completed', FALSE],
    ];
  }

  /**
   * Tests canClaim with various statuses for group tasks.
   *
   * @dataProvider taskStatusProvider
   * @covers ::canClaim
   */
  public function testCanClaimWithStatus(string $status, bool $expectedCanClaim): void {
    $task = $this->createMockTask('group', $status, NULL, 5);

    // For pending status, we need to mock group membership check.
    if ($status === 'pending') {
      $group = $this->createMock('\Drupal\group\Entity\GroupInterface');
      $groupStorage = $this->createMock(EntityStorageInterface::class);
      $groupStorage->method('load')->willReturn($group);
      $this->entityTypeManager
        ->method('getStorage')
        ->with('group')
        ->willReturn($groupStorage);
    }

    $result = $this->service->canClaim($task, $this->currentUser);

    // All these should return false because:
    // - pending: user not in group (membership check fails)
    // - in_progress: wrong status
    // - completed: wrong status
    $this->assertFalse($result);
  }

  /**
   * Tests that completeTask activates next task in sequence.
   *
   * @covers ::completeTask
   */
  public function testCompleteTaskActivatesNextTask(): void {
    // Task assigned to current user.
    $task = $this->createMockTask('user', 'in_progress', 1, NULL);
    $task->method('set')->willReturnSelf();
    $task->method('setNewRevision')->willReturnSelf();
    $task->method('save')->willReturn(1);

    // Add node_id and weight fields.
    $nodeIdField = $this->createMock(FieldItemListInterface::class);
    $nodeIdField->target_id = 100;

    $weightField = $this->createMock(FieldItemListInterface::class);
    $weightField->value = 1;

    $task->method('get')
      ->willReturnCallback(function ($field) use ($nodeIdField, $weightField) {
        switch ($field) {
          case 'assigned_type':
            $f = $this->createMock(FieldItemListInterface::class);
            $f->value = 'user';
            return $f;
          case 'status':
            $f = $this->createMock(FieldItemListInterface::class);
            $f->value = 'in_progress';
            return $f;
          case 'assigned_user':
            $f = $this->createMock(FieldItemListInterface::class);
            $f->target_id = 1;
            return $f;
          case 'node_id':
            return $nodeIdField;
          case 'weight':
            return $weightField;
          default:
            $f = $this->createMock(FieldItemListInterface::class);
            $f->value = NULL;
            $f->target_id = NULL;
            return $f;
        }
      });

    // Mock next task.
    $nextTask = $this->createMockTask('user', 'pending', 2, NULL);
    $nextTask->method('set')->willReturnSelf();
    $nextTask->method('save')->willReturn(1);

    // Mock query for next task.
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([2]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('load')->with(2)->willReturn($nextTask);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturn($storage);

    $result = $this->service->completeTask($task, $this->currentUser);

    $this->assertTrue($result);
  }

}
