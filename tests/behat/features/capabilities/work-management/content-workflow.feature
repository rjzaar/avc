@api @work-management
Feature: Content Type Workflow Integration
  Benefit: Track workflow tasks across different AVC content types
  Role: As a content contributor
  Goal/desire: I want to manage workflow tasks for documents, resources, and projects

  Background:
    Given I enable the module "avc_work_management"

  @verified @content-type-filtering
  Scenario: Dashboard shows tasks for all tracked content types
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a workflow task for avc_document "Technical Guide" assigned to me with status "in_progress"
    And there is a workflow task for avc_resource "API Reference" assigned to me with status "in_progress"
    And there is a workflow task for avc_project "Website Redesign" assigned to me with status "in_progress"

    When I go to "/my-work"

    Then I should see "Technical Guide" in the ".section-active" element
    And I should see "API Reference" in the ".section-active" element
    And I should see "Website Redesign" in the ".section-active" element

  @verified @content-type-summary
  Scenario: Summary cards show counts per content type
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there are 3 workflow tasks for avc_document assigned to me with status "in_progress"
    And there are 2 workflow tasks for avc_resource assigned to me with status "in_progress"
    And there is 1 workflow task for avc_project assigned to me with status "in_progress"

    When I go to "/my-work"

    Then I should see "Documents" in the ".my-work-summary" element
    And I should see "3" in the ".summary-card:contains('Documents')" element
    And I should see "Resources" in the ".my-work-summary" element
    And I should see "2" in the ".summary-card:contains('Resources')" element
    And I should see "Projects" in the ".my-work-summary" element
    And I should see "1" in the ".summary-card:contains('Projects')" element

  @verified @content-type-document
  Scenario: Document workflow task appears in dashboard
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a "avc_document" content with title "Policy Document"
    And there is a workflow task for that content assigned to me with status "in_progress"

    When I go to "/my-work"

    Then I should see "Policy Document" in the ".section-active" element
    And I should see "Documents" in the ".task-row:contains('Policy Document')" element

  @verified @content-type-resource
  Scenario: Resource workflow task appears in dashboard
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a "avc_resource" content with title "Tutorial Video"
    And there is a workflow task for that content assigned to me with status "in_progress"

    When I go to "/my-work"

    Then I should see "Tutorial Video" in the ".section-active" element
    And I should see "Resources" in the ".task-row:contains('Tutorial Video')" element

  @verified @content-type-project
  Scenario: Project workflow task appears in dashboard
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a "avc_project" content with title "Q4 Initiative"
    And there is a workflow task for that content assigned to me with status "in_progress"

    When I go to "/my-work"

    Then I should see "Q4 Initiative" in the ".section-active" element
    And I should see "Projects" in the ".task-row:contains('Q4 Initiative')" element

  @verified @content-link
  Scenario: Task row links to the content
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a "avc_document" content with title "Linked Document"
    And there is a workflow task for that content assigned to me with status "in_progress"

    When I go to "/my-work"
    And I click "Linked Document"

    Then I should see "Linked Document"
    And I should be on the "avc_document" page

  @verified @open-button
  Scenario: Open button navigates to content
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a "avc_resource" content with title "Resource to Open"
    And there is a workflow task for that content assigned to me with status "in_progress"

    When I go to "/my-work"
    And I click "Open" in the ".task-row:contains('Resource to Open')" element

    Then I should see "Resource to Open"

  @verified @multiple-content-types-summary
  Scenario: Summary accurately reflects tasks across sections
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    # Active tasks
    And there is a workflow task for avc_document "Active Doc" assigned to me with status "in_progress"
    # Upcoming tasks
    And there is a workflow task for avc_resource "Upcoming Resource" assigned to me with status "pending"
    # Completed tasks
    And there is a workflow task for avc_project "Done Project" assigned to me with status "completed"

    When I go to "/my-work"

    Then I should see "Active Doc" in the ".section-active" element
    And I should see "Upcoming Resource" in the ".section-upcoming" element
    And I should see "Done Project" in the ".section-completed" element

  @verified @untracked-content-types
  Scenario: Untracked content types don't appear in dashboard
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there is a "page" content with title "Basic Page"
    And there is a workflow task for that content assigned to me with status "in_progress"

    When I go to "/my-work"

    # Basic page is not in tracked_content_types
    Then I should not see "Basic Page" in the ".section-active" element

  @verified @view-all-filtered
  Scenario: View All shows all tasks for that section
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there are 15 workflow tasks for avc_document assigned to me with status "in_progress"

    When I go to "/my-work"

    Then I should see "View All" in the ".section-active" element

    When I click "View All" in the ".section-active" element

    Then I should be on "/my-work/active"
    And I should see 15 task rows

  @verified @empty-content-type
  Scenario: Dashboard handles content types with no tasks
    Given I am logged in as a user with the verified role
    And I have the permission "access my work dashboard"
    And there are no workflow tasks

    When I go to "/my-work"

    Then I should see "0" in the ".summary-card:contains('Documents') .stat-count" element
    And I should see "0" in the ".summary-card:contains('Resources') .stat-count" element
    And I should see "0" in the ".summary-card:contains('Projects') .stat-count" element
