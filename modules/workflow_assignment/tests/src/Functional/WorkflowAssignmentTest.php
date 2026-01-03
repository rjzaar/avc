<?php

namespace Drupal\Tests\workflow_assignment\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\workflow_assignment\Entity\WorkflowList;
use Drupal\node\Entity\NodeType;

/**
 * Tests workflow assignment functionality.
 *
 * @group workflow_assignment
 */
class WorkflowAssignmentTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['workflow_assignment', 'node', 'taxonomy', 'user', 'field'];

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a content type if it doesn't exist.
    if (!NodeType::load('page')) {
      NodeType::create([
        'type' => 'page',
        'name' => 'Basic page',
      ])->save();
    }

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer workflow lists',
      'assign workflow lists to content',
      'view workflow list assignments',
      'administer site configuration',
      'create page content',
      'edit any page content',
      'access content',
    ]);
  }

  /**
   * Test workflow creation.
   */
  public function testWorkflowCreation() {
    $workflow = WorkflowList::create([
      'id' => 'test_workflow',
      'label' => 'Test Workflow',
      'description' => 'Test workflow description',
    ]);
    $workflow->save();

    $loaded = WorkflowList::load('test_workflow');
    $this->assertNotNull($loaded);
    $this->assertEquals('Test Workflow', $loaded->label());
    $this->assertEquals('Test workflow description', $loaded->getDescription());
  }

  /**
   * Test workflow with assignment.
   */
  public function testWorkflowAssignment() {
    $workflow = WorkflowList::create([
      'id' => 'assigned_workflow',
      'label' => 'Assigned Workflow',
      'assigned_type' => 'user',
      'assigned_id' => $this->adminUser->id(),
    ]);
    $workflow->save();

    $loaded = WorkflowList::load('assigned_workflow');
    $this->assertEquals('user', $loaded->getAssignedType());
    $this->assertEquals($this->adminUser->id(), $loaded->getAssignedId());
    $this->assertEquals($this->adminUser->getDisplayName(), $loaded->getAssignedLabel());
  }

  /**
   * Test destination locations.
   */
  public function testDestinationLocations() {
    // Check if destination_locations vocabulary exists.
    $vocabulary = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_vocabulary')
      ->load('destination_locations');
    $this->assertNotNull($vocabulary);

    // Check for default terms.
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => 'destination_locations',
        'name' => 'Public',
      ]);
    $this->assertNotEmpty($terms);
  }

  /**
   * Test workflow tab access with permission.
   */
  public function testWorkflowTabAccess() {
    $this->drupalLogin($this->adminUser);

    // Enable workflow on page.
    \Drupal::configFactory()
      ->getEditable('workflow_assignment.settings')
      ->set('enabled_content_types', ['page'])
      ->save();

    // Create page.
    $node = $this->drupalCreateNode(['type' => 'page']);

    // Visit workflow tab.
    $this->drupalGet('/node/' . $node->id() . '/workflow');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Workflow Information');
  }

  /**
   * Test workflow tab access denied without permission.
   */
  public function testWorkflowTabAccessDenied() {
    // Create a user without workflow permissions.
    $user = $this->drupalCreateUser([
      'create page content',
      'access content',
    ]);
    $this->drupalLogin($user);

    // Enable workflow on page.
    \Drupal::configFactory()
      ->getEditable('workflow_assignment.settings')
      ->set('enabled_content_types', ['page'])
      ->save();

    // Create page.
    $node = $this->drupalCreateNode(['type' => 'page']);

    // Try to visit workflow tab.
    $this->drupalGet('/node/' . $node->id() . '/workflow');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test settings page.
   */
  public function testSettingsPage() {
    $this->drupalLogin($this->adminUser);

    // Visit settings page.
    $this->drupalGet('/admin/config/workflow/workflow-assignment');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Workflow Assignment');
    $this->assertSession()->pageTextContains('Enabled Content Types');
    $this->assertSession()->pageTextContains('Email Notifications');
  }

  /**
   * Test workflow list admin page.
   */
  public function testWorkflowListPage() {
    $this->drupalLogin($this->adminUser);

    // Create a workflow.
    WorkflowList::create([
      'id' => 'list_test_workflow',
      'label' => 'List Test Workflow',
    ])->save();

    // Visit workflow list page.
    $this->drupalGet('/admin/structure/workflow-list');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('List Test Workflow');
  }

  /**
   * Test workflow clone form.
   */
  public function testWorkflowClone() {
    $this->drupalLogin($this->adminUser);

    // Create a workflow.
    WorkflowList::create([
      'id' => 'clone_source',
      'label' => 'Clone Source',
      'description' => 'Source description',
    ])->save();

    // Visit clone page.
    $this->drupalGet('/admin/structure/workflow-list/clone_source/clone');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Clone Workflow');
  }

  /**
   * Test workflow history page.
   */
  public function testHistoryPage() {
    $this->drupalLogin($this->adminUser);

    // Visit history page.
    $this->drupalGet('/admin/structure/workflow-list/history');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Workflow History');
  }

  /**
   * Test workflow template page.
   */
  public function testTemplatePage() {
    $this->drupalLogin($this->adminUser);

    // Visit template list page.
    $this->drupalGet('/admin/structure/workflow-template');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Workflow Templates');
  }

}
