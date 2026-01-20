# AVC Website Progression Configuration

This document explains the different ways the AVC website can be configured to progress someone through the system, including points systems, mentor recognition, verification types, and automatic advancement options.

## Overview

The AVC system provides multiple interconnected progression mechanisms:

1. **Skill Level Progression** - Advance through skill levels (1-10) based on credits
2. **Points/Scoring System** - Earn points for activities that contribute to role promotion
3. **Verification System** - Different methods to verify level advancement
4. **Mentor Recognition** - Ratification and endorsement by mentors
5. **Guild Role Promotion** - Automatic or manual advancement through guild roles
6. **Workflow Task Completion** - Track completion of assigned work

---

## 1. Skill Level Progression

**Location:** `modules/avc_features/avc_guild/src/Entity/SkillLevel.php`

Each skill can have multiple levels (e.g., 1-10). Each level is configurable with:

| Configuration | Description | Example |
|--------------|-------------|---------|
| `level` | Level number | 1, 2, 3... 10 |
| `name` | Level name | "Beginner", "Competent", "Expert" |
| `credits_required` | Credits needed to reach this level | 100, 250, 500 |
| `verification_type` | How advancement is verified | auto, mentor, peer, committee, assessment |
| `verifier_minimum_level` | Minimum level a verifier must have | 3 (for peer verification) |
| `votes_required` | Number of votes needed (peer/committee) | 3 |
| `time_minimum_days` | Minimum days at previous level | 30 |
| `weight` | Display ordering | 1, 2, 3 |

### Credit Sources

Credits can be earned from multiple sources (defined in `SkillCredit.php`):

| Source Type | Description |
|-------------|-------------|
| `task_review` | Credits from reviewed/completed tasks |
| `endorsement` | Credits from receiving endorsements |
| `assessment` | Credits from formal assessments |
| `time` | Time-based credits (days at level) |
| `manual` | Manually awarded by admin |
| `migration` | Credits from data migration |

### Eligibility Check

**Location:** `SkillProgressionService.php` (lines 133-167)

A member qualifies for the next level when:
1. They have accumulated enough credits (`credits_required`)
2. They have spent minimum days at current level (`time_minimum_days`)
3. They don't already have a pending verification

---

## 2. Verification Types

**Location:** `SkillLevel.php` (lines 55-59)

Five verification methods control how level advancement is confirmed:

### VERIFICATION_AUTO
- Automatic advancement when thresholds are met
- No human approval required
- Best for: Early levels, time-based progression

### VERIFICATION_MENTOR
- Single mentor approval required
- Mentor must meet `verifier_minimum_level`
- Best for: Skill validation requiring expert review

### VERIFICATION_PEER
- Community voting required
- Configurable `votes_required` threshold
- Voters must meet `verifier_minimum_level`
- Best for: Community recognition of competence

### VERIFICATION_COMMITTEE
- Formal committee voting
- Higher threshold typically than peer
- Best for: Advanced levels, leadership positions

### VERIFICATION_ASSESSMENT
- Formal assessment required
- May include tests, demonstrations, or reviews
- Best for: Certification-level skills

### Verification Statuses

**Location:** `LevelVerification.php` (lines 52-57)

| Status | Description |
|--------|-------------|
| `pending` | Awaiting verification |
| `approved` | Verification passed, level granted |
| `denied` | Verification failed |
| `deferred` | Postponed for later review |
| `expired` | Verification request timed out |

---

## 3. Points/Scoring System

**Location:** `GuildScore.php` and `GuildSettingsForm.php`

Points are earned for various activities and contribute to guild role promotion.

### Default Point Values

| Action | Default Points | Config Key |
|--------|---------------|------------|
| Task Completed | 10 | `points_task_completed` |
| Task Ratified | 15 | `points_task_ratified` |
| Ratification Given | 5 | `points_ratification_given` |
| Endorsement Received | 20 | `points_endorsement_received` |
| Endorsement Given | 5 | `points_endorsement_given` |

### Admin Configuration

**Location:** `GuildSettingsForm.php` (lines 38-91)

Administrators can configure:
- Custom point values for each action type
- Enable/disable auto-promotion (`auto_promote`)
- Set promotion threshold score (`default_threshold`)

**Admin Interface:** `/admin/config/avc/guild/settings`

---

## 4. Mentor Recognition

### Ratification System

**Location:** `Ratification.php` and `RatificationService.php`

Ratification is mentor approval of junior member work.

| Status | Description |
|--------|-------------|
| `pending` | Work submitted, awaiting review |
| `approved` | Mentor approved the work |
| `changes_requested` | Mentor requested changes |

**Flow:**
1. Junior completes task
2. Task submitted for ratification
3. Mentor reviews and approves/requests changes
4. Approval awards credits and points

### Endorsement System

**Location:** `SkillEndorsement.php` and `EndorsementService.php`

Endorsements are binary skill validations from recognized members.

**Validation Rules (lines 74-92):**
- Endorser must have sufficient permissions
- Self-endorsement is prevented
- One endorsement per endorser per skill

---

## 5. Guild Role Promotion

**Location:** Config files in `config/install/`

### Role Hierarchy

| Role | Description |
|------|-------------|
| `guild-outsider` | Non-member, limited access |
| `guild-junior` | New member, work requires ratification |
| `guild-member` | Full member, independent work |
| `guild-endorsed` | Recognized skill holder |
| `guild-mentor` | Can ratify junior work |
| `guild-admin` | Full administrative access |

### Auto-Promotion

**Location:** `ScoringService.php` (lines 230-261)

When enabled, juniors are automatically promoted when:
1. Auto-promotion is enabled for the guild
2. Junior's score reaches `field_promotion_threshold`
3. System triggers `checkPromotion()` method

**Configuration:**
- Set `auto_promote` toggle on guild
- Configure `default_threshold` score value

---

## 6. Workflow Task Completion

**Location:** `modules/avc_features/workflow_assignment/`

### Task Status States

**Location:** `WorkflowTask.php` (lines 208-231)

| Status | Description |
|--------|-------------|
| `pending` | Not yet started |
| `in_progress` | Currently being worked on |
| `completed` | Task finished |
| `skipped` | Task bypassed |

### Assignment Status States

**Location:** `WorkflowAssignment.php`

| Status | Description |
|--------|-------------|
| `proposed` | Assignment suggested |
| `accepted` | Assignment accepted |
| `completed` | All tasks done |

### Task Assignment Types

| Type | Color Code | Description |
|------|------------|-------------|
| User assignment | Green | Assigned to specific user |
| Group assignment | Blue | Assigned to group/role |
| Destination assignment | Orange | Final task destination |

---

## 7. Progression Flow Summary

```
Task Completion
     │
     ▼
Points Awarded (GuildScore)
     │
     ├──► Score Threshold Met? ──► Auto-Promotion to Member
     │
     ▼
Credits Awarded (SkillCredit)
     │
     ▼
Eligibility Check (SkillProgressionService)
     │
     ├──► Credits + Time Met?
     │           │
     │           ▼
     │    Verification Initiated
     │           │
     │           ▼
     │    Verification Type?
     │      ├── Auto ────────► Level Granted
     │      ├── Mentor ──────► Mentor Approval ──► Level Granted
     │      ├── Peer ────────► Voting ──────────► Level Granted
     │      ├── Committee ───► Committee Vote ──► Level Granted
     │      └── Assessment ──► Formal Test ─────► Level Granted
     │
     ▼
Next Level Unlocked
```

---

## 8. Admin Interfaces

| Interface | Path | Purpose |
|-----------|------|---------|
| Guild Settings | `/admin/config/avc/guild/settings` | Point values, auto-promotion |
| Skill Levels | `/admin/config/avc/guild/skill-levels` | Configure skill level requirements |
| Guild Scores | `/admin/config/avc/guild/scores` | View/manage point records |
| Level Verifications | `/admin/config/avc/guild/verifications` | Manage pending verifications |
| Ratifications | `/admin/config/avc/guild/ratifications` | Review ratification requests |
| Member Progress | `/admin/config/avc/guild/skill-progress` | View member skill progress |
| Workflow Templates | `/admin/structure/workflow-template` | Create/edit workflow templates |

---

## 9. Configuration Examples

### Example: Beginner-Friendly Guild

```yaml
Skill Levels:
  Level 1:
    credits_required: 50
    verification_type: auto
    time_minimum_days: 0
  Level 2:
    credits_required: 150
    verification_type: auto
    time_minimum_days: 7

Points:
  points_task_completed: 15
  points_task_ratified: 20
  auto_promote: true
  default_threshold: 100
```

### Example: High-Standards Guild

```yaml
Skill Levels:
  Level 1:
    credits_required: 100
    verification_type: mentor
    time_minimum_days: 14
  Level 2:
    credits_required: 300
    verification_type: peer
    votes_required: 3
    verifier_minimum_level: 2
    time_minimum_days: 30

Points:
  points_task_completed: 10
  points_task_ratified: 15
  auto_promote: false
```

---

## 10. Key Source Files Reference

| File | Purpose |
|------|---------|
| `SkillLevel.php` | Skill level entity and configuration |
| `MemberSkillProgress.php` | Tracks member progress through skills |
| `SkillCredit.php` | Credit transaction records |
| `LevelVerification.php` | Verification request management |
| `SkillProgressionService.php` | Eligibility checking and level granting |
| `GuildScore.php` | Point transaction records |
| `ScoringService.php` | Point awarding and auto-promotion |
| `GuildSettingsForm.php` | Admin configuration form |
| `Ratification.php` | Mentor ratification entity |
| `RatificationService.php` | Ratification workflow |
| `EndorsementService.php` | Skill endorsement validation |
| `WorkflowTask.php` | Workflow task tracking |
| `WorkflowAssignment.php` | Workflow assignment status |

All files located under: `modules/avc_features/avc_guild/src/`
