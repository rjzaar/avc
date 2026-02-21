# AVC Profile Installation

## Overview

AV Commons is a Drupal installation profile that extends Open Social. The profile is located at `profiles/custom/avc/` and defines the modules, themes, and configuration installed when creating a new AVC site.

## Requirements

- **Drupal**: 10.x or 11.x
- **PHP**: 8.1+
- **Database**: MySQL/MariaDB
- **Private file system**: Must be configured in `settings.php`
- **CommerceGuys\Addressing**: Required for address fields

## Installation Process

### 1. Select Profile

During Drupal installation, select "AV Commons" as the installation profile.

### 2. Dependencies Installed

The profile installs dependencies in this order:

1. **Core modules**: breakpoint, config, mysql, node, user, views
2. **Contrib modules**: color, flag, improved_theme_settings
3. **Open Social core**: social_group, social_activity, social_core, social_swiftmail, social_user

### 3. Feature Modules Installed

After core dependencies, these are installed via the `install:` list:

| Module | Purpose |
|--------|---------|
| social_comment, social_editor, etc. | Open Social features |
| avc_core | Foundation layer |
| avc_member | Member profiles and dashboards |
| avc_group | Group workflow dashboards |
| avc_asset | Asset management |
| avc_notification | Notification queue |
| avc_guild | Guild system |
| avc_content | Initial site content |
| workflow_assignment | Workflow engine |
| avc_work_management | My Work dashboard |
| avc_email_reply | Email reply webhook |
| avc_error_report | Error reporting |

### 4. Themes Installed

- `gin` - Admin theme
- `socialbase` - Open Social base theme
- `socialblue` - Default frontend theme (set as default)

### 5. Install Tasks

The profile defines custom install tasks in `avc.install`:

- User 1 receives the administrator role
- Homepage set to `/stream` (Open Social activity stream)
- Search API indexes configured
- Default configuration applied from `config/install/`

## Demo Content

After installation, enable `avc_devel` and run:

```bash
drush avc:generate
```

See [AVC_DEVEL_USAGE.md](AVC_DEVEL_USAGE.md) for details.

## Configuration Architecture

### `config/install/`

Configuration applied during profile installation. These are base configurations required for the profile to function:

- Entity form displays
- View displays
- Field configurations
- System settings
- Permissions

### `config/optional/`

Configuration that is only installed if its dependencies are met. Used for configurations that depend on optional modules.

### Per-Module Configuration

Each module under `modules/avc_features/` may have its own `config/install/` directory with module-specific configuration (entity types, fields, views, etc.).

## Module Directory Structure

### `modules/avc_features/`

All AVC custom modules. These are first-party modules built specifically for AVC:

- `avc_core`, `avc_member`, `avc_group`, `avc_asset`
- `avc_notification`, `avc_guild`, `avc_work_management`
- `avc_email_reply`, `avc_error_report`
- `avc_content`, `avc_devel`
- `workflow_assignment`

### `modules/custom/`

Third-party contributed module overrides and patches. These are modules from the Drupal community that AVC includes with modifications. Not AVC-original code.

*Last Updated: 2026-02-22*
