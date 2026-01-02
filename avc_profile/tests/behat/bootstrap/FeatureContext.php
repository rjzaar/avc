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
    // Create a workflow task for the current user.
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    if (!$user) {
      throw new \Exception('No user logged in');
    }

    // Create a basic node to attach the workflow task to
    $node = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Test Node for Workflow ' . rand(1000, 9999),
      'uid' => $user->uid,
    ]);
    $node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('workflow_task');
    $task = $storage->create([
      'title' => 'Test Assignment ' . rand(1000, 9999),
      'uid' => $user->uid,
      'node_id' => $node->id(),
      'assigned_user' => $user->uid,
      'status' => $status,
    ]);
    $task->save();
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
    $storage = \Drupal::entityTypeManager()->getStorage('workflow_task');

    foreach ($table->getHash() as $row) {
      // Create a basic node to attach the workflow task to
      $node = \Drupal::entityTypeManager()->getStorage('node')->create([
        'type' => 'page',
        'title' => 'Test Node for ' . $row['title'],
        'uid' => $user->uid,
      ]);
      $node->save();

      $task = $storage->create([
        'title' => $row['title'],
        'uid' => $user->uid,
        'node_id' => $node->id(),
        'assigned_user' => $user->uid,
        'status' => $row['status'],
      ]);
      $task->save();
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

  /**
   * @When I visit my dashboard
   */
  public function iVisitMyDashboard() {
    $this->minkContext->visitPath('/user');
  }

  /**
   * @When I visit the group :groupName
   */
  public function iVisitTheGroup($groupName) {
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);

    if (empty($groups)) {
      throw new \Exception(sprintf('Group "%s" not found', $groupName));
    }

    $group = reset($groups);
    $this->minkContext->visitPath('/group/' . $group->id());
  }

  /**
   * @When I visit the group workflow page for :groupName
   */
  public function iVisitTheGroupWorkflowPageFor($groupName) {
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);

    if (empty($groups)) {
      throw new \Exception(sprintf('Group "%s" not found', $groupName));
    }

    $group = reset($groups);
    $this->minkContext->visitPath('/group/' . $group->id() . '/workflow');
  }

  /**
   * @Given the group :groupName has a workflow assignment :title
   */
  public function theGroupHasAWorkflowAssignment($groupName, $title) {
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);

    if (empty($groups)) {
      throw new \Exception(sprintf('Group "%s" not found', $groupName));
    }

    $group = reset($groups);
    $user = $this->drupalContext->getUserManager()->getCurrentUser();

    // Create a node for the workflow task
    $node = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Test Node for ' . $title,
      'uid' => $user->uid,
    ]);
    $node->save();

    // Create workflow task assigned to the group
    $storage = \Drupal::entityTypeManager()->getStorage('workflow_task');
    $task = $storage->create([
      'title' => $title,
      'uid' => $user->uid,
      'node_id' => $node->id(),
      'assigned_group' => $group->id(),
      'status' => 'current',
    ]);
    $task->save();
  }

  /**
   * @Given I am a group manager of :groupName
   */
  public function iAmAGroupManagerOf($groupName) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    if (!$user) {
      throw new \Exception('No user logged in');
    }

    // Create or load the group
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

    // Add user to group as manager/admin
    $group->addMember(\Drupal\user\Entity\User::load($user->uid), ['group_roles' => ['open_group-admin']]);
  }

  /**
   * @Given I have a group assignment in :groupName with status :status
   */
  public function iHaveAGroupAssignmentInWithStatus($groupName, $status) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    if (!$user) {
      throw new \Exception('No user logged in');
    }

    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);

    if (empty($groups)) {
      throw new \Exception(sprintf('Group "%s" not found', $groupName));
    }

    $group = reset($groups);

    // Create a node for the workflow task
    $node = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Test Node for Group Assignment',
      'uid' => $user->uid,
    ]);
    $node->save();

    // Create workflow task assigned to user in the group
    $storage = \Drupal::entityTypeManager()->getStorage('workflow_task');
    $task = $storage->create([
      'title' => 'Group Assignment',
      'uid' => $user->uid,
      'node_id' => $node->id(),
      'assigned_user' => $user->uid,
      'assigned_group' => $group->id(),
      'status' => $status,
    ]);
    $task->save();
  }

  /**
   * @Then my assignment should be highlighted
   */
  public function myAssignmentShouldBeHighlighted() {
    $this->minkContext->assertSession()->elementExists('css', '.assignment-highlighted');
  }

  /**
   * @When I mark :taskTitle as completed
   */
  public function iMarkAsCompleted($taskTitle) {
    $this->minkContext->pressButton('Complete');
  }

  /**
   * @Then I should see :status status for :taskTitle
   */
  public function iShouldSeeStatusFor($status, $taskTitle) {
    $this->minkContext->assertSession()->pageTextContains($status);
  }

  /**
   * @When I click on the worklist row for :title
   */
  public function iClickOnTheWorklistRowFor($title) {
    $this->minkContext->getSession()->getPage()->find('xpath', "//tr[contains(., '$title')]")->click();
  }

  /**
   * @Then I should be on the workflow page for :title
   */
  public function iShouldBeOnTheWorkflowPageFor($title) {
    $this->minkContext->assertSession()->pageTextContains($title);
    $this->minkContext->assertSession()->pageTextContains('Workflow');
  }

  /**
   * @Given workflow is enabled for :contentType content type
   */
  public function workflowIsEnabledForContentType($contentType) {
    $config = \Drupal::configFactory()->getEditable('workflow_assignment.settings');
    $enabled_types = $config->get('enabled_content_types') ?: [];
    if (!in_array($contentType, $enabled_types)) {
      $enabled_types[] = $contentType;
      $config->set('enabled_content_types', $enabled_types)->save();
    }
  }

  /**
   * @Given a workflow list :listName exists
   */
  public function aWorkflowListExists($listName) {
    // This would create a workflow list entity if such entity exists
    // For now, we'll just note it exists
  }

}
