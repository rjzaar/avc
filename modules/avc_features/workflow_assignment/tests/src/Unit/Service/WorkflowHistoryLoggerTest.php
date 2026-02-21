<?php

namespace Drupal\Tests\workflow_assignment\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Database\Schema;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\workflow_assignment\WorkflowHistoryLogger;

/**
 * Unit tests for WorkflowHistoryLogger.
 *
 * @group workflow_assignment
 * @coversDefaultClass \Drupal\workflow_assignment\WorkflowHistoryLogger
 */
class WorkflowHistoryLoggerTest extends UnitTestCase {

  /**
   * The database connection mock.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

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
   * The database schema mock.
   *
   * @var \Drupal\Core\Database\Schema|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $schema;

  /**
   * The service under test.
   *
   * @var \Drupal\workflow_assignment\WorkflowHistoryLogger
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->database = $this->createMock(Connection::class);
    $this->currentUser = $this->createMock(AccountInterface::class);
    $this->currentUser->method('id')->willReturn(7);
    $this->time = $this->createMock(TimeInterface::class);
    $this->time->method('getRequestTime')->willReturn(1700000000);

    $this->schema = $this->createMock(Schema::class);
    $this->database->method('schema')->willReturn($this->schema);

    $this->logger = new WorkflowHistoryLogger(
      $this->database,
      $this->currentUser,
      $this->time
    );
  }

  /**
   * Tests that log inserts a record into the database.
   *
   * @covers ::log
   */
  public function testLogInsertsRecord(): void {
    $this->schema->method('tableExists')
      ->with('workflow_assignment_history')
      ->willReturn(TRUE);

    $insert = $this->createMock(Insert::class);
    $insert->expects($this->once())
      ->method('fields')
      ->with([
        'workflow_id' => 'wf_123',
        'node_id' => 10,
        'action' => 'update',
        'field_name' => 'assigned_user',
        'old_value' => 'User A',
        'new_value' => 'User B',
        'uid' => 7,
        'timestamp' => 1700000000,
      ])
      ->willReturnSelf();
    $insert->expects($this->once())
      ->method('execute');

    $this->database->expects($this->once())
      ->method('insert')
      ->with('workflow_assignment_history')
      ->willReturn($insert);

    $this->logger->log('wf_123', 'update', 10, 'assigned_user', 'User A', 'User B');
  }

  /**
   * Tests that logAssignment delegates to log with the 'assign' action.
   *
   * @covers ::logAssignment
   */
  public function testLogAssignmentCallsLog(): void {
    $this->schema->method('tableExists')
      ->with('workflow_assignment_history')
      ->willReturn(TRUE);

    $insert = $this->createMock(Insert::class);
    $insert->expects($this->once())
      ->method('fields')
      ->with([
        'workflow_id' => 'wf_456',
        'node_id' => 20,
        'action' => 'assign',
        'field_name' => NULL,
        'old_value' => NULL,
        'new_value' => NULL,
        'uid' => 7,
        'timestamp' => 1700000000,
      ])
      ->willReturnSelf();
    $insert->expects($this->once())
      ->method('execute');

    $this->database->expects($this->once())
      ->method('insert')
      ->with('workflow_assignment_history')
      ->willReturn($insert);

    $this->logger->logAssignment('wf_456', 20);
  }

  /**
   * Tests that logUnassignment delegates to log with the 'unassign' action.
   *
   * @covers ::logUnassignment
   */
  public function testLogUnassignmentCallsLog(): void {
    $this->schema->method('tableExists')
      ->with('workflow_assignment_history')
      ->willReturn(TRUE);

    $insert = $this->createMock(Insert::class);
    $insert->expects($this->once())
      ->method('fields')
      ->with([
        'workflow_id' => 'wf_789',
        'node_id' => 30,
        'action' => 'unassign',
        'field_name' => NULL,
        'old_value' => NULL,
        'new_value' => NULL,
        'uid' => 7,
        'timestamp' => 1700000000,
      ])
      ->willReturnSelf();
    $insert->expects($this->once())
      ->method('execute');

    $this->database->expects($this->once())
      ->method('insert')
      ->with('workflow_assignment_history')
      ->willReturn($insert);

    $this->logger->logUnassignment('wf_789', 30);
  }

}
