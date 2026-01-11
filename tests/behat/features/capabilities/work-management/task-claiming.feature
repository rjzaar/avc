@api @work-management
Feature: Task Claiming
  Benefit: Take ownership of group-assigned tasks
  Role: As a group member
  Goal/desire: I want to claim tasks assigned to my groups

  Background:
    Given I enable the module "avc_work_management"
    And I enable the module "group"

  @verified @task-claim-permission
  Scenario: User with claim permission can see claim buttons
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And I have the permission "claim workflow tasks"
    And I am a member of group "Editors"
    And there is a workflow task "Review Document" assigned to group "Editors" with status "pending"

    When I go to "/my-work"

    Then I should see "Available to Claim"
    And I should see "Review Document" in the ".section-available" element
    And I should see "Claim" in the ".section-available" element

  @verified @task-claim-success
  Scenario: Successfully claim a group task
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And I have the permission "claim workflow tasks"
    And I am a member of group "Reviewers"
    And there is a workflow task "Edit Article" assigned to group "Reviewers" with status "pending"

    When I go to "/my-work"
    And I click "Claim" in the ".section-available" element

    Then I should see "Claim this task?"
    And I should see "You will become the assignee for this task"

    When I press "Claim Task"

    Then I should see "Task claimed successfully"
    And I should be on "/my-work"
    And I should see "Edit Article" in the ".section-active" element

  @verified @task-claim-not-in-group
  Scenario: User cannot claim task from group they don't belong to
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And I have the permission "claim workflow tasks"
    And I am not a member of group "Admins"
    And there is a workflow task "Admin Task" assigned to group "Admins" with status "pending"

    When I go to "/my-work"

    Then I should not see "Admin Task" in the ".section-available" element

  @verified @task-claim-cancel
  Scenario: User can cancel claiming a task
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And I have the permission "claim workflow tasks"
    And I am a member of group "Writers"
    And there is a workflow task "Write Content" assigned to group "Writers" with status "pending"

    When I go to "/my-work"
    And I click "Claim" in the ".section-available" element

    Then I should see "Claim this task?"

    When I click "Cancel"

    Then I should be on "/my-work"
    And I should see "Write Content" in the ".section-available" element

  @authenticated @task-claim-no-permission
  Scenario: User without claim permission cannot claim tasks
    Given I am logged in as a user with the authenticated role
    And I have the permission "access my work dashboard"
    And I do not have the permission "claim workflow tasks"
    And I am a member of group "Editors"
    And there is a workflow task "Review Document" assigned to group "Editors" with status "pending"

    When I go to "/my-work"

    Then I should see "Available to Claim"
    # User should see the task but not the claim button
    And I should not see "Claim" in the ".section-available" element

  @verified @task-already-claimed
  Scenario: Cannot claim task already claimed by another user
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And I have the permission "claim workflow tasks"
    And I am a member of group "Editors"
    And there is a workflow task "Already Claimed" assigned to user "other_user" with status "in_progress"

    When I go to "/my-work"

    Then I should not see "Already Claimed" in the ".section-available" element

  @verified @task-claim-moves-sections
  Scenario: Claimed task moves from Available to Action Needed
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And I have the permission "claim workflow tasks"
    And I am a member of group "Publishers"
    And there is a workflow task "Publish Article" assigned to group "Publishers" with status "pending"

    When I go to "/my-work"

    Then I should see "Publish Article" in the ".section-available" element
    And I should not see "Publish Article" in the ".section-active" element

    When I click "Claim" in the ".section-available" element
    And I press "Claim Task"
    And I go to "/my-work"

    Then I should see "Publish Article" in the ".section-active" element
    And I should not see "Publish Article" in the ".section-available" element

  @verified @task-complete
  Scenario: Complete an assigned task
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a workflow task "My Active Task" assigned to me with status "in_progress"

    When I go to "/my-work"

    Then I should see "My Active Task" in the ".section-active" element
    And I should see "Open" in the ".section-active" element

    When I click "Open" in the ".section-active" element

    Then I should see the task details

  @verified @task-visibility-own
  Scenario: User only sees their own assigned tasks in Action Needed
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a workflow task "My Task" assigned to me with status "in_progress"
    And there is a workflow task "Other User Task" assigned to user "other_user" with status "in_progress"

    When I go to "/my-work"

    Then I should see "My Task" in the ".section-active" element
    And I should not see "Other User Task" in the ".section-active" element

  @verified @task-upcoming
  Scenario: User sees pending personal tasks in Upcoming
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a workflow task "Future Task" assigned to me with status "pending"

    When I go to "/my-work"

    Then I should see "Future Task" in the ".section-upcoming" element

  @verified @task-completed-history
  Scenario: User sees completed tasks in Recently Completed
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a workflow task "Done Task" assigned to me with status "completed"

    When I go to "/my-work"

    Then I should see "Done Task" in the ".section-completed" element
    And I should see "Completed" in the ".section-completed" element
