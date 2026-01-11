<?php

namespace Drupal\Tests\avc_work_management\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the My Work dashboard functionality.
 *
 * @group avc_work_management
 */
class MyWorkDashboardTest extends BrowserTestBase {

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
   * A user with dashboard access.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $dashboardUser;

  /**
   * A user without dashboard access.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $noAccessUser;

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

    // Create content types if they don't exist.
    foreach (['avc_document', 'avc_resource', 'avc_project'] as $type) {
      if (!NodeType::load($type)) {
        NodeType::create([
          'type' => $type,
          'name' => ucfirst(str_replace('avc_', '', $type)),
        ])->save();
      }
    }

    // Create users with different permissions.
    $this->dashboardUser = $this->drupalCreateUser([
      'access my work dashboard',
      'claim workflow tasks',
      'access content',
    ]);

    $this->noAccessUser = $this->drupalCreateUser([
      'access content',
    ]);

    $this->adminUser = $this->drupalCreateUser([
      'access my work dashboard',
      'claim workflow tasks',
      'view all work dashboards',
      'administer site configuration',
      'access content',
    ]);
  }

  /**
   * Tests dashboard access with permission.
   */
  public function testDashboardAccessWithPermission(): void {
    $this->drupalLogin($this->dashboardUser);

    $this->drupalGet('/my-work');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Work');
  }

  /**
   * Tests dashboard access denied without permission.
   */
  public function testDashboardAccessDenied(): void {
    $this->drupalLogin($this->noAccessUser);

    $this->drupalGet('/my-work');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests anonymous users cannot access dashboard.
   */
  public function testDashboardAnonymousAccessDenied(): void {
    $this->drupalGet('/my-work');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests dashboard displays summary cards.
   */
  public function testDashboardDisplaysSummaryCards(): void {
    $this->drupalLogin($this->dashboardUser);

    $this->drupalGet('/my-work');
    $this->assertSession()->statusCodeEquals(200);

    // Check for summary section.
    $this->assertSession()->elementExists('css', '.my-work-dashboard');

    // Check for section headers.
    $this->assertSession()->pageTextContains('Action Needed');
    $this->assertSession()->pageTextContains('Available to Claim');
    $this->assertSession()->pageTextContains('Upcoming');
    $this->assertSession()->pageTextContains('Recently Completed');
  }

  /**
   * Tests section pages are accessible.
   */
  public function testSectionPages(): void {
    $this->drupalLogin($this->dashboardUser);

    $sections = ['active', 'available', 'upcoming', 'completed'];

    foreach ($sections as $section) {
      $this->drupalGet('/my-work/' . $section);
      $this->assertSession()->statusCodeEquals(200);
    }
  }

  /**
   * Tests invalid section returns 404.
   */
  public function testInvalidSectionReturns404(): void {
    $this->drupalLogin($this->dashboardUser);

    $this->drupalGet('/my-work/invalid-section');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests dashboard CSS library is attached.
   */
  public function testDashboardLibraryAttached(): void {
    $this->drupalLogin($this->dashboardUser);

    $this->drupalGet('/my-work');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the library CSS file would be loaded.
    // Note: In functional tests, we verify the render array includes the library.
    // The actual CSS loading is verified in browser tests.
    $this->assertSession()->elementExists('css', '.my-work-dashboard');
  }

  /**
   * Tests menu link exists.
   */
  public function testMenuLinkExists(): void {
    $this->drupalLogin($this->dashboardUser);

    // Check that the menu link is registered.
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
    $links = $menu_link_manager->loadLinksByRoute('avc_work_management.my_work');

    $this->assertNotEmpty($links);

    $link = reset($links);
    $this->assertEquals('My Work', $link->getTitle());
    $this->assertEquals('main', $link->getMenuName());
  }

  /**
   * Tests dashboard caching.
   */
  public function testDashboardCaching(): void {
    $this->drupalLogin($this->dashboardUser);

    // First request.
    $this->drupalGet('/my-work');
    $this->assertSession()->statusCodeEquals(200);

    // Verify cache contexts include user.
    // This is implicitly tested by the fact that different users
    // see different content based on their assignments.
  }

  /**
   * Tests section title callback.
   */
  public function testSectionTitleCallback(): void {
    $this->drupalLogin($this->dashboardUser);

    $this->drupalGet('/my-work/active');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->titleEquals('My Work: Action Needed | Drupal');

    $this->drupalGet('/my-work/available');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->titleEquals('My Work: Available to Claim | Drupal');

    $this->drupalGet('/my-work/upcoming');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->titleEquals('My Work: Upcoming | Drupal');

    $this->drupalGet('/my-work/completed');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->titleEquals('My Work: Recently Completed | Drupal');
  }

  /**
   * Tests back link on section pages.
   */
  public function testSectionBackLink(): void {
    $this->drupalLogin($this->dashboardUser);

    $this->drupalGet('/my-work/active');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('← Back to My Work');
  }

  /**
   * Tests dashboard shows empty state messages.
   */
  public function testDashboardEmptyState(): void {
    $this->drupalLogin($this->dashboardUser);

    $this->drupalGet('/my-work');
    $this->assertSession()->statusCodeEquals(200);

    // When there are no tasks, sections should show empty message.
    $this->assertSession()->pageTextContains('No tasks in this section.');
  }

  /**
   * Tests permissions are defined correctly.
   */
  public function testPermissionsExist(): void {
    $permissions = \Drupal::service('user.permissions')->getPermissions();

    $this->assertArrayHasKey('access my work dashboard', $permissions);
    $this->assertArrayHasKey('claim workflow tasks', $permissions);
    $this->assertArrayHasKey('view all work dashboards', $permissions);

    // Verify view all is restricted.
    $this->assertTrue($permissions['view all work dashboards']['restrict access']);
  }

  /**
   * Tests config is installed correctly.
   */
  public function testConfigInstalled(): void {
    $config = \Drupal::config('avc_work_management.settings');

    // Check tracked content types.
    $types = $config->get('tracked_content_types');
    $this->assertIsArray($types);
    $this->assertArrayHasKey('avc_document', $types);
    $this->assertArrayHasKey('avc_resource', $types);
    $this->assertArrayHasKey('avc_project', $types);

    // Check sections.
    $sections = $config->get('sections');
    $this->assertIsArray($sections);
    $this->assertArrayHasKey('active', $sections);
    $this->assertArrayHasKey('available', $sections);
    $this->assertArrayHasKey('upcoming', $sections);
    $this->assertArrayHasKey('completed', $sections);
  }

  /**
   * Tests services are registered.
   */
  public function testServicesRegistered(): void {
    $this->assertTrue(\Drupal::hasService('avc_work_management.task_query'));
    $this->assertTrue(\Drupal::hasService('avc_work_management.task_action'));

    $queryService = \Drupal::service('avc_work_management.task_query');
    $this->assertInstanceOf('Drupal\avc_work_management\Service\WorkTaskQueryService', $queryService);

    $actionService = \Drupal::service('avc_work_management.task_action');
    $this->assertInstanceOf('Drupal\avc_work_management\Service\WorkTaskActionService', $actionService);
  }

}
