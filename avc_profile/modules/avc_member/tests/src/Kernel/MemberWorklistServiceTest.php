<?php

namespace Drupal\Tests\avc_member\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Kernel tests for MemberWorklistService.
 *
 * @group avc_member
 */
class MemberWorklistServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'taxonomy',
    'node',
    'dynamic_entity_reference',
    'workflow_assignment',
    'avc_core',
    'avc_member',
  ];

  /**
   * The worklist service.
   *
   * @var \Drupal\avc_member\Service\MemberWorklistService
   */
  protected $worklistService;

  /**
   * A test user.
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
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('node');
    $this->installSchema('system', 'sequences');
    $this->installConfig(['user', 'taxonomy']);

    // Create a test user.
    $this->testUser = User::create([
      'name' => 'test_user',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $this->testUser->save();

    $this->worklistService = $this->container->get('avc_member.worklist');
  }

  /**
   * Tests getUserWorklist returns empty array for user with no assignments.
   */
  public function testGetUserWorklistEmpty() {
    $result = $this->worklistService->getUserWorklist($this->testUser);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests getUserNotificationSettings returns defaults.
   */
  public function testGetUserNotificationSettingsDefaults() {
    $result = $this->worklistService->getUserNotificationSettings($this->testUser);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('default', $result);
    $this->assertArrayHasKey('last_run', $result);
    $this->assertArrayHasKey('groups', $result);
    $this->assertEquals('x', $result['default']);
  }

  /**
   * Tests service is properly injected.
   */
  public function testServiceExists() {
    $this->assertNotNull($this->worklistService);
    $this->assertInstanceOf(
      'Drupal\avc_member\Service\MemberWorklistService',
      $this->worklistService
    );
  }

}
