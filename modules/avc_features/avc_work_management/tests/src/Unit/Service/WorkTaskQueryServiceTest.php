<?php

namespace Drupal\Tests\avc_work_management\Unit\Service;

use Drupal\avc_work_management\Service\WorkTaskQueryService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for WorkTaskQueryService.
 *
 * @group avc_work_management
 * @coversDefaultClass \Drupal\avc_work_management\Service\WorkTaskQueryService
 */
class WorkTaskQueryServiceTest extends UnitTestCase {

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
   * The group membership loader mock.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $groupMembershipLoader;

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_work_management\Service\WorkTaskQueryService
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
    $this->groupMembershipLoader = $this->createMock(GroupMembershipLoaderInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);

    // Set up default config.
    $this->setupDefaultConfig();

    $this->service = new WorkTaskQueryService(
      $this->entityTypeManager,
      $this->currentUser,
      $this->groupMembershipLoader,
      $this->configFactory
    );
  }

  /**
   * Sets up default config mock.
   */
  protected function setupDefaultConfig(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnCallback(function ($key) {
        $data = [
          'tracked_content_types' => [
            'avc_document' => [
              'label' => 'Documents',
              'icon' => 'file-text',
              'color' => '#4a90d9',
            ],
            'avc_resource' => [
              'label' => 'Resources',
              'icon' => 'link',
              'color' => '#7b68ee',
            ],
            'avc_project' => [
              'label' => 'Projects',
              'icon' => 'folder',
              'color' => '#50c878',
            ],
          ],
          'sections' => [
            'active' => [
              'label' => 'Action Needed',
              'status' => 'in_progress',
              'assigned_to' => 'user',
              'limit' => 10,
              'show_view_all' => TRUE,
            ],
            'available' => [
              'label' => 'Available to Claim',
              'status' => 'pending',
              'assigned_to' => 'group',
              'limit' => 5,
              'show_view_all' => TRUE,
              'show_claim' => TRUE,
            ],
            'upcoming' => [
              'label' => 'Upcoming',
              'status' => 'pending',
              'assigned_to' => 'user',
              'limit' => 5,
              'show_view_all' => TRUE,
            ],
            'completed' => [
              'label' => 'Recently Completed',
              'status' => 'completed',
              'assigned_to' => 'user',
              'limit' => 5,
              'show_view_all' => TRUE,
            ],
          ],
          'sections.active' => [
            'label' => 'Action Needed',
            'status' => 'in_progress',
            'assigned_to' => 'user',
            'limit' => 10,
          ],
          'sections.available' => [
            'label' => 'Available to Claim',
            'status' => 'pending',
            'assigned_to' => 'group',
            'limit' => 5,
            'show_claim' => TRUE,
          ],
          'sections.upcoming' => [
            'label' => 'Upcoming',
            'status' => 'pending',
            'assigned_to' => 'user',
            'limit' => 5,
          ],
          'sections.completed' => [
            'label' => 'Recently Completed',
            'status' => 'completed',
            'assigned_to' => 'user',
            'limit' => 5,
          ],
          'sections.invalid' => NULL,
        ];
        return $data[$key] ?? NULL;
      });

    $this->configFactory->method('get')
      ->with('avc_work_management.settings')
      ->willReturn($config);
  }

  /**
   * Tests getTrackedContentTypes returns configured types.
   *
   * @covers ::getTrackedContentTypes
   */
  public function testGetTrackedContentTypes(): void {
    $types = $this->service->getTrackedContentTypes();

    $this->assertIsArray($types);
    $this->assertCount(3, $types);
    $this->assertArrayHasKey('avc_document', $types);
    $this->assertArrayHasKey('avc_resource', $types);
    $this->assertArrayHasKey('avc_project', $types);
    $this->assertEquals('Documents', $types['avc_document']['label']);
    $this->assertEquals('file-text', $types['avc_document']['icon']);
    $this->assertEquals('#4a90d9', $types['avc_document']['color']);
  }

  /**
   * Tests countTasks returns zero when no tasks exist.
   *
   * @covers ::countTasks
   */
  public function testCountTasksEmpty(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(0);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturnCallback(function ($type) use ($storage) {
        return $storage;
      });

    $count = $this->service->countTasks($this->currentUser, NULL, 'in_progress', 'user');

    $this->assertEquals(0, $count);
  }

  /**
   * Tests countTasks returns correct count with tasks.
   *
   * @covers ::countTasks
   */
  public function testCountTasksWithTasks(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(5);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturnCallback(function ($type) use ($storage) {
        return $storage;
      });

    $count = $this->service->countTasks($this->currentUser, NULL, 'in_progress', 'user');

    $this->assertEquals(5, $count);
  }

  /**
   * Tests countTasks with content type filter.
   *
   * @covers ::countTasks
   */
  public function testCountTasksWithContentTypeFilter(): void {
    // Node query returns node IDs.
    $nodeQuery = $this->createMock(QueryInterface::class);
    $nodeQuery->method('condition')->willReturnSelf();
    $nodeQuery->method('accessCheck')->willReturnSelf();
    $nodeQuery->method('execute')->willReturn([1, 2, 3]);

    // Task query returns count.
    $taskQuery = $this->createMock(QueryInterface::class);
    $taskQuery->method('condition')->willReturnSelf();
    $taskQuery->method('accessCheck')->willReturnSelf();
    $taskQuery->method('count')->willReturnSelf();
    $taskQuery->method('execute')->willReturn(2);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($nodeQuery);

    $taskStorage = $this->createMock(EntityStorageInterface::class);
    $taskStorage->method('getQuery')->willReturn($taskQuery);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturnCallback(function ($type) use ($nodeStorage, $taskStorage) {
        return $type === 'node' ? $nodeStorage : $taskStorage;
      });

    $count = $this->service->countTasks($this->currentUser, 'avc_document', 'in_progress', 'user');

    $this->assertEquals(2, $count);
  }

  /**
   * Tests countTasks returns zero when no matching content type nodes.
   *
   * @covers ::countTasks
   */
  public function testCountTasksEmptyContentType(): void {
    // Node query returns empty.
    $nodeQuery = $this->createMock(QueryInterface::class);
    $nodeQuery->method('condition')->willReturnSelf();
    $nodeQuery->method('accessCheck')->willReturnSelf();
    $nodeQuery->method('execute')->willReturn([]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($nodeQuery);

    // Task query (for buildBaseQuery).
    $taskQuery = $this->createMock(QueryInterface::class);
    $taskQuery->method('condition')->willReturnSelf();
    $taskQuery->method('accessCheck')->willReturnSelf();
    $taskQuery->method('count')->willReturnSelf();
    $taskQuery->method('execute')->willReturn(0);

    $taskStorage = $this->createMock(EntityStorageInterface::class);
    $taskStorage->method('getQuery')->willReturn($taskQuery);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturnCallback(function ($type) use ($nodeStorage, $taskStorage) {
        return $type === 'node' ? $nodeStorage : $taskStorage;
      });

    $count = $this->service->countTasks($this->currentUser, 'avc_document', 'in_progress', 'user');

    $this->assertEquals(0, $count);
  }

  /**
   * Tests getTasks returns empty array when no tasks.
   *
   * @covers ::getTasks
   */
  public function testGetTasksEmpty(): void {
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
      ->willReturnCallback(function ($type) use ($storage) {
        return $storage;
      });

    $tasks = $this->service->getTasks($this->currentUser, NULL, 'in_progress', 'user', 10);

    $this->assertIsArray($tasks);
    $this->assertEmpty($tasks);
  }

  /**
   * Tests getTasksForSection returns tasks for active section.
   *
   * @covers ::getTasksForSection
   */
  public function testGetTasksForSectionActive(): void {
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
      ->willReturnCallback(function ($type) use ($storage) {
        return $storage;
      });

    $tasks = $this->service->getTasksForSection('active', $this->currentUser);

    $this->assertIsArray($tasks);
  }

  /**
   * Tests getTasksForSection returns empty for invalid section.
   *
   * @covers ::getTasksForSection
   */
  public function testGetTasksForSectionInvalid(): void {
    $tasks = $this->service->getTasksForSection('invalid', $this->currentUser);

    $this->assertIsArray($tasks);
    $this->assertEmpty($tasks);
  }

  /**
   * Tests getSummaryCounts returns counts for all content types.
   *
   * @covers ::getSummaryCounts
   */
  public function testGetSummaryCounts(): void {
    // Node query returns empty array (no nodes of this type).
    $nodeQuery = $this->createMock(QueryInterface::class);
    $nodeQuery->method('condition')->willReturnSelf();
    $nodeQuery->method('accessCheck')->willReturnSelf();
    $nodeQuery->method('execute')->willReturn([]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($nodeQuery);

    // Task query for counting.
    $taskQuery = $this->createMock(QueryInterface::class);
    $taskQuery->method('condition')->willReturnSelf();
    $taskQuery->method('accessCheck')->willReturnSelf();
    $taskQuery->method('count')->willReturnSelf();
    $taskQuery->method('execute')->willReturn(0);

    $taskStorage = $this->createMock(EntityStorageInterface::class);
    $taskStorage->method('getQuery')->willReturn($taskQuery);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturnCallback(function ($type) use ($nodeStorage, $taskStorage) {
        return $type === 'node' ? $nodeStorage : $taskStorage;
      });

    $summary = $this->service->getSummaryCounts($this->currentUser);

    $this->assertIsArray($summary);
    $this->assertCount(3, $summary);
    $this->assertArrayHasKey('avc_document', $summary);
    $this->assertArrayHasKey('avc_resource', $summary);
    $this->assertArrayHasKey('avc_project', $summary);

    // Check structure of each summary.
    foreach ($summary as $type => $data) {
      $this->assertArrayHasKey('label', $data);
      $this->assertArrayHasKey('icon', $data);
      $this->assertArrayHasKey('color', $data);
      $this->assertArrayHasKey('active', $data);
      $this->assertArrayHasKey('upcoming', $data);
      $this->assertArrayHasKey('completed', $data);
    }
  }

  /**
   * Tests getDashboardCacheTags returns correct tags.
   *
   * @covers ::getDashboardCacheTags
   */
  public function testGetDashboardCacheTags(): void {
    $this->groupMembershipLoader->method('loadByUser')->willReturn([]);

    $tags = $this->service->getDashboardCacheTags($this->currentUser);

    $this->assertIsArray($tags);
    $this->assertContains('user:1', $tags);
    $this->assertContains('workflow_task_list', $tags);
  }

  /**
   * Tests getDashboardCacheTags includes group tags.
   *
   * @covers ::getDashboardCacheTags
   */
  public function testGetDashboardCacheTagsWithGroups(): void {
    // Mock group membership.
    $group1 = $this->createMock('\Drupal\group\Entity\GroupInterface');
    $group1->method('id')->willReturn(10);

    $group2 = $this->createMock('\Drupal\group\Entity\GroupInterface');
    $group2->method('id')->willReturn(20);

    $membership1 = $this->createMock('\Drupal\group\GroupMembership');
    $membership1->method('getGroup')->willReturn($group1);

    $membership2 = $this->createMock('\Drupal\group\GroupMembership');
    $membership2->method('getGroup')->willReturn($group2);

    $this->groupMembershipLoader->method('loadByUser')
      ->willReturn([$membership1, $membership2]);

    $tags = $this->service->getDashboardCacheTags($this->currentUser);

    $this->assertContains('user:1', $tags);
    $this->assertContains('group:10', $tags);
    $this->assertContains('group:20', $tags);
  }

  /**
   * Tests countTasks for group-assigned tasks.
   *
   * @covers ::countTasks
   */
  public function testCountTasksGroupAssigned(): void {
    // Mock group membership.
    $group = $this->createMock('\Drupal\group\Entity\GroupInterface');
    $group->method('id')->willReturn(5);

    $membership = $this->createMock('\Drupal\group\GroupMembership');
    $membership->method('getGroup')->willReturn($group);

    $this->groupMembershipLoader->method('loadByUser')
      ->willReturn([$membership]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(3);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturnCallback(function ($type) use ($storage) {
        return $storage;
      });

    $count = $this->service->countTasks($this->currentUser, NULL, 'pending', 'group');

    $this->assertEquals(3, $count);
  }

  /**
   * Tests countTasks returns zero when user has no groups.
   *
   * @covers ::countTasks
   */
  public function testCountTasksGroupAssignedNoGroups(): void {
    $this->groupMembershipLoader->method('loadByUser')->willReturn([]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(0);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturnCallback(function ($type) use ($storage) {
        return $storage;
      });

    $count = $this->service->countTasks($this->currentUser, NULL, 'pending', 'group');

    $this->assertEquals(0, $count);
  }

  /**
   * Data provider for section tests.
   */
  public function sectionProvider(): array {
    return [
      'active section' => ['active', 'in_progress', 'user'],
      'available section' => ['available', 'pending', 'group'],
      'upcoming section' => ['upcoming', 'pending', 'user'],
      'completed section' => ['completed', 'completed', 'user'],
    ];
  }

  /**
   * Tests getTasksForSection with different sections.
   *
   * @dataProvider sectionProvider
   * @covers ::getTasksForSection
   */
  public function testGetTasksForSectionTypes(string $section, string $expectedStatus, string $expectedAssignedTo): void {
    // This test validates that the service correctly reads config for each section.
    // The actual query is mocked, but we can verify it's called properly.
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
      ->willReturnCallback(function ($type) use ($storage) {
        return $storage;
      });

    // For group sections, mock the membership loader.
    if ($expectedAssignedTo === 'group') {
      $group = $this->createMock('\Drupal\group\Entity\GroupInterface');
      $group->method('id')->willReturn(1);
      $membership = $this->createMock('\Drupal\group\GroupMembership');
      $membership->method('getGroup')->willReturn($group);
      $this->groupMembershipLoader->method('loadByUser')->willReturn([$membership]);
    }

    $tasks = $this->service->getTasksForSection($section, $this->currentUser);

    $this->assertIsArray($tasks);
  }

}
