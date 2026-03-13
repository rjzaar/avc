<?php

namespace Drupal\Tests\avc_member\Unit\Service;

use Drupal\avc_member\Service\MemberWorklistService;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;

/**
 * Unit tests for MemberWorklistService.
 *
 * @group avc_member
 * @coversDefaultClass \Drupal\avc_member\Service\MemberWorklistService
 */
class MemberWorklistServiceTest extends UnitTestCase {

  protected $entityTypeManager;
  protected $service;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->service = new MemberWorklistService($this->entityTypeManager);
  }

  /**
   * Helper to create a mock workflow task entity.
   */
  protected function createMockTaskEntity(int $id, string $status, string $label = 'Test Task') {
    $task = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['id', 'label', 'getDescription', 'getStatus', 'getNode'])
      ->getMock();
    $task->method('id')->willReturn($id);
    $task->method('label')->willReturn($label);
    $task->method('getDescription')->willReturn('Description for ' . $label);
    $task->method('getStatus')->willReturn($status);
    $task->method('getNode')->willReturn(NULL);
    return $task;
  }

  /**
   * Tests getUserWorklist returns empty array when no assignments.
   *
   * @covers ::getUserWorklist
   */
  public function testGetUserWorklistEmpty() {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn(1);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager
      ->method('hasDefinition')
      ->with('workflow_task')
      ->willReturn(TRUE);
    $this->entityTypeManager
      ->method('getStorage')
      ->with('workflow_task')
      ->willReturn($storage);

    $result = $this->service->getUserWorklist($user);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests getUserWorklist returns tasks with mapped status.
   *
   * @covers ::getUserWorklist
   */
  public function testGetUserWorklistWithAssignments() {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn(1);

    $task = $this->createMockTaskEntity(1, 'in_progress');

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')
      ->with([1])
      ->willReturn([1 => $task]);

    $this->entityTypeManager
      ->method('hasDefinition')
      ->with('workflow_task')
      ->willReturn(TRUE);
    $this->entityTypeManager
      ->method('getStorage')
      ->with('workflow_task')
      ->willReturn($storage);

    $result = $this->service->getUserWorklist($user);

    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertArrayHasKey('status', $result[0]);
    $this->assertEquals('current', $result[0]['status']);
    $this->assertEquals('in_progress', $result[0]['task_status']);
  }

  /**
   * Tests getUserNotificationSettings returns defaults when no fields.
   *
   * @covers ::getUserNotificationSettings
   */
  public function testGetUserNotificationSettingsDefaults() {
    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')
      ->willReturn(FALSE);

    $result = $this->service->getUserNotificationSettings($user);

    $this->assertIsArray($result);
    $this->assertEquals('x', $result['default']);
    $this->assertNull($result['last_run']);
    $this->assertEmpty($result['groups']);
  }

  /**
   * Tests getUserNotificationSettings reads from user fields.
   *
   * @covers ::getUserNotificationSettings
   */
  public function testGetUserNotificationSettingsFromFields() {
    $defaultField = new \stdClass();
    $defaultField->value = 'd';

    $lastRunField = new \stdClass();
    $lastRunField->value = '2024-01-01 12:00:00';

    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')
      ->willReturnCallback(function ($field_name) {
        return in_array($field_name, ['field_notification_default', 'field_notification_last_run']);
      });
    $user->method('get')
      ->willReturnCallback(function ($field_name) use ($defaultField, $lastRunField) {
        return match ($field_name) {
          'field_notification_default' => $defaultField,
          'field_notification_last_run' => $lastRunField,
          default => new \stdClass(),
        };
      });

    $result = $this->service->getUserNotificationSettings($user);

    $this->assertEquals('d', $result['default']);
    $this->assertEquals('2024-01-01 12:00:00', $result['last_run']);
  }

  /**
   * Tests getGroupWorklist returns empty array when no assignments.
   *
   * @covers ::getGroupWorklist
   */
  public function testGetGroupWorklistEmpty() {
    $group = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['id'])
      ->getMock();
    $group->method('id')->willReturn(1);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager
      ->method('hasDefinition')
      ->with('workflow_task')
      ->willReturn(TRUE);
    $this->entityTypeManager
      ->method('getStorage')
      ->with('workflow_task')
      ->willReturn($storage);

    $result = $this->service->getGroupWorklist($group);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Data provider for worklist status mapping tests.
   */
  public function worklistStatusProvider() {
    return [
      'completed returns completed' => ['completed', 'completed'],
      'in_progress returns current' => ['in_progress', 'current'],
      'pending returns upcoming' => ['pending', 'upcoming'],
    ];
  }

  /**
   * Tests status mapping via getUserWorklist.
   *
   * @dataProvider worklistStatusProvider
   * @covers ::getUserWorklist
   */
  public function testDetermineWorklistStatus($taskStatus, $expectedStatus) {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn(1);

    $task = $this->createMockTaskEntity(1, $taskStatus);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([1 => $task]);

    $this->entityTypeManager
      ->method('hasDefinition')
      ->willReturn(TRUE);
    $this->entityTypeManager
      ->method('getStorage')
      ->willReturn($storage);

    $result = $this->service->getUserWorklist($user);

    $this->assertEquals($expectedStatus, $result[0]['status']);
  }

}
