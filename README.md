# AV Commons (AVC)

AV Commons is a collaborative workflow platform for Apostoli Viae, built on Open Social (Drupal).

## Overview

AV Commons enables members to:
- Collaborate on Projects, Documents, and Resources
- Participate in skill-based Guilds with mentorship
- Track workflow assignments and approvals
- Receive customizable notifications

## Repository Structure

```
avc/
├── docs/
│   ├── IMPLEMENTATION_PLAN.md    # Phased implementation plan
│   ├── REPOSITORY_STRUCTURE.md   # Repository organization
│   ├── specs/                    # Specifications
│   │   ├── avc-specs.txt         # Full specs (text)
│   │   └── avc specs.docx        # Original specs document
│   ├── prototype/                # Google Apps Script prototype
│   │   ├── avc.gs                # Apps Script code
│   │   ├── AV Commons App.xlsx   # Spreadsheet prototype
│   │   └── spreadsheet-data.txt  # Extracted data
│   ├── help/                     # Help documentation
│   │   └── notification-settings.md
│   └── reference/                # Reference materials
│       └── google-docs-reference.md
└── README.md
```

## Platform

- **Base**: Open Social 12.x (Drupal distribution)
- **Custom Modules**: avc_profile containing AVC-specific modules
- **Existing Module**: workflow_assignment (general-purpose workflow)

## Key Features

| Feature | Status | Phase |
|---------|--------|-------|
| Workflow Assignment | Exists | - |
| Member Dashboards | Planned | 1 |
| Group Dashboards | Planned | 2 |
| Asset Management | Planned | 3 |
| Notification Digests | Planned | 4 |
| Guild System | Planned | 5 |

## Group Types

| Type | Purpose | Roles |
|------|---------|-------|
| Flexible Group | Standard teams | Admin, Member |
| Guild | Skill-based groups | Admin, Mentor, Endorsed, Junior |

## Related Repositories

| Repository | Purpose |
|------------|---------|
| [workflow_assignment](https://github.com/rjzaar/workflow_assignment) | General workflow module |
| [nwp](https://github.com/rjzaar/nwp) | NWP site management tools |

## Development

### Prerequisites

- Open Social 12.x environment
- PHP 8.1+
- Composer

### Installation

```bash
# Using NWP
pl install avc mysite

# Manual
composer require rjzaar/avc_profile
drush en avc_core avc_member avc_group -y
```

## Documentation

- [Implementation Plan](docs/IMPLEMENTATION_PLAN.md)
- [Repository Structure](docs/REPOSITORY_STRUCTURE.md)
- [Specifications](docs/specs/avc-specs.txt)
- [Google Docs Reference](docs/reference/google-docs-reference.md)

## License

GPL-2.0-or-later

## Contact

Rob Zaar - rjzaar@gmail.com
