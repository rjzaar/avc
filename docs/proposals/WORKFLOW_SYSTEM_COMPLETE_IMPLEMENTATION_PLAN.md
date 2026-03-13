# AVC Workflow System: Complete Implementation Plan

**Status:** PROPOSED
**Created:** January 18, 2026
**Scope:** Resource creation, workflow, versioning, claiming, and destinations

---

## Executive Summary

This plan details a comprehensive enhancement to the AVC workflow system covering:

1. **Post-Workflow Destinations** - Taxonomy-based access control for completed content
2. **Versioning & Re-Edit** - Semantic versioning (major.minor) with re-edit workflow support
3. **Time-Limited Task Claiming** - Users claim group tasks with configurable time limits and auto-release

**Key Recommendation:** Use taxonomy-based destinations (not FolderShare) for simplicity and alignment with existing architecture.

---

## Table of Contents

1. [Current State Analysis](#1-current-state-analysis)
2. [System Architecture Overview](#2-system-architecture-overview)
3. [Phase 1: Destination Access Control](#3-phase-1-destination-access-control)
4. [Phase 2: Versioning & Re-Edit](#4-phase-2-versioning--re-edit)
5. [Phase 3: Time-Limited Task Claiming](#5-phase-3-time-limited-task-claiming)
6. [Module Dependencies](#6-module-dependencies)
7. [Implementation Sequence](#7-implementation-sequence)
8. [Critical Files](#8-critical-files)
9. [Verification Plan](#9-verification-plan)

---

## 1. Current State Analysis

### What Already Exists

| Component | Status | Location |
|-----------|--------|----------|
| WorkflowTask entity | ✅ Complete | `workflow_assignment/src/Entity/WorkflowTask.php` |
| Claim mechanism (basic) | ✅ Complete | `avc_work_management/src/Service/WorkTaskActionService.php` |
| Group assignment | ✅ Complete | Entity fields + `avc_group/src/Service/GroupWorkflowService.php` |
| Access control | ✅ Partial | `WorkflowTaskAccessControlHandler.php` |
| Task dashboard | ✅ Complete | `avc_work_management/src/Controller/MyWorkController.php` |
| Node revisions | ✅ Enabled | `new_revision: true` on all asset types |
| destination_locations taxonomy | ✅ Basic | Created in `workflow_assignment.install` |
| History logging | ✅ Complete | `workflow_assignment_history` table |

### What's Missing

| Feature | Priority | Complexity |
|---------|----------|------------|
| Time-limited claims with auto-release | High | Medium |
| Destination-based access control | High | Medium |
| File scheme migration (public/private) | Medium | Low |
| Semantic versioning | Medium | Medium |
| Re-edit workflow | Medium | Medium |
| Version history UI | Low | Medium |

---

## 2. System Architecture Overview

### Complete Content Lifecycle

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                          AVC CONTENT LIFECYCLE                                │
├──────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  CREATE (v1.0)          WORKFLOW                    DESTINATION              │
│  ────────────          ─────────                   ───────────               │
│                                                                              │
│  ┌─────────┐     ┌─────────────────────┐     ┌────────────────────────┐     │
│  │  Draft  │ ──► │ Stage 1: Review     │ ──► │ Public Website         │     │
│  │  v1.0   │     │ (Group claims task) │     │ - Anonymous access     │     │
│  └─────────┘     │ ┌───────────────┐   │     │ - public:// files      │     │
│       │          │ │ CLAIMED by    │   │     │ - /public/docs/{title} │     │
│       │          │ │ User A        │   │     ├────────────────────────┤     │
│       ▼          │ │ Expires: 24h  │   │     │ Member Portal          │     │
│  ┌─────────┐     │ │ [Extend]      │   │     │ - Auth required        │     │
│  │ Edited  │     │ └───────────────┘   │     │ - private:// files     │     │
│  │  v1.1   │     ├─────────────────────┤     │ - /members/docs/{title}│     │
│  └─────────┘     │ Stage 2: Approve    │     ├────────────────────────┤     │
│                  │ (User assignment)   │     │ Group: Board           │     │
│                  ├─────────────────────┤     │ - Board members only   │     │
│                  │ Stage 3: Destination│     │ - /groups/board/{title}│     │
│                  │ (Final location)    │     └────────────────────────┘     │
│                  └─────────────────────┘              │                      │
│                                                       ▼                      │
│                                              ┌────────────────┐              │
│                                              │ RE-EDIT (v2.0) │              │
│                                              │ New workflow   │              │
│                                              │ cycle begins   │              │
│                                              └────────────────┘              │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Access Control Flow

```
Request: GET /node/123 or /members/documents/safety-protocol
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│ 1. Has ACTIVE workflow? (pending/in_progress tasks)     │
│    YES → WorkflowAccessManager::checkAccess()           │
│          - Author, assignees, participants allowed      │
│          - Others denied                                │
│    NO  → Continue to destination check                  │
└─────────────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│ 2. DestinationAccessManager::checkAccess()              │
│    public        → Allow anonymous                      │
│    authenticated → Require login                        │
│    group         → Check group membership               │
│    private       → Author/admin only                    │
└─────────────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│ 3. File access (if applicable)                          │
│    - Inherit from parent node                           │
│    - private:// requires access check                   │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Phase 1: Destination Access Control

### 3.1 Recommendation: Taxonomy over FolderShare

| Criterion | Taxonomy | FolderShare |
|-----------|----------|-------------|
| Already exists | ✅ Yes | ❌ No |
| Integration effort | Low | High |
| Group module compat | ✅ Native | Needs custom |
| Learning curve | None | New paradigm |

**Decision:** Enhance existing `destination_locations` taxonomy.

### 3.2 Taxonomy Field Additions

Add to `destination_locations` vocabulary:

| Field | Type | Values/Description |
|-------|------|---------------------|
| `field_access_level` | list_string | public, authenticated, group, private |
| `field_access_groups` | entity_reference (group) | Multiple groups |
| `field_file_scheme` | list_string | public://, private:// |
| `field_url_pattern` | string | /public/documents, /members/documents, etc. |
| `field_auto_publish` | boolean | Auto-publish on workflow completion |

### 3.3 Default Destinations

| Name | access_level | file_scheme | url_pattern |
|------|--------------|-------------|-------------|
| Public Website | public | public:// | /public/documents/{title} |
| Member Portal | authenticated | private:// | /members/documents/{title} |
| Board Documents | group (board) | private:// | /groups/board/documents/{title} |
| TNW Guides | group (tnw_team) | private:// | /groups/tnw/guides/{title} |
| Private Archive | private | private:// | /node/{nid} |

### 3.4 New Module: avc_content_access

```
avc_content_access/
├── src/
│   ├── Access/
│   │   ├── DestinationAccessManager.php
│   │   └── DestinationRouteAccess.php
│   ├── Service/
│   │   ├── FileAccessService.php
│   │   └── FileMigrationService.php
│   └── EventSubscriber/
│       └── WorkflowCompletionSubscriber.php
├── avc_content_access.module  (hook_node_access, hook_file_download)
└── avc_content_access.routing.yml
```

### 3.5 Access Control Hooks

```php
// hook_node_access()
function avc_content_access_node_access($node, $operation, $account) {
  $manager = \Drupal::service('avc_content_access.destination_access_manager');
  return $manager->checkAccess($node, $operation, $account);
}

// hook_file_download()
function avc_content_access_file_download($uri) {
  $service = \Drupal::service('avc_content_access.file_access');
  return $service->checkFileAccess($uri, \Drupal::currentUser());
}
```

### 3.6 File Migration on Workflow Completion

When destination task completes:
1. Determine target file scheme from destination
2. Move files from public:// to private:// (or vice versa)
3. Update file entity URIs
4. Log migration results

---

## 4. Phase 2: Versioning & Re-Edit

### 4.1 Version Numbering Scheme

```
Format: MAJOR.MINOR

MAJOR: Increments when workflow completes (reaches destination)
MINOR: Increments for edits within a workflow cycle

Timeline:
1.0 → 1.1 → 1.2 → [workflow completes] → 2.0 → 2.1 → [completes] → 3.0
```

### 4.2 New Node Fields

| Field | Type | Description |
|-------|------|-------------|
| `field_version_major` | integer | Major version (default 1) |
| `field_version_minor` | integer | Minor version (default 0) |
| `field_workflow_status` | list_string | draft, in_workflow, published, reedit |
| `field_active_workflow_cycle` | integer | Current cycle number |

### 4.3 WorkflowTask Field Additions

| Field | Type | Description |
|-------|------|-------------|
| `node_vid` | integer | Node revision when task created |
| `workflow_cycle` | integer | Which version cycle this belongs to |
| `approved_vid` | integer | Node revision that was approved |

### 4.4 Re-Edit Workflow

**New Service:** `ReEditWorkflowService`

```php
initiateReEdit($node, $options)
  // 1. Validate no active workflow
  // 2. Increment major version, reset minor to 0
  // 3. Set workflow_status = 'reedit'
  // 4. Increment workflow_cycle
  // 5. Create new workflow tasks from template
  // 6. Log to version history
```

**New Form:** `/node/{node}/reedit`
- Template selection (full, abbreviated, custom)
- Reason for re-edit (required)
- Shows version transition (e.g., "1.2 → 2.0")

### 4.5 Version History UI

**New Route:** `/node/{node}/versions`

Features:
- Timeline showing all workflow cycles
- Table of all versions with dates/status
- Checkbox selection for comparing two versions
- Side-by-side diff view

### 4.6 Parallel Editing Prevention

- Check `field_workflow_status` before allowing edits
- During active workflow: edits create minor version
- After publication: must use re-edit workflow for changes

---

## 5. Phase 3: Time-Limited Task Claiming

### 5.1 New Entity Fields

Add to `WorkflowTask` entity:

| Field | Type | Description |
|-------|------|-------------|
| `claimed_at` | timestamp | When task was claimed |
| `claim_expires` | timestamp | When claim auto-releases |
| `original_group` | entity_reference (group) | Group to return to on release |
| `extension_count` | integer | Times claim has been extended |
| `expiry_warning_sent` | boolean | Prevents duplicate warnings |

### 5.2 Configuration

```yaml
# avc_work_management.claim_settings.yml
default_claim_duration: 24  # hours
max_extensions: 2
extension_duration: 24      # hours per extension
warning_threshold: 4        # hours before expiry to warn
allow_self_extension: true
```

### 5.3 Service Enhancements

**WorkTaskActionService** - Enhanced methods:

```php
claimTask($task, $user)      // Sets claimed_at, claim_expires, original_group
releaseTask($task, $reason)  // Restores to original_group
extendClaim($task, $user)    // Adds extension_duration, increments extension_count
forceRelease($task, $admin)  // Admin override
getClaimTimeRemaining($task) // Returns seconds remaining
isClaimExpired($task)        // Boolean check
```

**ClaimExpirationService** (NEW):

```php
processExpiredClaims()  // Called by cron, releases expired claims
sendExpiryWarnings()    // Sends warnings for approaching expiry
```

### 5.4 Cron Job

```php
function avc_work_management_cron() {
  $service = \Drupal::service('avc_work_management.claim_expiration');
  $service->processExpiredClaims();  // Auto-release expired
  $service->sendExpiryWarnings();    // Warn users
}
```

### 5.5 New UI Elements

| Route | Form | Purpose |
|-------|------|---------|
| `/my-work/extend/{task}` | ExtendClaimForm | Request more time |
| `/my-work/release/{task}` | ReleaseTaskForm | Voluntarily release |
| `/admin/workflow-task/{task}/force-release` | AdminForceReleaseForm | Admin override |

### 5.6 Notifications

- **Claim expiry warning** - 4 hours before expiry
- **Claim expired** - When auto-released
- **Task returned to pool** - Notify group members

---

## 6. Module Dependencies

### Existing (No Changes)

- `workflow_assignment` - Core workflow entities
- `avc_work_management` - Dashboard and claiming
- `avc_asset` - Asset content types
- `avc_notification` - Notification system
- `group` - Group functionality

### New Modules

| Module | Purpose | Dependencies |
|--------|---------|--------------|
| `avc_content_access` | Destination access control | workflow_assignment, group |

### Considered but NOT Recommended

| Module | Reason for Rejection |
|--------|---------------------|
| FolderShare | Integration complexity, existing taxonomy approach better |
| content_lock | Already have claim mechanism in workflow_assignment |
| content_moderation | Already have custom workflow system |

---

## 7. Implementation Sequence

### Week 1-2: Destination Access

1. Create avc_content_access module skeleton
2. Add taxonomy fields via config
3. Create default destination terms
4. Implement DestinationAccessManager
5. Add hook_node_access()
6. Implement FileAccessService
7. Add hook_file_download()
8. Implement FileMigrationService
9. Create WorkflowCompletionSubscriber
10. Add URL routes

### Week 3-4: Versioning & Re-Edit

1. Add version fields to node types
2. Add WorkflowTask revision fields
3. Create version history table
4. Implement version increment on save
5. Create ReEditWorkflowService
6. Build ReEditWorkflowForm
7. Create VersionHistoryController
8. Build version comparison UI

### Week 5-6: Time-Limited Claims

1. Add entity fields to WorkflowTask (claimed_at, claim_expires, etc.)
2. Create database update hook
3. Add configuration schema and form
4. Enhance WorkTaskActionService
5. Create ClaimExpirationService
6. Add cron hook
7. Create UI forms (extend, release, force-release)
8. Add notification templates

### Week 7: Testing & Documentation

1. Unit tests for all services
2. Functional tests for forms
3. Integration tests for workflows
4. Manual testing per checklists
5. Documentation updates

---

## 8. Critical Files

### To Modify

| File | Changes |
|------|---------|
| `workflow_assignment/src/Entity/WorkflowTask.php` | Add claimed_at, claim_expires, original_group, node_vid, workflow_cycle, approved_vid |
| `workflow_assignment/workflow_assignment.install` | Update hooks for new fields/tables |
| `workflow_assignment/workflow_assignment.module` | Add hook_node_presave for versioning |
| `avc_work_management/src/Service/WorkTaskActionService.php` | Enhance claim methods with time limits |
| `avc_work_management/avc_work_management.module` | Add cron hook |

### To Create

| File | Purpose |
|------|---------|
| `avc_work_management/src/Service/ClaimExpirationService.php` | Cron processing |
| `avc_work_management/src/Form/ExtendClaimForm.php` | Extend claim UI |
| `avc_work_management/src/Form/ReleaseTaskForm.php` | Release claim UI |
| `avc_content_access/` (entire module) | Destination access control |
| `workflow_assignment/src/Service/ReEditWorkflowService.php` | Re-edit initiation |
| `workflow_assignment/src/Controller/VersionHistoryController.php` | Version UI |

---

## 9. Verification Plan

### Automated Tests

```bash
# Run all tests for affected modules
vendor/bin/phpunit profiles/custom/avc/modules/avc_features/workflow_assignment/tests/
vendor/bin/phpunit profiles/custom/avc/modules/avc_features/avc_work_management/tests/
vendor/bin/phpunit profiles/custom/avc/modules/avc_features/avc_content_access/tests/
```

### Manual Testing Checklist

**Time-Limited Claims**
- [ ] User can claim group-assigned task
- [ ] Claim shows expiration time in UI
- [ ] Warning notification sent before expiry
- [ ] Task auto-releases after expiry via cron
- [ ] User can extend claim (up to max)
- [ ] User can voluntarily release
- [ ] Admin can force-release
- [ ] Released task appears in group's available pool

**Destination Access**
- [ ] Public content accessible to anonymous
- [ ] Member content requires login
- [ ] Group content restricted to members
- [ ] Private content author/admin only
- [ ] Files follow same access rules
- [ ] Files migrate to correct scheme on completion
- [ ] URL patterns work correctly

**Versioning & Re-Edit**
- [ ] New content starts at v1.0
- [ ] Edits during workflow increment minor (1.1, 1.2)
- [ ] Workflow completion increments major (2.0)
- [ ] Re-edit form shows version transition
- [ ] New workflow tasks created for re-edit
- [ ] Version history shows all cycles
- [ ] Can compare any two versions
- [ ] No parallel workflows on same content

### Integration Verification

```bash
# Clear caches and verify
drush cr

# Run workflow cron manually
drush eval "\Drupal::service('avc_work_management.claim_expiration')->processExpiredClaims();"

# Verify database schema
drush sqlq "DESCRIBE workflow_task"
drush sqlq "SELECT * FROM taxonomy_term__field_access_level"
```

---

## Appendix A: Best Practices Research Summary

### Content Locking Patterns

| Pattern | Use Case | AVC Approach |
|---------|----------|--------------|
| Pessimistic (content_lock) | High-contention editing | Claim mechanism |
| Optimistic | Low-conflict | Not used |
| Time-limited | Prevent orphaned locks | claim_expires + cron |

### Document Management Patterns

| Feature | Industry Standard | AVC Implementation |
|---------|-------------------|-------------------|
| Check-out/Check-in | Lock on edit start | claimTask/releaseTask |
| Version numbering | Major.Minor.Patch | Major.Minor (simpler) |
| Revision tracking | Full history | Drupal revisions + version_history table |
| Access control | Role + content-based | Destination taxonomy |

### Drupal Modules Evaluated

| Module | Verdict | Reason |
|--------|---------|--------|
| FolderShare | Not recommended | Integration complexity |
| content_lock | Not needed | Custom claim exists |
| content_moderation | Not needed | Custom workflow exists |
| diff | Consider | For version comparison |

---

## Appendix B: Configuration Examples

### Claim Settings Admin Form

```
Default Claim Duration: [24] hours
Maximum Extensions: [2]
Extension Duration: [24] hours
Warning Threshold: [4] hours before expiry
[x] Allow users to extend their own claims

Per Content Type Overrides:
  avc_document: [24] hours, [2] extensions
  avc_resource: [48] hours, [3] extensions
  avc_project:  [72] hours, [3] extensions
```

### Destination Term Configuration

```
Name: Board Documents
Access Level: [Group]
Access Groups: [Board of Directors]
File Scheme: [private://]
URL Pattern: /groups/board/documents
[x] Auto-publish on workflow completion
```

---

*Document created: January 18, 2026*
