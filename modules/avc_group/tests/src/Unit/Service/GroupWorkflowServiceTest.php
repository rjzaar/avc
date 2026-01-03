<?php

namespace Drupal\Tests\avc_group\Unit\Service;

use Drupal\avc_group\Service\GroupWorkflowService;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembership;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for GroupWorkflowService.
 *
 * @group avc_group
 * @coversDefaultClass \Drupal\avc_group\Service\GroupWorkflowService
 */
class GroupWorkflowServiceTest extends UnitTestCase {

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
   * The service under test.
   *
   * @var \Drupal\avc_group\Service\GroupWorkflowService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->currentUser = $this->createMock(AccountInterface::class);

    $this->service = new GroupWorkflowService(
      $this->entityTypeManager,
      $this->currentUser
    );
  }

  /**
   * Tests getGroupAssignments returns empty when no workflow_assignment entity.
   *
   * @covers ::getGroupAssignments
   */
  public function testGetGroupAssignmentsNoEntity() {
    $group = $this->createMock(GroupInterface::class);
    $group->method('id')->willReturn(1);

    $this->entityTypeManager
      ->method('hasDefinition')
      ->with('workflow_assignment')
      ->willReturn(FALSE);

    $result = $this->service->getGroupAssignments($group);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests getGroupMembers returns member information.
   *
   * @covers ::getGroupMembers
   */
  public function testGetGroupMembers() {
    // Create mock user.
    $user = $this->createMock('\Drupal\user\UserInterface');
    $user->method('id')->willReturn(1);
    $user->method('getDisplayName')->willReturn('Test User');

    // Create mock role.
    $role = $this->createMock(GroupRoleInterface::class);
    $role->method('id')->willReturn('member');
    $role->method('label')->willReturn('Member');
    $role->method('hasPermission')->willReturn(FALSE);

    // Create mock membership.
    $membership = $this->createMock(GroupMembership::class);
    $membership->method('getUser')->willReturn($user);
    $membership->method('getRoles')->willReturn([$role]);

    // Create mock group.
    $group = $this->createMock(GroupInterface::class);
    $group->method('id')->willReturn(1);
    $group->method('getMembers')->willReturn([$membership]);
    $group->method('getMember')->willReturn($membership);

    $result = $this->service->getGroupMembers($group);

    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertEquals('Test User', $result[0]['name']);
    $this->assertEquals(1, $result[0]['uid']);
    $this->assertContains('Member', $result[0]['roles']);
  }

  /**
   * Tests isGroupManager returns true for admin role.
   *
   * @covers ::isGroupManager
   */
  public function testIsGroupManagerAdmin() {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(1);

    // Create mock admin role.
    $role = $this->createMock(GroupRoleInterface::class);
    $role->method('id')->willReturn('group_admin');
    $role->method('hasPermission')
      ->with('administer group')
      ->willReturn(TRUE);

    // Create mock membership.
    $membership = $this->createMock(GroupMembership::class);
    $membership->method('getRoles')->willReturn([$role]);

    // Create mock group.
    $group = $this->createMock(GroupInterface::class);
    $group->method('getMember')
      ->with($account)
      ->willReturn($membership);

    $result = $this->service->isGroupManager($group, $account);

    $this->assertTrue($result);
  }

  /**
   * Tests isGroupManager returns false for non-members.
   *
   * @covers ::isGroupManager
   */
  public function testIsGroupManagerNonMember() {
    $account = $this->createMock(AccountInterface::class);

    $group = $this->createMock(GroupInterface::class);
    $group->method('getMember')
      ->with($account)
      ->willReturn(NULL);

    $result = $this->service->isGroupManager($group, $account);

    $this->assertFalse($result);
  }

  /**
   * Tests isGroupManager returns false for regular members.
   *
   * @covers ::isGroupManager
   */
  public function testIsGroupManagerRegularMember() {
    $account = $this->createMock(AccountInterface::class);

    // Create mock regular role.
    $role = $this->createMock(GroupRoleInterface::class);
    $role->method('id')->willReturn('member');
    $role->method('hasPermission')
      ->with('administer group')
      ->willReturn(FALSE);

    // Create mock membership.
    $membership = $this->createMock(GroupMembership::class);
    $membership->method('getRoles')->willReturn([$role]);

    // Create mock group.
    $group = $this->createMock(GroupInterface::class);
    $group->method('getMember')
      ->with($account)
      ->willReturn($membership);

    $result = $this->service->isGroupManager($group, $account);

    $this->assertFalse($result);
  }

}
