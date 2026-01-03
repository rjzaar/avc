@api @avc_notification
Feature: Notification System
  As a member of AV Commons
  I want to receive notifications about workflow events
  So that I stay informed about assignments and progress

  Background:
    Given I am logged in as a user with the "authenticated" role

  Scenario: Create a notification queue entry
    Given I am a member of a group "Editorial Team"
    And the group "Editorial Team" has a workflow assignment "Review Chapter 1"
    When a workflow event occurs for "Review Chapter 1"
    Then a notification should be queued for me
    And the notification should have event type "workflow_advance"
    And the notification status should be "pending"

  Scenario: User can view notification settings
    When I visit my notification preferences
    Then the response status code should be 200

  Scenario: User can set immediate notification preference
    When I visit my notification preferences
    And I set my notification preference to "immediate"
    And I save my notification preferences
    Then my notification preference should be "n"

  Scenario: User can set daily digest preference
    When I visit my notification preferences
    And I set my notification preference to "daily"
    And I save my notification preferences
    Then my notification preference should be "d"

  Scenario: User can set weekly digest preference
    When I visit my notification preferences
    And I set my notification preference to "weekly"
    And I save my notification preferences
    Then my notification preference should be "w"

  Scenario: User can disable notifications
    When I visit my notification preferences
    And I set my notification preference to "none"
    And I save my notification preferences
    Then my notification preference should be "x"

  Scenario: User can set group-specific notification override
    Given I am a member of a group "Translation Team"
    When I visit my notification preferences
    When I set notification preference for "Translation Team" to "daily"
    And I save my notification preferences
    And my notification override for "Translation Team" should be "d"

  Scenario: Group override takes precedence over default preference
    Given I am a member of a group "Proofreading Team"
    And my default notification preference is "weekly"
    And my notification override for "Proofreading Team" is "immediate"
    When a workflow event occurs in "Proofreading Team"
    Then I should receive an immediate notification

  Scenario: User with "none" preference receives no notifications
    Given my default notification preference is "none"
    And I am a member of a group "Editorial Team"
    When a workflow event occurs in "Editorial Team"
    Then no notification should be queued for me

  Scenario: Multiple notifications are aggregated for daily digest
    Given my default notification preference is "daily"
    And I am a member of a group "Editorial Team"
    When the following workflow events occur:
      | Event Title        | Event Type        |
      | Task assigned      | assignment        |
      | Task completed     | workflow_advance  |
      | Task needs review  | workflow_advance  |
    Then 3 notifications should be pending for me
    When the daily digest is processed
    Then I should receive 1 email with 3 events
    And all notifications should be marked as "sent"

  Scenario: Notifications are aggregated for weekly digest
    Given my default notification preference is "weekly"
    And I am a member of a group "Editorial Team"
    When 5 workflow events occur throughout the week
    Then 5 notifications should be pending for me
    When the weekly digest is processed
    Then I should receive 1 email with 5 events
    And all notifications should be marked as "sent"

  Scenario: Immediate notifications are sent right away
    Given my default notification preference is "immediate"
    When a workflow event occurs with title "Urgent Review Needed"
    Then a notification should be queued for me
    When immediate notifications are processed
    Then I should receive 1 email about "Urgent Review Needed"
    And the notification status should be "sent"

  Scenario: Notification includes relevant event details
    Given my default notification preference is "immediate"
    And I am a member of a group "Editorial Team"
    And the group "Editorial Team" has a workflow assignment "Review Chapter 5"
    When a workflow advance occurs for "Review Chapter 5"
    Then the notification should contain:
      | Field        | Value                  |
      | event_type   | workflow_advance       |
      | target_group | Editorial Team         |
      | asset_id     | Review Chapter 5       |
      | message      | Workflow has advanced  |

  Scenario: Failed notifications are marked appropriately
    Given my default notification preference is "immediate"
    And a notification is queued for me
    When the email sending fails
    Then the notification status should be "failed"

  Scenario: Old sent notifications are cleaned up
    Given 10 notifications were sent 30 days ago
    When the notification cleanup process runs
    Then the old notifications should be deleted
    But recent notifications should be retained

  Scenario: Admin can view notification queue
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/avc/notifications/queue"
    Then the response status code should be 200

  Scenario: Ratification notifications are queued
    Given I am a member of a guild "Translation Guild" with role "junior"
    And I complete a workflow task in "Translation Guild"
    When the task requires ratification
    Then a notification should be queued for mentors
    And the notification event type should be "ratification_needed"

  Scenario: Endorsement notifications are sent
    Given I am a member of a guild "Editorial Guild"
    When another member endorses me for skill "Technical Writing/Editing"
    Then a notification should be queued for me
    And the notification event type should be "endorsement"

  Scenario: Guild promotion notifications are sent
    Given I am a member of a guild "Technical Guild" with role "junior"
    And I have earned enough points for promotion
    When my guild role is promoted to "endorsed"
    Then a notification should be queued for me
    And the notification event type should be "guild_promotion"
