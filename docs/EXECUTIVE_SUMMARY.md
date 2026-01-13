# AV Commons (AVC) - Executive Summary

## Overview

**AV Commons (AVC)** is a collaborative workflow platform built as a Drupal distribution extending [Open Social](https://www.getopensocial.com/). It provides skill-based communities, guild structures, mentorship workflows, and work management dashboards for organizations managing complex work assignments.

| | |
|---|---|
| **Version** | 0.5.0 (Jan 2026) |
| **Platform** | Drupal 10.x-11.x |
| **Requirements** | PHP 8.1+, MySQL/MariaDB |
| **Foundation** | Open Social distribution |

---

# Part 1: Open Social Foundation

> *AVC is built on Open Social, inheriting all its community features. This section describes what comes from Open Social.*

## About Open Social

[Open Social](https://www.getopensocial.com/) is an open-source Drupal distribution for building community platforms. It powers **800+ community systems worldwide** and provides the social networking foundation that AVC extends.

### Features Inherited from Open Social

| Feature | Description |
|---------|-------------|
| **Activity Streams** | Real-time feed of community activity |
| **Groups** | Public, closed, and secret group types |
| **Events** | Event creation, enrollment, and calendars |
| **Topics & Posts** | Content creation and discussion |
| **User Profiles** | Member profiles with skills and interests |
| **Private Messaging** | Direct member-to-member communication |
| **Search** | Full-text search across content, users, and events |
| **Notifications** | Configurable email and on-site notifications |
| **Mobile-First Design** | Responsive UI based on Material Design |
| **Multilingual Ready** | Translation and localization support |

### Open Social Success Stories

| Organization | Use Case |
|--------------|----------|
| **[Greenpeace Greenwire](https://www.drupal.org/case-study/open-social-a-community-and-extranet-solution)** | Volunteer engagement platform - increased activity by 600% in Netherlands |
| **[United Nations GlobalDevHub](https://www.drupal.org/node/2630826/case-studies)** | Knowledge sharing for development community (Splash Award winner) |
| **[Victims Support Netherlands](https://www.drupal.org/node/2630826/case-studies)** | Support community platform (Splash Award winner) |

### Open Social Resources

- [Open Social on Drupal.org](https://www.drupal.org/project/social)
- [Open Social Official Site](https://www.getopensocial.com/)
- [Case Studies](https://www.drupal.org/node/2630826/case-studies)
- [Reviews on Capterra](https://www.capterra.com/p/179231/Open-Social/reviews/)

---

# Part 2: AVC Custom Features

> *This section describes what AVC adds on top of Open Social - the enterprise workflow and guild features unique to AV Commons.*

## Target Users

Organizations needing:
- Collaborative workflows with multi-step task tracking
- Skill-based communities with mentorship
- Enterprise work management with task governance
- Flexible assignment models (user, group, or location-based)

---

## AVC Features (Beyond Open Social)

### 1. Collaborative Workflows
- Three assignment types: **User** (green), **Group** (blue), **Destination** (orange)
- Full audit trail, workflow comments, revision history
- Claiming system for group-assigned work

### 2. Guild System
- Role hierarchy: Admin → Mentor → Endorsed → Junior
- Scoring system with points for task completion
- Skill endorsements and ratification workflow
- Multi-level Skill Proficiency - Configurable levels (Apprentice → Contributor → Mentor → Master) with credit accumulation and verification

### 3. Work Management
- "My Work" dashboard with task status filtering
- Summary cards by content type (Documents, Resources, Projects)
- Sections: Action Needed, Available to Claim, Upcoming, Recently Completed
- Task claiming for group assignments

### 4. Enhanced Notifications
- Queue-based with digest preferences (None/Daily/Weekly/Immediate)
- Per-user and per-group overrides
- Cron-based email delivery
- **Email Reply**: Reply to notification emails to post comments automatically

### 5. Asset Management
- Three types: Projects, Documents, Resources
- Workflow step tracking per asset
- Project containment model

---

## AVC Module Architecture

| Module | Purpose | Entities | Routes |
|--------|---------|----------|--------|
| `avc_core` | Shared services, utilities, base fields | 0 | 0 |
| `workflow_assignment` | Flexible workflow engine (4 entities) | 4 | 26 |
| `avc_member` | Member profiles, dashboards, worklists | 0 | 3 |
| `avc_group` | Group workflow dashboards | 0 | 3 |
| `avc_asset` | Projects, Documents, Resources | 0 | 5 |
| `avc_notification` | Queue-based digest notifications | 1 | 7 |
| `avc_guild` | Guild scoring, endorsements, skill levels | 7 | 15 |
| `avc_work_management` | "My Work" task dashboard | 0 | 3 |
| `avc_email_reply` | Inbound email webhook for comments | 0 | 2 |
| `avc_content` | Initial site content, help pages | 0 | 0 |
| `avc_devel` | Test data generation | 0 | 2 |
| **Total** | **11 modules** | **14** | **60+** |

---

## Implementation Status

| Phase | Feature | Status |
|-------|---------|--------|
| 1 | Core Member System | ✅ Complete |
| 2 | Group System | ✅ Complete |
| 3 | Asset System | ✅ Complete |
| 4 | Notification System | ✅ Complete |
| 5 | Guild System (base) | ✅ Complete |
| 5.1 | Guild Skill Levels | ✅ Complete |
| 5.5 | Work Management Dashboard | ✅ Complete |
| 5.6 | Email Reply System | ✅ Complete |
| 6 | Forums | Planned |
| 7 | Version Control & Diff | Planned |
| 8 | Issue Flagging | Planned |
| 9 | Training/Courses | Planned |
| 10+ | Multilingual, Mobile, Offline | Future |

---

## Key Differentiators (AVC vs Plain Open Social)

| Feature | Open Social | AVC |
|---------|-------------|-----|
| **Workflows** | Basic content moderation | Multi-step with 3 assignment types |
| **Groups** | Standard groups | Guilds with scoring & mentorship |
| **Skills** | Profile fields only | Endorsements, levels, verification |
| **Tasks** | None | Full work management dashboard |
| **Notifications** | Standard | Digest preferences (n/d/w/x) |
| **Email** | Outbound only | Reply-to-comment via email webhook |

---

## Recent Additions (v0.5.0)

- **Work Management Dashboard** - "My Work" interface with:
  - Summary cards by content type
  - Task sections: Action Needed, Available, Upcoming, Completed
  - Task claiming for group assignments

- **Guild Skill Level System** - Multi-level proficiency:
  - 4 progression levels per skill (Apprentice → Master)
  - Credit accumulation from approved work
  - Verification workflow (auto/mentor/peer/committee)
  - Member skill dashboards and analytics

- **Email Reply System** - Inbound email integration:
  - Webhook endpoint for email services
  - Reply to notifications → automatic comment posting
  - Security: token validation, sender verification

- Help content migrated to CMS-editable Book pages

---

# Part 3: Technical Reference

## Technical Stack

- **Open Social** - Social networking foundation
- **Drupal Group module** - Group management
- **Custom workflow engine** - `workflow_assignment` module
- **Modular design** - Selective feature installation

## Quality & Testing

- Behat BDD tests (27+ scenarios)
- PHPUnit unit tests
- PHPStan static analysis
- Drupal coding standards compliance
- Selenium 4 JavaScript testing

---

## Documentation

### Core Documents

| Document | Description |
|----------|-------------|
| [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) | **Master roadmap** - Phased development plan covering all 10+ phases, current status, and specifications for each module. Start here for understanding the full scope. |
| [REPOSITORY_STRUCTURE.md](REPOSITORY_STRUCTURE.md) | **Code organization** - Repository layout, recommended structure, and how AVC fits within the NWP tooling ecosystem. |
| [CODE_ANALYSIS_REPORT_2026-01-13.md](CODE_ANALYSIS_REPORT_2026-01-13.md) | **Code analysis** - Deep analysis of all modules, entities, services, controllers, and routes. Updated Jan 2026. |
| [DOCUMENTATION_UPDATE_SUMMARY.md](DOCUMENTATION_UPDATE_SUMMARY.md) | **Update summary** - Summary of documentation changes from code analysis. |

### Workflow Documentation (`workflow/`)

| Document | Description |
|----------|-------------|
| [WORK_MANAGEMENT_MODULE.md](workflow/WORK_MANAGEMENT_MODULE.md) | **"My Work" dashboard spec** - Complete specification for the unified work dashboard showing tasks by status (Action Needed, Available, Upcoming, Completed). |
| [WORK_MANAGEMENT_IMPLEMENTATION.md](workflow/WORK_MANAGEMENT_IMPLEMENTATION.md) | **Implementation plan** - Technical implementation details for work dashboard and workflow access control. |
| [WORKFLOW_ACCESS_CONTROL.md](workflow/WORKFLOW_ACCESS_CONTROL.md) | **Access control spec** - How node access is restricted to workflow participants during active workflows. |
| [guild-skill-level-design.md](workflow/guild-skill-level-design.md) | **Skill level system** - Complete design for multi-level skill proficiency (Apprentice → Master), credit accumulation, and verification workflows. Status: ✅ IMPLEMENTED. |
| [guild-skill-systems-research.md](workflow/guild-skill-systems-research.md) | **Research document** - Historical guild models and modern certification systems that informed the skill level design. |

### Mobile App Documentation (`mobile/`)

| Document | Description |
|----------|-------------|
| [mobile-app-options.md](mobile/mobile-app-options.md) | **Options analysis** - Comparison of mobile development approaches ranked by complexity (PWA, React Native, Flutter, Native). |
| [hybrid-mobile-approach.md](mobile/hybrid-mobile-approach.md) | **Recommended approach** - Why keeping Drupal backend + mobile frontend is fastest path (2-4 months vs 9-12 months rebuild). |
| [hybrid-implementation-plan.md](mobile/hybrid-implementation-plan.md) | **Implementation phases** - 7-phase plan from API foundation through offline support. |
| [python-alternatives.md](mobile/python-alternatives.md) | **Python investigation** - Analysis of recreating AVC in Python (Django/FastAPI) - useful for understanding feature scope. |

### Proposals (`proposals/`)

| Document | Description |
|----------|-------------|
| [GROUP_EMAIL_REPLY_SYSTEM.md](proposals/GROUP_EMAIL_REPLY_SYSTEM.md) | **Email reply proposal** - System for replying to notification emails with automatic comment posting to groups. Status: ✅ IMPLEMENTED as `avc_email_reply` module. |

### Reference (`reference/`)

| Document | Description |
|----------|-------------|
| [google-docs-reference.md](reference/google-docs-reference.md) | **Google Docs links** - Reference to original prototype documentation in Google Docs (help guides, specs). |

### Help Content (`help/`)

| Document | Description |
|----------|-------------|
| [notification-settings.md](help/notification-settings.md) | **User guide** - Notification settings documentation (n/d/w/x digest options). |

### Specifications (`specs/`)

| File | Description |
|------|-------------|
| `avc-specs.txt` | Plain text extraction of original specifications |
| `avc specs.docx` | Original Word document with 10 Epics defining AVC requirements |

### Prototype (`prototype/`)

| File | Description |
|------|-------------|
| `avc.gs` | Google Apps Script prototype (79KB) - original working prototype |
| `AV Commons App.xlsx` | Excel spreadsheet with data model and workflows |
| `spreadsheet-data.txt` | Extracted spreadsheet data for reference |

---

## Quick Start

1. Ensure requirements met (Drupal 10/11, PHP 8.1+, private file system)
2. Configure private file system path in `settings.php`
3. Run Drupal installation selecting "AV Commons" profile
4. Complete module configuration during installation
5. Optionally enable demo content via `avc_devel` module

---

## Development Commands

```bash
# Generate test data
drush avc:generate-content

# Run tests
vendor/bin/behat --config tests/behat/behat.yml
vendor/bin/phpunit --configuration tests/phpunit/phpunit.xml

# Static analysis
vendor/bin/phpstan analyse -c tests/phpstan/phpstan.neon

# Code standards
vendor/bin/phpcs --standard=Drupal,DrupalPractice modules/avc_features/
```

---

*Last Updated: January 13, 2026*
