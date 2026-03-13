<?php

namespace Drupal\Tests\workflow_assignment\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\workflow_assignment\Service\ReEditWorkflowService;

/**
 * Unit tests for ReEditWorkflowService.
 *
 * @group workflow_assignment
 * @coversDefaultClass \Drupal\workflow_assignment\Service\ReEditWorkflowService
 */
class ReEditWorkflowServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountInterface $currentUser;
  protected ReEditWorkflowService $service;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->currentUser = $this->createMock(AccountInterface::class);
    $this->currentUser->method('id')->willReturn(1);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $this->service = new ReEditWorkflowService(
      $this->entityTypeManager,
      $this->currentUser,
      $loggerFactory
    );
  }

  /**
   * Tests canReEdit returns true when no active tasks.
   *
   * @covers ::canReEdit
   */
  public function testCanReEditWithNoActiveTasks(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(0);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')
      ->with('workflow_task')
      ->willReturn($storage);

    $this->assertTrue($this->service->canReEdit($node));
  }

  /**
   * Tests canReEdit returns false when active tasks exist.
   *
   * @covers ::canReEdit
   */
  public function testCanReEditWithActiveTasks(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(2);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')
      ->with('workflow_task')
      ->willReturn($storage);

    $this->assertFalse($this->service->canReEdit($node));
  }

  /**
   * Tests initiateReEdit fails when active workflow exists.
   *
   * @covers ::initiateReEdit
   */
  public function testInitiateReEditFailsWithActiveWorkflow(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(1);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')
      ->with('workflow_task')
      ->willReturn($storage);

    $result = $this->service->initiateReEdit($node, 'test reason');

    $this->assertFalse($result);
  }

}
