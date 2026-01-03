@api @avc_member
Feature: Member Worklist
  As a member of AV Commons
  I want to see my workflow assignments organized by group
  So that I can track my responsibilities

  Background:
    Given I am logged in as a user with the "authenticated" role

  @smoke
  Scenario: Member sees personal worklist section
    When I visit my dashboard
    Then the response status code should be 200

  Scenario: Member sees group worklists
    Given I am a member of a group "Editorial Team"
    When I visit my dashboard
    Then I should see the group "Editorial Team" in my dashboard

  Scenario: Worklist shows assignment details
    Given the following workflow assignments exist:
      | title                | status  |
      | Proofread Chapter 5  | current |
    When I visit my dashboard
    Then I should see "Proofread Chapter 5"
    And I should see "Action needed"

  @javascript @wip
  Scenario: Clicking worklist row navigates to assignment
    Given the following workflow assignments exist:
      | title          | status  |
      | Review Draft   | current |
    When I visit my dashboard
    And I click on the worklist row for "Review Draft"
    Then I should be on the workflow page for "Review Draft"

  Scenario: Completed assignments are de-emphasized
    Given the following workflow assignments exist:
      | title              | status    |
      | Completed Task     | completed |
    When I visit my dashboard
    Then the worklist item should show "completed" status

  Scenario: Member with multiple group memberships sees all group worklists
    Given I am a member of a group "Translation Team"
    And I am a member of a group "Editorial Team"
    When I visit my dashboard
    Then I should see the group "Translation Team" in my dashboard
    And I should see the group "Editorial Team" in my dashboard
