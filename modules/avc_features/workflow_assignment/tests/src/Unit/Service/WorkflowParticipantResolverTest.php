<?php

namespace Drupal\Tests\workflow_assignment\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\workflow_assignment\Service\WorkflowParticipantResolver;

/**
 * Unit tests for WorkflowParticipantResolver.
 *
 * @group workflow_assignment
 * @coversDefaultClass \Drupal\workflow_assignment\Service\WorkflowParticipantResolver
 */
class WorkflowParticipantResolverTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The service under test.
   *
   * @var \Drupal\workflow_assignment\Service\WorkflowParticipantResolver
   */
  protected $resolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->resolver = new WorkflowParticipantResolver($this->entityTypeManager);
  }

  /**
   * Creates a mock node.
   */
  protected function createMockNode(int $id = 1, int $owner_id = 1) {
    $node = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['id', 'getOwnerId', 'bundle', 'access'])
      ->getMock();
    $node->method('id')->willReturn($id);
    $node->method('getOwnerId')->willReturn($owner_id);
    $node->method('bundle')->willReturn('avc_document');
    return $node;
  }

  /**
   * Creates a mock workflow task.
   */
  protected function createMockTask(string $assigned_type = 'user', ?int $assigned_user = NULL, ?int $assigned_group = NULL, string $status = 'pending', int $id = 1) {
    $task = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['get', 'id', 'hasField'])
      ->getMock();

    $assignedTypeField = new \stdClass();
    $assignedTypeField->value = $assigned_type;

    $assignedUserField = new \stdClass();
    $assignedUserField->target_id = $assigned_user;

    $assignedGroupField = new \stdClass();
    $assignedGroupField->target_id = $assigned_group;

    $statusField = new \stdClass();
    $statusField->value = $status;

    $nodeIdField = new \stdClass();
    $nodeIdField->target_id = 1;

    $task->method('get')
      ->willReturnCallback(function ($field) use ($assignedTypeField, $assignedUserField, $assignedGroupField, $statusField, $nodeIdField) {
        switch ($field) {
          case 'assigned_type':
            return $assignedTypeField;
          case 'assigned_user':
            return $assignedUserField;
          case 'assigned_group':
            return $assignedGroupField;
          case 'status':
            return $statusField;
          case 'node_id':
            return $nodeIdField;
          default:
            $mock = new \stdClass();
            $mock->value = NULL;
            $mock->target_id = NULL;
            return $mock;
        }
      });

    $task->method('id')->willReturn($id);
    $task->method('hasField')->willReturn(TRUE);

    return $task;
  }

  /**
   * Sets up entity type manager to return workflow tasks.
   */
  protected function setupTaskQuery(array $task_ids, array $tasks = []) {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($task_ids);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn($tasks);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('workflow_task')
      ->willReturn($storage);
  }

  /**
   * Tests getActiveWorkflowTasks returns empty when no tasks.
   *
   * @covers ::getActiveWorkflowTasks
   */
  public function testGetActiveWorkflowTasksReturnsEmptyWhenNoTasks(): void {
    $node = $this->createMockNode();
    $this->setupTaskQuery([]);

    $result = $this->resolver->getActiveWorkflowTasks($node);

    $this->assertEmpty($result);
  }

  /**
   * Tests getActiveWorkflowTasks returns tasks.
   *
   * @covers ::getActiveWorkflowTasks
   */
  public function testGetActiveWorkflowTasksReturnsTasks(): void {
    $node = $this->createMockNode();
    $task = $this->createMockTask('user', 1);
    $this->setupTaskQuery([1], [1 => $task]);

    $result = $this->resolver->getActiveWorkflowTasks($node);

    $this->assertCount(1, $result);
  }

  /**
   * Tests getCurrentTask returns null when no tasks.
   *
   * @covers ::getCurrentTask
   */
  public function testGetCurrentTaskReturnsNullWhenNoTasks(): void {
    $node = $this->createMockNode();
    $this->setupTaskQuery([]);

    $result = $this->resolver->getCurrentTask($node);

    $this->assertNull($result);
  }

  /**
   * Tests isAssignedToTask for user assignment.
   *
   * @covers ::isAssignedToTask
   */
  public function testIsAssignedToTaskForUserAssignment(): void {
    $task = $this->createMockTask('user', 5);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(5);

    $result = $this->resolver->isAssignedToTask($task, $account);

    $this->assertTrue($result);
  }

  /**
   * Tests isAssignedToTask returns false for wrong user.
   *
   * @covers ::isAssignedToTask
   */
  public function testIsAssignedToTaskReturnsFalseForWrongUser(): void {
    $task = $this->createMockTask('user', 5);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(99);

    $result = $this->resolver->isAssignedToTask($task, $account);

    $this->assertFalse($result);
  }

  /**
   * Tests isAssignedToTask returns false for destination type.
   *
   * @covers ::isAssignedToTask
   */
  public function testIsAssignedToTaskReturnsFalseForDestination(): void {
    $task = $this->createMockTask('destination');
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(1);

    $result = $this->resolver->isAssignedToTask($task, $account);

    $this->assertFalse($result);
  }

  /**
   * Tests getAccessCacheTags.
   *
   * @covers ::getAccessCacheTags
   */
  public function testGetAccessCacheTags(): void {
    $node = $this->createMockNode(42);
    $this->setupTaskQuery([]);

    $tags = $this->resolver->getAccessCacheTags($node);

    $this->assertContains('workflow_task_list:42', $tags);
  }

}
