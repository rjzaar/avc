@api @avc_member
Feature: Member Dashboard
  As a member of AV Commons
  I want to view my personal dashboard
  So that I can see my assignments and manage my preferences

  Background:
    Given I am logged in as a user with the "authenticated" role

  @smoke
  Scenario: Member can access their dashboard
    When I visit "/user"
    And I click "Dashboard"
    Then I should see the member dashboard
    And I should see "My Worklist"

  Scenario: Member sees empty worklist when no assignments
    When I visit my dashboard
    Then I should see "No current assignments"

  @javascript
  Scenario: Member with assignments sees worklist items
    Given the following workflow assignments exist:
      | title             | status   |
      | Review Document A | current  |
      | Edit Chapter 2    | upcoming |
    When I visit my dashboard
    Then I should see "Review Document A"
    And I should see "Edit Chapter 2"
    And I should see 2 worklist items

  Scenario: Current assignments are highlighted
    Given I have a workflow assignment with status "current"
    When I visit my dashboard
    Then the worklist item should show "current" status
    And I should see "Action needed"

  Scenario: Upcoming assignments show upcoming status
    Given I have a workflow assignment with status "upcoming"
    When I visit my dashboard
    Then the worklist item should show "upcoming" status
