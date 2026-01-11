<?php

namespace Drupal\Tests\avc_work_management\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Kernel tests for WorkTaskQueryService.
 *
 * @group avc_work_management
 */
class WorkTaskQueryServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'taxonomy',
    'options',
    'avc_work_management',
    'workflow_assignment',
  ];

  /**
   * The work task query service.
   *
   * @var \Drupal\avc_work_management\Service\WorkTaskQueryService
   */
  protected $taskQueryService;

  /**
   * Test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('workflow_task');
    $this->installEntitySchema('workflow_assignment');
    $this->installEntitySchema('workflow_list');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'user', 'avc_work_management', 'workflow_assignment']);

    // Create content types.
    foreach (['avc_document', 'avc_resource', 'avc_project'] as $type) {
      if (!NodeType::load($type)) {
        NodeType::create([
          'type' => $type,
          'name' => ucfirst(str_replace('avc_', '', $type)),
        ])->save();
      }
    }

    // Create test user.
    $this->testUser = User::create([
      'name' => 'test_user',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $this->testUser->save();

    $this->taskQueryService = \Drupal::service('avc_work_management.task_query');
  }

  /**
   * Tests service instantiation.
   */
  public function testServiceInstantiation(): void {
    $this->assertInstanceOf(
      'Drupal\avc_work_management\Service\WorkTaskQueryService',
      $this->taskQueryService
    );
  }

  /**
   * Tests getTrackedContentTypes returns configured types.
   */
  public function testGetTrackedContentTypes(): void {
    $types = $this->taskQueryService->getTrackedContentTypes();

    $this->assertIsArray($types);
    $this->assertArrayHasKey('avc_document', $types);
    $this->assertArrayHasKey('avc_resource', $types);
    $this->assertArrayHasKey('avc_project', $types);

    // Verify structure.
    $this->assertEquals('Documents', $types['avc_document']['label']);
    $this->assertEquals('file-text', $types['avc_document']['icon']);
    $this->assertEquals('#4a90d9', $types['avc_document']['color']);
  }

  /**
   * Tests getSummaryCounts with no tasks.
   */
  public function testGetSummaryCountsEmpty(): void {
    $summary = $this->taskQueryService->getSummaryCounts($this->testUser);

    $this->assertIsArray($summary);
    $this->assertCount(3, $summary);

    foreach ($summary as $type => $counts) {
      $this->assertEquals(0, $counts['active']);
      $this->assertEquals(0, $counts['upcoming']);
      $this->assertEquals(0, $counts['completed']);
    }
  }

  /**
   * Tests countTasks returns zero with no tasks.
   */
  public function testCountTasksEmpty(): void {
    $count = $this->taskQueryService->countTasks(
      $this->testUser,
      NULL,
      'in_progress',
      'user'
    );

    $this->assertEquals(0, $count);
  }

  /**
   * Tests getTasks returns empty array with no tasks.
   */
  public function testGetTasksEmpty(): void {
    $tasks = $this->taskQueryService->getTasks(
      $this->testUser,
      NULL,
      'in_progress',
      'user',
      10
    );

    $this->assertIsArray($tasks);
    $this->assertEmpty($tasks);
  }

  /**
   * Tests getTasksForSection with valid section.
   */
  public function testGetTasksForSectionActive(): void {
    $tasks = $this->taskQueryService->getTasksForSection('active', $this->testUser);

    $this->assertIsArray($tasks);
  }

  /**
   * Tests getTasksForSection with invalid section.
   */
  public function testGetTasksForSectionInvalid(): void {
    $tasks = $this->taskQueryService->getTasksForSection('nonexistent', $this->testUser);

    $this->assertIsArray($tasks);
    $this->assertEmpty($tasks);
  }

  /**
   * Tests getDashboardCacheTags.
   */
  public function testGetDashboardCacheTags(): void {
    $tags = $this->taskQueryService->getDashboardCacheTags($this->testUser);

    $this->assertIsArray($tags);
    $this->assertContains('user:' . $this->testUser->id(), $tags);
    $this->assertContains('workflow_task_list', $tags);
  }

  /**
   * Tests config schema is valid.
   */
  public function testConfigSchema(): void {
    $config = \Drupal::config('avc_work_management.settings');

    // Verify config exists and has expected structure.
    $this->assertNotNull($config->get('tracked_content_types'));
    $this->assertNotNull($config->get('sections'));
    $this->assertNotNull($config->get('display'));
  }

  /**
   * Tests section configuration.
   */
  public function testSectionConfiguration(): void {
    $config = \Drupal::config('avc_work_management.settings');
    $sections = $config->get('sections');

    // Verify active section.
    $this->assertEquals('Action Needed', $sections['active']['label']);
    $this->assertEquals('in_progress', $sections['active']['status']);
    $this->assertEquals('user', $sections['active']['assigned_to']);

    // Verify available section.
    $this->assertEquals('Available to Claim', $sections['available']['label']);
    $this->assertEquals('pending', $sections['available']['status']);
    $this->assertEquals('group', $sections['available']['assigned_to']);
    $this->assertTrue($sections['available']['show_claim']);

    // Verify upcoming section.
    $this->assertEquals('Upcoming', $sections['upcoming']['label']);
    $this->assertEquals('pending', $sections['upcoming']['status']);
    $this->assertEquals('user', $sections['upcoming']['assigned_to']);

    // Verify completed section.
    $this->assertEquals('Recently Completed', $sections['completed']['label']);
    $this->assertEquals('completed', $sections['completed']['status']);
    $this->assertEquals('user', $sections['completed']['assigned_to']);
  }

  /**
   * Tests countTasks with content type filter when no nodes exist.
   */
  public function testCountTasksWithContentTypeNoNodes(): void {
    $count = $this->taskQueryService->countTasks(
      $this->testUser,
      'avc_document',
      'in_progress',
      'user'
    );

    $this->assertEquals(0, $count);
  }

  /**
   * Tests countTasks with content type filter when nodes exist.
   */
  public function testCountTasksWithContentTypeWithNodes(): void {
    // Create a document node.
    $node = Node::create([
      'type' => 'avc_document',
      'title' => 'Test Document',
      'uid' => $this->testUser->id(),
      'status' => 1,
    ]);
    $node->save();

    // Still no tasks, so count should be 0.
    $count = $this->taskQueryService->countTasks(
      $this->testUser,
      'avc_document',
      'in_progress',
      'user'
    );

    $this->assertEquals(0, $count);
  }

  /**
   * Tests multiple content types in summary.
   */
  public function testSummaryMultipleContentTypes(): void {
    // Create nodes of different types.
    foreach (['avc_document', 'avc_resource', 'avc_project'] as $type) {
      Node::create([
        'type' => $type,
        'title' => 'Test ' . $type,
        'uid' => $this->testUser->id(),
        'status' => 1,
      ])->save();
    }

    $summary = $this->taskQueryService->getSummaryCounts($this->testUser);

    // All should have 0 tasks (nodes exist but no workflow tasks).
    foreach (['avc_document', 'avc_resource', 'avc_project'] as $type) {
      $this->assertArrayHasKey($type, $summary);
      $this->assertEquals(0, $summary[$type]['active']);
      $this->assertEquals(0, $summary[$type]['upcoming']);
      $this->assertEquals(0, $summary[$type]['completed']);
    }
  }

}
