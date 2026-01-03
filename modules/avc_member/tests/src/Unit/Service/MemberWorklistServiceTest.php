<?php

namespace Drupal\Tests\avc_member\Unit\Service;

use Drupal\avc_member\Service\MemberWorklistService;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;

/**
 * Unit tests for MemberWorklistService.
 *
 * @group avc_member
 * @coversDefaultClass \Drupal\avc_member\Service\MemberWorklistService
 */
class MemberWorklistServiceTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_member\Service\MemberWorklistService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->service = new MemberWorklistService($this->entityTypeManager);
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
      ->method('getStorage')
      ->with('workflow_assignment')
      ->willReturn($storage);

    $result = $this->service->getUserWorklist($user);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests getUserWorklist returns assignments with status.
   *
   * @covers ::getUserWorklist
   */
  public function testGetUserWorklistWithAssignments() {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn(1);

    // Mock completion field.
    $completionField = $this->createMock(FieldItemListInterface::class);
    $completionField->value = 'accepted';

    // Mock assignment entity.
    $assignment = $this->createMock('\Drupal\Core\Entity\EntityInterface');
    $assignment->method('get')
      ->with('completion')
      ->willReturn($completionField);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')
      ->with([1])
      ->willReturn([1 => $assignment]);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('workflow_assignment')
      ->willReturn($storage);

    $result = $this->service->getUserWorklist($user);

    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertArrayHasKey('assignment', $result[0]);
    $this->assertArrayHasKey('status', $result[0]);
    $this->assertEquals('current', $result[0]['status']);
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
    $defaultField = $this->createMock(FieldItemListInterface::class);
    $defaultField->value = 'd';

    $lastRunField = $this->createMock(FieldItemListInterface::class);
    $lastRunField->value = '2024-01-01 12:00:00';

    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')
      ->willReturnMap([
        ['field_notification_default', TRUE],
        ['field_notification_last_run', TRUE],
      ]);
    $user->method('get')
      ->willReturnMap([
        ['field_notification_default', $defaultField],
        ['field_notification_last_run', $lastRunField],
      ]);

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
    $group = $this->createMock('\Drupal\group\Entity\GroupInterface');
    $group->method('id')->willReturn(1);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('workflow_assignment')
      ->willReturn($storage);

    $result = $this->service->getGroupWorklist($group);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Data provider for worklist status tests.
   */
  public function worklistStatusProvider() {
    return [
      'completed returns completed' => ['completed', 'completed'],
      'accepted returns current' => ['accepted', 'current'],
      'proposed returns upcoming' => ['proposed', 'upcoming'],
      'null returns upcoming' => [NULL, 'upcoming'],
    ];
  }

  /**
   * Tests determineWorklistStatus via getUserWorklist.
   *
   * @dataProvider worklistStatusProvider
   * @covers ::getUserWorklist
   */
  public function testDetermineWorklistStatus($completion, $expectedStatus) {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn(1);

    $completionField = $this->createMock(FieldItemListInterface::class);
    $completionField->value = $completion;

    $assignment = $this->createMock('\Drupal\Core\Entity\EntityInterface');
    $assignment->method('get')
      ->with('completion')
      ->willReturn($completionField);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([1 => $assignment]);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturn($storage);

    $result = $this->service->getUserWorklist($user);

    $this->assertEquals($expectedStatus, $result[0]['status']);
  }

}
