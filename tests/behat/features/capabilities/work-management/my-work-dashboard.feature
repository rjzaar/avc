@api @work-management
Feature: My Work Dashboard
  Benefit: Easily see and manage my workflow tasks
  Role: As an authenticated user
  Goal/desire: I want to view my assigned tasks in one place

  Background:
    Given I enable the module "avc_work_management"

  @verified @perfect @dashboard-access
  Scenario: Authenticated user can access My Work dashboard
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"

    When I go to "/my-work"

    Then the response status code should be 200
    And I should see "My Work"
    And I should see "Action Needed"
    And I should see "Available to Claim"
    And I should see "Upcoming"
    And I should see "Recently Completed"

  @anonymous @dashboard-access
  Scenario: Anonymous user cannot access My Work dashboard
    Given I am an anonymous user

    When I go to "/my-work"

    Then the response status code should be 403

  @authenticated @dashboard-access
  Scenario: User without permission cannot access My Work dashboard
    Given I am logged in as a user with the authenticated role
    And I do not have the permission "access my work dashboard"

    When I go to "/my-work"

    Then the response status code should be 403

  @verified @dashboard-sections
  Scenario: User can navigate to section view all pages
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"

    When I go to "/my-work"

    Then I should see "My Work"

    When I go to "/my-work/active"
    Then the response status code should be 200
    And I should see "Action Needed"
    And I should see "← Back to My Work"

    When I go to "/my-work/available"
    Then the response status code should be 200
    And I should see "Available to Claim"

    When I go to "/my-work/upcoming"
    Then the response status code should be 200
    And I should see "Upcoming"

    When I go to "/my-work/completed"
    Then the response status code should be 200
    And I should see "Recently Completed"

  @verified @dashboard-sections
  Scenario: Invalid section returns 404
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"

    When I go to "/my-work/invalid-section"

    Then the response status code should be 404

  @verified @dashboard-empty
  Scenario: Dashboard shows empty state when no tasks
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And I have no workflow tasks assigned

    When I go to "/my-work"

    Then I should see "No tasks in this section."

  @verified @dashboard-content-types
  Scenario: Dashboard shows content type summary cards
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"

    When I go to "/my-work"

    Then I should see an ".my-work-dashboard" element
    And I should see an ".my-work-summary" element
    And I should see "Documents" in the ".my-work-summary" element
    And I should see "Resources" in the ".my-work-summary" element
    And I should see "Projects" in the ".my-work-summary" element

  @verified @dashboard-menu
  Scenario: My Work menu link appears in main menu
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"

    When I go to the homepage

    Then I should see the link "My Work"

  @verified @dashboard-back-link
  Scenario: Section pages have back link to dashboard
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"

    When I go to "/my-work/active"

    Then I should see the link "← Back to My Work"

    When I click "← Back to My Work"

    Then I should be on "/my-work"
