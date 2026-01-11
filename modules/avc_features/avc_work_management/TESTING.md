# AVC Work Management Module - Testing Guide

This document describes how to run tests for the `avc_work_management` module.

## Test Structure

```
tests/
├── src/
│   ├── Unit/
│   │   └── Service/
│   │       ├── WorkTaskQueryServiceTest.php    # Unit tests for query service
│   │       └── WorkTaskActionServiceTest.php   # Unit tests for action service
│   ├── Kernel/
│   │   └── WorkTaskQueryServiceTest.php        # Kernel integration tests
│   └── Functional/
│       ├── MyWorkDashboardTest.php             # Browser tests for dashboard
│       └── ClaimTaskFormTest.php               # Browser tests for claim form
└── behat/
    └── features/
        └── capabilities/
            └── work-management/
                ├── my-work-dashboard.feature   # Dashboard acceptance tests
                ├── task-claiming.feature       # Task claiming acceptance tests
                └── content-workflow.feature    # Content type workflow tests
```

## Prerequisites

1. **DDEV Environment**: Tests run inside the DDEV container
2. **Module Enabled**: `avc_work_management` must be installed
3. **Dependencies**: `workflow_assignment`, `group` modules must be available

## Running Tests

### Quick Start (DDEV)

```bash
# Navigate to site directory
cd /home/rob/nwp/sites/avc

# Run all unit tests
ddev exec ./vendor/bin/phpunit -c html/profiles/custom/avc/modules/avc_features/avc_work_management/phpunit.xml.dist --testsuite=unit

# Run all kernel tests
ddev exec ./vendor/bin/phpunit -c html/profiles/custom/avc/modules/avc_features/avc_work_management/phpunit.xml.dist --testsuite=kernel

# Run all functional tests
ddev exec ./vendor/bin/phpunit -c html/profiles/custom/avc/modules/avc_features/avc_work_management/phpunit.xml.dist --testsuite=functional

# Run all PHPUnit tests
ddev exec ./vendor/bin/phpunit -c html/profiles/custom/avc/modules/avc_features/avc_work_management/phpunit.xml.dist
```

### Running Behat Tests

```bash
# From site root with Behat installed
ddev exec ./vendor/bin/behat --tags=@work-management

# Run specific feature
ddev exec ./vendor/bin/behat html/profiles/custom/avc/tests/behat/features/capabilities/work-management/my-work-dashboard.feature

# Run with verbose output
ddev exec ./vendor/bin/behat --tags=@work-management -v
```

### Running Individual Test Classes

```bash
# Specific test class
ddev exec ./vendor/bin/phpunit html/profiles/custom/avc/modules/avc_features/avc_work_management/tests/src/Unit/Service/WorkTaskQueryServiceTest.php

# Specific test method
ddev exec ./vendor/bin/phpunit --filter=testGetTrackedContentTypes html/profiles/custom/avc/modules/avc_features/avc_work_management/tests/src/Unit/Service/WorkTaskQueryServiceTest.php
```

## Test Categories

### Unit Tests (`@group avc_work_management`)

Pure unit tests that mock all dependencies:

- **WorkTaskQueryServiceTest**: Tests query building, counting, filtering
- **WorkTaskActionServiceTest**: Tests task claiming, completion, release

### Kernel Tests (`@group avc_work_management`)

Integration tests with real database:

- **WorkTaskQueryServiceTest**: Tests config loading, entity queries

### Functional Tests (`@group avc_work_management`)

Browser-based tests:

- **MyWorkDashboardTest**: Route access, permissions, page content
- **ClaimTaskFormTest**: Claim form access and validation

### Behat Tests (`@work-management`)

Acceptance tests for user scenarios:

- **my-work-dashboard.feature**: Dashboard access and navigation
- **task-claiming.feature**: Claiming workflow tasks
- **content-workflow.feature**: Content type integration

## CI/CD Integration

### GitLab CI

The module includes `.gitlab-ci.yml` with:

- **php-lint**: Syntax checking
- **phpcs**: Drupal coding standards
- **phpstan**: Static analysis
- **phpunit-unit**: Unit test suite
- **phpunit-kernel**: Kernel test suite
- **phpunit-functional**: Functional test suite
- **behat**: Acceptance test suite

### Running in CI

```yaml
# Include in parent .gitlab-ci.yml
include:
  - local: 'html/profiles/custom/avc/modules/avc_features/avc_work_management/.gitlab-ci.yml'
```

## Code Coverage

Generate coverage report:

```bash
ddev exec ./vendor/bin/phpunit \
  -c html/profiles/custom/avc/modules/avc_features/avc_work_management/phpunit.xml.dist \
  --coverage-html build/coverage \
  --testsuite=unit,kernel
```

## Test Data Setup

### Creating Test Workflow Tasks

```php
// In test setup
$task = \Drupal::entityTypeManager()
  ->getStorage('workflow_task')
  ->create([
    'label' => 'Test Task',
    'node_id' => $node->id(),
    'assigned_type' => 'user',
    'assigned_user' => $user->id(),
    'status' => 'in_progress',
    'weight' => 0,
  ]);
$task->save();
```

### Behat Context Steps

Custom steps available for Behat tests:

```gherkin
Given there is a workflow task "Task Name" assigned to me with status "in_progress"
Given there is a workflow task "Task Name" assigned to group "Group Name" with status "pending"
Given I am a member of group "Group Name"
Given there are 5 workflow tasks for avc_document assigned to me with status "in_progress"
```

## Debugging Tests

### Verbose Output

```bash
# PHPUnit with verbose output
ddev exec ./vendor/bin/phpunit --debug -v [test-file]

# Behat with verbose output
ddev exec ./vendor/bin/behat --tags=@work-management -vvv
```

### Browser Test Screenshots

Functional tests save screenshots on failure to:
```
/tmp/simpletest/browser_output/
```

### Behat Screenshots

Configure in `behat.yml`:
```yaml
extensions:
  Behat\MinkExtension:
    show_auto: true
    screenshots:
      path: '%paths.base%/tests/behat/screenshots'
```

## Test Checklist

Before submitting changes, ensure:

- [ ] All unit tests pass
- [ ] All kernel tests pass
- [ ] All functional tests pass
- [ ] All Behat scenarios pass
- [ ] No PHP syntax errors
- [ ] Code follows Drupal coding standards
- [ ] New features have corresponding tests

## Troubleshooting

### "Class not found" Errors

Clear Drupal cache and rebuild autoloader:
```bash
ddev drush cr
ddev exec composer dump-autoload
```

### Database Connection Errors

Ensure DDEV is running:
```bash
ddev status
ddev start
```

### Behat "Element not found" Errors

Check that:
1. Module is enabled: `ddev drush pm:list --filter=avc_work_management`
2. Permissions are granted: `ddev drush role:perm:add authenticated 'access my work dashboard'`
3. Clear cache: `ddev drush cr`
