# Changelog

All notable changes to AV Commons will be documented in this file.

## [v0.5.1] - 2026-01-16

### Added
- **Error Reporting Module** (`avc_error_report` - Phase 5.7)
  - Footer link "Report an Error" visible to authenticated users
  - Form captures current page URL automatically
  - User describes action taken and pastes error page content
  - Creates formatted GitLab issues with full context
  - Auto-captures environment info (Drupal version, PHP, browser, roles)
  - Rate limiting (5 reports/hour/user, configurable)
  - Admin settings form for GitLab configuration
  - "Test Connection" button to verify GitLab API
  - Unit tests for RateLimitService

### Changed
- Updated IMPLEMENTATION_PLAN.md with Phase 5.7
- Updated Error Reporting proposal with simplified workflow

### Statistics
- 13 modules total (1 new: avc_error_report)
- 2 new routes (/report-error, /admin/config/avc/error-report)
- 2 new services (gitlab, rate_limit)

## [v0.5.0] - 2026-01-13

### Added
- **Guild Skill Level System** (`avc_guild` - Phase 5.1)
  - 4 new entities: SkillLevel, MemberSkillProgress, SkillCredit, LevelVerification
  - Multi-level proficiency tracking (Apprentice → Contributor → Mentor → Master)
  - Credit accumulation from task reviews, endorsements, and assessments
  - Configurable verification workflows (auto, mentor, peer, committee, assessment)
  - Member skill dashboards with progress visualization
  - Verification queue for peer/committee voting
  - Guild skills analytics and reporting
  - Sample content generation in avc_devel

- **Email Reply System** (`avc_email_reply` - Phase 5.6)
  - Inbound email webhook endpoint (`/api/email/inbound`)
  - Reply to notification emails to post comments automatically
  - Secure token generation and validation
  - Email content extraction with signature removal
  - Rate limiting for abuse prevention
  - Admin settings form for configuration
  - Unit tests for all services

- **Executive Summary Documentation**
  - Complete project overview with Open Social foundation
  - Feature comparison (Open Social vs AVC)
  - Module architecture with entity/route counts
  - Comprehensive documentation index with summaries

- **Code Analysis Report**
  - Deep analysis of all 11 modules
  - Entity, service, controller, and route inventory
  - Documentation accuracy audit
  - Discrepancy resolution

### Changed
- Updated IMPLEMENTATION_PLAN.md with phases 5.1, 5.5, 5.6
- Marked guild-skill-level-design.md as IMPLEMENTED
- Enhanced RatificationForm with skill credit checkboxes
- Enhanced RatificationService to award skill credits on approval
- Added GROUP_COMMENT event type to NotificationQueue
- Enhanced NotificationSender with reply-to token support

### Statistics
- 11 modules total (2 new: avc_email_reply, documentation)
- 14 entities (7 new in avc_guild)
- 60+ routes (15 new in avc_guild, 2 in avc_email_reply)
- 20+ services
- ~11,200 lines added since v0.4.0

## [v0.4.0] - 2026-01-11

### Added
- **Work Management Module** (`avc_work_management`)
  - My Work dashboard showing tasks by status (active, available, upcoming, completed)
  - Task claiming functionality for group-assigned work
  - Summary cards by content type (Documents, Resources, Projects)
  - Configurable dashboard sections via settings
  - Full test coverage (Unit, Kernel, Functional, Behat)

- **Help Content as Book Pages**
  - Migrated all help content from hardcoded controller to Book nodes
  - Each help page now editable through CMS
  - Editorial Documentation workflow (Draft → Editor Review → Final Approval → Publish)
  - Book navigation with prev/next links
  - Public visibility for anonymous access

- **Workflow Documentation**
  - Added workflow access control documentation
  - Work management implementation guide
  - Module structure documentation

### Changed
- Help menu links now use URL-based routing to book pages
- Removed ContentController and related templates (content now in Book nodes)

### Fixed
- Fixed `hook_ENTITY_TYPE_view()` signature in avc_asset module
- Removed redundant entity display loading
- Added workflow library attachment to status displays

## [v0.3.1] - Previous Release

See git history for earlier changes.
