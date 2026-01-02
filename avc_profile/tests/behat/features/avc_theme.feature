@api @theme
Feature: AVC Theme
  As a site visitor
  I want the AVC theme to be active and working
  So that the site has proper branding and styling

  Scenario: Theme CSS is loaded on pages
    Given I am on the homepage
    Then the response should contain "theme=avc_theme"

  Scenario: About page is accessible
    Given I am on "/about"
    Then I should see "About AV Commons"
    And the response should contain "theme=avc_theme"

  Scenario: Contact page is accessible
    Given I am on "/contact"
    Then I should see "Contact Us"
    And the response should contain "theme=avc_theme"

  Scenario: User guide page is accessible
    Given I am on "/help/user-guide"
    Then I should see "AV Commons User Guide"
    And the response should contain "theme=avc_theme"

  Scenario: Getting started page is accessible
    Given I am on "/help/getting-started"
    Then I should see "Getting Started"
    And the response should contain "theme=avc_theme"

  Scenario: FAQ page is accessible
    Given I am on "/help/faq"
    Then I should see "Frequently Asked Questions"
    And the response should contain "theme=avc_theme"

  Scenario: Dashboard help page is accessible
    Given I am on "/help/dashboard"
    Then I should see "Understanding Your Dashboard"
    And the response should contain "theme=avc_theme"

  Scenario: Assets help page is accessible
    Given I am on "/help/assets"
    Then I should see "Working with Assets"
    And the response should contain "theme=avc_theme"

  Scenario: Workflow help page is accessible
    Given I am on "/help/workflow"
    Then I should see "Understanding the Workflow System"
    And the response should contain "theme=avc_theme"

  Scenario: Guilds help page is accessible
    Given I am on "/help/guilds"
    Then I should see "The Guild System"
    And the response should contain "theme=avc_theme"

  Scenario: Notifications help page is accessible
    Given I am on "/help/notifications"
    Then I should see "Notification Settings"
    And the response should contain "theme=avc_theme"

  Scenario: Theme blocks are rendered correctly
    Given I am on the homepage
    Then the response should contain "block-avc-theme"
