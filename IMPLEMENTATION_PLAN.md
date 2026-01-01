# AV Commons Drupal Implementation Plan

## Executive Summary

This document provides a numbered, phased implementation plan for the AV Commons system in Drupal, based on:
- **Specifications**: `avc specs.docx` (10 Epics)
- **Prototype**: `avc.gs` (Google Apps Script) and `AV Commons App.xlsx`
- **Existing Drupal Module**: `workflow_assignment` at `~/nwp/avc/html/modules/custom/workflow_assignment`

---

## Current State Analysis

### Existing Drupal Implementation (workflow_assignment module)

The following features are **already implemented**:

| Feature | Status | Notes |
|---------|--------|-------|
| Workflow List (Config Entity) | Complete | Supports user, group, destination assignments |
| Workflow Assignment (Content Entity) | Complete | Full revision tracking |
| Workflow Templates | Complete | Reusable workflow configurations |
| Color-coded assignments | Complete | User=green, Group=blue, Destination=orange |
| Email notifications | Complete | On assignment/unassignment |
| Audit trail/history | Complete | Full change logging |
| Node workflow tab | Complete | Secondary tab on content pages |
| Drag-and-drop reordering | Complete | AJAX-based |
| Inline editing | Complete | Description/comments |
| Permission system | Complete | 3-tier permissions |
| Destination taxonomy | Complete | `destination_locations` vocabulary |

### Gap Analysis (What Needs Building)

Based on specs vs. current implementation:

| Epic | Spec Feature | Current State | Priority |
|------|--------------|---------------|----------|
| 1 | Member registration/profiles | Not started | High |
| 1 | Member dashboards | Not started | High |
| 2 | Group spaces/dashboards | Not started | High |
| 2 | Group forums | Not started | Medium |
| 2 | Guild system (junior/endorsed/mentor) | Not started | Medium |
| 3 | Asset types (Project/Doc/Resource) | Partial (workflow exists) | High |
| 3 | Version control/diff | Not started | Medium |
| 3 | Issue flagging | Not started | Low |
| 4 | Advanced notification system | Basic exists | High |
| 5 | Courses/LMS integration | Not started | Low |
| 6 | Suggestions system | Not started | Low |
| 7-10 | Multilingual/App/Desktop/Offline | Not started | Future |

---

## Phased Implementation Plan

### PHASE 1: Core Member System
**Goal**: Implement member registration, profiles, and dashboards
**Estimated Effort**: Foundation layer - build first

#### 1.1 Member Registration Enhancement
```
1.1.1 Create custom registration form with fields:
      - Full name (first/last)
      - Email (username)
      - Phone (optional)
      - AV Level (Disciple/Aspirant/Sojourner/None)
      - Skills multi-select (from taxonomy)
      - Credentials text area
      - Public domain acknowledgment checkbox

1.1.2 Create Skills taxonomy vocabulary:
      - Computer skills
      - Pedagogy
      - Prayer warrior
      - Resource trialing
      - Spiritual writing
      - Technical skills
      - Video editing
      - Technical writing/editing
      - Workflows/testing
      - Theology
      - Creatives

1.1.3 Create Member Profile content type or extend User entity:
      - Profile fields from registration
      - Background/credentials field
      - Notification preferences (default setting)
      - Leadership style scores (People/Task/Vision 1-10)
```

#### 1.2 Member Dashboard
```
1.2.1 Create Member Dashboard view/page:
      - Personal editable section (text field)
      - Notification settings display with edit capability
      - Last notification run timestamp

1.2.2 Group membership display:
      - List all groups (available and joined)
      - Indicate membership status per group
      - "Join" / "Leave" action links
      - Per-group notification override setting

1.2.3 Personal asset worklist:
      - Assets where member is in workflow
      - Status indicator (waiting on me / future / completed)
      - Color-coded current stage highlighting
      - Link to each asset

1.2.4 Group worklists aggregation:
      - For each group member belongs to
      - Assets assigned to that group
      - "Take on" action for group tasks
```

#### 1.3 Files to Create/Modify
```
modules/custom/avc_member/
├── avc_member.info.yml
├── avc_member.module
├── avc_member.install
├── avc_member.routing.yml
├── avc_member.permissions.yml
├── src/
│   ├── Controller/
│   │   └── MemberDashboardController.php
│   ├── Form/
│   │   ├── MemberRegistrationForm.php
│   │   ├── MemberProfileForm.php
│   │   └── NotificationPreferencesForm.php
│   ├── Plugin/
│   │   └── Block/
│   │       └── MemberDashboardBlock.php
│   └── Service/
│       └── MemberService.php
├── templates/
│   ├── member-dashboard.html.twig
│   └── member-worklist.html.twig
└── config/install/
    ├── taxonomy.vocabulary.member_skills.yml
    └── field.storage.user.field_*.yml
```

---

### PHASE 2: Group System
**Goal**: Implement group spaces, membership, and dashboards
**Dependency**: Phase 1 (Members)

#### 2.1 Group Entity/Content Type
```
2.1.1 Create Group content type (or use Group module):
      - Group name
      - Description (editable section)
      - Public/Private flag
      - AV Website Group link (optional)
      - Group dashboard document link

2.1.2 Group membership system:
      - Member-Group relationship entity
      - Role field (Admin/Member)
      - Notification preference override
      - Join date
```

#### 2.2 Group Dashboard
```
2.2.1 Create Group Dashboard page:
      - Editable description section
      - Last updated timestamp

2.2.2 Member listing table:
      - Member number
      - Member name (linked to profile)
      - Role indicator

2.2.3 Group asset worklist:
      - All assets with group in workflow
      - Columns: ID, Name (linked), Type, Relevant Stage, Current Stage, Assigned To
      - Color highlighting for current stage matches
      - "Take on" action button

2.2.4 Bulk update functions:
      - Update all member dashboards
      - Update group dashboard content
```

#### 2.3 Files to Create/Modify
```
modules/custom/avc_group/
├── avc_group.info.yml
├── avc_group.module
├── avc_group.install
├── avc_group.routing.yml
├── src/
│   ├── Entity/
│   │   └── GroupMembership.php
│   ├── Controller/
│   │   └── GroupDashboardController.php
│   ├── Form/
│   │   ├── GroupForm.php
│   │   ├── JoinGroupForm.php
│   │   └── GroupSettingsForm.php
│   └── Service/
│       └── GroupService.php
└── templates/
    ├── group-dashboard.html.twig
    └── group-member-list.html.twig
```

---

### PHASE 3: Enhanced Asset System
**Goal**: Implement Projects, Documents, Resources with workflow tables
**Dependency**: Phase 1 & 2

#### 3.1 Asset Content Types
```
3.1.1 Create Asset base content type with:
      - Asset number (auto-generated)
      - Asset name/title
      - Asset type (Project/Document/Resource)
      - Description/body
      - Initiator (user reference)
      - Gatekeeper (user reference)
      - Approver (user reference)
      - Destination (taxonomy reference)
      - Process status field
      - Workflow assignment field (existing)

3.1.2 Project-specific fields:
      - Contained assets (entity reference, unlimited)
      - Project assets table view

3.1.3 Resource-specific fields:
      - External resource link
      - Resource metadata
```

#### 3.2 Workflow Table (Embedded in Assets)
```
3.2.1 Create Workflow Step paragraph type:
      - Workflow type (Initiator/Gatekeeper/Approver/Destination)
      - Assignment type (M=Member, G=Group, D=Destination)
      - Assignment number (entity ID)
      - Assignment name (computed)
      - Comment field
      - Log field (system-generated)
      - Step order (weight)

3.2.2 Workflow validation:
      - Destination must be last step
      - All assignees must exist in system
      - Color-coded validation status

3.2.3 Workflow processing logic:
      - Check current stage (first step without comment)
      - Advance workflow when comment added
      - Log all transitions
      - Trigger notifications
```

#### 3.3 Asset Checking & Processing
```
3.3.1 Check asset function (port from checkDoc):
      - Validate all workflow entries
      - Match member/group/destination by name or number
      - Color-code validation results (green=OK, red=error, amber=missing)
      - Fix obvious issues automatically

3.3.2 Process asset function (port from processaDoc):
      - Detect when ready to advance
      - Send notifications to next assignee
      - Update dashboards
      - Log transitions

3.3.3 "Send again" functionality:
      - Resend notification for current stage
      - Log resend action
```

#### 3.4 Files to Create/Modify
```
modules/custom/avc_asset/
├── avc_asset.info.yml
├── avc_asset.module
├── avc_asset.install
├── avc_asset.routing.yml
├── src/
│   ├── Entity/
│   │   └── WorkflowStep.php (paragraph type)
│   ├── Controller/
│   │   └── AssetController.php
│   ├── Form/
│   │   ├── AssetForm.php
│   │   └── WorkflowStepForm.php
│   ├── Service/
│   │   ├── AssetService.php
│   │   ├── WorkflowProcessor.php
│   │   └── AssetValidator.php
│   └── Plugin/
│       └── Action/
│           ├── CheckAssetAction.php
│           └── ProcessAssetAction.php
└── templates/
    ├── asset-workflow-table.html.twig
    └── asset-detail.html.twig
```

---

### PHASE 4: Advanced Notification System
**Goal**: Implement flexible notification preferences and batching
**Dependency**: Phase 1, 2, 3

#### 4.1 Notification Preferences
```
4.1.1 Notification settings (per user):
      - Default: n (new/immediate), d (daily), w (weekly), x (none)
      - Personal notification last run timestamp

4.1.2 Per-group notification override:
      - p (personal/use default)
      - n, d, w, x options
      - Per-group last run timestamp

4.1.3 Settings storage:
      - User profile field for default
      - Group membership entity field for overrides
```

#### 4.2 Notification Queue & Processing
```
4.2.1 Notification queue entity:
      - Event type (workflow_advance, assignment, etc.)
      - Target user
      - Target group (optional)
      - Asset reference
      - Message content
      - Created timestamp
      - Sent status
      - Sent timestamp

4.2.2 Notification aggregation service:
      - Check each user's settings
      - Aggregate by timing preference
      - Daily digest: collect all since last run
      - Weekly digest: collect all since last run
      - Immediate: send right away

4.2.3 Cron-based processing:
      - Hourly check for immediate notifications
      - Daily run at configured time
      - Weekly run on configured day
      - Error handling with rollback
      - Admin notification on failures

4.2.4 Sent log retention:
      - Log all sent notifications
      - Cleanup entries older than 1 week
```

#### 4.3 Email Templates
```
4.3.1 Workflow advance notification:
      - From: AV Commons team
      - Subject: "AV Commons Resource Check"
      - Body template with tokens:
        [name], [initiatorname], [resourcename],
        [Checktype], [resourcelink], [dashboard],
        [previousperson], [comment]

4.3.2 Daily/Weekly digest template:
      - Summary of all pending items
      - Grouped by group membership
      - Links to dashboards

4.3.3 Admin alert templates:
      - Shepherd follow-up required
      - Tech follow-up required
```

#### 4.4 Files to Create/Modify
```
modules/custom/avc_notification/
├── avc_notification.info.yml
├── avc_notification.module
├── avc_notification.install
├── avc_notification.routing.yml
├── src/
│   ├── Entity/
│   │   └── NotificationQueue.php
│   ├── Service/
│   │   ├── NotificationService.php
│   │   ├── NotificationAggregator.php
│   │   └── NotificationSender.php
│   ├── Plugin/
│   │   └── QueueWorker/
│   │       └── NotificationProcessor.php
│   └── Form/
│       └── NotificationSettingsForm.php
└── templates/
    ├── email/
    │   ├── workflow-advance.html.twig
    │   ├── daily-digest.html.twig
    │   └── weekly-digest.html.twig
    └── notification-log.html.twig
```

---

### PHASE 5: Guild System (Skills & Ratings)
**Goal**: Implement junior/endorsed/mentor roles with skill tracking
**Dependency**: Phase 2

#### 5.1 Guild Roles
```
5.1.1 Member level per group:
      - Junior: can take tasks, work needs ratification
      - Endorsed: can complete work independently
      - Mentor: can check/ratify junior work

5.1.2 Role assignment:
      - Admin can promote/demote
      - Automatic promotion based on score threshold
```

#### 5.2 Skills & Scoring
```
5.2.1 Skills taxonomy per guild:
      - Guild-specific skill terms
      - E.g., Theology: Christology, Morality, Liturgy, Scripture
      - E.g., Coding: Python, Drupal, Flask

5.2.2 Scoring system:
      - Points per completed action
      - Points per ratified action (weighted higher)
      - Skill-based scoring
      - Cumulative score display

5.2.3 Endorsement system:
      - Members can endorse others' skills
      - Threshold for promotion
      - Levels within skills (optional)
```

#### 5.3 Ratification Workflow
```
5.3.1 Junior task completion:
      - Mark task as "pending ratification"
      - Notify available mentors
      - Assign mentor to review

5.3.2 Mentor review:
      - Approve: task completed, junior gets points
      - Request changes: back to junior
      - Mentor name recorded in log
```

#### 5.4 Files to Create/Modify
```
modules/custom/avc_guild/
├── avc_guild.info.yml
├── avc_guild.module
├── src/
│   ├── Entity/
│   │   ├── GuildMembership.php
│   │   └── SkillEndorsement.php
│   ├── Service/
│   │   ├── GuildService.php
│   │   ├── ScoringService.php
│   │   └── RatificationService.php
│   └── Form/
│       ├── RatificationForm.php
│       └── EndorseSkillForm.php
└── templates/
    └── guild-member-profile.html.twig
```

---

### PHASE 6: Group Forums
**Goal**: Implement discussion forums within groups
**Dependency**: Phase 2

#### 6.1 Forum Implementation
```
6.1.1 Options evaluation:
      - Use Drupal Forum module
      - Use Advanced Forum module
      - Custom implementation with Comment module

6.1.2 Forum features:
      - Threaded discussions per group
      - Email notifications to group members
      - Reply-by-email functionality (optional)
```

#### 6.2 Email Integration
```
6.2.1 Outgoing:
      - New post notification to group
      - Reply notification

6.2.2 Incoming (advanced):
      - Inbound email parsing
      - Auto-post from email replies
```

---

### PHASE 7: Version Control & Diff
**Goal**: Implement asset versioning with comparison tools
**Dependency**: Phase 3

#### 7.1 Version System
```
7.1.1 Leverage Drupal revisions:
      - Enable revisions on asset content types
      - Revision log messages
      - Revision author tracking

7.1.2 Version comparison:
      - Diff module integration
      - Visual diff display
      - Field-level change tracking
```

#### 7.2 Edit Locking
```
7.2.1 Option A: Content locking:
      - Lock when editing begins
      - Timeout-based unlock
      - Force unlock capability

7.2.2 Option B: Collaborative editing (future):
      - Yjs integration (as noted in specs)
      - Real-time collaboration
```

---

### PHASE 8: Issue Flagging System
**Goal**: Allow flagging content for various issues
**Dependency**: Phase 3

#### 8.1 Flag Types
```
8.1.1 Flag taxonomy:
      - Spelling/Grammar
      - Theological error
      - Broken links
      - Factual error
      - Other

8.1.2 Flag workflow:
      - Flag button on assets
      - Routes to appropriate guild
      - Resolution workflow
```

---

### PHASE 9: Training/Courses System
**Goal**: Implement course management for member formation
**Dependency**: Phase 5 (Guilds)

#### 9.1 Options Evaluation
```
9.1.1 H5P integration:
      - Interactive content
      - Quizzes, drag-drop
      - SCORM-like tracking

9.1.2 Moodle integration:
      - External LMS connection
      - SSO with Drupal users
      - Grade sync

9.1.3 Custom course content type:
      - Chapters/lessons
      - Progress tracking
      - Completion certificates
```

#### 9.2 Theological Orthodoxy Testing
```
9.2.1 Quiz/assessment creation
9.2.2 Agreement forms (e.g., oath of fidelity)
9.2.3 Credential verification
```

---

### PHASE 10: Suggestions System
**Goal**: Capture community wisdom and divine inspirations
**Dependency**: Phase 3

#### 10.1 Suggestion Feature
```
10.1.1 Suggestion button on content
10.1.2 Suggestion submission form
10.1.3 Suggestion workflow (check/approve)
10.1.4 Scoring for approved suggestions
```

---

### FUTURE PHASES (Post-Core)

#### Phase 11: Multilingual Support
- Drupal content translation
- Interface translation
- Workflow for translation review

#### Phase 12: Mobile App Version
- Progressive Web App (PWA)
- Or native app with API

#### Phase 13: Desktop Application
- Electron-based desktop app
- Or PWA installable

#### Phase 14: Offline Mode
- Service worker implementation
- Local asset caching
- Sync when online

#### Phase 15: Video Asset Type
- Video content type
- Transcription storage
- Keyword/topic annotations
- Searchable annotations
- Time-based rating system

---

## Implementation Priority Matrix

| Phase | Priority | Complexity | Dependencies | Recommended Order |
|-------|----------|------------|--------------|-------------------|
| 1: Members | High | Medium | None | 1st |
| 2: Groups | High | Medium | Phase 1 | 2nd |
| 3: Assets | High | High | Phase 1, 2 | 3rd |
| 4: Notifications | High | High | Phase 1, 2, 3 | 4th |
| 5: Guilds | Medium | Medium | Phase 2 | 5th |
| 6: Forums | Medium | Low | Phase 2 | 6th |
| 7: Versioning | Medium | Low | Phase 3 | 7th |
| 8: Flagging | Low | Low | Phase 3 | 8th |
| 9: Courses | Low | High | Phase 5 | 9th |
| 10: Suggestions | Low | Medium | Phase 3 | 10th |

---

## Integration with Existing workflow_assignment Module

The existing `workflow_assignment` module provides a solid foundation. Integration points:

### Reuse
- WorkflowList entity → Extend for asset workflow steps
- WorkflowAssignment entity → Use for step instances
- Notification service → Extend for advanced notification preferences
- History logging → Extend for all asset changes
- UI components → Reuse color-coding, drag-drop, inline editing

### Extend
- Add member/group dashboard views consuming workflow data
- Add notification preference fields to users
- Add processing logic for workflow advancement
- Add validation logic for workflow completeness

### Modify
- Update workflow tab to show member dashboard link
- Add "Check" and "Process" actions to workflow tab
- Integrate group-based assignment taking

---

## Data Migration Strategy (from Google Sheets Prototype)

If migrating existing data from the Google Sheets prototype:

```
1. Members sheet → Drupal users + profiles
2. Groups sheet → Group content/entities
3. Member-Groups sheet → Group membership entities
4. Assets sheet → Asset content items
5. Destinations sheet → destination_locations taxonomy
6. Settings sheet → Drupal configuration
```

---

## Module Dependency Tree

```
avc_core (shared services, base fields)
├── avc_member (Phase 1)
│   └── avc_notification (Phase 4)
├── avc_group (Phase 2)
│   ├── avc_guild (Phase 5)
│   └── avc_forum (Phase 6)
├── avc_asset (Phase 3)
│   ├── avc_versioning (Phase 7)
│   ├── avc_flagging (Phase 8)
│   └── avc_suggestion (Phase 10)
├── avc_course (Phase 9)
└── workflow_assignment (existing - integrate)
```

---

## Next Steps

1. **Review this plan** with stakeholders
2. **Prioritize** based on immediate needs
3. **Set up development environment** with Open Social/Drupal 10+
4. **Begin Phase 1** implementation
5. **Iterate** through phases with testing at each stage

---

*Document generated: 2026-01-02*
*Based on: avc specs.docx, avc.gs prototype, workflow_assignment module analysis*
