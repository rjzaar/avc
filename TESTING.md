# AVC Profile Testing Guide

This document explains how to run tests for the AV Commons profile and its modules.

## Prerequisites

Install dev dependencies:

```bash
composer install --dev
```

## PHPUnit Tests

### Running All Tests

```bash
# Run all tests
composer test

# Or directly
./vendor/bin/phpunit
```

### Running Specific Test Suites

```bash
# Unit tests only (fast, no database)
composer test:unit

# Kernel tests (requires Drupal bootstrap)
composer test:kernel

# Functional tests (requires full Drupal install)
composer test:functional
```

### Running Tests for a Specific Module

```bash
# Run all avc_member tests
./vendor/bin/phpunit modules/avc_member/tests/

# Run only unit tests for avc_member
./vendor/bin/phpunit modules/avc_member/tests/src/Unit/

# Run a specific test file
./vendor/bin/phpunit modules/avc_member/tests/src/Unit/Service/MemberWorklistServiceTest.php
```

### Test Groups

Tests are tagged with `@group` annotations. Run tests by group:

```bash
./vendor/bin/phpunit --group avc_member
./vendor/bin/phpunit --group workflow_assignment
```

## Behat Tests

Behat tests are located in `tests/behat/`.

### Configuration

1. Copy and customize the behat configuration:

```bash
cd tests/behat
cp behat.yml behat.local.yml
```

2. Update `behat.local.yml` with your local settings:
   - `base_url`: Your local Drupal site URL
   - `drupal_root`: Path to your Drupal installation

### Running Behat Tests

```bash
# Run all Behat tests
composer test:behat

# Or directly
cd tests/behat && behat

# Run specific feature
behat features/member_dashboard.feature

# Run tests with a specific tag
behat --tags @smoke
behat --tags @avc_member
behat --tags @workflow_assignment

# Run with verbose output
behat -v
```

### Available Tags

- `@smoke` - Quick smoke tests
- `@api` - Tests using Drupal API driver
- `@javascript` - Tests requiring JavaScript (Selenium)
- `@avc_member` - Member module tests
- `@avc_group` - Group module tests
- `@workflow_assignment` - Workflow assignment tests

## Test Structure

```
avc_profile/
├── phpunit.xml              # PHPUnit configuration
├── tests/
│   ├── behat/
│   │   ├── behat.yml        # Behat configuration
│   │   ├── bootstrap/
│   │   │   └── FeatureContext.php
│   │   └── features/
│   │       ├── member_dashboard.feature
│   │       ├── member_worklist.feature
│   │       ├── notification_preferences.feature
│   │       ├── workflow_assignment.feature
│   │       └── group_workflow.feature
│   └── phpunit/             # Shared PHPUnit utilities
│
└── modules/
    ├── avc_member/
    │   └── tests/
    │       └── src/
    │           ├── Unit/    # Fast isolated tests
    │           ├── Kernel/  # Integration tests
    │           └── Functional/ # Full browser tests
    └── workflow_assignment/
        └── tests/
            └── src/
                └── Functional/
```

## Writing Tests

### PHPUnit Unit Tests

```php
namespace Drupal\Tests\avc_member\Unit;

use Drupal\Tests\UnitTestCase;

class MyServiceTest extends UnitTestCase {
  public function testSomething() {
    $this->assertTrue(true);
  }
}
```

### Behat Feature Tests

```gherkin
@api @avc_member
Feature: My Feature
  As a user
  I want to do something
  So that I get value

  Scenario: Basic test
    Given I am logged in as a user with the "authenticated" role
    When I visit "/my-page"
    Then I should see "Expected content"
```

## Continuous Integration

Tests are run automatically on push. See `.gitlab-ci.yml` for CI configuration.

## Troubleshooting

### PHPUnit: "Class not found"

Make sure to run composer dump-autoload:

```bash
composer dump-autoload
```

### Behat: "Step not implemented"

Check `tests/behat/bootstrap/FeatureContext.php` for custom step definitions.

### Database errors in Kernel/Functional tests

Ensure the `SIMPLETEST_DB` environment variable is set:

```bash
export SIMPLETEST_DB="sqlite://localhost/sites/default/files/.sqlite"
```
