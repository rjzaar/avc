@api @avc_member
Feature: Notification Preferences
  As a member of AV Commons
  I want to manage my notification preferences
  So that I receive updates in my preferred format

  Background:
    Given I am logged in as a member with notification preferences

  @wip
  Scenario: Member can view notification settings
    When I visit my dashboard
    Then I should see notification settings
    And I should see "Notification Preferences"

  @wip
  Scenario: Member can set immediate notifications
    When I visit "/user/notification-preferences"
    And I set my notification preference to "immediate"
    And I save my notification preferences
    Then I should see a success message
    And I should see "Notification preferences saved"

  @wip
  Scenario: Member can set daily digest
    When I visit "/user/notification-preferences"
    And I set my notification preference to "daily"
    And I save my notification preferences
    Then I should see a success message

  @wip
  Scenario: Member can set weekly digest
    When I visit "/user/notification-preferences"
    And I set my notification preference to "weekly"
    And I save my notification preferences
    Then I should see a success message

  @wip
  Scenario: Member can disable notifications
    When I visit "/user/notification-preferences"
    And I set my notification preference to "none"
    And I save my notification preferences
    Then I should see a success message

  @javascript
  Scenario: Member can set per-group notification overrides
    Given I am a member of a group "Translation Team"
    When I visit "/user/notification-preferences"
    Then I should see "Translation Team"
    And I should see "Group notification overrides"
