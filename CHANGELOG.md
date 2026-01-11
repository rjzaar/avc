# Changelog

All notable changes to AV Commons will be documented in this file.

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
