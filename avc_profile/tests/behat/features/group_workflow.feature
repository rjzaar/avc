@api @avc_group
Feature: Group Workflow Dashboard
  As a group member
  I want to see my group's workflow dashboard
  So that I can track group assignments and progress

  Background:
    Given I am logged in as a user with the "authenticated" role

  @wip
  Scenario: Member can see group dashboard
    Given I am a member of a group "Editorial Team"
    When I visit the group "Editorial Team"
    And I click "Workflow"
    Then I should see "Group Workflow"
    And I should see "Editorial Team"

  @wip
  Scenario: Group dashboard shows assignments
    Given I am a member of a group "Translation Team"
    And the group "Translation Team" has a workflow assignment "Translate Chapter 1"
    When I visit the group workflow page for "Translation Team"
    Then I should see "Translate Chapter 1"

  @wip
  Scenario: Group managers can create assignments
    Given I am a group manager of "Editorial Team"
    When I visit the group workflow page for "Editorial Team"
    And I click "Add assignment"
    And I fill in "Title" with "Review Newsletter"
    And I press "Save"
    Then I should see "Workflow assignment created"

  @wip
  Scenario: Group members see their assignments highlighted
    Given I am a member of a group "Proofreading Team"
    And I have a group assignment in "Proofreading Team" with status "current"
    When I visit the group workflow page for "Proofreading Team"
    Then my assignment should be highlighted

  @javascript
  Scenario: Group dashboard updates dynamically
    Given I am a group manager of "Editorial Team"
    And the group "Editorial Team" has a workflow assignment "Pending Task"
    When I visit the group workflow page for "Editorial Team"
    And I mark "Pending Task" as completed
    Then I should see "completed" status for "Pending Task"
