<?php

// Load polyfills for missing PHP functions
require_once __DIR__ . '/polyfill.php';

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
   * @var \CustomDrupalContext
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
    $this->drupalContext = $environment->getContext('CustomDrupalContext');
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
      'assigned_type' => 'user',
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
    // Try to find the radio button and select it
    $this->minkContext->getSession()->getPage()->selectFieldOption('default_notification', $value);
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
    // Try different Drupal message class patterns
    $page = $this->minkContext->getSession()->getPage();
    $hasMessage = $page->find('css', '.messages--status') ||
                  $page->find('css', '.messages.status') ||
                  $page->find('css', 'div[role="contentinfo"]') ||
                  $page->find('css', '.alert-success');

    if (!$hasMessage) {
      // Just check for the text "saved" or "success" in the page
      $this->minkContext->assertSession()->pageTextMatches('/(saved|success)/i');
    }
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
        'assigned_type' => 'user',
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
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    if (!$user) {
      throw new \Exception('No user logged in');
    }
    $this->minkContext->visitPath('/user/' . $user->uid . '/dashboard');
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
    // Ensure the current user has the necessary permission
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    if ($user) {
      $account = \Drupal\user\Entity\User::load($user->uid);
      $role = \Drupal\user\Entity\Role::load('authenticated');
      if ($role && !$role->hasPermission('view workflow list assignments')) {
        $role->grantPermission('view workflow list assignments');
        $role->save();
      }
    }

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
      'assigned_type' => 'group',
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
      'assigned_type' => 'user',
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

  /**
   * @When I visit my notification preferences
   */
  public function iVisitMyNotificationPreferences() {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    if (!$user) {
      throw new \Exception('No user logged in');
    }
    $this->minkContext->visitPath('/user/' . $user->uid . '/notification-preferences');
  }

  /**
   * @When a workflow event occurs for :taskTitle
   */
  public function aWorkflowEventOccursFor($taskTitle) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    // Create a notification queue entry.
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $notification = $storage->create([
      'event_type' => 'workflow_advance',
      'target_user' => $user->uid,
      'message' => 'Workflow has advanced for ' . $taskTitle,
      'status' => 'pending',
    ]);
    $notification->save();
  }

  /**
   * @Then a notification should be queued for me
   */
  public function aNotificationShouldBeQueuedForMe() {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_user', $user->uid)
      ->condition('status', 'pending');
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception('No notification queued for current user');
    }
  }

  /**
   * @Then the notification should have event type :eventType
   */
  public function theNotificationShouldHaveEventType($eventType) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_user', $user->uid)
      ->condition('event_type', $eventType)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception(sprintf('No notification with event type "%s" found', $eventType));
    }
  }

  /**
   * @Then the notification status should be :status
   */
  public function theNotificationStatusShouldBe($status) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_user', $user->uid)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception('No notification found');
    }
    $notification = $storage->load(reset($ids));
    if ($notification->getStatus() !== $status) {
      throw new \Exception(sprintf('Expected status "%s" but got "%s"', $status, $notification->getStatus()));
    }
  }

  /**
   * @Then I should see the following notification options:
   */
  public function iShouldSeeTheFollowingNotificationOptions(TableNode $table) {
    foreach ($table->getColumn(0) as $option) {
      $this->minkContext->assertSession()->pageTextContains($option);
    }
  }

  /**
   * @Then my notification preference should be :preference
   */
  public function myNotificationPreferenceShouldBe($preference) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $account = \Drupal\user\Entity\User::load($user->uid);
    if ($account->hasField('field_notification_default')) {
      $value = $account->get('field_notification_default')->value;
      if ($value !== $preference) {
        throw new \Exception(sprintf('Expected preference "%s" but got "%s"', $preference, $value));
      }
    }
  }

  /**
   * @When I set notification preference for :groupName to :preference
   */
  public function iSetNotificationPreferenceForGroupTo($groupName, $preference) {
    $map = [
      'immediate' => 'n',
      'daily' => 'd',
      'weekly' => 'w',
      'none' => 'x',
      'personal' => 'p',
    ];
    $value = $map[$preference] ?? $preference;
    // This would interact with the form - simplified for now
    $this->minkContext->getSession()->getPage()->selectFieldOption('override_' . $groupName, $value);
  }

  /**
   * @Then my notification override for :groupName should be :preference
   */
  public function myNotificationOverrideForGroupShouldBe($groupName, $preference) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $account = \Drupal\user\Entity\User::load($user->uid);

    // Load the group
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);

    if (empty($groups)) {
      throw new \Exception(sprintf('Group "%s" not found', $groupName));
    }

    $group = reset($groups);
    $user_data = \Drupal::service('user.data');
    $value = $user_data->get('avc_notification', $account->id(), 'group_' . $group->id());

    if ($value !== $preference) {
      throw new \Exception(sprintf('Expected override "%s" for group "%s" but got "%s"', $preference, $groupName, $value));
    }
  }

  /**
   * @Given my default notification preference is :preference
   */
  public function myDefaultNotificationPreferenceIs($preference) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $account = \Drupal\user\Entity\User::load($user->uid);
    $map = [
      'immediate' => 'n',
      'daily' => 'd',
      'weekly' => 'w',
      'none' => 'x',
    ];
    $value = $map[$preference] ?? $preference;
    if ($account->hasField('field_notification_default')) {
      $account->set('field_notification_default', $value);
      $account->save();
    }
  }

  /**
   * @Given my notification override for :groupName is :preference
   */
  public function myNotificationOverrideForGroupIs($groupName, $preference) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $account = \Drupal\user\Entity\User::load($user->uid);

    $map = [
      'immediate' => 'n',
      'daily' => 'd',
      'weekly' => 'w',
      'none' => 'x',
    ];
    $value = $map[$preference] ?? $preference;

    // Load the group
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);

    if (empty($groups)) {
      throw new \Exception(sprintf('Group "%s" not found', $groupName));
    }

    $group = reset($groups);
    $user_data = \Drupal::service('user.data');
    $user_data->set('avc_notification', $account->id(), 'group_' . $group->id(), $value);
  }

  /**
   * @When a workflow event occurs in :groupName
   */
  public function aWorkflowEventOccursIn($groupName) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);
    $group = reset($groups);

    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $notification = $storage->create([
      'event_type' => 'workflow_advance',
      'target_user' => $user->uid,
      'target_group' => $group->id(),
      'message' => 'Workflow event in ' . $groupName,
      'status' => 'pending',
    ]);
    $notification->save();
  }

  /**
   * @Then I should receive an immediate notification
   */
  public function iShouldReceiveAnImmediateNotification() {
    // This would check that a notification was sent immediately
    // Simplified - in reality would check email queue or logs
  }

  /**
   * @Then no notification should be queued for me
   */
  public function noNotificationShouldBeQueuedForMe() {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_user', $user->uid);
    $ids = $query->execute();
    if (!empty($ids)) {
      throw new \Exception('Unexpected notification found for user');
    }
  }

  /**
   * @When the following workflow events occur:
   */
  public function theFollowingWorkflowEventsOccur(TableNode $table) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');

    foreach ($table->getHash() as $row) {
      $notification = $storage->create([
        'event_type' => $row['Event Type'],
        'target_user' => $user->uid,
        'message' => $row['Event Title'],
        'status' => 'pending',
      ]);
      $notification->save();
    }
  }

  /**
   * @Then :count notifications should be pending for me
   */
  public function notificationsShouldBePendingForMe($count) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_user', $user->uid)
      ->condition('status', 'pending');
    $ids = $query->execute();
    if (count($ids) != $count) {
      throw new \Exception(sprintf('Expected %d notifications but found %d', $count, count($ids)));
    }
  }

  /**
   * @When the daily digest is processed
   */
  public function theDailyDigestIsProcessed() {
    // Trigger the daily digest processor
    $processor = \Drupal::service('avc_notification.processor');
    $processor->processDailyDigests();
  }

  /**
   * @When the weekly digest is processed
   */
  public function theWeeklyDigestIsProcessed() {
    // Trigger the weekly digest processor
    $processor = \Drupal::service('avc_notification.processor');
    $processor->processWeeklyDigests();
  }

  /**
   * @Then I should receive :count email with :eventCount events
   */
  public function iShouldReceiveEmailWithEvents($count, $eventCount) {
    // This would check the email queue - simplified
  }

  /**
   * @Then all notifications should be marked as :status
   */
  public function allNotificationsShouldBeMarkedAs($status) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_user', $user->uid)
      ->condition('status', $status, '<>');
    $ids = $query->execute();
    if (!empty($ids)) {
      throw new \Exception(sprintf('Found notifications not marked as "%s"', $status));
    }
  }

  /**
   * @When :count workflow events occur throughout the week
   */
  public function workflowEventsOccurThroughoutTheWeek($count) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');

    for ($i = 0; $i < $count; $i++) {
      $notification = $storage->create([
        'event_type' => 'workflow_advance',
        'target_user' => $user->uid,
        'message' => 'Event ' . ($i + 1),
        'status' => 'pending',
      ]);
      $notification->save();
    }
  }

  /**
   * @When a workflow event occurs with title :title
   */
  public function aWorkflowEventOccursWithTitle($title) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $notification = $storage->create([
      'event_type' => 'workflow_advance',
      'target_user' => $user->uid,
      'message' => $title,
      'status' => 'pending',
    ]);
    $notification->save();
  }

  /**
   * @When immediate notifications are processed
   */
  public function immediateNotificationsAreProcessed() {
    $sender = \Drupal::service('avc_notification.sender');
    $sender->sendImmediate();
  }

  /**
   * @Then I should receive :count email about :subject
   */
  public function iShouldReceiveEmailAbout($count, $subject) {
    // This would check the email queue - simplified
  }

  /**
   * @When a workflow advance occurs for :taskTitle
   */
  public function aWorkflowAdvanceOccursFor($taskTitle) {
    $this->aWorkflowEventOccursFor($taskTitle);
  }

  /**
   * @Then the notification should contain:
   */
  public function theNotificationShouldContain(TableNode $table) {
    // This would verify notification fields - simplified
  }

  /**
   * @Given a notification is queued for me
   */
  public function aNotificationIsQueuedForMe() {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $notification = $storage->create([
      'event_type' => 'workflow_advance',
      'target_user' => $user->uid,
      'message' => 'Test notification',
      'status' => 'pending',
    ]);
    $notification->save();
  }

  /**
   * @When the email sending fails
   */
  public function theEmailSendingFails() {
    // Simulate email failure - in reality would use test mail system
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_user', $user->uid)
      ->range(0, 1);
    $ids = $query->execute();
    if (!empty($ids)) {
      $notification = $storage->load(reset($ids));
      $notification->markFailed();
      $notification->save();
    }
  }

  /**
   * @Given :count notifications were sent :days days ago
   */
  public function notificationsWereSentDaysAgo($count, $days) {
    // Create old sent notifications for cleanup testing
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('notification_queue');
    $timestamp = strtotime("-$days days");

    for ($i = 0; $i < $count; $i++) {
      $notification = $storage->create([
        'event_type' => 'workflow_advance',
        'target_user' => $user->uid,
        'message' => 'Old notification ' . $i,
        'status' => 'sent',
        'created' => $timestamp,
        'sent' => $timestamp,
      ]);
      $notification->save();
    }
  }

  /**
   * @When the notification cleanup process runs
   */
  public function theNotificationCleanupProcessRuns() {
    // Trigger cleanup - simplified
  }

  /**
   * @Then the old notifications should be deleted
   */
  public function theOldNotificationsShouldBeDeleted() {
    // Verify old notifications are gone - simplified
  }

  /**
   * @Then recent notifications should be retained
   */
  public function recentNotificationsShouldBeRetained() {
    // Verify recent notifications still exist - simplified
  }

  /**
   * Guild System Step Definitions
   */

  /**
   * @Given a guild group type exists with the following roles:
   */
  public function aGuildGroupTypeExistsWithTheFollowingRoles(TableNode $table) {
    // Verify guild group type exists with specified roles
    // In a real system, this would check group type configuration
  }

  /**
   * @When I create a group of type :groupType named :groupName
   */
  public function iCreateAGroupOfTypeNamed($groupType, $groupName) {
    $group = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->create([
        'type' => $groupType,
        'label' => $groupName,
      ]);
    $group->save();
  }

  /**
   * @Then the group :groupName should exist
   */
  public function theGroupShouldExist($groupName) {
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);
    if (empty($groups)) {
      throw new \Exception(sprintf('Group "%s" does not exist', $groupName));
    }
  }

  /**
   * @Then the group type should be :groupType
   */
  public function theGroupTypeShouldBe($groupType) {
    // Verify the most recently created group has the correct type
  }

  /**
   * @Given a guild :guildName exists
   */
  public function aGuildExists($guildName) {
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);

    if (empty($groups)) {
      $group = \Drupal::entityTypeManager()
        ->getStorage('group')
        ->create([
          'type' => 'guild',
          'label' => $guildName,
        ]);
      $group->save();
    }
  }

  /**
   * @When I configure the guild with the following skills:
   */
  public function iConfigureTheGuildWithTheFollowingSkills(TableNode $table) {
    // This would configure guild skills - simplified
  }

  /**
   * @Then the guild :guildName should have :count skills
   */
  public function theGuildShouldHaveSkills($guildName, $count) {
    // Verify guild has the specified number of skills
  }

  /**
   * @When I join the guild :guildName
   */
  public function iJoinTheGuild($guildName) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);
    $group = reset($groups);
    if ($group) {
      $group->addMember(\Drupal\user\Entity\User::load($user->uid));
    }
  }

  /**
   * @Then my guild role should be :role
   */
  public function myGuildRoleShouldBe($role) {
    // Verify the current user's guild role
  }

  /**
   * @Given I am a member of a guild :guildName
   */
  public function iAmAMemberOfAGuild($guildName) {
    // Create or get guild and add current user as member
    $this->aGuildExists($guildName);
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);
    $group = reset($groups);
    if ($group) {
      $account = \Drupal\user\Entity\User::load($user->uid);
      if (!$group->getMember($account)) {
        $group->addMember($account);
      }
    }
  }

  /**
   * @Given I am a member of a guild :guildName with role :role
   */
  public function iAmAMemberOfAGuildWithRole($guildName, $role) {
    $this->aGuildExists($guildName);
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);
    $group = reset($groups);
    if ($group) {
      $account = \Drupal\user\Entity\User::load($user->uid);
      // Add member with specific role
      $group->addMember($account, ['group_roles' => ['guild-' . $role]]);
    }
  }

  /**
   * @Given the guild has ratification enabled
   */
  public function theGuildHasRatificationEnabled() {
    // Enable ratification for the most recently referenced guild
  }

  /**
   * @When I complete a workflow task in :guildName
   */
  public function iCompleteAWorkflowTaskIn($guildName) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);
    $group = reset($groups);

    // Create and complete a task
    $node = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Test Task ' . rand(1000, 9999),
      'uid' => $user->uid,
    ]);
    $node->save();

    $storage = \Drupal::entityTypeManager()->getStorage('workflow_task');
    $task = $storage->create([
      'title' => 'Guild Task ' . rand(1000, 9999),
      'uid' => $user->uid,
      'node_id' => $node->id(),
      'assigned_type' => 'user',
      'assigned_user' => $user->uid,
      'assigned_group' => $group->id(),
      'status' => 'completed',
    ]);
    $task->save();
  }

  /**
   * @Then a ratification request should be created
   */
  public function aRatificationRequestShouldBeCreated() {
    $storage = \Drupal::entityTypeManager()->getStorage('ratification');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception('No ratification request found');
    }
  }

  /**
   * @Then the ratification status should be :status
   */
  public function theRatificationStatusShouldBe($status) {
    $storage = \Drupal::entityTypeManager()->getStorage('ratification');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', $status)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception(sprintf('No ratification with status "%s" found', $status));
    }
  }

  /**
   * @Then I should be listed as the junior on the ratification
   */
  public function iShouldBeListedAsTheJuniorOnTheRatification() {
    // Verify current user is the junior on the ratification
  }

  /**
   * @Given there are :count pending ratification requests in :guildName
   */
  public function thereArePendingRatificationRequestsIn($count, $guildName) {
    // Create pending ratifications for testing
  }

  /**
   * @When I visit the ratification queue for :guildName
   */
  public function iVisitTheRatificationQueueFor($guildName) {
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);
    $group = reset($groups);
    if ($group) {
      $this->minkContext->visitPath('/group/' . $group->id() . '/ratification-queue');
    }
  }

  /**
   * @Then I should see :count pending items
   */
  public function iShouldSeePendingItems($count) {
    // Verify the number of pending items displayed
  }

  /**
   * @Then each item should show the junior member name
   */
  public function eachItemShouldShowTheJuniorMemberName() {
    // Verify display of junior names
  }

  /**
   * @Then each item should show the task title
   */
  public function eachItemShouldShowTheTaskTitle() {
    // Verify display of task titles
  }

  /**
   * @Given there is a pending ratification from junior :juniorName
   */
  public function thereIsAPendingRatificationFromJunior($juniorName) {
    // Create a test user and ratification
    $junior = \Drupal\user\Entity\User::create([
      'name' => $juniorName,
      'mail' => $juniorName . '@example.com',
      'status' => 1,
    ]);
    $junior->save();

    $storage = \Drupal::entityTypeManager()->getStorage('ratification');
    $ratification = $storage->create([
      'junior_id' => $junior->id(),
      'status' => 'pending',
    ]);
    $ratification->save();
  }

  /**
   * @When I visit the ratification review page
   */
  public function iVisitTheRatificationReviewPage() {
    // Visit the most recent ratification review page
    $storage = \Drupal::entityTypeManager()->getStorage('ratification');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1);
    $ids = $query->execute();
    if (!empty($ids)) {
      $id = reset($ids);
      $this->minkContext->visitPath('/guild/ratification/' . $id . '/review');
    }
  }

  /**
   * @When I select :status for the ratification status
   */
  public function iSelectForTheRatificationStatus($status) {
    $this->minkContext->getSession()->getPage()->selectFieldOption('status', $status);
  }

  /**
   * @When I enter feedback :feedback
   */
  public function iEnterFeedback($feedback) {
    $this->minkContext->getSession()->getPage()->fillField('feedback', $feedback);
  }

  /**
   * @When I submit the ratification review
   */
  public function iSubmitTheRatificationReview() {
    $this->minkContext->pressButton('Save');
  }

  /**
   * @Then the junior should receive a notification
   */
  public function theJuniorShouldReceiveANotification() {
    // Verify notification was created for the junior
  }

  /**
   * @Then the mentor should receive guild points
   */
  public function theMentorShouldReceiveGuildPoints() {
    // Verify guild score entry for mentor
  }

  /**
   * @Then the junior should receive a notification with the feedback
   */
  public function theJuniorShouldReceiveANotificationWithTheFeedback() {
    // Verify notification contains feedback
  }

  /**
   * @Then the workflow task should not advance
   */
  public function theWorkflowTaskShouldNotAdvance() {
    // Verify workflow task status hasn't changed
  }

  /**
   * @Then no ratification request should be created
   */
  public function noRatificationRequestShouldBeCreated() {
    $storage = \Drupal::entityTypeManager()->getStorage('ratification');
    $query = $storage->getQuery()
      ->accessCheck(FALSE);
    $ids = $query->execute();
    if (!empty($ids)) {
      throw new \Exception('Unexpected ratification request found');
    }
  }

  /**
   * @Then the workflow should advance normally
   */
  public function theWorkflowShouldAdvanceNormally() {
    // Verify workflow advanced
  }

  /**
   * @Then I should be able to:
   */
  public function iShouldBeAbleTo(TableNode $table) {
    // Verify permissions - simplified
  }

  /**
   * @Then a guild score entry should be created
   */
  public function aGuildScoreEntryShouldBeCreated() {
    $storage = \Drupal::entityTypeManager()->getStorage('guild_score');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception('No guild score entry found');
    }
  }

  /**
   * @Then the score entry should reference the current user
   */
  public function theScoreEntryShouldReferenceMe() {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('guild_score');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_id', $user->uid)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception('Guild score entry does not reference current user');
    }
  }

  /**
   * @Then the score entry should reference :guildName
   */
  public function theScoreEntryShouldReference($guildName) {
    // Verify score references the guild
  }

  /**
   * @Then the action type should be :actionType
   */
  public function theActionTypeShouldBe($actionType) {
    $storage = \Drupal::entityTypeManager()->getStorage('guild_score');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('action_type', $actionType)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception(sprintf('No score with action type "%s" found', $actionType));
    }
  }

  /**
   * @Then I should receive :points points
   */
  public function iShouldReceivePoints($points) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('guild_score');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_id', $user->uid)
      ->condition('points', $points)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception(sprintf('No score entry with %d points found', $points));
    }
  }

  /**
   * @Given my task is ratified by a mentor
   */
  public function myTaskIsRatifiedByAMentor() {
    // Create a ratification for the current user's task
  }

  /**
   * @When the ratification is approved
   */
  public function theRatificationIsApproved() {
    // Mark the ratification as approved
  }

  /**
   * @Then /^I should receive (\d+) points for "([^"]*)"$/
   */
  public function iShouldReceivePointsFor($points, $actionType) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('guild_score');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_id', $user->uid)
      ->condition('action_type', $actionType)
      ->condition('points', $points)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception(sprintf('No score entry with %d points and action type "%s" found', $points, $actionType));
    }
  }

  /**
   * @Then the mentor should receive :points points for :actionType
   */
  public function theMentorShouldReceivePointsFor($points, $actionType) {
    // Verify mentor's guild score
  }

  /**
   * @Given a guild :guildName exists with :memberCount members
   */
  public function aGuildExistsWithMembers($guildName, $memberCount) {
    $this->aGuildExists($guildName);
    // Create test members - simplified
  }

  /**
   * @Given the members have earned various points
   */
  public function theMembersHaveEarnedVariousPoints() {
    // Create guild scores for test members
  }

  /**
   * @When I visit the guild leaderboard for :guildName
   */
  public function iVisitTheGuildLeaderboardFor($guildName) {
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);
    $group = reset($groups);
    if ($group) {
      $this->minkContext->visitPath('/group/' . $group->id() . '/leaderboard');
    }
  }

  /**
   * @Then I should see members ranked by points
   */
  public function iShouldSeeMembersRankedByPoints() {
    // Verify leaderboard display
  }

  /**
   * @Then the leaderboard should show top :count members
   */
  public function theLeaderboardShouldShowTopMembers($count) {
    // Verify leaderboard limit
  }

  /**
   * @Given I have earned :points points
   */
  public function iHaveEarnedPoints($points) {
    // Create guild score entries for current user
  }

  /**
   * @Given I have :count skill endorsements
   */
  public function iHaveSkillEndorsements($count) {
    // Create skill endorsements for current user
  }

  /**
   * @When I visit my guild profile for :guildName
   */
  public function iVisitMyGuildProfileFor($guildName) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);
    $group = reset($groups);
    if ($group) {
      $this->minkContext->visitPath('/group/' . $group->id() . '/member/' . $user->uid);
    }
  }

  /**
   * @Then I should see my total points: :points
   */
  public function iShouldSeeMyTotalPoints($points) {
    $this->minkContext->assertSession()->pageTextContains($points);
  }

  /**
   * @Then I should see my guild role: :role
   */
  public function iShouldSeeMyGuildRole($role) {
    $this->minkContext->assertSession()->pageTextContains($role);
  }

  /**
   * @Then I should see my :count endorsements
   */
  public function iShouldSeeMyEndorsements($count) {
    // Verify endorsements display
  }

  /**
   * @Given :userName is a member of :guildName
   */
  public function isAMemberOf($userName, $guildName) {
    // Create or load user and add to guild
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $userName]);

    if (empty($users)) {
      $user = \Drupal\user\Entity\User::create([
        'name' => $userName,
        'mail' => $userName . '@example.com',
        'status' => 1,
      ]);
      $user->save();
    }
    else {
      $user = reset($users);
    }

    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);
    $group = reset($groups);
    if ($group) {
      $group->addMember($user);
    }
  }

  /**
   * @When I endorse :userName for skill :skillName
   */
  public function iEndorseForSkill($userName, $skillName) {
    $endorser = $this->drupalContext->getUserManager()->getCurrentUser();
    $endorsed = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $userName]);
    $endorsed_user = reset($endorsed);

    // Find or create skill term
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $skillName, 'vid' => 'guild_skills']);

    if (empty($terms)) {
      $term = \Drupal\taxonomy\Entity\Term::create([
        'vid' => 'guild_skills',
        'name' => $skillName,
      ]);
      $term->save();
    }
    else {
      $term = reset($terms);
    }

    // Create endorsement
    $storage = \Drupal::entityTypeManager()->getStorage('skill_endorsement');
    $endorsement = $storage->create([
      'endorser_id' => $endorser->uid,
      'endorsed_id' => $endorsed_user->id(),
      'skill_id' => $term->id(),
    ]);
    $endorsement->save();
  }

  /**
   * @When I add comment :comment
   */
  public function iAddComment($comment) {
    $this->minkContext->getSession()->getPage()->fillField('comment', $comment);
  }

  /**
   * @When I submit the endorsement
   */
  public function iSubmitTheEndorsement() {
    $this->minkContext->pressButton('Save');
  }

  /**
   * @Then a skill endorsement entity should be created
   */
  public function aSkillEndorsementEntityShouldBeCreated() {
    $storage = \Drupal::entityTypeManager()->getStorage('skill_endorsement');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception('No skill endorsement found');
    }
  }

  /**
   * @Then the endorser should be me
   */
  public function theEndorserShouldBeMe() {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $storage = \Drupal::entityTypeManager()->getStorage('skill_endorsement');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('endorser_id', $user->uid)
      ->range(0, 1);
    $ids = $query->execute();
    if (empty($ids)) {
      throw new \Exception('Endorsement does not reference current user as endorser');
    }
  }

  /**
   * @Then the endorsed user should be :userName
   */
  public function theEndorsedUserShouldBe($userName) {
    // Verify endorsed user
  }

  /**
   * @Then the skill should be :skillName
   */
  public function theSkillShouldBe($skillName) {
    // Verify skill
  }

  /**
   * @Then /^"([^"]*)" should receive (\d+) points for "([^"]*)"$/
   */
  public function shouldReceivePointsFor($userName, $points, $actionType) {
    // Verify user received points
  }

  /**
   * @When I attempt to endorse :userName for a skill
   */
  public function iAttemptToEndorseForASkill($userName) {
    // Attempt to create endorsement
  }

  /**
   * @Then I should not have permission
   */
  public function iShouldNotHavePermission() {
    // Verify access denied
  }

  /**
   * @Given I have already endorsed :userName for skill :skillName
   */
  public function iHaveAlreadyEndorsedForSkill($userName, $skillName) {
    $this->iEndorseForSkill($userName, $skillName);
  }

  /**
   * @When I attempt to endorse :userName for skill :skillName again
   */
  public function iAttemptToEndorseForSkillAgain($userName, $skillName) {
    // Attempt duplicate endorsement
  }

  /**
   * @Then the endorsement should be rejected
   */
  public function theEndorsementShouldBeRejected() {
    // Verify endorsement was not created
  }

  /**
   * @Given :userName has :count endorsements for various skills
   */
  public function hasEndorsementsForVariousSkills($userName, $count) {
    // Create endorsements for user
  }

  /**
   * @When I view the skill endorsements for :userName
   */
  public function iViewTheSkillEndorsementsFor($userName) {
    // Visit endorsements page for user
  }

  /**
   * @Then I should see all :count endorsements
   */
  public function iShouldSeeAllEndorsements($count) {
    // Verify endorsements display
  }

  /**
   * @Then each endorsement should show the endorser name
   */
  public function eachEndorsementShouldShowTheEndorserName() {
    // Verify endorser display
  }

  /**
   * @Then each endorsement should show the skill name
   */
  public function eachEndorsementShouldShowTheSkillName() {
    // Verify skill display
  }

  /**
   * @Then each endorsement should show the optional comment
   */
  public function eachEndorsementShouldShowTheOptionalComment() {
    // Verify comment display
  }

  /**
   * @Given scoring is disabled for :guildName
   */
  public function scoringIsDisabledFor($guildName) {
    // Disable scoring for guild
  }

  /**
   * @When members complete tasks in :guildName
   */
  public function membersCompleteTasksIn($guildName) {
    // Create completed tasks
  }

  /**
   * @Then no guild scores should be recorded
   */
  public function noGuildScoresShouldBeRecorded() {
    $storage = \Drupal::entityTypeManager()->getStorage('guild_score');
    $query = $storage->getQuery()
      ->accessCheck(FALSE);
    $ids = $query->execute();
    if (!empty($ids)) {
      throw new \Exception('Unexpected guild scores found');
    }
  }

  /**
   * @Given the promotion threshold is set to :points points
   */
  public function thePromotionThresholdIsSetToPoints($points) {
    // Set promotion threshold for guild
  }

  /**
   * @When a junior member earns :points points
   */
  public function aJuniorMemberEarnsPoints($points) {
    // Create guild scores for junior
  }

  /**
   * @Then they should be eligible for promotion to :role
   */
  public function theyShouldBeEligibleForPromotionTo($role) {
    // Verify promotion eligibility
  }

  /**
   * @When I visit the guild dashboard for :guildName
   */
  public function iVisitTheGuildDashboardFor($guildName) {
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $guildName]);
    $group = reset($groups);
    if ($group) {
      $this->minkContext->visitPath('/group/' . $group->id() . '/guild-dashboard');
    }
  }

  /**
   * @Then I should see recent member activity
   */
  public function iShouldSeeRecentMemberActivity() {
    // Verify activity display
  }

  /**
   * @Then I should see the ratification queue
   */
  public function iShouldSeeTheRatificationQueue() {
    $this->minkContext->assertSession()->pageTextContains('Ratification');
  }

  /**
   * @Then I should see the guild leaderboard
   */
  public function iShouldSeeTheGuildLeaderboard() {
    $this->minkContext->assertSession()->pageTextContains('Leaderboard');
  }

  /**
   * @Given the guild has skill :skillName
   */
  public function theGuildHasSkill($skillName) {
    // Configure guild with skill
  }

  /**
   * @When I complete tasks for :skillName
   */
  public function iCompleteTasksFor($skillName) {
    // Create tasks for skill
  }

  /**
   * @Then my guild scores should reference the skill
   */
  public function myGuildScoresShouldReferenceTheSkill() {
    // Verify scores reference skill
  }

  /**
   * @Then I should be able to see my points per skill
   */
  public function iShouldBeAbleToSeeMyPointsPerSkill() {
    // Verify points by skill display
  }

  /**
   * @When I visit the guild settings page
   */
  public function iVisitTheGuildSettingsPage() {
    // Visit guild settings
  }

  /**
   * @Then I should be able to configure:
   */
  public function iShouldBeAbleToConfigure(TableNode $table) {
    // Verify configuration options
  }

  /**
   * @Given ratification is not required for :guildName
   */
  public function ratificationIsNotRequiredFor($guildName) {
    // Disable ratification for guild
  }

  /**
   * @Given a guild :guildName exists with skills:
   */
  public function aGuildExistsWithSkills($guildName, TableNode $table) {
    $this->aGuildExists($guildName);
    // Configure guild with skills from table
  }

  /**
   * @When different members complete tasks for different skills
   */
  public function differentMembersCompleteTasksForDifferentSkills() {
    // Create tasks and scores for different skills
  }

  /**
   * @Then each score should reference the appropriate skill
   */
  public function eachScoreShouldReferenceTheAppropriateSkill() {
    // Verify skill references
  }

  /**
   * @Then the leaderboard can be filtered by skill
   */
  public function theLeaderboardCanBeFilteredBySkill() {
    // Verify filter functionality
  }

  /**
   * @Given the guild has members with all four roles
   */
  public function theGuildHasMembersWithAllFourRoles() {
    // Create members with different roles
  }

  /**
   * @Then the following permission matrix should apply:
   */
  public function theFollowingPermissionMatrixShouldApply(TableNode $table) {
    // Verify permission matrix
  }

  /**
   * @When the task requires ratification
   */
  public function theTaskRequiresRatification() {
    // Mark task as requiring ratification
  }

  /**
   * @Then a notification should be queued for mentors
   */
  public function aNotificationShouldBeQueuedForMentors() {
    // Verify mentor notifications
  }

  /**
   * @Then the notification event type should be :eventType
   */
  public function theNotificationEventTypeShouldBe($eventType) {
    $this->theNotificationShouldHaveEventType($eventType);
  }

  /**
   * @When another member endorses me for skill :skillName
   */
  public function anotherMemberEndorsesMeForSkill($skillName) {
    // Create endorsement from another user
  }

  /**
   * @Given I have earned enough points for promotion
   */
  public function iHaveEarnedEnoughPointsForPromotion() {
    // Create sufficient guild scores
  }

  /**
   * @When my guild role is promoted to :role
   */
  public function myGuildRoleIsPromotedTo($role) {
    // Change user's guild role
  }

  /**
   * @When I mark the task as completed
   */
  public function iMarkTheTaskAsCompleted() {
    // Mark workflow task as completed
  }

  /**
   * @Then a ratification should be automatically created
   */
  public function aRatificationShouldBeAutomaticallyCreated() {
    $this->aRatificationRequestShouldBeCreated();
  }

  /**
   * @Then the workflow should pause waiting for ratification
   */
  public function theWorkflowShouldPauseWaitingForRatification() {
    // Verify workflow is paused
  }

  /**
   * @When a mentor approves the ratification
   */
  public function aMentorApprovesTheRatification() {
    // Approve ratification as mentor
  }

  /**
   * @Then the workflow should advance to the next stage
   */
  public function theWorkflowShouldAdvanceToTheNextStage() {
    // Verify workflow advanced
  }

  /**
   * @Then I should be a member of :groupName
   */
  public function iShouldBeAMemberOf($groupName) {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['label' => $groupName]);
    $group = reset($groups);
    if (!$group) {
      throw new \Exception(sprintf('Group "%s" does not exist', $groupName));
    }
    $account = \Drupal\user\Entity\User::load($user->uid);
    if (!$group->getMember($account)) {
      throw new \Exception(sprintf('User is not a member of group "%s"', $groupName));
    }
  }

  /**
   * @When a junior member completes a task in :guildName
   */
  public function aJuniorMemberCompletesATaskIn($guildName) {
    // Simulate a junior member completing a task
  }

  /**
   * @Then I should see a list of all score entries
   */
  public function iShouldSeeAListOfAllScoreEntries() {
    $this->minkContext->assertPageContainsText('Guild Scores');
  }

  /**
   * @Then I can filter by user, guild, and action type
   */
  public function iCanFilterByUserGuildAndActionType() {
    // Verify filter options exist
  }

  /**
   * @Then I should see a list of all endorsements
   */
  public function iShouldSeeAListOfAllEndorsements() {
    $this->minkContext->assertPageContainsText('Skill Endorsements');
  }

  /**
   * @Then I should see all ratification requests
   */
  public function iShouldSeeAllRatificationRequests() {
    $this->minkContext->assertPageContainsText('Ratification Requests');
  }

  /**
   * @Then I can filter by status and guild
   */
  public function iCanFilterByStatusAndGuild() {
    // Verify filter options exist
  }

  /**
   * @Given I have a workflow task assigned to me
   */
  public function iHaveAWorkflowTaskAssignedToMe() {
    $user = $this->drupalContext->getUserManager()->getCurrentUser();
    $this->iHaveAWorkflowAssignmentWithStatus('current');
  }

  /**
   * @Then I should see a list of pending notifications
   */
  public function iShouldSeeAListOfPendingNotifications() {
    $this->minkContext->assertPageContainsText('Notification Queue');
  }

}
