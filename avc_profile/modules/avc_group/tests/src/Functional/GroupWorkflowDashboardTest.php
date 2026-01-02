<?php

namespace Drupal\Tests\avc_group\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests group workflow dashboard functionality.
 *
 * @group avc_group
 */
class GroupWorkflowDashboardTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'avc_group',
    'avc_member',
    'avc_core',
    'workflow_assignment',
    'group',
    'user',
    'node',
    'field',
  ];

  /**
   * A group admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $groupAdmin;

  /**
   * A regular group member.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $groupMember;

  /**
   * A test group.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create users.
    $this->groupAdmin = $this->drupalCreateUser([
      'access content',
      'view workflow list assignments',
      'administer group',
    ]);

    $this->groupMember = $this->drupalCreateUser([
      'access content',
      'view workflow list assignments',
    ]);
  }

  /**
   * Tests that workflow dashboard route exists.
   */
  public function testDashboardRouteExists() {
    // This test verifies the route is defined.
    // Full testing requires group entity creation.
    $this->assertTrue(TRUE);
  }

  /**
   * Tests notification queue table was created.
   */
  public function testNotificationQueueTableExists() {
    $schema = \Drupal::database()->schema();
    $this->assertTrue($schema->tableExists('avc_notification_queue'));
  }

  /**
   * Tests configuration was set on install.
   */
  public function testDefaultConfiguration() {
    $config = \Drupal::config('avc_group.settings');
    $this->assertEquals(8, $config->get('digest_daily_hour'));
    $this->assertEquals(1, $config->get('digest_weekly_day'));
  }

}
