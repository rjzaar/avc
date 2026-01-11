# AV Commons (AVC)

A collaborative workflow platform built as a Drupal distribution profile extending [Open Social](https://www.getopensocial.com/). AVC provides skill-based communities, guild structures, mentorship workflows, and work management dashboards.

## Overview

AV Commons is designed for organizations managing complex work assignments across skill-based communities. It combines social networking features from Open Social with enterprise workflow management, supporting:

- **Collaborative Workflows** - Multi-step task assignment and tracking
- **Skill-Based Communities** - Guild groups with mentorship and scoring
- **Work Management** - Personal and group dashboards for tracking workflows
- **Flexible Assignment** - Assign work to users, groups, or location destinations
- **Notification Control** - Configurable digest preferences (none/daily/weekly/exclusive)
- **Skill Endorsement** - Community-based endorsement system
- **Quality Control** - Ratification workflow for junior work review

## Requirements

- **Drupal**: 10.x or 11.x
- **PHP**: 8.1 or higher
- **Database**: MySQL/MariaDB
- **File System**: Private file system must be configured
- **Address Library**: Required for installation (CommerceGuys\Addressing)

## Installation

AVC is typically installed as part of a larger site deployment using the NWP (Network Web Platform) tooling. For standalone installation:

1. Ensure all requirements are met
2. Configure private file system path in settings.php
3. Run Drupal installation selecting the "AV Commons" profile
4. Complete the module configuration form during installation
5. Optionally enable demo content for testing

### Post-Installation

- Homepage is set to `/stream` (Open Social activity stream)
- User 1 is automatically assigned the administrator role
- Search API indexes are built if demo content is enabled

## Module Architecture

AVC follows a phased implementation with feature modules organized under `modules/avc_features/`:

### Core Modules

| Module | Description |
|--------|-------------|
| **avc_core** | Foundation layer with shared services and utilities |
| **workflow_assignment** | Flexible workflow engine with color-coded assignment types |

### Feature Modules

| Module | Phase | Description |
|--------|-------|-------------|
| **avc_member** | 1 | Member profiles, dashboards, and personal worklists |
| **avc_group** | 2 | Group-level workflow dashboards and member management |
| **avc_asset** | 3 | Project, Document, and Resource management with workflows |
| **avc_notification** | 4 | Advanced notification queue with digest preferences |
| **avc_guild** | 5 | Guild group type for skill-based communities |
| **avc_work_management** | 6 | "My Work" dashboard for task management |
| **avc_content** | - | Initial site content and demo pages |
| **avc_devel** | - | Development tools and test data generation |

### Workflow Assignment System

The `workflow_assignment` module provides the core workflow engine with three assignment types:

- **User Assignment** (Green) - Direct assignment to a specific user
- **Group Assignment** (Blue) - Assignment to a group for any member to claim
- **Destination Assignment** (Orange) - Assignment to a location/destination

Features include workflow comments, expandable inline viewing, and separate workflow tabs on content pages.

### Guild System

Guilds are skill-based community groups with:

- **Role Hierarchy**: Admin → Mentor → Endorsed → Junior
- **Scoring System**: Points earned for task completion and ratification
- **Endorsement System**: Community skill endorsements
- **Ratification Workflow**: Quality control for junior member contributions

## Directory Structure

```
avc/
├── config/
│   ├── install/          # Configuration applied during installation
│   └── optional/         # Optional configuration
├── docs/
│   ├── help/             # User documentation
│   ├── mobile/           # Mobile planning
│   ├── prototype/        # Prototype specifications
│   ├── specs/            # AVC specifications
│   └── workflow/         # Workflow documentation
├── modules/
│   └── avc_features/     # All AVC feature modules
├── src/
│   ├── Exception/        # Custom exception classes
│   ├── Installer/        # Installation form classes
│   └── PHPStan/          # Static analysis configuration
├── tests/
│   ├── behat/            # BDD testing with Behat
│   ├── phpunit/          # Unit tests
│   └── phpstan/          # Static analysis config
├── themes/
│   ├── avc_theme/        # Custom AVC theme
│   ├── socialbase/       # Open Social base theme
│   └── socialblue/       # Open Social default theme
├── avc.info.yml          # Profile definition
├── avc.install           # Installation hooks
├── avc.profile           # Profile hooks and requirements
└── README.md             # This file
```

## Configuration

### Private File System

AVC requires a private file system. Configure in `settings.php`:

```php
$settings['file_private_path'] = '/path/to/private/files';
```

### Notification Preferences

Members can configure notification digests:

- **n** (None) - No notifications
- **d** (Daily) - Daily digest
- **w** (Weekly) - Weekly digest
- **x** (Exclusive) - Immediate notifications only

### Guild Configuration

Each guild can configure:

- Skills available within the guild
- Scoring enabled/disabled
- Promotion thresholds
- Ratification requirements

## Theme Customization

AVC includes `avc_theme` which extends the Open Social `socialblue` theme. Customizations:

- Custom branding and colors
- Library overrides for AVC-specific styling
- Regional block structure for dashboard layouts

## Development

### Requirements

- Composer for dependency management
- Drush for command-line operations
- PHP CodeSniffer for coding standards
- PHPStan for static analysis

### Testing

```bash
# Run Behat tests
vendor/bin/behat --config tests/behat/behat.yml

# Run PHPUnit tests
vendor/bin/phpunit --configuration tests/phpunit/phpunit.xml

# Run static analysis
vendor/bin/phpstan analyse -c tests/phpstan/phpstan.neon
```

### Generating Test Data

The `avc_devel` module provides Drush commands for generating test content:

```bash
drush avc:generate-content
```

### Coding Standards

AVC follows Drupal coding standards. All PHP files should pass:

```bash
vendor/bin/phpcs --standard=Drupal,DrupalPractice modules/avc_features/
```

## Key Services

### Member Services

- `avc_member.worklist_service` - Member worklist filtering and display
- `avc_member.dashboard_service` - Member dashboard data

### Guild Services

- `avc_guild.scoring_service` - Guild scoring calculations
- `avc_guild.endorsement_service` - Skill endorsement management
- `avc_guild.ratification_service` - Work ratification workflow
- `avc_guild.guild_service` - General guild operations

### Workflow Services

- `workflow_assignment.workflow_manager` - Core workflow management
- `avc_work_management.work_service` - Work dashboard functionality

## Contributing

1. Create a feature branch from `main`
2. Follow Drupal coding standards
3. Write tests for new functionality
4. Update documentation as needed
5. Submit a merge request

## Documentation

Additional documentation is available in the `docs/` directory:

- [Implementation Plan](docs/IMPLEMENTATION_PLAN.md) - Phased development roadmap
- [Repository Structure](docs/REPOSITORY_STRUCTURE.md) - Code organization
- [Workflow Documentation](docs/workflow/) - Workflow system details

## Based on Open Social

AVC extends the [Open Social](https://www.drupal.org/project/social) distribution, inheriting its community features including:

- Activity streams
- Groups and events
- User profiles
- Content types (topics, posts)
- Search and discovery

For Open Social documentation, see the [official documentation](https://www.drupal.org/docs/distributions/open-social).

## License

This project follows the licensing of its base distribution, Open Social, and Drupal core (GPL-2.0-or-later).

## Support

For issues and feature requests, please use the project issue tracker.
