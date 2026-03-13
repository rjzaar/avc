<?php

namespace Drupal\Tests\avc_content_access\Unit\Access;

use Drupal\avc_content_access\Access\DestinationAccessManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for DestinationAccessManager.
 *
 * @group avc_content_access
 * @coversDefaultClass \Drupal\avc_content_access\Access\DestinationAccessManager
 */
class DestinationAccessManagerTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ConfigFactoryInterface $configFactory;
  protected DestinationAccessManager $manager;
  protected AccountInterface $account;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnCallback(function ($key) {
        return match ($key) {
          'enabled_content_types' => ['avc_document', 'avc_resource'],
          default => NULL,
        };
      });

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('workflow_assignment.settings')
      ->willReturn($config);

    $this->manager = new DestinationAccessManager(
      $this->entityTypeManager,
      $this->configFactory
    );

    $this->account = $this->createMock(AccountInterface::class);
    $this->account->method('id')->willReturn(1);
    $this->account->method('isAuthenticated')->willReturn(TRUE);
  }

  /**
   * Tests neutral access for non-enabled content types.
   *
   * @covers ::checkAccess
   */
  public function testNeutralForNonEnabledType(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('page');
    $node->method('id')->willReturn(1);
    $node->method('getCacheTags')->willReturn(['node:1']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);

    $result = $this->manager->checkAccess($node, 'view', $this->account);

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests neutral when no completed destination task exists.
   *
   * @covers ::checkAccess
   * @covers ::getCompletedDestination
   */
  public function testNeutralWhenNoDestination(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('avc_document');
    $node->method('id')->willReturn(1);
    $node->method('getCacheTags')->willReturn(['node:1']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);

    // Mock empty query result.
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

    $result = $this->manager->checkAccess($node, 'view', $this->account);

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests public access level allows everyone.
   *
   * @covers ::checkAccess
   */
  public function testPublicAccessAllowsAnonymous(): void {
    $node = $this->createMockNode(1, 'avc_document', 2);

    $term = $this->createMockDestinationTerm(10, 'public');

    $this->setupDestinationQuery($node, $term);

    $anon = $this->createMock(AccountInterface::class);
    $anon->method('id')->willReturn(0);
    $anon->method('isAuthenticated')->willReturn(FALSE);

    $result = $this->manager->checkAccess($node, 'view', $anon);

    $this->assertTrue($result->isAllowed());
  }

  /**
   * Tests authenticated access level denies anonymous.
   *
   * @covers ::checkAccess
   */
  public function testAuthenticatedAccessDeniesAnonymous(): void {
    $node = $this->createMockNode(1, 'avc_document', 2);

    $term = $this->createMockDestinationTerm(10, 'authenticated');

    $this->setupDestinationQuery($node, $term);

    $anon = $this->createMock(AccountInterface::class);
    $anon->method('id')->willReturn(0);
    $anon->method('isAuthenticated')->willReturn(FALSE);

    $result = $this->manager->checkAccess($node, 'view', $anon);

    $this->assertTrue($result->isForbidden());
  }

  /**
   * Tests authenticated access allows logged-in user.
   *
   * @covers ::checkAccess
   */
  public function testAuthenticatedAccessAllowsLoggedIn(): void {
    $node = $this->createMockNode(1, 'avc_document', 2);

    $term = $this->createMockDestinationTerm(10, 'authenticated');

    $this->setupDestinationQuery($node, $term);

    $result = $this->manager->checkAccess($node, 'view', $this->account);

    $this->assertTrue($result->isAllowed());
  }

  /**
   * Tests private access allows author.
   *
   * @covers ::checkAccess
   */
  public function testPrivateAccessAllowsAuthor(): void {
    // Node owned by user 1, checking as user 1.
    $node = $this->createMockNode(1, 'avc_document', 1);

    $term = $this->createMockDestinationTerm(10, 'private');

    $this->setupDestinationQuery($node, $term);

    $result = $this->manager->checkAccess($node, 'view', $this->account);

    $this->assertTrue($result->isAllowed());
  }

  /**
   * Tests private access denies non-author without admin permission.
   *
   * @covers ::checkAccess
   */
  public function testPrivateAccessDeniesNonAuthor(): void {
    // Node owned by user 2, checking as user 1.
    $node = $this->createMockNode(1, 'avc_document', 2);

    $term = $this->createMockDestinationTerm(10, 'private');

    $this->setupDestinationQuery($node, $term);

    $this->account->method('hasPermission')
      ->willReturn(FALSE);

    $result = $this->manager->checkAccess($node, 'view', $this->account);

    $this->assertTrue($result->isForbidden());
  }

  /**
   * Helper to create a mock node.
   */
  protected function createMockNode(int $id, string $bundle, int $owner_id): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn($id);
    $node->method('bundle')->willReturn($bundle);
    $node->method('getOwnerId')->willReturn($owner_id);
    $node->method('getCacheTags')->willReturn(['node:' . $id]);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);
    return $node;
  }

  /**
   * Helper to create a mock destination term.
   */
  protected function createMockDestinationTerm(int $id, string $access_level): object {
    $term = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['id', 'hasField', 'get', 'getName', 'getCacheTags', 'getCacheContexts', 'getCacheMaxAge'])
      ->getMock();

    $term->method('id')->willReturn($id);
    $term->method('getName')->willReturn('Test Destination');
    $term->method('getCacheTags')->willReturn(['taxonomy_term:' . $id]);
    $term->method('getCacheContexts')->willReturn([]);
    $term->method('getCacheMaxAge')->willReturn(-1);

    $term->method('hasField')->willReturnCallback(function ($field) {
      return in_array($field, ['field_access_level', 'field_access_groups', 'field_file_scheme', 'field_auto_publish']);
    });

    $access_level_field = new \stdClass();
    $access_level_field->value = $access_level;

    $term->method('get')->willReturnCallback(function ($field) use ($access_level_field) {
      if ($field === 'field_access_level') {
        return $access_level_field;
      }
      $mock = new \stdClass();
      $mock->value = NULL;
      $mock->target_id = NULL;
      return $mock;
    });

    return $term;
  }

  /**
   * Helper to set up mock queries for destination lookup.
   */
  protected function setupDestinationQuery(NodeInterface $node, object $term): void {
    // Mock the workflow_task query.
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([99]);

    // Mock the task entity.
    $task = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['get'])
      ->getMock();

    $dest_field = new \stdClass();
    $dest_field->target_id = $term->id();

    $task->method('get')->willReturnCallback(function ($field) use ($dest_field) {
      if ($field === 'assigned_destination') {
        return $dest_field;
      }
      $mock = new \stdClass();
      $mock->value = NULL;
      $mock->target_id = NULL;
      return $mock;
    });

    $taskStorage = $this->createMock(EntityStorageInterface::class);
    $taskStorage->method('getQuery')->willReturn($query);
    $taskStorage->method('load')->with(99)->willReturn($task);

    $termStorage = $this->createMock(EntityStorageInterface::class);
    $termStorage->method('load')->with($term->id())->willReturn($term);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($entity_type) use ($taskStorage, $termStorage) {
        return match ($entity_type) {
          'workflow_task' => $taskStorage,
          'taxonomy_term' => $termStorage,
          default => $this->createMock(EntityStorageInterface::class),
        };
      });
  }

}
