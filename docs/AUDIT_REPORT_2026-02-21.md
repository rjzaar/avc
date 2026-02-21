# AVC Codebase Audit Report

**Date:** 2026-02-21
**Scope:** Full documentation-vs-code audit of AV Commons Drupal profile
**Method:** Automated code analysis of all modules, config, tests, and documentation

---

## Table of Contents

- [1. Implementation Status Audit](#1-implementation-status-audit)
  - [1.1 Phase Completion Matrix](#11-phase-completion-matrix)
  - [1.2 Module Inventory: Code vs Profile Installation](#12-module-inventory-code-vs-profile-installation)
  - [1.3 Entity Inventory](#13-entity-inventory)
  - [1.4 Service Inventory](#14-service-inventory)
  - [1.5 Test Coverage Inventory](#15-test-coverage-inventory)
- [2. Documentation Accuracy Audit](#2-documentation-accuracy-audit)
  - [2.1 EXECUTIVE_SUMMARY.md](#21-executive_summarymd)
  - [2.2 IMPLEMENTATION_PLAN.md](#22-implementation_planmd)
  - [2.3 README.md](#23-readmemd)
  - [2.4 CHANGELOG.md](#24-changelogmd)
  - [2.5 CODE_ANALYSIS_REPORT_2026-01-13.md](#25-code_analysis_report_2026-01-13md)
  - [2.6 Document Organisation Issues](#26-document-organisation-issues)
  - [2.7 Missing Documentation](#27-missing-documentation)
- [3. Critical Code Issues](#3-critical-code-issues)
  - [3.1 Modules Not in Profile Install](#31-modules-not-in-profile-install)
  - [3.2 Workflow Access Control: Documented but Not Implemented](#32-workflow-access-control-documented-but-not-implemented)
  - [3.3 Module Directory Inconsistency](#33-module-directory-inconsistency)
  - [3.4 Notification Field Dependency](#34-notification-field-dependency)
  - [3.5 Skill Progression TODOs in Production Code](#35-skill-progression-todos-in-production-code)
  - [3.6 Stub Hook in workflow_assignment](#36-stub-hook-in-workflow_assignment)
- [4. Phased Remediation Plan](#4-phased-remediation-plan)
  - [Phase A: Critical Fixes (Profile & Installation)](#phase-a-critical-fixes-profile--installation)
  - [Phase B: Documentation Corrections](#phase-b-documentation-corrections)
  - [Phase C: Test Coverage](#phase-c-test-coverage)
  - [Phase D: Code Completeness](#phase-d-code-completeness)
  - [Phase E: Documentation Organisation & Polish](#phase-e-documentation-organisation--polish)
  - [Phase F: Future Implementation Readiness](#phase-f-future-implementation-readiness)
- [5. Version & Release Recommendation](#5-version--release-recommendation)

---

## 1. Implementation Status Audit

### 1.1 Phase Completion Matrix

| # | Phase | Documented Status | Actual Code Status | Verdict |
|---|-------|------------------|-------------------|---------|
| 1.1.1 | Phase 1: Member System | Complete | Fully implemented — controller, service, form, 4 test classes | **Accurate** |
| 1.1.2 | Phase 2: Group System | Complete | Fully implemented — 2 controllers, 2 forms, 2 services, tests | **Accurate** |
| 1.1.3 | Phase 3: Asset System | Complete | Fully implemented — controller, 3 services, extensive hooks | **Accurate** |
| 1.1.4 | Phase 4: Notification System | Complete | Fully implemented — entity, 5 services, 2 controllers, 2 forms, templates, cron | **Accurate** |
| 1.1.5 | Phase 5: Guild System | Complete | Fully implemented — 7 entities, 6 services, 6 controllers, 5 forms | **Accurate** |
| 1.1.6 | Phase 5.1: Skill Levels | Complete | 85-90% implemented — 2 TODOs remain in services | **Mostly accurate** |
| 1.1.7 | Phase 5.5: Work Management | Complete | Fully implemented — controller, 2 services, form, 3 templates, 57 tests | **Accurate** |
| 1.1.8 | Phase 5.6: Email Reply | Complete | Fully implemented — 4 services, 2 controllers, form, queue worker, Drush commands | **Accurate** |
| 1.1.9 | Phase 5.7: Error Reporting | Complete | Fully implemented — 2 forms, 2 services, CSS, tests | **Accurate** |
| 1.1.10 | Workflow Access Control | Draft doc | **NOT implemented** — hook_node_access is a no-op stub | **INACCURATE** |
| 1.1.11 | Multiple Verification Types | Proposed | Not implemented | Accurate (proposal only) |
| 1.1.12 | Time-Limited Claims | Proposed | Not implemented | Accurate (proposal only) |
| 1.1.13 | Destination Access Control | Proposed | Not implemented | Accurate (proposal only) |
| 1.1.14 | Versioning & Re-Edit | Proposed | Not implemented | Accurate (proposal only) |
| 1.1.15 | Phase 6: Forums | Not started | Not implemented | Accurate |
| 1.1.16 | Phases 7-10: Future | Not started | Not implemented | Accurate |

### 1.2 Module Inventory: Code vs Profile Installation

| # | Module | Location | Has Code | In `avc.info.yml` Install | Gap |
|---|--------|----------|----------|---------------------------|-----|
| 1.2.1 | `avc_core` | `modules/avc_features/` | Yes | Yes | None |
| 1.2.2 | `avc_member` | `modules/avc_features/` | Yes | Yes | None |
| 1.2.3 | `avc_group` | `modules/avc_features/` | Yes | Yes | None |
| 1.2.4 | `avc_asset` | `modules/avc_features/` | Yes | Yes | None |
| 1.2.5 | `avc_notification` | `modules/avc_features/` | Yes | Yes | None |
| 1.2.6 | `avc_guild` | `modules/avc_features/` | Yes | Yes | None |
| 1.2.7 | `avc_content` | `modules/avc_features/` | Yes | Yes | None |
| 1.2.8 | `workflow_assignment` | `modules/custom/` | Yes | Yes | None |
| 1.2.9 | `avc_work_management` | `modules/avc_features/` | Yes (57 tests) | **NO** | **MISSING** |
| 1.2.10 | `avc_email_reply` | `modules/avc_features/` | Yes (3 test files) | **NO** | **MISSING** |
| 1.2.11 | `avc_error_report` | `modules/custom/` | Yes (1 test file) | **NO** | **MISSING** |
| 1.2.12 | `avc_devel` | `modules/avc_features/` | Yes | **NO** | Expected (dev only) |

### 1.3 Entity Inventory

| # | Entity | Module | Fields | Status |
|---|--------|--------|--------|--------|
| 1.3.1 | `WorkflowTemplate` | workflow_assignment | Config entity, reusable workflow definitions | Complete |
| 1.3.2 | `WorkflowList` | workflow_assignment | Content entity, workflow instance on a node | Complete |
| 1.3.3 | `WorkflowTask` | workflow_assignment | Content entity, individual step in a workflow | Complete |
| 1.3.4 | `WorkflowAssignment` | workflow_assignment | Content entity, user/group/destination assignment | Complete |
| 1.3.5 | `NotificationQueue` | avc_notification | Content entity, 7 event types, 4 statuses | Complete |
| 1.3.6 | `GuildScore` | avc_guild | Content entity, member scoring | Complete |
| 1.3.7 | `SkillEndorsement` | avc_guild | Content entity, peer endorsements | Complete |
| 1.3.8 | `Ratification` | avc_guild | Content entity, junior work approval | Complete |
| 1.3.9 | `SkillLevel` | avc_guild | Content entity, level config per guild+skill | Complete |
| 1.3.10 | `MemberSkillProgress` | avc_guild | Content entity, user progression tracking | Complete |
| 1.3.11 | `SkillCredit` | avc_guild | Content entity, credit event records | Complete |
| 1.3.12 | `LevelVerification` | avc_guild | Content entity, verification voting workflow | Complete |

**Custom database tables:**
| # | Table | Module | Purpose |
|---|-------|--------|---------|
| 1.3.13 | `level_verification_vote` | avc_guild | Individual verifier votes with unique constraint | Complete |

### 1.4 Service Inventory

| # | Service ID | Module | Class | Status |
|---|-----------|--------|-------|--------|
| 1.4.1 | `avc_member.worklist` | avc_member | MemberWorklistService | Complete |
| 1.4.2 | `avc_group.workflow` | avc_group | GroupWorkflowService | Complete |
| 1.4.3 | `avc_group.notification` | avc_group | GroupNotificationService | Complete |
| 1.4.4 | `avc_asset.manager` | avc_asset | AssetManager | Complete |
| 1.4.5 | `avc_asset.workflow_checker` | avc_asset | WorkflowChecker | Complete |
| 1.4.6 | `avc_asset.workflow_processor` | avc_asset | WorkflowProcessor | Complete |
| 1.4.7 | `avc_notification.preferences` | avc_notification | NotificationPreferences | Complete |
| 1.4.8 | `avc_notification.service` | avc_notification | NotificationService | Complete |
| 1.4.9 | `avc_notification.aggregator` | avc_notification | NotificationAggregator | Complete |
| 1.4.10 | `avc_notification.sender` | avc_notification | NotificationSender | Complete |
| 1.4.11 | `avc_notification.processor` | avc_notification | NotificationProcessor | Complete |
| 1.4.12 | `avc_guild.service` | avc_guild | GuildService | Complete |
| 1.4.13 | `avc_guild.scoring` | avc_guild | ScoringService | Complete |
| 1.4.14 | `avc_guild.skill_progression` | avc_guild | SkillProgressionService | Complete (2 TODOs) |
| 1.4.15 | `avc_guild.skill_configuration` | avc_guild | SkillConfigurationService | Complete (1 TODO) |
| 1.4.16 | `avc_guild.endorsement` | avc_guild | EndorsementService | Complete |
| 1.4.17 | `avc_guild.ratification` | avc_guild | RatificationService | Complete |
| 1.4.18 | `avc_work_management.task_query` | avc_work_management | WorkTaskQueryService | Complete |
| 1.4.19 | `avc_work_management.task_action` | avc_work_management | WorkTaskActionService | Complete |
| 1.4.20 | `avc_email_reply.processor` | avc_email_reply | EmailReplyProcessor | Complete |
| 1.4.21 | `avc_email_reply.token` | avc_email_reply | ReplyTokenService | Complete |
| 1.4.22 | `avc_email_reply.content_extractor` | avc_email_reply | ReplyContentExtractor | Complete |
| 1.4.23 | `avc_email_reply.rate_limiter` | avc_email_reply | EmailRateLimiter | Complete |
| 1.4.24 | `avc_error_report.gitlab` | avc_error_report | GitLabService | Complete |
| 1.4.25 | `avc_error_report.rate_limit` | avc_error_report | RateLimitService | Complete |
| 1.4.26 | `workflow_assignment.notification` | workflow_assignment | WorkflowNotificationService | Complete |
| 1.4.27 | `workflow_assignment.history_logger` | workflow_assignment | WorkflowHistoryLogger | Complete |

### 1.5 Test Coverage Inventory

| # | Module | Unit | Kernel | Functional | Test Methods | Risk Level |
|---|--------|------|--------|------------|-------------|------------|
| 1.5.1 | `avc_member` | 1 file | 1 file | 2 files | ~20+ | Low |
| 1.5.2 | `avc_group` | 1 file | 0 | 1 file | ~10+ | Medium |
| 1.5.3 | `avc_asset` | 0 | 0 | 0 | **0** | **HIGH** |
| 1.5.4 | `avc_notification` | 0 | 0 | 0 | **0** | **HIGH** |
| 1.5.5 | `avc_guild` | 0 | 0 | 0 | **0** | **CRITICAL** |
| 1.5.6 | `avc_work_management` | 2 files | 1 file | 2 files | 57 | Low |
| 1.5.7 | `avc_email_reply` | 3 files | 0 | 0 | ~15+ | Medium |
| 1.5.8 | `avc_error_report` | 1 file | 0 | 0 | ~6 | Medium |
| 1.5.9 | `workflow_assignment` | 0 | 0 | 1 file | ~5 | **HIGH** |

---

## 2. Documentation Accuracy Audit

### 2.1 EXECUTIVE_SUMMARY.md

| # | Issue | Details | Severity |
|---|-------|---------|----------|
| 2.1.1 | Stale version number | States "0.5.1 (Jan 2026)" — unreleased work has accumulated since | Medium |
| 2.1.2 | Module count inaccurate | States "12 modules total" — listing only names 9 in architecture section | Low |
| 2.1.3 | Route count unverified | States "66+ routes" — not verified against current codebase | Low |
| 2.1.4 | Missing modules | Does not mention `avc_email_reply`, `avc_error_report`, or `avc_work_management` in module listing | Medium |
| 2.1.5 | Last updated date | States "February 1, 2026" — 20 days stale | Low |

### 2.2 IMPLEMENTATION_PLAN.md

| # | Issue | Details | Severity |
|---|-------|---------|----------|
| 2.2.1 | Missing cross-references | Does not reference the Workflow System Complete Implementation Plan proposal | Medium |
| 2.2.2 | Missing cross-references | Does not reference the Guild Multiple Verification Types proposal | Medium |
| 2.2.3 | File path incorrect | Error Reporting module location (`modules/custom/` vs `modules/avc_features/`) not documented | Low |
| 2.2.4 | Missing database table | Phase 5.1 section does not mention the `level_verification_vote` custom table | Low |
| 2.2.5 | Phase numbering inconsistency | Work Management is "Phase 5.5" here but "Phase 6" in README.md | Medium |

### 2.3 README.md

| # | Issue | Details | Severity |
|---|-------|---------|----------|
| 2.3.1 | Phase numbering mismatch | Lists `avc_work_management` as "Phase 6" — should be "Phase 5.5" per IMPLEMENTATION_PLAN.md | Medium |
| 2.3.2 | Missing modules | Does not list `avc_email_reply` or `avc_error_report` in Module Architecture section | High |
| 2.3.3 | Incomplete directory structure | Does not show `modules/custom/` subdirectory where `avc_error_report` and `workflow_assignment` live | Low |
| 2.3.4 | Missing services | Key Services section omits work management, email reply, and error reporting services | Medium |

### 2.4 CHANGELOG.md

| # | Issue | Details | Severity |
|---|-------|---------|----------|
| 2.4.1 | Unreleased section too large | Accumulated significant items with no version assignment | Medium |
| 2.4.2 | Mixed content types | Unreleased section mixes proposals (docs only) with actual bug fixes and features | Low |
| 2.4.3 | Vague entries | "Guild progression documentation" doesn't specify what was added | Low |

### 2.5 CODE_ANALYSIS_REPORT_2026-01-13.md

| # | Issue | Details | Severity |
|---|-------|---------|----------|
| 2.5.1 | Stale snapshot | Does not include `avc_error_report` (added Jan 16), workflow proposals (Jan 18), or verification types proposal (Jan 24) | Medium |
| 2.5.2 | Module count outdated | Lists "11 modules" — now 12 with `avc_error_report` | Low |
| 2.5.3 | No staleness indicator | Not marked as a historical snapshot — could mislead readers | Medium |

### 2.6 Document Organisation Issues

| # | Document | Current Location | Recommended Location | Reason |
|---|----------|-----------------|---------------------|--------|
| 2.6.1 | `WORKFLOW_ACCESS_CONTROL.md` | `docs/workflow/` | `docs/proposals/` | Not implemented — misleadingly sits alongside implemented features |
| 2.6.2 | `AVC_ERROR_REPORTING_MODULE.md` | `docs/proposals/` | OK, but mark as IMPLEMENTED | Code is complete — proposal status is stale |
| 2.6.3 | `GROUP_EMAIL_REPLY_SYSTEM.md` | `docs/proposals/` | OK, but mark as IMPLEMENTED | Code is complete — proposal status is stale |
| 2.6.4 | `WORK_MANAGEMENT_MODULE.md` | `docs/workflow/` | OK | Correctly placed |
| 2.6.5 | `guild-skill-level-design.md` | `docs/workflow/` | OK (marked IMPLEMENTED) | Correctly placed and labelled |

### 2.7 Missing Documentation

| # | Component | Exists In Code | Has Documentation | Gap |
|---|-----------|---------------|-------------------|-----|
| 2.7.1 | `avc_devel` Drush commands | Yes — `avc:generate-content`, `avc:cleanup-content` | No | No docs for test content generation commands |
| 2.7.2 | `avc_content` initial content | Yes — module installs default pages | No | No docs explaining what content is created |
| 2.7.3 | Theme inheritance chain | Yes — `socialbase → socialblue → avc_theme` | No | No architecture doc for 3-level theme chain |
| 2.7.4 | Profile installation process | Yes — install tasks, demo content, requirements | No | No docs for custom install tasks and requirements |
| 2.7.5 | Configuration architecture | Yes — `config/install/` and `config/optional/` | No | No docs explaining which configs ship with profile |

---

## 3. Critical Code Issues

### 3.1 Modules Not in Profile Install

**Impact:** New installations of the AVC profile will not include three fully-implemented modules.

| # | Module | Tests | Lines of Code (est.) | User-Facing Features Missing |
|---|--------|-------|---------------------|------------------------------|
| 3.1.1 | `avc_work_management` | 57 test methods | ~1,100 | My Work dashboard, task claiming |
| 3.1.2 | `avc_email_reply` | 3 test files | ~800 | Reply-to-email comment posting |
| 3.1.3 | `avc_error_report` | 1 test file | ~500 | Error reporting to GitLab |

### 3.2 Workflow Access Control: Documented but Not Implemented

**Impact:** Documentation in `docs/workflow/WORKFLOW_ACCESS_CONTROL.md` describes a node access control system that does not exist in code.

| # | Component | Expected | Actual |
|---|-----------|----------|--------|
| 3.2.1 | `WorkflowParticipantResolver` | Service class in `src/Service/` | **Does not exist** |
| 3.2.2 | `WorkflowAccessManager` | Service class in `src/Access/` | **Does not exist** |
| 3.2.3 | `WorkflowAccessSubscriber` | Event subscriber in `src/EventSubscriber/` | **Does not exist** |
| 3.2.4 | `hook_node_access()` | Full access control logic | **Returns `AccessResult::neutral()` only** |
| 3.2.5 | Access control settings | Section in `WorkflowAssignmentSettingsForm` | **Not present** |
| 3.2.6 | Services registration | 2 new services in `.services.yml` | **Not registered** |

### 3.3 Module Directory Inconsistency

| # | Module | Location | Expected Location | Issue |
|---|--------|----------|-------------------|-------|
| 3.3.1 | `avc_error_report` | `modules/custom/` | `modules/avc_features/` | Inconsistent with other AVC modules |
| 3.3.2 | `workflow_assignment` | `modules/custom/` | Could be either | Used to be standalone — acceptable but undocumented |

### 3.4 Notification Field Dependency

| # | Field | Referenced In | Created By | Status |
|---|-------|--------------|------------|--------|
| 3.4.1 | `field_notification_default` | `UserNotificationPreferencesForm.php` | `avc_notification.install` | OK |
| 3.4.2 | `field_notification_last_run` | `NotificationPreferences.php` | `avc_notification.install` | OK |
| 3.4.3 | `field_notification_override` | `UserNotificationPreferencesForm.php` | **Unknown** | **VERIFY** — may be missing from install hooks |

### 3.5 Skill Progression TODOs in Production Code

| # | File | TODO | Impact |
|---|------|------|--------|
| 3.5.1 | `SkillConfigurationService.php` | "Make this configurable per guild via a config entity or field. For now, return defaults." | Credit source config is hardcoded — works but inflexible |
| 3.5.2 | `SkillProgressionService.php` | "Filter by verifier eligibility (level, role)" | Verification queue may show ineligible verifiers |

### 3.6 Stub Hook in workflow_assignment

| # | Issue | Details |
|---|-------|---------|
| 3.6.1 | `hook_node_access()` at `workflow_assignment.module:64-67` returns `AccessResult::neutral()` unconditionally | Misleading — appears as if access control is wired up but does nothing |

---

## 4. Phased Remediation Plan

### Phase A: Critical Fixes (Profile & Installation)

These items affect whether completed features are available to users on new installations.

| # | Task | Files Affected | Priority |
|---|------|---------------|----------|
| A.1 | Add `avc:avc_work_management` to `avc.info.yml` install list | `avc.info.yml` | **Critical** |
| A.2 | Add `avc:avc_email_reply` to `avc.info.yml` install list | `avc.info.yml` | **Critical** |
| A.3 | Add `avc:avc_error_report` to `avc.info.yml` install list (or `avc.installer_options_list.yml` as optional) | `avc.info.yml` or `avc.installer_options_list.yml` | **Critical** |
| A.4 | Verify `field_notification_override` exists on group membership entities; add to install hook if missing | `avc_notification.install` or `avc_guild.install` | **High** |
| A.5 | Move `avc_error_report` from `modules/custom/` to `modules/avc_features/` for consistency | Directory move | **Medium** |
| A.6 | Remove or comment out the no-op `hook_node_access()` stub in `workflow_assignment.module` to avoid confusion | `workflow_assignment.module` | **Medium** |

### Phase B: Documentation Corrections

Factual inaccuracies and stale information that could mislead developers or users.

| # | Task | File | Priority |
|---|------|------|----------|
| B.1 | Update version number in EXECUTIVE_SUMMARY.md to reflect current state | `docs/EXECUTIVE_SUMMARY.md` | **High** |
| B.2 | Add `avc_email_reply`, `avc_error_report`, `avc_work_management` to module listing in EXECUTIVE_SUMMARY.md | `docs/EXECUTIVE_SUMMARY.md` | **High** |
| B.3 | Add `avc_email_reply`, `avc_error_report` to Module Architecture section of README.md | `README.md` | **High** |
| B.4 | Fix phase numbering: standardise `avc_work_management` as "Phase 5.5" in README.md (currently says "Phase 6") | `README.md` | **High** |
| B.5 | Add missing services to Key Services section of README.md | `README.md` | **Medium** |
| B.6 | Update `modules/custom/` directory in README.md directory structure section | `README.md` | **Medium** |
| B.7 | Add cross-references to Workflow System Implementation Plan in IMPLEMENTATION_PLAN.md | `docs/IMPLEMENTATION_PLAN.md` | **Medium** |
| B.8 | Add cross-reference to Guild Multiple Verification Types proposal in IMPLEMENTATION_PLAN.md | `docs/IMPLEMENTATION_PLAN.md` | **Medium** |
| B.9 | Document `level_verification_vote` custom table in Phase 5.1 section of IMPLEMENTATION_PLAN.md | `docs/IMPLEMENTATION_PLAN.md` | **Low** |
| B.10 | Add "Historical snapshot — see current code for latest state" header to CODE_ANALYSIS_REPORT_2026-01-13.md | `docs/CODE_ANALYSIS_REPORT_2026-01-13.md` | **Low** |

### Phase C: Test Coverage

Modules ranked by risk (complexity x user impact x current coverage).

| # | Task | Module | Current Tests | Risk Without Tests |
|---|------|--------|--------------|-------------------|
| C.1 | Write unit tests for `SkillProgressionService` and `SkillConfigurationService` | `avc_guild` | 0 | **Critical** — most complex module, 7 entities, 6 services |
| C.2 | Write functional tests for guild dashboard, verification queue, skill admin | `avc_guild` | 0 | **Critical** — untested user-facing workflows |
| C.3 | Write unit tests for `NotificationProcessor`, `NotificationAggregator`, `NotificationSender` | `avc_notification` | 0 | **High** — core infrastructure, cron-driven |
| C.4 | Write functional tests for notification preferences form and admin controller | `avc_notification` | 0 | **High** — user-facing configuration |
| C.5 | Write unit tests for `AssetManager`, `WorkflowChecker`, `WorkflowProcessor` | `avc_asset` | 0 | **High** — business-critical asset management |
| C.6 | Write unit tests for `WorkflowNotificationService` and `WorkflowHistoryLogger` | `workflow_assignment` | 0 | **Medium** — core workflow engine services |
| C.7 | Write kernel tests for `avc_email_reply` services | `avc_email_reply` | Unit only | **Medium** — needs integration-level tests |
| C.8 | Write functional tests for `avc_error_report` form submission and GitLab integration | `avc_error_report` | Unit only | **Medium** — needs end-to-end tests |

### Phase D: Code Completeness

Resolve TODOs and incomplete implementations in shipped code.

| # | Task | File | Priority |
|---|------|------|----------|
| D.1 | Implement per-guild credit source configuration (replace hardcoded defaults) | `SkillConfigurationService.php` | **Medium** |
| D.2 | Implement verifier eligibility filtering in verification queue | `SkillProgressionService.php` | **Medium** |
| D.3 | Add Views integration for NotificationQueue entity (default admin view) | `avc_notification` | **Low** |
| D.4 | Add one-click unsubscribe links to notification email templates | `avc_notification` templates | **Low** |

### Phase E: Documentation Organisation & Polish

Restructure docs for clarity; add missing docs.

| # | Task | Details | Priority |
|---|------|---------|----------|
| E.1 | Move `WORKFLOW_ACCESS_CONTROL.md` from `docs/workflow/` to `docs/proposals/` | Currently misleadingly positioned alongside implemented features | **High** |
| E.2 | Add "STATUS: IMPLEMENTED" header to `docs/proposals/AVC_ERROR_REPORTING_MODULE.md` | Proposal is complete — reader may not realise code exists | **Medium** |
| E.3 | Add "STATUS: IMPLEMENTED" header to `docs/proposals/GROUP_EMAIL_REPLY_SYSTEM.md` | Proposal is complete — reader may not realise code exists | **Medium** |
| E.4 | Cut new CHANGELOG version for accumulated unreleased changes | Separate proposals/docs from code changes | **Medium** |
| E.5 | Write `avc_devel` usage documentation | Document Drush commands: `avc:generate-content`, `avc:cleanup-content` | **Medium** |
| E.6 | Write theme architecture documentation | Document `socialbase → socialblue → avc_theme` inheritance | **Low** |
| E.7 | Write profile installation documentation | Document install tasks, demo content, requirements checking | **Low** |
| E.8 | Write configuration architecture documentation | Document `config/install/` vs `config/optional/` contents | **Low** |
| E.9 | Document the distinction between `modules/custom/` and `modules/avc_features/` | Or consolidate all modules into one location | **Low** |

### Phase F: Future Implementation Readiness

Proposals that have detailed designs ready for implementation.

| # | Proposal | Document | Design Completeness | Dependencies |
|---|----------|----------|--------------------|----|
| F.1 | Workflow Access Control | `docs/workflow/WORKFLOW_ACCESS_CONTROL.md` | Complete design — entities, services, hooks, settings all specified | None |
| F.2 | Guild Multiple Verification Types | `docs/proposals/GUILD_MULTIPLE_VERIFICATION_TYPES.md` | Complete design — entity changes, service logic, UI updates, migration plan | F.1 not required |
| F.3 | Time-Limited Task Claiming | `docs/WORKFLOW_SYSTEM_COMPLETE_IMPLEMENTATION_PLAN.md` Phase 1 | Complete design — fields, services, cron, UI, notifications | None |
| F.4 | Destination Access Control | `docs/WORKFLOW_SYSTEM_COMPLETE_IMPLEMENTATION_PLAN.md` Phase 2 | Complete design — taxonomy enhancement, new module, file migration | F.3 recommended first |
| F.5 | Versioning & Re-Edit | `docs/WORKFLOW_SYSTEM_COMPLETE_IMPLEMENTATION_PLAN.md` Phase 3 | Complete design — version fields, diff comparison, re-edit workflow | F.3 and F.4 recommended first |
| F.6 | Phase 6: Forums | `docs/IMPLEMENTATION_PLAN.md` | Minimal design — leverage Open Social Topics | None |

---

## 5. Version & Release Recommendation

### 5.1 Immediate Release: v0.5.2

Cut a release for accumulated bug fixes and documentation:

```
## [v0.5.2] - 2026-02-21

### Fixed
- Fixed TypeError in VerificationQueueController by loading UserInterface
- Fixed FieldItemList errors in skill progress pages
- Fixed FieldItemList errors in leaderboard and member profile pages
- Added missing getter methods to SkillCredit entity
- Fixed field_group_affiliation configuration to allow all group bundles
- Fixed Guild dashboard rendering and added missing role configurations

### Changed
- Secured help pages and updated branding for AV Commons
- Added automated sample content generation script for avc-dev
- Use complex random passwords for social demo users

### Documentation
- Guild Multiple Verification Types proposal
- Guild progression documentation
- Workflow System Implementation Plan (three-phase proposal)
- Endpoints and Destinations research documentation
```

### 5.2 Next Feature Release: v0.6.0

After completing Phase A (profile installation fixes):

```
## [v0.6.0] - TBD

### Added
- avc_work_management added to default profile installation
- avc_email_reply added to default profile installation
- avc_error_report added to default profile installation

### Fixed
- Removed no-op hook_node_access stub from workflow_assignment
- Verified notification field dependencies
```

### 5.3 Future Releases

| Version | Content | Depends On |
|---------|---------|-----------|
| v0.6.1 | Phase C test coverage for `avc_guild` and `avc_notification` | v0.6.0 |
| v0.7.0 | Workflow Access Control implementation (F.1) | v0.6.0 |
| v0.8.0 | Guild Multiple Verification Types (F.2) or Time-Limited Claims (F.3) | v0.7.0 recommended |
| v0.9.0 | Destination Access Control (F.4) + Versioning (F.5) | v0.8.0 |
| v1.0.0 | Production-ready release with full test coverage | All above |

---

## 6. Post-Audit Actions Taken

The following issues identified in this audit were addressed on 2026-02-21 (commit `45b7bc2c8`).

### 6.1 Email Reply Security Fixes (Completed)

| # | Issue | Fix | Commit |
|---|-------|-----|--------|
| 6.1.1 | Open webhook endpoint — `validateSendGridSignature()` returned TRUE when no signature headers present | Replaced with provider-aware `validateWebhookSignature()` dispatching to SendGrid, Mailgun, or local validators. All fail closed. | `45b7bc2c8` |
| 6.1.2 | Hardcoded spam threshold (5.0) | Now reads from `avc_email_reply.settings.spam_score_threshold` config | `45b7bc2c8` |
| 6.1.3 | Service locator in `getCommentFieldName()` — `\Drupal::service('entity_field.manager')` | Injected `EntityFieldManagerInterface` via constructor and `services.yml` | `45b7bc2c8` |
| 6.1.4 | Rate limiter state key accumulation — hourly/daily keys never cleaned up | Added key tracking list and `cleanupStaleKeys()` method called from `hook_cron()` | `45b7bc2c8` |
| 6.1.5 | PSR-4 violation — `ProcessResult` and `SecurityCheckResult` defined inline in `EmailReplyProcessor.php` | Extracted to `src/Service/ProcessResult.php` and `src/Service/SecurityCheckResult.php` | `45b7bc2c8` |

### 6.2 Email Infrastructure Setup (Completed)

Server-side email infrastructure was configured on `git.nwpcode.org` (same host as `avc.nwpcode.org`):

| # | Component | Status | Details |
|---|-----------|--------|---------|
| 6.2.1 | `reply@nwpcode.org` email alias | **Done** | Added to `/etc/postfix/virtual`, forwards to Gmail + pipes to webhook |
| 6.2.2 | `noreply@nwpcode.org` email alias | **Done** | Added to `/etc/postfix/virtual`, forwards to Gmail |
| 6.2.3 | Postfix `recipient_delimiter = +` | **Already configured** | Enables `reply+{token}@nwpcode.org` addressing |
| 6.2.4 | Pipe delivery script | **Done** | `/usr/local/bin/avc-email-reply-pipe.sh` — reads email from stdin, POSTs to local webhook |
| 6.2.5 | Postfix pipe transport | **Done** | `reply-pipe` alias in `/etc/aliases` pipes to delivery script |
| 6.2.6 | Webhook secret generated | **Done** | `cb092a30a5dd542ac44fdf0dadc19c08016268d4e25e27a75dc251846aee9445` |
| 6.2.7 | DKIM signing | **Already configured** | Verified outbound mail from nwpcode.org is DKIM signed |

---

## 7. Remaining Work

### 7.1 Email Reply Module Deployment (Blocked — fail2ban)

SSH access to `git.nwpcode.org` is currently blocked by fail2ban. Once restored, complete these steps:

| # | Task | Command / Details | Priority |
|---|------|-------------------|----------|
| 7.1.1 | Sync updated module code to live server | `tar czf - avc_email_reply/ \| ssh gitlab@git.nwpcode.org "sudo tar xzf - -C /var/www/avc/html/profiles/custom/avc/modules/avc_features/ && sudo chown -R www-data:www-data ..."` | **Critical** |
| 7.1.2 | Enable module on live site | `sudo -u www-data vendor/bin/drush en avc_email_reply -y` | **Critical** |
| 7.1.3 | Set `enabled: true` | `drush cset avc_email_reply.settings enabled 1 -y` | **Critical** |
| 7.1.4 | Set `email_provider: local` | `drush cset avc_email_reply.settings email_provider local -y` | **Critical** |
| 7.1.5 | Set `reply_domain: nwpcode.org` | `drush cset avc_email_reply.settings reply_domain nwpcode.org -y` | **Critical** |
| 7.1.6 | Set webhook secret | `drush cset avc_email_reply.settings webhook_secret cb092a30a5dd542ac44fdf0dadc19c08016268d4e25e27a75dc251846aee9445 -y` | **Critical** |
| 7.1.7 | Clear caches | `drush cr` | **Critical** |

### 7.2 End-to-End Testing

| # | Task | Details | Priority |
|---|------|---------|----------|
| 7.2.1 | Create demo email accounts on nwpcode.org | Add `testuser1@nwpcode.org` and `testuser2@nwpcode.org` to `/etc/postfix/virtual` | **High** |
| 7.2.2 | Verify webhook endpoint responds | `curl -s -o /dev/null -w "%{http_code}" https://avc.nwpcode.org/api/email/inbound` — expect 401 (no secret) | **High** |
| 7.2.3 | Verify webhook accepts valid local request | POST from localhost with correct `X-Webhook-Secret` header — expect 200 | **High** |
| 7.2.4 | Test token generation | Create a notification email and verify Reply-To header contains `reply+{token}@nwpcode.org` | **High** |
| 7.2.5 | Test full reply flow | Reply to notification email, verify comment is created on the correct entity | **High** |
| 7.2.6 | Test rate limiting | Send multiple rapid replies and verify rate limiter blocks excess | **Medium** |
| 7.2.7 | Test spam score rejection | Send email with high spam score and verify rejection | **Low** |
| 7.2.8 | Test invalid token handling | Send email with fabricated token and verify rejection | **Medium** |

### 7.3 Profile Installation Fixes (From Section 3.1)

| # | Task | Status | Priority |
|---|------|--------|----------|
| 7.3.1 | Add `avc_work_management` to `avc.info.yml` install list | **✅ Complete** | **Critical** |
| 7.3.2 | Add `avc_email_reply` to `avc.info.yml` install list | **✅ Complete** | **Critical** |
| 7.3.3 | Add `avc_error_report` to `avc.info.yml` install list (or as optional) | **✅ Complete** | **Critical** |
| 7.3.4 | Verify `field_notification_override` exists; add to install hook if missing | **✅ Complete** | **High** |

### 7.4 Documentation Corrections (From Section 4, Phase B)

| # | Task | Status | Priority |
|---|------|--------|----------|
| 7.4.1 | Update EXECUTIVE_SUMMARY.md — version, module count, module listing | **✅ Complete** | **High** |
| 7.4.2 | Update README.md — add missing modules, fix phase numbering, add services | **✅ Complete** | **High** |
| 7.4.3 | Update IMPLEMENTATION_PLAN.md — cross-references, custom table docs | **✅ Complete** | **Medium** |
| 7.4.4 | Mark CODE_ANALYSIS_REPORT_2026-01-13.md as historical snapshot | **✅ Complete** | **Low** |
| 7.4.5 | Move WORKFLOW_ACCESS_CONTROL.md to `docs/proposals/` | **✅ Complete** | **High** |
| 7.4.6 | Mark implemented proposals (error reporting, email reply) with STATUS: IMPLEMENTED | **✅ Complete** | **Medium** |

### 7.5 Test Coverage (From Section 4, Phase C)

| # | Module | Current Coverage | Target | Priority |
|---|--------|-----------------|--------|----------|
| 7.5.1 | `avc_guild` | **✅ 2 unit test files** (ScoringServiceTest, EndorsementServiceTest) | Unit + functional for 6 services, 7 entities | **Partial** |
| 7.5.2 | `avc_notification` | **✅ 1 unit test file** (NotificationProcessorTest) | Unit + functional for 5 services | **Partial** |
| 7.5.3 | `avc_asset` | **✅ 1 unit test file** (WorkflowCheckerTest) | Unit for 3 services | **Partial** |
| 7.5.4 | `workflow_assignment` | **✅ 3 unit test files** (WorkflowHistoryLoggerTest, WorkflowParticipantResolverTest, WorkflowAccessManagerTest) + 1 functional | Unit for 2 services | **Complete** |
| 7.5.5 | `avc_email_reply` | 3 unit tests | Add kernel tests | **Medium** |
| 7.5.6 | `avc_error_report` | 1 unit test | Add functional tests | **Medium** |

### 7.6 Code Completeness (From Section 4, Phase D)

| # | Task | Status | Priority |
|---|------|--------|----------|
| 7.6.1 | Implement per-guild credit source configuration | **✅ Complete** — reads from `field_credit_sources` with defaults fallback | **Medium** |
| 7.6.2 | Implement verifier eligibility filtering | **✅ Complete** — guild membership check + level requirement in `canVerify()` | **Medium** |

### 7.7 Documentation Organisation (From Section 4, Phase E)

| # | Task | Status | Priority |
|---|------|--------|----------|
| 7.7.1 | Move WORKFLOW_ACCESS_CONTROL.md to proposals | **✅ Complete** | **High** |
| 7.7.2 | Mark implemented proposals | **✅ Complete** | **Medium** |
| 7.7.3 | Cut CHANGELOG v0.6.0 | **✅ Complete** | **Medium** |
| 7.7.4 | Write avc_devel usage docs | **✅ Complete** — `docs/AVC_DEVEL_USAGE.md` | **Medium** |
| 7.7.5 | Write theme architecture docs | **✅ Complete** — `docs/THEME_ARCHITECTURE.md` | **Low** |
| 7.7.6 | Write profile installation docs | **✅ Complete** — `docs/PROFILE_INSTALLATION.md` | **Low** |

### 7.8 Feature Implementation (From Section 4, Phase F)

| # | Proposal | Status | Priority |
|---|----------|--------|----------|
| 7.8.1 | F.1: Workflow Access Control | **✅ Complete** — WorkflowParticipantResolver, WorkflowAccessManager, hook_node_access, cache invalidation, settings form, tests | **High** |
| 7.8.2 | F.6: Phase 6 Forums | **✅ Complete** — Topic notification integration with create_topic and topic_comment events | **Low** |
| 7.8.3 | F.2: Guild Multiple Verification Types | Not started | **Medium** |
| 7.8.4 | F.3: Time-Limited Task Claiming | Not started | **Medium** |

---

*Report generated 2026-02-21 by automated codebase analysis.*
*Updated 2026-02-22 with completed remediation phases A–F.1, F.6.*
