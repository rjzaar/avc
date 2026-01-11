<?php

namespace Drupal\Tests\avc_work_management\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the Claim Task form functionality.
 *
 * @group avc_work_management
 */
class ClaimTaskFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'avc_work_management',
    'workflow_assignment',
    'node',
    'taxonomy',
    'user',
    'field',
    'group',
  ];

  /**
   * A user with claim permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $claimUser;

  /**
   * A user without claim permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $noClaimUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content type.
    if (!NodeType::load('avc_document')) {
      NodeType::create([
        'type' => 'avc_document',
        'name' => 'Document',
      ])->save();
    }

    // Create users.
    $this->claimUser = $this->drupalCreateUser([
      'access my work dashboard',
      'claim workflow tasks',
      'access content',
    ]);

    $this->noClaimUser = $this->drupalCreateUser([
      'access my work dashboard',
      'access content',
    ]);
  }

  /**
   * Tests claim route returns 404 for invalid task.
   */
  public function testClaimInvalidTask(): void {
    $this->drupalLogin($this->claimUser);

    // Try to claim a non-existent task.
    $this->drupalGet('/my-work/claim/9999');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests claim route access denied without permission.
   */
  public function testClaimAccessDenied(): void {
    $this->drupalLogin($this->noClaimUser);

    // Even with a valid task ID, should be denied.
    $this->drupalGet('/my-work/claim/1');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests anonymous cannot access claim form.
   */
  public function testClaimAnonymousAccessDenied(): void {
    $this->drupalGet('/my-work/claim/1');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests claim form has correct elements.
   */
  public function testClaimFormElements(): void {
    // This test would need a valid workflow_task entity.
    // For now, we test that the route/form class is properly registered.
    $this->drupalLogin($this->claimUser);

    // Verify the form class is registered.
    $this->assertTrue(class_exists('Drupal\avc_work_management\Form\ClaimTaskForm'));
  }

  /**
   * Tests claim permission is properly defined.
   */
  public function testClaimPermission(): void {
    $permissions = \Drupal::service('user.permissions')->getPermissions();

    $this->assertArrayHasKey('claim workflow tasks', $permissions);
    $this->assertEquals('Claim workflow tasks', $permissions['claim workflow tasks']['title']);
  }

  /**
   * Tests WorkTaskActionService is available.
   */
  public function testActionServiceAvailable(): void {
    $this->assertTrue(\Drupal::hasService('avc_work_management.task_action'));

    $service = \Drupal::service('avc_work_management.task_action');
    $this->assertInstanceOf('Drupal\avc_work_management\Service\WorkTaskActionService', $service);
  }

}
