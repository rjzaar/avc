# AV Commons Drupal Implementation Plan

## Executive Summary

This document provides a numbered, phased implementation plan for the AV Commons system built on **Open Social**, based on:
- **Specifications**: `avc specs.docx` (10 Epics)
- **Prototype**: `avc.gs` (Google Apps Script) and `AV Commons App.xlsx`
- **Existing Drupal Module**: `workflow_assignment` at `~/nwp/avc/html/modules/custom/workflow_assignment`
- **Platform**: Open Social (Drupal distribution)

---

## Open Social Platform Overview

### Hybrid Approach: Open Social + Custom Extensions

This implementation uses Open Social as the base platform, extending it with:
- Custom **Guild** group type (for skill-based groups with mentorship)
- Custom **Asset** content types (Projects/Documents/Resources)
- Custom **Workflow** integration (leveraging existing workflow_assignment module)

### Open Social Out-of-the-Box Features

| Feature | Open Social Component | Spec Coverage | Use As-Is? |
|---------|----------------------|---------------|------------|
| User Profiles | `social_user`, `social_profile` | Epic 1: Members | Extend |
| Groups | `social_group` (Group module) | Epic 2: Groups | Extend |
| Group Membership | `social_group` with roles | Epic 2: Groups | Extend |
| Activity Stream | `social_activity` | Dashboards | Yes |
| Notifications | `social_notifications` | Epic 4: Notifications | Extend |
| Topics/Discussions | `social_topic` | Epic 2: Forums | Yes |
| Private Messaging | `social_private_message` | Communication | Yes |
| Events | `social_event` | Future use | Yes |
| Books | `social_book` | Documentation | Yes |
| Search | `social_search` | Discovery | Yes |

### Group Type Strategy

| Group Type | Source | Purpose | Roles |
|------------|--------|---------|-------|
| **Flexible Group** | Open Social | Standard teams (Theology, Tech, etc.) | Admin, Member |
| **Guild** | Custom | Skill-based groups with mentorship | Admin, Mentor, Endorsed, Junior |

### What We Build Custom

| Component | Purpose | Complexity |
|-----------|---------|------------|
| **Guild group type** | Skill-based groups with scoring/mentorship | Medium |
| **Asset content types** | Projects/Documents/Resources | Medium |
| **Workflow integration** | Check/Process/Advance logic | Medium (exists) |
| **Worklist dashboards** | Member & group task views | Medium |
| **Notification digests** | n/d/w/x preference system | Medium |
| **Scoring system** | Guild member scoring | Low-Medium |
| **Endorsement system** | Skill endorsements | Low |
| **Ratification workflow** | Junior work approval | Low-Medium |
| **Suggestions system** | Community suggestions | Low |
| **Courses integration** | H5P/Moodle | Low (future) |

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

### PHASE 1: Core Member System (Open Social Extension)
**Goal**: Extend Open Social profiles and create member dashboards
**Platform**: Leverages `social_user`, `social_profile`

#### 1.1 Extend Open Social Profile
```
1.1.1 Add custom fields to existing Open Social profile:
      - AV Level (list: Disciple/Aspirant/Sojourner/None)
      - Skills (entity reference to taxonomy, multi-value)
      - Credentials (text_long)
      - Public domain acknowledgment (boolean)
      - Leadership scores (People/Task/Vision - 3 integer fields 1-10)
      - Notification default (list: n/d/w/x)
      - Notification last run (timestamp)

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

1.1.3 Extend registration form (alter social_user_register):
      - Add AV-specific fields to Open Social registration
      - Include skills selection
      - Public domain acknowledgment required
```

#### 1.2 Member Dashboard (Custom Page)
```
1.2.1 Create Member Dashboard route/page:
      - Integrate with Open Social user profile page
      - OR create separate /user/{uid}/dashboard route
      - Personal editable section (text field)
      - Notification settings with edit form

1.2.2 Group membership display (from Open Social):
      - Use social_group API to list user's groups
      - Add per-group notification override setting
      - Show membership status per group
      - Link to group pages

1.2.3 Personal asset worklist (workflow integration):
      - Query workflow_assignment entities for user
      - Show assets where user is in workflow
      - Status: waiting on me (green) / future / completed
      - Color-coded current stage
      - Link to each asset

1.2.4 Group worklists aggregation:
      - For each group user belongs to
      - Query assets with group in workflow
      - "Take on" action for unclaimed group tasks
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
│   │   └── NotificationPreferencesForm.php
│   ├── Plugin/
│   │   └── Block/
│   │       ├── MemberWorklistBlock.php
│   │       └── GroupWorklistsBlock.php
│   └── Service/
│       └── MemberWorklistService.php
├── templates/
│   ├── member-dashboard.html.twig
│   └── member-worklist.html.twig
└── config/install/
    ├── taxonomy.vocabulary.member_skills.yml
    ├── field.storage.profile.field_av_level.yml
    ├── field.storage.profile.field_skills.yml
    ├── field.storage.profile.field_credentials.yml
    ├── field.storage.profile.field_notification_default.yml
    └── field.field.profile.profile.field_*.yml

Dependencies:
  - social_user
  - social_profile
  - workflow_assignment
```

---

### PHASE 2: Group System (Open Social Extension)
**Goal**: Extend Open Social groups with workflow dashboards
**Platform**: Leverages `social_group` (built on Group module)
**Dependency**: Phase 1 (Members)

#### 2.1 Extend Open Social Groups
```
2.1.1 Add custom fields to Open Social group types:
      - AV Website Group link (link field, optional)
      - Group notification default (list: p/n/d/w/x)
      - Public/Private visibility (already exists in Open Social)

2.1.2 Extend group membership (group_content):
      - Add notification preference override field
      - Guild level field (Junior/Endorsed/Mentor) - for Phase 5

2.1.3 Open Social group types to use:
      - Flexible group (default, configurable visibility)
      - OR create custom "AVC Group" type
```

#### 2.2 Group Dashboard (Workflow Tab/Block)
```
2.2.1 Add Workflow Dashboard to group pages:
      - Option A: Add as tab on group page
      - Option B: Add as block on group page
      - Editable description section (group description)
      - Last updated timestamp

2.2.2 Member listing (from Open Social):
      - Use social_group API for member list
      - Add guild level indicator column
      - Link to member profiles

2.2.3 Group asset worklist:
      - Query assets where group is in workflow
      - Columns: ID, Name (linked), Type, Relevant Stage, Current Stage, Assigned To
      - Color highlighting for current stage matches
      - "Take on" action (assigns to current user)
      - Filter: Pending / Completed / All

2.2.4 Activity integration:
      - Post to Open Social activity stream on workflow events
      - "Asset X advanced to stage Y"
```

#### 2.3 Files to Create/Modify
```
modules/custom/avc_group/
├── avc_group.info.yml
├── avc_group.module
├── avc_group.install
├── avc_group.routing.yml
├── src/
│   ├── Controller/
│   │   └── GroupWorkflowController.php
│   ├── Plugin/
│   │   └── Block/
│   │       ├── GroupWorklistBlock.php
│   │       └── GroupMemberListBlock.php
│   ├── EventSubscriber/
│   │   └── WorkflowActivitySubscriber.php
│   └── Service/
│       └── GroupWorklistService.php
├── templates/
│   ├── group-worklist.html.twig
│   └── group-workflow-tab.html.twig
└── config/install/
    ├── field.storage.group.field_av_website_link.yml
    ├── field.storage.group_content.field_notification_override.yml
    └── field.storage.group_content.field_guild_level.yml

Dependencies:
  - social_group
  - workflow_assignment
  - avc_member
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

### PHASE 5: Guild System (Custom Group Type in Open Social)
**Goal**: Create Guild group type with mentorship, scoring, and skill tracking
**Platform**: Custom group type extending Open Social's Group module
**Dependency**: Phase 2

#### 5.1 Create Guild Group Type
```
5.1.1 Define Guild group type in Open Social:
      /admin/group/types/add
      - Machine name: guild
      - Label: Guild
      - Description: Skill-based group with mentorship and scoring

5.1.2 Guild-specific fields:
      - field_guild_skills (taxonomy reference, multi-value)
      - field_scoring_enabled (boolean)
      - field_promotion_threshold (integer)
      - field_ratification_required (boolean)

5.1.3 Create custom group roles:
      - guild-admin: Full administration
      - guild-mentor: Can ratify work, train juniors
      - guild-endorsed: Independent work capability
      - guild-junior: Needs ratification (default for new members)

5.1.4 Configure guild permissions:
      - Junior: create content, take tasks
      - Endorsed: create content, take tasks, no ratification needed
      - Mentor: all above + ratify junior work + promote members
      - Admin: all above + manage guild settings
```

#### 5.2 Skills & Scoring System
```
5.2.1 Skills taxonomy (per guild):
      - Create child vocabularies per guild OR
      - Use taxonomy with guild reference field
      - Examples:
        - Theology Guild: Christology, Morality, Liturgy, Scripture
        - Coding Guild: Python, Drupal, Flask, JavaScript
        - Writing Guild: Editing, Proofreading, Spiritual Writing

5.2.2 Scoring entity (avc_guild_score):
      - User reference
      - Guild reference
      - Skill reference (optional, for skill-specific scores)
      - Points (integer)
      - Action type (task_completed, ratification_given, endorsement_received)
      - Timestamp

5.2.3 Score calculation service:
      - Points per completed action: +10
      - Points per ratified action: +15 (bonus)
      - Points for giving ratification: +5
      - Points for endorsement received: +20
      - Display cumulative score on guild member profile

5.2.4 Automatic promotion:
      - Check score against guild's promotion_threshold
      - Queue promotion for admin approval OR
      - Auto-promote if enabled
```

#### 5.3 Endorsement System
```
5.3.1 Endorsement entity (avc_skill_endorsement):
      - Endorser (user reference)
      - Endorsed user (user reference)
      - Skill (taxonomy reference)
      - Guild (group reference)
      - Comment (text)
      - Timestamp

5.3.2 Endorsement rules:
      - Only Endorsed/Mentor can endorse others
      - Cannot endorse yourself
      - One endorsement per skill per endorser
      - Display endorsement count on skills
```

#### 5.4 Ratification Workflow
```
5.4.1 Junior task completion:
      - Junior completes workflow step
      - Status set to "pending_ratification"
      - Notification sent to guild mentors
      - Task appears in mentor's ratification queue

5.4.2 Ratification entity (avc_ratification):
      - Task/Asset reference
      - Junior user reference
      - Mentor user reference (assigned or claimed)
      - Status (pending, approved, changes_requested)
      - Feedback (text)
      - Timestamp

5.4.3 Mentor review actions:
      - Approve:
        - Task marked complete
        - Junior receives points
        - Mentor receives points
        - Activity posted to guild stream
      - Request changes:
        - Task returned to junior
        - Feedback provided
        - No points awarded yet
```

#### 5.5 Files to Create/Modify
```
modules/custom/avc_guild/
├── avc_guild.info.yml
├── avc_guild.module
├── avc_guild.install
├── avc_guild.routing.yml
├── avc_guild.permissions.yml
├── src/
│   ├── Entity/
│   │   ├── GuildScore.php
│   │   ├── SkillEndorsement.php
│   │   └── Ratification.php
│   ├── Plugin/
│   │   └── Group/
│   │       └── GroupType/
│   │           └── Guild.php
│   ├── Service/
│   │   ├── GuildService.php
│   │   ├── ScoringService.php
│   │   ├── EndorsementService.php
│   │   └── RatificationService.php
│   ├── Form/
│   │   ├── RatificationForm.php
│   │   ├── EndorseSkillForm.php
│   │   └── GuildSettingsForm.php
│   ├── Controller/
│   │   ├── GuildDashboardController.php
│   │   └── RatificationQueueController.php
│   └── Plugin/
│       └── Block/
│           ├── GuildLeaderboardBlock.php
│           ├── RatificationQueueBlock.php
│           └── MemberSkillsBlock.php
├── templates/
│   ├── guild-dashboard.html.twig
│   ├── guild-member-profile.html.twig
│   ├── ratification-queue.html.twig
│   └── skill-endorsements.html.twig
└── config/install/
    ├── group.type.guild.yml
    ├── group.role.guild-junior.yml
    ├── group.role.guild-endorsed.yml
    ├── group.role.guild-mentor.yml
    ├── group.role.guild-admin.yml
    ├── field.storage.group.field_guild_skills.yml
    ├── field.storage.group.field_scoring_enabled.yml
    └── field.storage.group.field_promotion_threshold.yml

Dependencies:
  - social_group
  - workflow_assignment
  - avc_member
  - avc_group
```

---

### PHASE 6: Group Forums (Open Social Topics)
**Goal**: Leverage Open Social Topics for group discussions
**Platform**: Uses `social_topic` (already built-in)
**Dependency**: Phase 2

#### 6.1 Open Social Topics (Already Available)
```
6.1.1 Open Social provides:
      - Topics content type with discussions
      - Topics can be posted in groups
      - Threaded comments/replies
      - Activity stream integration
      - Basic notifications

6.1.2 Minimal customization needed:
      - Ensure Topics enabled for AVC groups
      - Configure notification settings
      - Style to match AVC design
```

#### 6.2 Email Integration Enhancement
```
6.2.1 Outgoing (extend social_notifications):
      - New topic notification to group
      - Reply notification
      - Integrate with AVC notification preferences (Phase 4)

6.2.2 Incoming (optional future enhancement):
      - Inbound email parsing with Mailhandler module
      - Auto-post from email replies
```

#### 6.3 Files to Create/Modify
```
Minimal - mostly configuration:
- Enable social_topic for group types
- Configure views for topic display
- Add notification hooks in avc_notification module
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

| Phase | Priority | Complexity | Open Social Leverage | Recommended Order |
|-------|----------|------------|---------------------|-------------------|
| 1: Members | High | Medium | Extend social_profile | 1st |
| 2: Groups | High | Medium | Extend social_group | 2nd |
| 3: Assets | High | High | Custom (workflow focus) | 3rd |
| 4: Notifications | High | Medium | Extend social_notifications | 4th |
| 5: Guilds | High | Medium | Custom group type | 5th |
| 6: Forums | Low | Minimal | Use social_topic as-is | 6th |
| 7: Versioning | Medium | Low | Drupal revisions | 7th |
| 8: Flagging | Low | Low | Custom | 8th |
| 9: Courses | Low | High | H5P/Moodle integration | 9th |
| 10: Suggestions | Low | Medium | Custom | 10th |

### Open Social Feature Utilization

| Open Social Feature | AVC Usage | Customization Level |
|--------------------|-----------|---------------------|
| User registration | Use | Extend with AV fields |
| Profiles | Use | Extend with skills, credentials |
| Flexible Groups | Use | Add workflow dashboard |
| **Guild (custom)** | Create | New group type |
| Topics/Forums | Use | As-is (minimal config) |
| Activity Stream | Use | Add workflow events |
| Notifications | Use | Extend with digest system |
| Private Messages | Use | As-is |
| Events | Use | As-is (future) |
| Books | Use | As-is (documentation) |
| Search | Use | Index assets |

---

## Integration with Existing Modules

### workflow_assignment Module Integration

The existing `workflow_assignment` module provides a solid foundation. Integration points:

#### Reuse
- WorkflowList entity → Extend for asset workflow steps
- WorkflowAssignment entity → Use for step instances
- Notification service → Extend for advanced notification preferences
- History logging → Extend for all asset changes
- UI components → Reuse color-coding, drag-drop, inline editing

#### Extend
- Add member/group dashboard views consuming workflow data
- Add notification preference fields to users
- Add processing logic for workflow advancement
- Add validation logic for workflow completeness

#### Modify
- Update workflow tab to show member dashboard link
- Add "Check" and "Process" actions to workflow tab
- Integrate group-based assignment taking

### Open Social Integration Points

| Open Social Module | AVC Integration |
|-------------------|-----------------|
| `social_user` | Extend user registration, add AV-specific fields |
| `social_profile` | Add skills, credentials, notification preferences |
| `social_group` | Add workflow dashboard, extend membership with guild levels |
| `social_topic` | Use for group forums (minimal customization) |
| `social_activity` | Post workflow events to activity stream |
| `social_notifications` | Extend with digest preferences (n/d/w/x) |
| `social_search` | Index assets for search |

### Open Social Hooks to Implement

```php
// In avc_member.module
function avc_member_form_social_user_register_form_alter(&$form, FormStateInterface $form_state) {
  // Add AV-specific registration fields
}

// In avc_group.module
function avc_group_social_group_view_alter(&$build, GroupInterface $group) {
  // Add workflow dashboard tab/block to group pages
}

// In avc_notification.module
function avc_notification_social_notifications_alter(&$notifications, AccountInterface $account) {
  // Filter/aggregate based on user preferences
}
```

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
Open Social Distribution (base platform)
├── social_user ─────────────┐
├── social_profile ──────────┼── avc_member (Phase 1)
├── social_group ────────────┼── avc_group (Phase 2)
├── social_topic ────────────┼── (Phase 6 - minimal config)
├── social_activity ─────────┤
├── social_notifications ────┴── avc_notification (Phase 4)
│
└── Custom AVC Modules:
    │
    ├── avc_core (shared services, base fields)
    │   └── depends on: workflow_assignment
    │
    ├── avc_member (Phase 1)
    │   ├── depends on: social_user, social_profile, workflow_assignment
    │   └── avc_notification (Phase 4)
    │       └── depends on: social_notifications
    │
    ├── avc_group (Phase 2)
    │   ├── depends on: social_group, workflow_assignment, avc_member
    │   └── avc_guild (Phase 5)
    │
    ├── avc_asset (Phase 3)
    │   ├── depends on: workflow_assignment, avc_member, avc_group
    │   ├── avc_versioning (Phase 7)
    │   ├── avc_flagging (Phase 8)
    │   └── avc_suggestion (Phase 10)
    │
    ├── avc_course (Phase 9)
    │   └── depends on: avc_guild, (H5P or Moodle integration)
    │
    └── workflow_assignment (existing - core dependency)
```

---

## Next Steps

1. **Review this plan** with stakeholders
2. **Prioritize** based on immediate needs
3. **Set up development environment**:
   - Open Social distribution (latest stable)
   - Ensure workflow_assignment module is compatible
   - Install development tools (ddev, lando, or similar)
4. **Begin Phase 1** implementation:
   - Extend Open Social profile with AV-specific fields
   - Create member dashboard with worklist
5. **Iterate** through phases with testing at each stage

---

## Open Social Version Requirements

| Component | Minimum Version | Notes |
|-----------|----------------|-------|
| Open Social | 12.x or later | Drupal 10 compatible |
| Drupal Core | 10.2+ | Required by Open Social 12.x |
| PHP | 8.1+ | Required by Drupal 10 |
| Group module | 3.x | Included with Open Social |

---

*Document generated: 2026-01-02*
*Platform: Open Social Distribution*
*Based on: avc specs.docx, avc.gs prototype, workflow_assignment module analysis*
