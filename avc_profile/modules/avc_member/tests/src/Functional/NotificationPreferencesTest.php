<?php

namespace Drupal\Tests\avc_member\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests notification preferences functionality.
 *
 * @group avc_member
 */
class NotificationPreferencesTest extends BrowserTestBase {

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

    $this->regularUser = $this->drupalCreateUser([
      'access content',
      'view workflow list assignments',
    ]);
  }

  /**
   * Tests notification preferences form is accessible.
   */
  public function testNotificationFormAccess() {
    $this->drupalLogin($this->regularUser);

    $this->drupalGet('/user/' . $this->regularUser->id() . '/notification-preferences');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Notification Preferences');
  }

  /**
   * Tests notification preferences form shows options.
   */
  public function testNotificationFormOptions() {
    $this->drupalLogin($this->regularUser);

    $this->drupalGet('/user/' . $this->regularUser->id() . '/notification-preferences');

    // Check for notification options.
    $this->assertSession()->pageTextContains('Default notification preference');
    $this->assertSession()->pageTextContains('Immediate');
    $this->assertSession()->pageTextContains('Daily digest');
    $this->assertSession()->pageTextContains('Weekly digest');
    $this->assertSession()->pageTextContains('None');
  }

  /**
   * Tests saving notification preferences.
   */
  public function testSaveNotificationPreferences() {
    $this->drupalLogin($this->regularUser);

    $this->drupalGet('/user/' . $this->regularUser->id() . '/notification-preferences');

    // Submit the form with daily digest.
    $this->submitForm([
      'default_preference' => 'd',
    ], 'Save preferences');

    $this->assertSession()->pageTextContains('Notification preferences saved');
  }

  /**
   * Tests anonymous users cannot access notification preferences.
   */
  public function testNotificationFormAnonymousAccess() {
    $this->drupalGet('/user/' . $this->regularUser->id() . '/notification-preferences');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests users can only edit their own notification preferences.
   */
  public function testNotificationFormOwnAccess() {
    $otherUser = $this->drupalCreateUser([
      'access content',
    ]);

    $this->drupalLogin($this->regularUser);

    // Try to access another user's preferences.
    $this->drupalGet('/user/' . $otherUser->id() . '/notification-preferences');
    $this->assertSession()->statusCodeEquals(403);
  }

}
