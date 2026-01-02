<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDrupalContext implements Context {

  /**
   * The Drupal context.
   *
   * @var \Drupal\DrupalExtension\Context\DrupalContext
   */
  protected $drupalContext;

  /**
   * The Mink context.
   *
   * @var \Drupal\DrupalExtension\Context\MinkContext
   */
  protected $minkContext;

  /**
   * Initializes context.
   */
  public function __construct() {
  }

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope) {
    $environment = $scope->getEnvironment();
    $this->drupalContext = $environment->getContext('Drupal\DrupalExtension\Context\DrupalContext');
    $this->minkContext = $environment->getContext('Drupal\DrupalExtension\Context\MinkContext');
  }

  /**
   * @Given I am logged in as a member with notification preferences
   */
  public function iAmLoggedInAsAMemberWithNotificationPreferences() {
    $user = (object) [
      'name' => 'test_member_' . rand(1000, 9999),
      'mail' => 'test_member_' . rand(1000, 9999) . '@example.com',
      'pass' => 'password123',
      'status' => 1,
    ];
    $this->drupalContext->userCreate($user);
    $this->drupalContext->login($user);
  }

  /**
   * @Given I have a workflow assignment with status :status
   */
  public function iHaveAWorkflowAssignmentWithStatus($status) {
    // Create a workflow assignment for the current user.
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    if (!$user) {
      throw new \Exception('No user logged in');
    }

    $storage = \Drupal::entityTypeManager()->getStorage('workflow_assignment');
    $assignment = $storage->create([
      'label' => 'Test Assignment ' . rand(1000, 9999),
      'assigned_user' => $user->uid,
      'status' => $status,
    ]);
    $assignment->save();
  }

  /**
   * @Then I should see the member dashboard
   */
  public function iShouldSeeTheMemberDashboard() {
    $this->minkContext->assertSession()->pageTextContains('Dashboard');
  }

  /**
   * @Then I should see my worklist
   */
  public function iShouldSeeMyWorklist() {
    $this->minkContext->assertSession()->elementExists('css', '.avc-member-worklist-block, .avc-worklist-table');
  }

  /**
   * @Then I should see notification settings
   */
  public function iShouldSeeNotificationSettings() {
    $this->minkContext->assertSession()->pageTextContains('Notification');
  }

  /**
   * @When I set my notification preference to :preference
   */
  public function iSetMyNotificationPreferenceTo($preference) {
    $map = [
      'immediate' => 'n',
      'daily' => 'd',
      'weekly' => 'w',
      'none' => 'x',
    ];
    $value = $map[$preference] ?? $preference;
    $this->minkContext->selectOption('Default notification preference', $value);
  }

  /**
   * @When I save my notification preferences
   */
  public function iSaveMyNotificationPreferences() {
    $this->minkContext->pressButton('Save preferences');
  }

  /**
   * @Then I should see a success message
   */
  public function iShouldSeeASuccessMessage() {
    $this->minkContext->assertSession()->elementExists('css', '.messages--status');
  }

  /**
   * @Given I am a member of a group :groupName
   */
  public function iAmAMemberOfAGroup($groupName) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    if (!$user) {
      throw new \Exception('No user logged in');
    }

    // Create or load the group.
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);

    if (empty($groups)) {
      $group = \Drupal::entityTypeManager()
        ->getStorage('group')
        ->create([
          'type' => 'open_group',
          'label' => $groupName,
        ]);
      $group->save();
    }
    else {
      $group = reset($groups);
    }

    // Add user to group.
    $group->addMember(\Drupal\user\Entity\User::load($user->uid));
  }

  /**
   * @Then I should see the group :groupName in my dashboard
   */
  public function iShouldSeeTheGroupInMyDashboard($groupName) {
    $this->minkContext->assertSession()->pageTextContains($groupName);
  }

  /**
   * @Then the worklist item should show :status status
   */
  public function theWorklistItemShouldShowStatus($status) {
    $this->minkContext->assertSession()->elementExists('css', '.status-' . strtolower($status));
  }

  /**
   * @Given the following workflow assignments exist:
   */
  public function theFollowingWorkflowAssignmentsExist(TableNode $table) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('workflow_assignment');

    foreach ($table->getHash() as $row) {
      $assignment = $storage->create([
        'label' => $row['title'],
        'assigned_user' => $user->uid,
        'status' => $row['status'],
      ]);
      $assignment->save();
    }
  }

  /**
   * @Then I should see :count worklist items
   */
  public function iShouldSeeWorklistItems($count) {
    $elements = $this->minkContext->getSession()->getPage()->findAll('css', '.worklist-item');
    if (count($elements) != $count) {
      throw new \Exception(sprintf('Expected %d worklist items, found %d', $count, count($elements)));
    }
  }

}
