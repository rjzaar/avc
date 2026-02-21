<?php

namespace Drupal\Tests\avc_asset\Unit\Service;

use Drupal\avc_asset\Service\WorkflowChecker;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for WorkflowChecker.
 *
 * @group avc_asset
 * @coversDefaultClass \Drupal\avc_asset\Service\WorkflowChecker
 */
class WorkflowCheckerTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_asset\Service\WorkflowChecker
   */
  protected $checker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->checker = new WorkflowChecker($this->entityTypeManager);
  }

  /**
   * Tests check returns valid status for a node without workflow assignments.
   *
   * When a node has no workflow_task entities, the check method should return
   * a warning status with an appropriate message.
   *
   * @covers ::check
   */
  public function testCheckReturnsValidForNodeWithoutWorkflows(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);

    // The entity type manager reports that workflow_task definition exists
    // but the query returns no results.
    $this->entityTypeManager->method('hasDefinition')
      ->with('workflow_task')
      ->willReturn(TRUE);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with([])->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->with('workflow_task')
      ->willReturn($storage);

    $result = $this->checker->check($node);

    $this->assertEquals(WorkflowChecker::STATUS_WARNING, $result['status']);
    $this->assertNotEmpty($result['messages']);
    $this->assertEquals('No workflow assignments found.', $result['messages'][0]['text']);
  }

  /**
   * Tests check returns a properly structured array.
   *
   * The returned array must contain the keys: status, messages, and entries.
   *
   * @covers ::check
   */
  public function testCheckReturnsArrayStructure(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(42);

    // Simulate no workflow_task entity type definition so
    // getNodeWorkflowAssignments returns an empty array quickly.
    $this->entityTypeManager->method('hasDefinition')
      ->with('workflow_task')
      ->willReturn(FALSE);

    $result = $this->checker->check($node);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('status', $result);
    $this->assertArrayHasKey('messages', $result);
    $this->assertArrayHasKey('entries', $result);
    // With no assignments, entries should be empty.
    $this->assertEmpty($result['entries']);
  }

  /**
   * Tests resolveAssigneeByName returns NULL for an unknown name.
   *
   * When the entity storage finds no matching user, the method should
   * return NULL.
   *
   * @covers ::resolveAssigneeByName
   */
  public function testResolveAssigneeByNameReturnsNullForUnknown(): void {
    // Mock user storage that returns empty results for loadByProperties
    // and an empty query result.
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadByProperties')
      ->with(['name' => 'Nonexistent Person'])
      ->willReturn([]);
    $userStorage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')
      ->with('user')
      ->willReturn($userStorage);

    $result = $this->checker->resolveAssigneeByName('Nonexistent Person', 'user');

    $this->assertNull($result);
  }

}
