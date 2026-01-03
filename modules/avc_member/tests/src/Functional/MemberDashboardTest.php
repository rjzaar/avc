<?php

namespace Drupal\Tests\avc_member\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests member dashboard functionality.
 *
 * @group avc_member
 */
class MemberDashboardTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'avc_member',
    'avc_core',
    'workflow_assignment',
    'user',
    'node',
    'field',
    'taxonomy',
  ];

  /**
   * A regular authenticated user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $regularUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a regular user.
    $this->regularUser = $this->drupalCreateUser([
      'access content',
      'view workflow list assignments',
    ]);
  }

  /**
   * Tests that the dashboard page is accessible.
   */
  public function testDashboardAccess() {
    $this->drupalLogin($this->regularUser);

    // Visit the dashboard.
    $this->drupalGet('/user/' . $this->regularUser->id() . '/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Dashboard');
  }

  /**
   * Tests that anonymous users cannot access dashboard.
   */
  public function testDashboardAnonymousAccess() {
    $this->drupalGet('/user/' . $this->regularUser->id() . '/dashboard');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that users can only access their own dashboard.
   */
  public function testDashboardOwnAccess() {
    $otherUser = $this->drupalCreateUser([
      'access content',
    ]);

    $this->drupalLogin($this->regularUser);

    // Try to access another user's dashboard.
    $this->drupalGet('/user/' . $otherUser->id() . '/dashboard');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests dashboard shows empty worklist message.
   */
  public function testDashboardEmptyWorklist() {
    $this->drupalLogin($this->regularUser);

    $this->drupalGet('/user/' . $this->regularUser->id() . '/dashboard');
    $this->assertSession()->pageTextContains('My Worklist');
    $this->assertSession()->pageTextContains('No current assignments');
  }

  /**
   * Tests dashboard shows notification settings link.
   */
  public function testDashboardNotificationSettings() {
    $this->drupalLogin($this->regularUser);

    $this->drupalGet('/user/' . $this->regularUser->id() . '/dashboard');
    $this->assertSession()->pageTextContains('Notification');
  }

  /**
   * Tests member_skills vocabulary was created.
   */
  public function testMemberSkillsVocabulary() {
    $vocabulary = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_vocabulary')
      ->load('member_skills');
    $this->assertNotNull($vocabulary, 'member_skills vocabulary exists');

    // Check for default terms.
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => 'member_skills',
        'name' => 'Translation',
      ]);
    $this->assertNotEmpty($terms, 'Translation skill term exists');
  }

}
