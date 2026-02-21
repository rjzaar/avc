<?php

namespace Drupal\Tests\workflow_assignment\Unit\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\workflow_assignment\Access\WorkflowAccessManager;
use Drupal\workflow_assignment\Service\WorkflowParticipantResolver;

/**
 * Unit tests for WorkflowAccessManager.
 *
 * @group workflow_assignment
 * @coversDefaultClass \Drupal\workflow_assignment\Access\WorkflowAccessManager
 */
class WorkflowAccessManagerTest extends UnitTestCase {

  /**
   * The participant resolver mock.
   *
   * @var \Drupal\workflow_assignment\Service\WorkflowParticipantResolver|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $participantResolver;

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The service under test.
   *
   * @var \Drupal\workflow_assignment\Access\WorkflowAccessManager
   */
  protected $accessManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->participantResolver = $this->createMock(WorkflowParticipantResolver::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);

    $this->accessManager = new WorkflowAccessManager(
      $this->participantResolver,
      $this->configFactory
    );
  }

  /**
   * Creates a mock node.
   */
  protected function createMockNode(string $bundle = 'avc_document', int $owner_id = 1) {
    $node = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['id', 'getOwnerId', 'bundle', 'getCacheTags', 'getCacheContexts', 'getCacheMaxAge'])
      ->getMock();
    $node->method('id')->willReturn(1);
    $node->method('getOwnerId')->willReturn($owner_id);
    $node->method('bundle')->willReturn($bundle);
    $node->method('getCacheTags')->willReturn(['node:1']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);
    return $node;
  }

  /**
   * Sets up config with given values.
   */
  protected function setupConfig(array $enabled = [], array $access_control = [], bool $past_view = TRUE, bool $restrict_delete = TRUE) {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnCallback(function ($key) use ($enabled, $access_control, $past_view, $restrict_delete) {
        switch ($key) {
          case 'enabled_content_types':
            return $enabled;
          case 'workflow_access_control_types':
            return $access_control;
          case 'allow_past_participants_view':
            return $past_view;
          case 'restrict_delete_during_workflow':
            return $restrict_delete;
          default:
            return NULL;
        }
      });

    $this->configFactory->method('get')
      ->with('workflow_assignment.settings')
      ->willReturn($config);
  }

  /**
   * Tests appliesTo returns false for unconfigured types.
   *
   * @covers ::appliesTo
   */
  public function testAppliesToReturnsFalseForUnconfiguredType(): void {
    $node = $this->createMockNode('page');
    $this->setupConfig(['avc_document'], ['avc_document']);

    $result = $this->accessManager->appliesTo($node);

    $this->assertFalse($result);
  }

  /**
   * Tests appliesTo returns true for configured types.
   *
   * @covers ::appliesTo
   */
  public function testAppliesToReturnsTrueForConfiguredType(): void {
    $node = $this->createMockNode('avc_document');
    $this->setupConfig(['avc_document'], ['avc_document']);

    $result = $this->accessManager->appliesTo($node);

    $this->assertTrue($result);
  }

  /**
   * Tests checkAccess returns neutral when not applicable.
   *
   * @covers ::checkAccess
   */
  public function testCheckAccessReturnsNeutralWhenNotApplicable(): void {
    $node = $this->createMockNode('page');
    $account = $this->createMock(AccountInterface::class);
    $this->setupConfig(['avc_document'], ['avc_document']);

    $result = $this->accessManager->checkAccess($node, 'view', $account);

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests checkAccess returns neutral when no active tasks.
   *
   * @covers ::checkAccess
   */
  public function testCheckAccessReturnsNeutralWhenNoActiveTasks(): void {
    $node = $this->createMockNode('avc_document');
    $account = $this->createMock(AccountInterface::class);
    $this->setupConfig(['avc_document'], ['avc_document']);

    $this->participantResolver->method('getActiveWorkflowTasks')->willReturn([]);
    $this->participantResolver->method('getAccessCacheTags')->willReturn(['workflow_task_list:1']);

    $result = $this->accessManager->checkAccess($node, 'view', $account);

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests checkAccess allows node author.
   *
   * @covers ::checkAccess
   */
  public function testCheckAccessAllowsNodeAuthor(): void {
    $node = $this->createMockNode('avc_document', 5);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(5);
    $this->setupConfig(['avc_document'], ['avc_document']);

    $task = new \stdClass();
    $this->participantResolver->method('getActiveWorkflowTasks')->willReturn([$task]);
    $this->participantResolver->method('getAccessCacheTags')->willReturn(['workflow_task_list:1']);

    $result = $this->accessManager->checkAccess($node, 'view', $account);

    $this->assertTrue($result->isAllowed());
  }

  /**
   * Tests checkAccess allows workflow admin.
   *
   * @covers ::checkAccess
   */
  public function testCheckAccessAllowsWorkflowAdmin(): void {
    $node = $this->createMockNode('avc_document', 1);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(99);
    $account->method('hasPermission')
      ->with('administer workflow tasks')
      ->willReturn(TRUE);
    $this->setupConfig(['avc_document'], ['avc_document']);

    $task = new \stdClass();
    $this->participantResolver->method('getActiveWorkflowTasks')->willReturn([$task]);
    $this->participantResolver->method('getAccessCacheTags')->willReturn(['workflow_task_list:1']);

    $result = $this->accessManager->checkAccess($node, 'view', $account);

    $this->assertTrue($result->isAllowed());
  }

  /**
   * Tests checkAccess denies non-participant.
   *
   * @covers ::checkAccess
   */
  public function testCheckAccessDeniesNonParticipant(): void {
    $node = $this->createMockNode('avc_document', 1);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(99);
    $account->method('hasPermission')->willReturn(FALSE);
    $this->setupConfig(['avc_document'], ['avc_document']);

    $task = new \stdClass();
    $this->participantResolver->method('getActiveWorkflowTasks')->willReturn([$task]);
    $this->participantResolver->method('getCurrentTask')->willReturn($task);
    $this->participantResolver->method('isAssignedToTask')->willReturn(FALSE);
    $this->participantResolver->method('isParticipant')->willReturn(FALSE);
    $this->participantResolver->method('getAccessCacheTags')->willReturn(['workflow_task_list:1']);

    $result = $this->accessManager->checkAccess($node, 'view', $account);

    $this->assertTrue($result->isForbidden());
  }

}
