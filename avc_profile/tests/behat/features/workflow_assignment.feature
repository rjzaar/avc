@api @workflow_assignment
Feature: Workflow Assignment
  As a content editor
  I want to manage workflow assignments on content
  So that I can track the progress of content through its lifecycle

  Background:
    Given I am logged in as a user with the "administrator" role

  @smoke @wip
  Scenario: Admin can access workflow settings
    When I visit "/admin/config/workflow/workflow-assignment"
    Then the response status code should be 200

  @wip
  Scenario: Admin can enable workflow on content types
    When I visit "/admin/config/workflow/workflow-assignment"
    And I check "page"
    And I press "Save configuration"
    Then I should see "The configuration options have been saved"

  @wip
  Scenario: Admin can view workflow list
    When I visit "/admin/structure/workflow-list"
    Then the response status code should be 200

  @wip
  Scenario: Admin can create a workflow list
    When I visit "/admin/structure/workflow-list/add"
    And I fill in "Name" with "Editorial Review"
    And I fill in "Machine-readable name" with "editorial_review"
    And I press "Save"
    Then I should see "Workflow"

  @api @wip
  Scenario: Workflow tab appears on enabled content
    Given workflow is enabled for "page" content type
    And I am viewing a "page" content with the title "Test Article"
    Then I should see "Test Article"

  @wip
  Scenario: Admin can view workflow history
    When I visit "/admin/structure/workflow-list/history"
    Then the response status code should be 200

  @wip
  Scenario: Admin can manage workflow templates
    When I visit "/admin/structure/workflow-template"
    Then the response status code should be 200

  @wip @javascript
  Scenario: Workflow can be assigned to a user
    Given a workflow list "review_task" exists
    And I am viewing a "page" content with the title "Document to Review"
    When I click "Workflow"
    And I select "Test User" from "Assigned to"
    And I press "Save workflow"
    Then I should see "Workflow assignment saved"
