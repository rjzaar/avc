# AVC Work Management Implementation Plan

**Status:** PLANNED
**Created:** January 11, 2026
**Last Updated:** January 11, 2026
**Related Modules:** workflow_assignment, avc_asset, avc_work_management

---

## Executive Summary

This implementation plan covers the complete workflow lifecycle for AVC content (Documents, Resources, Projects):

1. **Work Dashboard** - Users see what they can work on, are working on, and have completed
2. **Workflow Access Control** - Only workflow participants can access content during workflow
3. **Post-Workflow Destinations** - Completed content is accessible based on configured endpoints

---

## Table of Contents

- [Current State](#current-state)
- [Target State](#target-state)
- [Architecture Overview](#architecture-overview)
- [Phase 1: Work Management Dashboard](#phase-1-work-management-dashboard)
- [Phase 2: Workflow Access Control](#phase-2-workflow-access-control)
- [Phase 3: Post-Workflow Destinations](#phase-3-post-workflow-destinations)
- [Phase 4: File and Document Access](#phase-4-file-and-document-access)
- [Implementation Order](#implementation-order)
- [Testing Strategy](#testing-strategy)

---

## Current State

| Component | Status | Notes |
|-----------|--------|-------|
| WorkflowTask entity | Exists | Supports user/group/destination assignments |
| Workflow on nodes | Exists | `/node/{id}/workflow` tab |
| User dashboard | Basic | Shows tasks but no content type filtering |
| Group dashboard | Exists | `/group/{id}/workflow` |
| Workflow-based node access | Missing | Anyone with permission can view during workflow |
| Post-workflow access control | Missing | No destination-based access |
| File access control | Missing | Files accessible if you have the URL |

---

## Target State

```
CONTENT LIFECYCLE

┌──────────────────────────────────────────────────────────────────────────┐
│                                                                          │
│  CREATE              WORKFLOW                 DESTINATION                │
│  ──────              ────────                 ───────────                │
│                                                                          │
│  Author creates  →   Moves through    →    Published to endpoint:        │
│  AVC Document       workflow stages        - Public (anonymous)          │
│                     (restricted access)    - Members (authenticated)     │
│                                            - Group (specific group)      │
│                                            - Private (author only)       │
│                                                                          │
│  ┌─────────┐     ┌─────────────────┐     ┌─────────────────────────┐    │
│  │         │     │ Stage 1: Review │     │                         │    │
│  │ Draft   │ ──► │ Stage 2: Legal  │ ──► │  Final Destination      │    │
│  │         │     │ Stage 3: Approve│     │  (configured access)    │    │
│  └─────────┘     └─────────────────┘     └─────────────────────────┘    │
│       │                   │                          │                   │
│       ▼                   ▼                          ▼                   │
│  Author only      Workflow participants       Based on destination      │
│                   + Author + Admins           configuration             │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Architecture Overview

### Module Structure

```
avc_features/
├── workflow_assignment/          # EXISTS - Core workflow entities
│   └── (add access control extension)
│
├── avc_work_management/          # NEW - User dashboard
│   ├── src/Controller/MyWorkController.php
│   ├── src/Service/WorkTaskQueryService.php
│   └── templates/my-work-dashboard.html.twig
│
├── avc_asset/                    # EXISTS - Asset types
│   └── (add destination access integration)
│
└── avc_content_access/           # NEW - Post-workflow access control
    ├── src/Access/DestinationAccessManager.php
    └── src/EventSubscriber/ContentAccessSubscriber.php
```

### Access Control Layers

```
┌─────────────────────────────────────────────────────────────────┐
│                     ACCESS DECISION FLOW                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Request: GET /node/123                                         │
│                    │                                            │
│                    ▼                                            │
│  ┌─────────────────────────────────────────────┐               │
│  │ 1. Has active workflow?                     │               │
│  │    YES → Check workflow participation       │               │
│  │    NO  → Continue to destination check      │               │
│  └─────────────────────────────────────────────┘               │
│                    │                                            │
│                    ▼                                            │
│  ┌─────────────────────────────────────────────┐               │
│  │ 2. What is the destination?                 │               │
│  │    - Public → Allow anonymous               │               │
│  │    - Members → Require authentication       │               │
│  │    - Group X → Require group membership     │               │
│  │    - Private → Author/admin only            │               │
│  └─────────────────────────────────────────────┘               │
│                    │                                            │
│                    ▼                                            │
│  ┌─────────────────────────────────────────────┐               │
│  │ 3. File access (if applicable)              │               │
│  │    - Inherit from parent node               │               │
│  │    - Private file system for restricted     │               │
│  └─────────────────────────────────────────────┘               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Work Management Dashboard

### Objective

Provide users with a unified view of their workflow tasks across all AVC content types.

### User Interface

```
┌─────────────────────────────────────────────────────────────────┐
│  MY WORK                                                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─── DOCUMENTS ───┐  ┌─── RESOURCES ───┐  ┌─── PROJECTS ───┐  │
│  │ ● 3 Active      │  │ ● 1 Active      │  │ ● 0 Active     │  │
│  │ ○ 5 Upcoming    │  │ ○ 2 Upcoming    │  │ ○ 1 Upcoming   │  │
│  │ ✓ 12 Completed  │  │ ✓ 8 Completed   │  │ ✓ 3 Completed  │  │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘  │
│                                                                 │
│  ACTION NEEDED (4)                                    [View All]│
│  ─────────────────────────────────────────────────────────────  │
│  📄 Safety Protocol Review      Document   Due: Jan 15  [Open]  │
│  📄 Q1 Report Draft             Document   Due: Jan 18  [Open]  │
│  🔗 Vendor Link Verification    Resource   Due: Jan 20  [Open]  │
│  📁 2024 Archive Project        Project    Due: Jan 25  [Open]  │
│                                                                 │
│  AVAILABLE TO CLAIM (2)                               [View All]│
│  ─────────────────────────────────────────────────────────────  │
│  📄 New Member Handbook         Document   Reviewers   [Claim]  │
│  🔗 Partner Portal Links        Resource   Content     [Claim]  │
│                                                                 │
│  UPCOMING (7)                                         [View All]│
│  ─────────────────────────────────────────────────────────────  │
│  📄 Board Meeting Minutes       Document   Pending              │
│  📁 Website Redesign            Project    Pending              │
│                                                                 │
│  RECENTLY COMPLETED (5)                               [View All]│
│  ─────────────────────────────────────────────────────────────  │
│  ✓ Training Video Script        Document   Completed Jan 10     │
│  ✓ Emergency Contacts           Resource   Completed Jan 8      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Deliverables

| Deliverable | Description |
|-------------|-------------|
| `/my-work` page | Main dashboard with summary cards and task lists |
| Summary by content type | Counts for Documents, Resources, Projects |
| Action Needed section | Tasks assigned to me, in progress |
| Available to Claim section | Group tasks I can claim |
| Upcoming section | Tasks assigned to me, pending |
| Completed section | Tasks I've finished |
| Claim functionality | Claim group tasks for myself |

### Files to Create

```
avc_work_management/
├── avc_work_management.info.yml
├── avc_work_management.module
├── avc_work_management.routing.yml
├── avc_work_management.services.yml
├── avc_work_management.permissions.yml
├── avc_work_management.libraries.yml
├── config/install/avc_work_management.settings.yml
├── src/
│   ├── Controller/MyWorkController.php
│   ├── Service/WorkTaskQueryService.php
│   ├── Service/WorkTaskActionService.php
│   └── Form/ClaimTaskForm.php
├── templates/
│   ├── my-work-dashboard.html.twig
│   ├── my-work-task-row.html.twig
│   └── my-work-section.html.twig
└── css/my-work-dashboard.css
```

### Technical Details

See: [WORK_MANAGEMENT_MODULE.md](WORK_MANAGEMENT_MODULE.md)

---

## Phase 2: Workflow Access Control

### Objective

Restrict access to content during active workflow to only participants.

### Access Rules During Workflow

| User Type | View | Edit | Delete |
|-----------|------|------|--------|
| Node author | Yes | Yes | No |
| Current task assignee | Yes | Yes | No |
| Group member (if group-assigned) | Yes | Yes | No |
| Past workflow participant | Yes | No | No |
| Workflow administrator | Yes | Yes | Yes |
| Everyone else | No | No | No |

### Files to Create/Modify

```
workflow_assignment/
├── src/
│   ├── Access/
│   │   └── WorkflowAccessManager.php      # NEW
│   └── Service/
│       └── WorkflowParticipantResolver.php # NEW
└── workflow_assignment.module              # MODIFY - add hook_node_access
```

### Technical Details

See: [WORKFLOW_ACCESS_CONTROL.md](WORKFLOW_ACCESS_CONTROL.md)

---

## Phase 3: Post-Workflow Destinations

### Objective

When workflow completes, content is published to a destination with appropriate access control.

### Destination Types

| Destination | Access Level | Who Can View | Example Use |
|-------------|--------------|--------------|-------------|
| **Public** | Anonymous | Anyone, including non-members | Press releases, public policies |
| **Members** | Authenticated | Any logged-in member | Member newsletters, internal docs |
| **Group** | Group membership | Only members of specific group(s) | Team documents, committee files |
| **Private** | Author only | Author and administrators | Personal drafts, sensitive records |

### Destination Configuration

Extend the existing `destination_locations` taxonomy:

```yaml
# Taxonomy: destination_locations
terms:
  - name: Public Website
    access_level: public
    description: "Visible to anyone"

  - name: Member Portal
    access_level: authenticated
    description: "Visible to logged-in members"

  - name: Board Documents
    access_level: group
    access_groups:
      - board_of_directors
    description: "Visible to board members only"

  - name: Executive Team
    access_level: group
    access_groups:
      - executive_team
    description: "Visible to executives only"

  - name: Private Archive
    access_level: private
    description: "Author and admins only"
```

### Destination Fields (Add to Taxonomy)

| Field | Type | Description |
|-------|------|-------------|
| `field_access_level` | List (select) | public, authenticated, group, private |
| `field_access_groups` | Entity reference (Group) | Groups that can access (if access_level = group) |
| `field_file_scheme` | List (select) | public://, private:// |
| `field_require_download_auth` | Boolean | Require login for file downloads |

### Content Endpoints by Destination

| Destination | URL Pattern | Access | Notes |
|-------------|-------------|--------|-------|
| Public Website | `/public/documents/{title}` | Anonymous | SEO-friendly URLs |
| Member Portal | `/members/documents/{title}` | Authenticated | Login required |
| Group: Board | `/groups/board/documents/{title}` | Group members | Board members only |
| Private | `/node/{nid}` | Author/admin | Default node view |

### Implementation

```
avc_content_access/
├── avc_content_access.info.yml
├── avc_content_access.module
├── avc_content_access.services.yml
├── config/
│   └── install/
│       └── taxonomy.vocabulary.destination_locations.yml (update)
├── src/
│   ├── Access/
│   │   └── DestinationAccessManager.php
│   ├── Service/
│   │   └── DestinationResolver.php
│   └── EventSubscriber/
│       └── WorkflowCompletionSubscriber.php
└── templates/
    └── destination-access-denied.html.twig
```

---

## Phase 4: File and Document Access

### Objective

Ensure files attached to AVC content follow the same access rules as the content.

### File Storage Strategy

| Destination Access | File Scheme | Storage Location |
|--------------------|-------------|------------------|
| Public | `public://` | `/sites/default/files/` |
| Members/Group/Private | `private://` | `/sites/default/private/` |

### File Access Flow

```
User requests: /system/files/documents/report.pdf
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────┐
│ 1. Find parent node for this file                               │
│    - Query file_managed + media + node relationships            │
└─────────────────────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. Check node access                                            │
│    - Workflow access (if active)                                │
│    - Destination access (if completed)                          │
└─────────────────────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Serve file or deny                                           │
│    - 200 + file content (if allowed)                            │
│    - 403 (if denied)                                            │
│    - 302 to login (if needs authentication)                     │
└─────────────────────────────────────────────────────────────────┘
```

### File Migration on Workflow Completion

When workflow completes and destination is set, files are automatically moved to the appropriate storage scheme (public:// or private://) based on the destination's access level.

---

## Implementation Order

### Recommended Sequence

| Order | Phase | Effort | Dependencies |
|-------|-------|--------|--------------|
| 1 | Phase 1: Work Dashboard | Medium | workflow_assignment exists |
| 2 | Phase 2: Workflow Access Control | Medium | Phase 1 (optional) |
| 3 | Phase 3: Destination Access | Medium | Taxonomy updates |
| 4 | Phase 4: File Access | Low | Phase 3 |

### Detailed Steps

#### Step 1: Work Management Dashboard
1. Create `avc_work_management` module structure
2. Implement `WorkTaskQueryService`
3. Implement `MyWorkController`
4. Create Twig templates
5. Add CSS styling
6. Test dashboard functionality
7. Add claim task functionality

#### Step 2: Workflow Access Control
1. Create `WorkflowParticipantResolver` service
2. Create `WorkflowAccessManager` service
3. Add `hook_node_access()` implementation
4. Test access restrictions during workflow
5. Add cache invalidation

#### Step 3: Destination Configuration
1. Add fields to `destination_locations` taxonomy
2. Create default destination terms
3. Create `DestinationAccessManager` service
4. Implement `hook_node_access()` for destinations
5. Create workflow completion subscriber
6. Test destination-based access

#### Step 4: File Access
1. Configure private file system
2. Implement `hook_file_download()`
3. Create file migration service
4. Test file access control
5. Add download logging (optional)

---

## Configuration

### Permissions

| Permission | Description | Default Role |
|------------|-------------|--------------|
| `access my work dashboard` | View My Work page | Authenticated |
| `claim workflow tasks` | Claim group tasks | Authenticated |
| `administer destinations` | Manage destination terms | Administrator |
| `bypass destination access` | View all content regardless of destination | Administrator |

---

## Testing Strategy

### Manual Testing Checklist

```
WORK DASHBOARD
[ ] Dashboard shows correct counts by content type
[ ] Action Needed shows my in-progress tasks
[ ] Available shows claimable group tasks
[ ] Claim button moves task to my Action Needed
[ ] View All pages show complete lists

WORKFLOW ACCESS
[ ] Author can access during workflow
[ ] Current assignee can access during workflow
[ ] Group member can access if group-assigned
[ ] Non-participant cannot access during workflow
[ ] Admin can always access

DESTINATION ACCESS
[ ] Public content accessible to anonymous
[ ] Members content requires login
[ ] Group content restricted to group members
[ ] Private content restricted to author
[ ] File downloads follow same rules

WORKFLOW COMPLETION
[ ] Content moves to destination on completion
[ ] Files migrate to correct scheme
[ ] Access updates when workflow completes
```

---

## Rollback Plan

### Phase 1 Rollback
```bash
drush pmu avc_work_management -y
drush cr
```

### Phase 2 Rollback
```bash
# Remove hook_node_access changes
# Redeploy previous workflow_assignment.module
drush cr
```

### Phase 3 Rollback
```bash
drush pmu avc_content_access -y
# Remove taxonomy field additions via update hook
drush cr
```

---

## Related Documents

- [WORK_MANAGEMENT_MODULE.md](WORK_MANAGEMENT_MODULE.md) - Dashboard module code
- [WORKFLOW_ACCESS_CONTROL.md](WORKFLOW_ACCESS_CONTROL.md) - Access control code
- [../../modules/avc_features/workflow_assignment/README.md](../../modules/avc_features/workflow_assignment/README.md) - Existing workflow module

---

*Document created: January 11, 2026*
