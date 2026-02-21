# avc_devel Module Usage

Development and test data generation module for AV Commons.

## Drush Commands

### `avc:generate`

Generate test content with options for content types and quantities.

```bash
# Generate all default content
drush avc:generate

# Generate with specific options
drush avc:generate --users=20 --groups=5 --assets=30
```

**Options:**
- `--users` - Number of test users to create (default: 10)
- `--groups` - Number of groups to create (default: 3)
- `--assets` - Number of assets to create (default: 10)

### `avc:cleanup`

Remove all generated test content.

```bash
drush avc:cleanup
```

Removes users, groups, assets, and workflow data created by the generator.

### `avc:generate-users`

Generate only test users.

```bash
drush avc:generate-users 20
```

### `avc:generate-groups`

Generate only test groups.

```bash
drush avc:generate-groups 5
```

### `avc:generate-assignments`

Generate workflow assignments for existing content.

```bash
drush avc:generate-assignments 10
```

## Admin UI

The module also provides admin forms at:
- `/admin/config/avc/generate-content` - Content generation form
- `/admin/config/avc/cleanup-content` - Content cleanup form

## Important

This module should only be enabled on development sites. Do not enable on production.

*Last Updated: 2026-02-22*
