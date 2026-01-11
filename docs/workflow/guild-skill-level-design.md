# Guild Skill Level System Design

## Overview

This document proposes enhancements to AVC's guild system to support:
- Configurable skill levels per guild
- Skill-specific competency progression
- Flexible verification processes
- Automatic credit accumulation from work

---

## Current State Analysis

### What AVC Already Has

| Feature | Implementation | Location |
|---------|---------------|----------|
| Guild roles | junior → endorsed → mentor → admin | `group.role.guild-*.yml` |
| Scoring system | Points for actions (10-20 pts) | `GuildScore` entity, `ScoringService` |
| Skill endorsements | Binary (endorsed/not) | `SkillEndorsement` entity |
| Ratification | Mentor approves junior work | `Ratification` entity, `RatificationService` |
| Auto-promotion | Score threshold triggers role change | `ScoringService::checkPromotion()` |
| Skill vocabulary | `guild_skills` taxonomy | `avc_guild.install` |

### What's Missing

1. **Multi-level proficiency** - Currently skills are binary (endorsed or not)
2. **Configurable levels per guild** - All guilds use same 4-role structure
3. **Skill-specific advancement paths** - No progression within a skill
4. **Verification configuration** - Fixed mentor-ratifies-junior model
5. **Credit accumulation** - Only endorsements count, not work history
6. **Level prerequisites** - No skill dependencies

---

## Proposed Architecture

### Core Concept: Skill Proficiency Levels

Transform the current binary endorsement into a multi-level proficiency system:

```
Skill: "Technical Writing"
  Level 1: Beginner     (can do supervised work)
  Level 2: Competent    (can work independently)
  Level 3: Proficient   (can review others' work)
  Level 4: Expert       (can train and certify others)
```

### New Entity: SkillLevel

Defines the levels available for a skill within a guild:

```php
/**
 * @ContentEntityType(
 *   id = "skill_level",
 *   label = @Translation("Skill Level"),
 *   base_table = "skill_level",
 * )
 */
class SkillLevel extends ContentEntityBase {
  // Fields:
  // - guild_id (entity_reference: group)
  // - skill_id (entity_reference: taxonomy_term)
  // - level (integer: 1-10)
  // - name (string: "Beginner", "Competent", etc.)
  // - description (text_long: what this level means)
  // - credits_required (integer: credits needed to reach this level)
  // - verification_type (list: 'auto', 'peer', 'mentor', 'committee')
  // - verifier_minimum_level (integer: what level can verify this)
  // - time_minimum_days (integer: minimum days at previous level)
  // - weight (integer: for ordering)
}
```

### New Entity: MemberSkillProgress

Tracks a member's current level and credit progress in each skill:

```php
/**
 * @ContentEntityType(
 *   id = "member_skill_progress",
 *   base_table = "member_skill_progress",
 * )
 */
class MemberSkillProgress extends ContentEntityBase {
  // Fields:
  // - user_id (entity_reference: user)
  // - guild_id (entity_reference: group)
  // - skill_id (entity_reference: taxonomy_term)
  // - current_level (integer: 0 = none, 1+ = attained level)
  // - current_credits (integer: credits toward next level)
  // - level_achieved_date (timestamp: when current level was confirmed)
  // - pending_verification (boolean: awaiting confirmation)
}
```

### New Entity: SkillCredit

Records individual credit events toward skill advancement:

```php
/**
 * @ContentEntityType(
 *   id = "skill_credit",
 *   base_table = "skill_credit",
 * )
 */
class SkillCredit extends ContentEntityBase {
  // Fields:
  // - user_id (entity_reference: user)
  // - guild_id (entity_reference: group)
  // - skill_id (entity_reference: taxonomy_term)
  // - credits (integer: points awarded)
  // - source_type (list: 'task_review', 'endorsement', 'assessment', 'time', 'manual')
  // - source_id (integer: reference to source entity)
  // - reviewer_id (entity_reference: user who granted credit)
  // - notes (text: optional reviewer notes)
  // - created (timestamp)
}
```

### New Entity: LevelVerification

Records verification/confirmation of level advancement:

```php
/**
 * @ContentEntityType(
 *   id = "level_verification",
 *   base_table = "level_verification",
 * )
 */
class LevelVerification extends ContentEntityBase {
  // Fields:
  // - user_id (entity_reference: user being verified)
  // - guild_id (entity_reference: group)
  // - skill_id (entity_reference: taxonomy_term)
  // - target_level (integer: level being verified for)
  // - status (list: 'pending', 'approved', 'denied', 'deferred')
  // - verification_type (list: 'auto', 'peer', 'mentor', 'committee')
  // - verifiers (entity_reference: users, multiple)
  // - votes_required (integer: for committee verification)
  // - votes_received (integer)
  // - feedback (text_long)
  // - evidence (entity_reference: nodes/ratifications as evidence)
  // - created, completed (timestamps)
}
```

---

## Configuration Model

### Guild-Level Configuration

Each guild can define its own skill level structure:

```yaml
# Example: Technical Writing Guild configuration
guild_id: 42
skills:
  technical_writing:
    levels:
      - level: 1
        name: "Apprentice"
        credits_required: 0  # Entry level
        verification_type: "auto"  # Auto-granted on joining

      - level: 2
        name: "Contributor"
        credits_required: 50
        verification_type: "mentor"
        verifier_minimum_level: 3
        time_minimum_days: 30

      - level: 3
        name: "Editor"
        credits_required: 150
        verification_type: "peer"
        verifier_minimum_level: 3
        votes_required: 2
        time_minimum_days: 90

      - level: 4
        name: "Master Editor"
        credits_required: 400
        verification_type: "committee"
        verifier_minimum_level: 4
        votes_required: 3
        time_minimum_days: 180

    credit_sources:
      task_completed: 5
      task_reviewed_approved: 10
      review_given: 3
      endorsement_received: 15
```

### Verification Types

| Type | Who Verifies | Process |
|------|-------------|---------|
| **auto** | System | Automatic when credits + time met |
| **peer** | Same level or higher | N votes from qualified peers |
| **mentor** | Higher level (specified) | Single approval from mentor+ |
| **committee** | Multiple higher levels | Quorum of N verifiers must approve |
| **assessment** | Designated assessor | Formal test or evaluation |

---

## Workflow Integration

### Scenario 1: Task Completion Credit

```
1. Junior member claims a task tagged with skill "Technical Writing"
2. Member completes the task
3. System checks: Does member need ratification? (junior role)
   → Yes: Create ratification request
4. Mentor reviews and approves the work
5. System awards:
   - Member: task_completed credits (5) for "Technical Writing"
   - Member: task_reviewed_approved credits (10) for "Technical Writing"
   - Mentor: review_given credits (3) for "Technical Writing"
6. System checks member's total credits vs. next level threshold
   → If threshold met: Initiate level verification process
```

### Scenario 2: Level Verification (Mentor Type)

```
1. Member reaches 50 credits in "Technical Writing"
2. Member has been at Level 1 for 30+ days
3. System creates LevelVerification:
   - target_level: 2
   - verification_type: "mentor"
   - status: "pending"
4. Mentors (Level 3+) see pending verification in dashboard
5. Mentor reviews member's work history (ratifications, endorsements)
6. Mentor approves:
   - LevelVerification.status = "approved"
   - MemberSkillProgress.current_level = 2
   - MemberSkillProgress.level_achieved_date = now
   - Notification sent to member
7. Member gains Level 2 capabilities (e.g., can work without ratification for this skill)
```

### Scenario 3: Level Verification (Committee Type)

```
1. Member reaches 400 credits + 180 days at Level 3
2. System creates LevelVerification:
   - target_level: 4
   - verification_type: "committee"
   - votes_required: 3
   - status: "pending"
3. All Level 4 members notified
4. Each Level 4 member can:
   - Review evidence (completed work, endorsements)
   - Cast vote: approve / deny / defer
   - Add feedback
5. When votes_received >= votes_required:
   - If majority approve: status = "approved", level granted
   - If majority deny: status = "denied", feedback provided
   - If mixed: status = "deferred", additional review needed
```

### Scenario 4: Work Review as Skill Evidence

```
1. Senior member reviews junior's completed task
2. Review form includes:
   - Overall approval (existing)
   - Skill credit checkboxes:
     [x] Technical Writing: Good (+10 credits)
     [ ] Technical Writing: Exceptional (+15 credits)
     [x] Research: Adequate (+5 credits)
3. On submit:
   - Ratification approved
   - SkillCredit entities created for each checked skill
   - Member's MemberSkillProgress updated
```

---

## Role Mapping

### Current Roles → Skill Levels

The existing role system (junior/endorsed/mentor/admin) can coexist with skill levels:

| Guild Role | Purpose | Skill Level Equivalent |
|------------|---------|----------------------|
| Junior | New member, needs oversight | Level 1 in primary skill |
| Endorsed | Can work independently | Level 2+ in at least one skill |
| Mentor | Can train and verify others | Level 3+ in at least one skill |
| Admin | Guild management | Any level + admin permissions |

### Skill-Based Permissions

```php
function avc_guild_skill_can_work_independently($user, $guild, $skill) {
  $progress = MemberSkillProgress::load($user, $guild, $skill);
  $level_config = SkillLevel::getForSkill($guild, $skill, $progress->current_level);
  return $progress->current_level >= 2; // Or configurable per guild
}

function avc_guild_skill_can_verify($user, $guild, $skill, $target_level) {
  $progress = MemberSkillProgress::load($user, $guild, $skill);
  $level_config = SkillLevel::getForSkill($guild, $skill, $target_level);
  return $progress->current_level >= $level_config->verifier_minimum_level;
}
```

---

## New Services

### SkillProgressionService

```php
class SkillProgressionService {

  /**
   * Award credits to a user for a skill.
   */
  public function awardCredits(
    UserInterface $user,
    GroupInterface $guild,
    TermInterface $skill,
    int $credits,
    string $source_type,
    ?int $source_id = NULL,
    ?UserInterface $reviewer = NULL
  ): SkillCredit;

  /**
   * Check if user is eligible for level advancement.
   */
  public function checkEligibility(
    UserInterface $user,
    GroupInterface $guild,
    TermInterface $skill
  ): ?int; // Returns target level or NULL

  /**
   * Initiate level verification process.
   */
  public function initiateVerification(
    UserInterface $user,
    GroupInterface $guild,
    TermInterface $skill,
    int $target_level
  ): LevelVerification;

  /**
   * Process a verification vote.
   */
  public function recordVote(
    LevelVerification $verification,
    UserInterface $verifier,
    string $vote, // 'approve', 'deny', 'defer'
    ?string $feedback = NULL
  ): void;

  /**
   * Get user's skill profile.
   */
  public function getSkillProfile(
    UserInterface $user,
    GroupInterface $guild
  ): array; // [skill_id => ['level' => int, 'credits' => int, 'next_level' => int]]

  /**
   * Get pending verifications for a verifier.
   */
  public function getPendingVerifications(
    UserInterface $verifier,
    ?GroupInterface $guild = NULL
  ): array;
}
```

### SkillConfigurationService

```php
class SkillConfigurationService {

  /**
   * Get all skill levels for a guild.
   */
  public function getGuildSkillLevels(GroupInterface $guild): array;

  /**
   * Get level configuration for a specific skill/level.
   */
  public function getLevelConfig(
    GroupInterface $guild,
    TermInterface $skill,
    int $level
  ): ?SkillLevel;

  /**
   * Save skill level configuration for a guild.
   */
  public function saveSkillLevelConfig(
    GroupInterface $guild,
    TermInterface $skill,
    array $levels
  ): void;

  /**
   * Get credit sources configuration.
   */
  public function getCreditSources(GroupInterface $guild): array;
}
```

---

## UI Components

### 1. Guild Skill Configuration Form

For guild admins to configure skill levels:

```
┌─────────────────────────────────────────────────────────────┐
│ Configure Skill Levels: Technical Writing                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Level 1: [Apprentice        ]                               │
│   Credits required: [0    ]  (Entry level)                  │
│   Verification: [Auto          ▼]                           │
│   Time at previous level: [0   ] days                       │
│                                                              │
│ Level 2: [Contributor       ]                               │
│   Credits required: [50   ]                                 │
│   Verification: [Mentor        ▼]                           │
│   Verifier min level: [3  ]                                 │
│   Time at previous level: [30  ] days                       │
│                                                              │
│ Level 3: [Editor            ]                               │
│   Credits required: [150  ]                                 │
│   Verification: [Peer (2 votes)▼]                           │
│   Verifier min level: [3  ]                                 │
│   Time at previous level: [90  ] days                       │
│                                                              │
│ [+ Add Level]                                                │
│                                                              │
│ Credit Sources:                                              │
│   Task completed:          [5  ] credits                    │
│   Task reviewed (approved): [10 ] credits                   │
│   Review given:            [3  ] credits                    │
│   Endorsement received:    [15 ] credits                    │
│                                                              │
│ [Save Configuration]                                         │
└─────────────────────────────────────────────────────────────┘
```

### 2. Member Skill Progress Dashboard

```
┌─────────────────────────────────────────────────────────────┐
│ My Skills - Technical Writing Guild                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Technical Writing                                            │
│ ├─ Current Level: ★★☆☆ Contributor (Level 2)               │
│ ├─ Credits: 87 / 150 for Editor (Level 3)                  │
│ ├─ Time at level: 45 days (90 required)                    │
│ └─ [████████░░░░░░░░] 58% to next level                    │
│                                                              │
│ Research                                                     │
│ ├─ Current Level: ★☆☆☆ Apprentice (Level 1)                │
│ ├─ Credits: 23 / 50 for Contributor (Level 2)              │
│ ├─ Time at level: 45 days (30 required) ✓                  │
│ └─ [█████░░░░░░░░░░░] 46% to next level                    │
│                                                              │
│ Pending Verification:                                        │
│ └─ (none)                                                   │
│                                                              │
│ Recent Credits:                                              │
│ • +10 Technical Writing - Task #234 approved (2 days ago)  │
│ • +5 Research - Task #234 approved (2 days ago)            │
│ • +3 Technical Writing - Reviewed task #201 (5 days ago)   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 3. Verification Queue (for Mentors)

```
┌─────────────────────────────────────────────────────────────┐
│ Pending Verifications                                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Sarah Jones → Editor (Level 3) in Technical Writing         │
│ ├─ Credits: 162/150 ✓                                       │
│ ├─ Time at level: 95 days (90 required) ✓                  │
│ ├─ Verification type: Peer (2 votes needed)                │
│ ├─ Current votes: 1 approve, 0 deny                        │
│ ├─ Evidence:                                                │
│ │   • 12 approved tasks                                     │
│ │   • 4 endorsements received                               │
│ │   • [View work history]                                   │
│ └─ [Approve] [Deny] [Defer]                                 │
│                                                              │
│ Mike Chen → Contributor (Level 2) in Research               │
│ ├─ Credits: 58/50 ✓                                         │
│ ├─ Time at level: 35 days (30 required) ✓                  │
│ ├─ Verification type: Mentor                               │
│ ├─ Evidence:                                                │
│ │   • 5 approved tasks                                      │
│ │   • 1 endorsement received                                │
│ │   • [View work history]                                   │
│ └─ [Approve] [Deny] [Defer]                                 │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 4. Enhanced Ratification Form

```
┌─────────────────────────────────────────────────────────────┐
│ Review Task: Editorial Review - Article #456                 │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Completed by: John Smith (Contributor - Level 2)            │
│ Submitted: January 10, 2026                                  │
│                                                              │
│ [View submitted work]                                        │
│                                                              │
│ Overall Decision:                                            │
│ ○ Approve  ○ Request Changes                                │
│                                                              │
│ Feedback:                                                    │
│ ┌──────────────────────────────────────────────────────────┐│
│ │                                                          ││
│ └──────────────────────────────────────────────────────────┘│
│                                                              │
│ Skill Credits (check applicable):                           │
│ ┌──────────────────────────────────────────────────────────┐│
│ │ Technical Writing:                                       ││
│ │   ○ None  ● Standard (+10)  ○ Exceptional (+15)         ││
│ │                                                          ││
│ │ Research:                                                ││
│ │   ○ None  ● Standard (+5)   ○ Exceptional (+8)          ││
│ │                                                          ││
│ │ Theology:                                                ││
│ │   ● None  ○ Standard (+5)   ○ Exceptional (+8)          ││
│ └──────────────────────────────────────────────────────────┘│
│                                                              │
│ [Submit Review]                                              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Alternative Verification Approaches

### Approach 1: Review-Based Auto-Credit (Current Proposal)

```
Work → Review → Credits Awarded → Threshold → Verification
```
- Credits accumulate from approved work
- Level verification triggered when threshold met
- Verification confirms eligibility

### Approach 2: Portfolio Review

```
Work → Portfolio → Submission → Committee Review → Level Granted
```
- Member collects body of work
- Submits portfolio for level advancement
- Committee reviews holistically
- No credit counting, just quality assessment

### Approach 3: Assessment-Based

```
Work → Experience → Assessment Request → Test/Demo → Level Granted
```
- Member requests assessment when ready
- Formal evaluation (practical or written)
- Pass/fail determination
- Can combine with minimum work requirements

### Approach 4: Time + Endorsement

```
Work → Time at Level → Endorsements → Level Granted
```
- Minimum time at each level (e.g., 6 months)
- Must receive N endorsements from higher-level members
- No credit counting, social validation

### Approach 5: Hybrid (Recommended)

```
Credits + Time + Verification
```
- Credits track effort and approved work
- Time requirement prevents rushing
- Verification confirms quality
- Configurable per guild/skill/level

---

## Database Schema

### New Tables

```sql
-- Skill level definitions per guild
CREATE TABLE skill_level (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  guild_id INT UNSIGNED NOT NULL,
  skill_id INT UNSIGNED NOT NULL,
  level INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  credits_required INT DEFAULT 0,
  verification_type VARCHAR(32) DEFAULT 'mentor',
  verifier_minimum_level INT DEFAULT 0,
  votes_required INT DEFAULT 1,
  time_minimum_days INT DEFAULT 0,
  weight INT DEFAULT 0,
  created INT NOT NULL,
  changed INT NOT NULL,
  UNIQUE KEY (guild_id, skill_id, level),
  INDEX (guild_id),
  INDEX (skill_id)
);

-- Member progress in each skill
CREATE TABLE member_skill_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  guild_id INT UNSIGNED NOT NULL,
  skill_id INT UNSIGNED NOT NULL,
  current_level INT DEFAULT 0,
  current_credits INT DEFAULT 0,
  level_achieved_date INT,
  pending_verification TINYINT DEFAULT 0,
  created INT NOT NULL,
  changed INT NOT NULL,
  UNIQUE KEY (user_id, guild_id, skill_id),
  INDEX (guild_id, skill_id),
  INDEX (user_id)
);

-- Individual credit events
CREATE TABLE skill_credit (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  guild_id INT UNSIGNED NOT NULL,
  skill_id INT UNSIGNED NOT NULL,
  credits INT NOT NULL,
  source_type VARCHAR(32) NOT NULL,
  source_id INT UNSIGNED,
  reviewer_id INT UNSIGNED,
  notes TEXT,
  created INT NOT NULL,
  INDEX (user_id, guild_id, skill_id),
  INDEX (source_type, source_id)
);

-- Level verification records
CREATE TABLE level_verification (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  guild_id INT UNSIGNED NOT NULL,
  skill_id INT UNSIGNED NOT NULL,
  target_level INT NOT NULL,
  status VARCHAR(32) DEFAULT 'pending',
  verification_type VARCHAR(32) NOT NULL,
  votes_required INT DEFAULT 1,
  votes_approve INT DEFAULT 0,
  votes_deny INT DEFAULT 0,
  votes_defer INT DEFAULT 0,
  feedback TEXT,
  created INT NOT NULL,
  changed INT NOT NULL,
  completed INT,
  INDEX (status),
  INDEX (user_id, guild_id),
  INDEX (guild_id, skill_id, status)
);

-- Verification votes
CREATE TABLE level_verification_vote (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  verification_id INT UNSIGNED NOT NULL,
  verifier_id INT UNSIGNED NOT NULL,
  vote VARCHAR(16) NOT NULL, -- 'approve', 'deny', 'defer'
  feedback TEXT,
  created INT NOT NULL,
  UNIQUE KEY (verification_id, verifier_id),
  INDEX (verification_id)
);
```

---

## Implementation Phases

### Phase 1: Foundation (Entities + Basic Services)

1. Create new entities:
   - `SkillLevel`
   - `MemberSkillProgress`
   - `SkillCredit`
   - `LevelVerification`

2. Create services:
   - `SkillProgressionService`
   - `SkillConfigurationService`

3. Add database schema via `.install` hooks

### Phase 2: Configuration UI

1. Guild admin form for skill level configuration
2. Seed default skill levels from existing `guild_skills` vocabulary
3. Migration of existing endorsements to credits

### Phase 3: Workflow Integration

1. Enhance `RatificationService` to award skill credits
2. Update ratification form with skill credit checkboxes
3. Trigger verification eligibility checks after credit awards

### Phase 4: Verification Workflow

1. Create verification queue views
2. Verification forms for different types (mentor, peer, committee)
3. Notification integration for verification events

### Phase 5: Member Dashboard

1. Skill progress display on member profile
2. Credit history view
3. Verification status tracking

### Phase 6: Analytics + Reporting

1. Guild-wide skill distribution reports
2. Progression rate analytics
3. Verification success rate tracking

---

## Integration with Existing Systems

### Scoring System

The new skill credits **complement** the existing `GuildScore` points:
- `GuildScore` = overall guild reputation/contribution
- `SkillCredit` = skill-specific progression evidence

Both can coexist:
```php
// In RatificationService::approve()
$this->scoringService->awardPoints($user, $guild, 'task_ratified', 15);
$this->skillProgressionService->awardCredits($user, $guild, $skill, 10, 'task_review', $task->id());
```

### Role System

Skill levels can **inform** role progression:
```php
// Check if user qualifies for 'endorsed' role
$skills = $this->skillProgressionService->getSkillProfile($user, $guild);
$qualifies = FALSE;
foreach ($skills as $skill_data) {
  if ($skill_data['level'] >= 2) {
    $qualifies = TRUE;
    break;
  }
}
```

### Endorsement System

Existing endorsements can:
- Remain as social validation separate from skill levels
- Convert to skill credits (one-time migration or ongoing)
- Serve as bonus evidence in level verification

---

## Configuration Flexibility Summary

| What | Configured By | Where |
|------|--------------|-------|
| Which skills exist | Site admin | `guild_skills` vocabulary |
| Which skills a guild uses | Guild admin | `field_guild_skills` on group |
| How many levels per skill | Guild admin | `SkillLevel` entities per guild/skill |
| Level names and descriptions | Guild admin | `SkillLevel.name`, `.description` |
| Credits required per level | Guild admin | `SkillLevel.credits_required` |
| Verification type | Guild admin | `SkillLevel.verification_type` |
| Who can verify | Guild admin | `SkillLevel.verifier_minimum_level` |
| Time requirements | Guild admin | `SkillLevel.time_minimum_days` |
| Credit amounts per action | Guild admin | Guild config or global defaults |

---

## Example Guild Configurations

### Theology Guild (Academic Focus)

```
Skill: Biblical Exegesis
  Level 1: Student (auto, 0 credits)
  Level 2: Reader (mentor, 100 credits, 60 days)
  Level 3: Interpreter (peer×2, 300 credits, 180 days)
  Level 4: Scholar (committee×3, 600 credits, 365 days)

Credit sources:
  - Exegesis paper reviewed: +20
  - Teaching session delivered: +15
  - Peer study led: +10
```

### Creative Writing Guild (Portfolio Focus)

```
Skill: Fiction Writing
  Level 1: Apprentice Writer (auto, 0 credits)
  Level 2: Contributing Writer (mentor, 75 credits, 45 days)
  Level 3: Staff Writer (portfolio review, 200 credits, 120 days)
  Level 4: Senior Writer (committee×2, 400 credits, 240 days)

Credit sources:
  - Story published: +25
  - Story edited/reviewed: +15
  - Workshop participation: +5
```

### Technical Skills Guild (Practical Focus)

```
Skill: Video Editing
  Level 1: Trainee (auto, 0 credits)
  Level 2: Editor (assessment, 50 credits, 30 days)
  Level 3: Senior Editor (mentor + demo, 150 credits, 90 days)
  Level 4: Master Editor (portfolio + interview, 350 credits, 180 days)

Credit sources:
  - Video completed: +10
  - Complex project: +20
  - Training delivered: +15
```

---

## Open Questions

1. **Skill interdependencies**: Should some skills require others as prerequisites?
2. **Credit decay**: Should old credits expire or reduce over time?
3. **Cross-guild recognition**: Can skill levels transfer between guilds?
4. **External credentials**: How to incorporate external certifications?
5. **Demotion**: Can levels be revoked? Under what circumstances?
6. **Maximum levels**: Should there be a cap, or allow unlimited levels?

---

## Next Steps

1. Review this design with stakeholders
2. Prioritize which features are MVP vs. future
3. Create technical specifications for Phase 1
4. Estimate development effort
5. Plan data migration from existing endorsements

---
---

# Phase 1: Technical Specification

## Overview

Phase 1 establishes the foundation for the skill level system:
- 4 new content entities
- 2 new services
- Database schema via entity system
- Permissions and access control
- Basic integration hooks

**Estimated Files**: ~15 new files, ~5 modified files

---

## 1. Entity Definitions

### 1.1 SkillLevel Entity

**File**: `src/Entity/SkillLevel.php`

```php
<?php

namespace Drupal\avc_guild\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Defines the Skill Level entity.
 *
 * Configures skill levels available within a guild.
 *
 * @ContentEntityType(
 *   id = "skill_level",
 *   label = @Translation("Skill Level"),
 *   label_collection = @Translation("Skill Levels"),
 *   label_singular = @Translation("skill level"),
 *   label_plural = @Translation("skill levels"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\avc_guild\SkillLevelListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\avc_guild\Form\SkillLevelForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\avc_guild\SkillLevelAccessControlHandler",
 *   },
 *   base_table = "skill_level",
 *   admin_permission = "administer skill levels",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name",
 *   },
 *   links = {
 *     "collection" = "/admin/config/avc/guild/skill-levels",
 *     "canonical" = "/guild/{group}/skill-level/{skill_level}",
 *     "edit-form" = "/guild/{group}/skill-level/{skill_level}/edit",
 *     "delete-form" = "/guild/{group}/skill-level/{skill_level}/delete",
 *   },
 * )
 */
class SkillLevel extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * Verification types.
   */
  const VERIFICATION_AUTO = 'auto';
  const VERIFICATION_MENTOR = 'mentor';
  const VERIFICATION_PEER = 'peer';
  const VERIFICATION_COMMITTEE = 'committee';
  const VERIFICATION_ASSESSMENT = 'assessment';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Guild reference.
    $fields['guild_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Guild'))
      ->setDescription(t('The guild this skill level belongs to.'))
      ->setSetting('target_type', 'group')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Skill reference.
    $fields['skill_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Skill'))
      ->setDescription(t('The skill this level applies to.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => ['guild_skills' => 'guild_skills'],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Level number (1-10).
    $fields['level'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Level'))
      ->setDescription(t('The level number (1 = entry level, higher = more advanced).'))
      ->setRequired(TRUE)
      ->setDefaultValue(1)
      ->setSetting('min', 1)
      ->setSetting('max', 10)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Level name (e.g., "Apprentice", "Journeyman").
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The display name for this level.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Description.
    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('Description of what this level means and its capabilities.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 4,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Credits required to reach this level.
    $fields['credits_required'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Credits Required'))
      ->setDescription(t('Number of credits needed to qualify for this level.'))
      ->setRequired(TRUE)
      ->setDefaultValue(0)
      ->setSetting('min', 0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Verification type.
    $fields['verification_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Verification Type'))
      ->setDescription(t('How advancement to this level is verified.'))
      ->setRequired(TRUE)
      ->setDefaultValue(self::VERIFICATION_MENTOR)
      ->setSettings([
        'allowed_values' => [
          self::VERIFICATION_AUTO => 'Automatic (when credits + time met)',
          self::VERIFICATION_MENTOR => 'Mentor Approval',
          self::VERIFICATION_PEER => 'Peer Votes',
          self::VERIFICATION_COMMITTEE => 'Committee Vote',
          self::VERIFICATION_ASSESSMENT => 'Formal Assessment',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Minimum level required to verify.
    $fields['verifier_minimum_level'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Verifier Minimum Level'))
      ->setDescription(t('The minimum skill level a verifier must have to approve this level.'))
      ->setDefaultValue(0)
      ->setSetting('min', 0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 7,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Votes required (for peer/committee verification).
    $fields['votes_required'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Votes Required'))
      ->setDescription(t('Number of approval votes needed for peer/committee verification.'))
      ->setDefaultValue(1)
      ->setSetting('min', 1)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 8,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Minimum days at previous level.
    $fields['time_minimum_days'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Time Minimum (Days)'))
      ->setDescription(t('Minimum days at the previous level before eligibility.'))
      ->setDefaultValue(0)
      ->setSetting('min', 0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 9,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Weight for ordering.
    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('Weight for ordering levels.'))
      ->setDefaultValue(0);

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the level was created.'));

    // Changed timestamp.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the level was last updated.'));

    return $fields;
  }

  /**
   * Gets the guild.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The guild entity.
   */
  public function getGuild(): ?GroupInterface {
    return $this->get('guild_id')->entity;
  }

  /**
   * Gets the skill.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The skill term.
   */
  public function getSkill(): ?TermInterface {
    return $this->get('skill_id')->entity;
  }

  /**
   * Gets the level number.
   *
   * @return int
   *   The level number.
   */
  public function getLevel(): int {
    return (int) $this->get('level')->value;
  }

  /**
   * Gets the level name.
   *
   * @return string
   *   The level name.
   */
  public function getName(): string {
    return $this->get('name')->value ?? '';
  }

  /**
   * Gets the credits required.
   *
   * @return int
   *   The credits required.
   */
  public function getCreditsRequired(): int {
    return (int) $this->get('credits_required')->value;
  }

  /**
   * Gets the verification type.
   *
   * @return string
   *   The verification type.
   */
  public function getVerificationType(): string {
    return $this->get('verification_type')->value ?? self::VERIFICATION_MENTOR;
  }

  /**
   * Gets the verifier minimum level.
   *
   * @return int
   *   The minimum level.
   */
  public function getVerifierMinimumLevel(): int {
    return (int) $this->get('verifier_minimum_level')->value;
  }

  /**
   * Gets the votes required.
   *
   * @return int
   *   The votes required.
   */
  public function getVotesRequired(): int {
    return (int) $this->get('votes_required')->value ?: 1;
  }

  /**
   * Gets the time minimum in days.
   *
   * @return int
   *   The minimum days.
   */
  public function getTimeMinimumDays(): int {
    return (int) $this->get('time_minimum_days')->value;
  }

  /**
   * Checks if this is an auto-verification level.
   *
   * @return bool
   *   TRUE if auto-verified.
   */
  public function isAutoVerified(): bool {
    return $this->getVerificationType() === self::VERIFICATION_AUTO;
  }

}
```

---

### 1.2 MemberSkillProgress Entity

**File**: `src/Entity/MemberSkillProgress.php`

```php
<?php

namespace Drupal\avc_guild\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Member Skill Progress entity.
 *
 * Tracks a member's current level and credit progress in each skill.
 *
 * @ContentEntityType(
 *   id = "member_skill_progress",
 *   label = @Translation("Member Skill Progress"),
 *   label_collection = @Translation("Member Skill Progress"),
 *   label_singular = @Translation("member skill progress"),
 *   label_plural = @Translation("member skill progress records"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\avc_guild\MemberSkillProgressListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\avc_guild\MemberSkillProgressAccessControlHandler",
 *   },
 *   base_table = "member_skill_progress",
 *   admin_permission = "administer member skill progress",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/avc/guild/skill-progress",
 *   },
 * )
 */
class MemberSkillProgress extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // User reference.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user whose progress is tracked.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Guild reference.
    $fields['guild_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Guild'))
      ->setDescription(t('The guild context for this progress.'))
      ->setSetting('target_type', 'group')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Skill reference.
    $fields['skill_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Skill'))
      ->setDescription(t('The skill being tracked.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => ['guild_skills' => 'guild_skills'],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Current level (0 = none).
    $fields['current_level'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Current Level'))
      ->setDescription(t('The current skill level (0 = no level achieved).'))
      ->setRequired(TRUE)
      ->setDefaultValue(0)
      ->setSetting('min', 0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Current credits (toward next level).
    $fields['current_credits'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Current Credits'))
      ->setDescription(t('Credits accumulated toward the next level.'))
      ->setRequired(TRUE)
      ->setDefaultValue(0)
      ->setSetting('min', 0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Date when current level was achieved.
    $fields['level_achieved_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Level Achieved Date'))
      ->setDescription(t('When the current level was confirmed.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Whether verification is pending.
    $fields['pending_verification'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Pending Verification'))
      ->setDescription(t('Whether the user is awaiting level verification.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'boolean',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the record was created.'));

    // Changed timestamp.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the record was last updated.'));

    return $fields;
  }

  /**
   * Gets the user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user entity.
   */
  public function getUser(): ?UserInterface {
    return $this->get('user_id')->entity;
  }

  /**
   * Gets the guild.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The guild entity.
   */
  public function getGuild(): ?GroupInterface {
    return $this->get('guild_id')->entity;
  }

  /**
   * Gets the skill.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The skill term.
   */
  public function getSkill(): ?TermInterface {
    return $this->get('skill_id')->entity;
  }

  /**
   * Gets the current level.
   *
   * @return int
   *   The current level.
   */
  public function getCurrentLevel(): int {
    return (int) $this->get('current_level')->value;
  }

  /**
   * Sets the current level.
   *
   * @param int $level
   *   The level to set.
   *
   * @return $this
   */
  public function setCurrentLevel(int $level): self {
    $this->set('current_level', $level);
    $this->set('level_achieved_date', \Drupal::time()->getRequestTime());
    return $this;
  }

  /**
   * Gets the current credits.
   *
   * @return int
   *   The current credits.
   */
  public function getCurrentCredits(): int {
    return (int) $this->get('current_credits')->value;
  }

  /**
   * Adds credits.
   *
   * @param int $credits
   *   The credits to add.
   *
   * @return $this
   */
  public function addCredits(int $credits): self {
    $current = $this->getCurrentCredits();
    $this->set('current_credits', $current + $credits);
    return $this;
  }

  /**
   * Resets credits (after level advancement).
   *
   * @return $this
   */
  public function resetCredits(): self {
    $this->set('current_credits', 0);
    return $this;
  }

  /**
   * Gets the level achieved date.
   *
   * @return int|null
   *   The timestamp or NULL.
   */
  public function getLevelAchievedDate(): ?int {
    return $this->get('level_achieved_date')->value;
  }

  /**
   * Gets days at current level.
   *
   * @return int
   *   Number of days.
   */
  public function getDaysAtCurrentLevel(): int {
    $achieved = $this->getLevelAchievedDate();
    if (!$achieved) {
      // Use created date if no level achieved yet.
      $achieved = $this->get('created')->value;
    }

    $now = \Drupal::time()->getRequestTime();
    $diff = $now - $achieved;

    return (int) floor($diff / 86400);
  }

  /**
   * Checks if pending verification.
   *
   * @return bool
   *   TRUE if pending.
   */
  public function isPendingVerification(): bool {
    return (bool) $this->get('pending_verification')->value;
  }

  /**
   * Sets pending verification status.
   *
   * @param bool $pending
   *   The status.
   *
   * @return $this
   */
  public function setPendingVerification(bool $pending): self {
    $this->set('pending_verification', $pending);
    return $this;
  }

  /**
   * Loads or creates progress for a user/guild/skill combination.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   *
   * @return static
   *   The progress entity (new or existing).
   */
  public static function loadOrCreate(
    UserInterface $user,
    GroupInterface $guild,
    TermInterface $skill
  ): self {
    $storage = \Drupal::entityTypeManager()->getStorage('member_skill_progress');

    $existing = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_id', $user->id())
      ->condition('guild_id', $guild->id())
      ->condition('skill_id', $skill->id())
      ->execute();

    if (!empty($existing)) {
      return $storage->load(reset($existing));
    }

    // Create new progress record.
    return $storage->create([
      'user_id' => $user->id(),
      'guild_id' => $guild->id(),
      'skill_id' => $skill->id(),
      'current_level' => 0,
      'current_credits' => 0,
    ]);
  }

}
```

---

### 1.3 SkillCredit Entity

**File**: `src/Entity/SkillCredit.php`

```php
<?php

namespace Drupal\avc_guild\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Skill Credit entity.
 *
 * Records individual credit events toward skill advancement.
 *
 * @ContentEntityType(
 *   id = "skill_credit",
 *   label = @Translation("Skill Credit"),
 *   label_collection = @Translation("Skill Credits"),
 *   label_singular = @Translation("skill credit"),
 *   label_plural = @Translation("skill credits"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\avc_guild\SkillCreditListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\avc_guild\SkillCreditAccessControlHandler",
 *   },
 *   base_table = "skill_credit",
 *   admin_permission = "administer skill credits",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/avc/guild/skill-credits",
 *   },
 * )
 */
class SkillCredit extends ContentEntityBase {

  /**
   * Source types for credits.
   */
  const SOURCE_TASK_REVIEW = 'task_review';
  const SOURCE_ENDORSEMENT = 'endorsement';
  const SOURCE_ASSESSMENT = 'assessment';
  const SOURCE_TIME = 'time';
  const SOURCE_MANUAL = 'manual';
  const SOURCE_MIGRATION = 'migration';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // User reference.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user who received the credits.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Guild reference.
    $fields['guild_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Guild'))
      ->setDescription(t('The guild context for this credit.'))
      ->setSetting('target_type', 'group')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Skill reference.
    $fields['skill_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Skill'))
      ->setDescription(t('The skill this credit applies to.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => ['guild_skills' => 'guild_skills'],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Credit amount.
    $fields['credits'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Credits'))
      ->setDescription(t('The number of credits awarded.'))
      ->setRequired(TRUE)
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Source type.
    $fields['source_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Source Type'))
      ->setDescription(t('How the credits were earned.'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          self::SOURCE_TASK_REVIEW => 'Task Review',
          self::SOURCE_ENDORSEMENT => 'Endorsement',
          self::SOURCE_ASSESSMENT => 'Assessment',
          self::SOURCE_TIME => 'Time-based',
          self::SOURCE_MANUAL => 'Manual Award',
          self::SOURCE_MIGRATION => 'Migration',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Source entity ID (optional reference).
    $fields['source_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Source ID'))
      ->setDescription(t('The entity ID of the source (task, endorsement, etc.).'));

    // Reviewer who granted the credit.
    $fields['reviewer_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Reviewer'))
      ->setDescription(t('The user who awarded these credits (if applicable).'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Notes.
    $fields['notes'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Optional notes about this credit award.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the credit was awarded.'));

    return $fields;
  }

  /**
   * Gets the user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user entity.
   */
  public function getUser(): ?UserInterface {
    return $this->get('user_id')->entity;
  }

  /**
   * Gets the guild.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The guild entity.
   */
  public function getGuild(): ?GroupInterface {
    return $this->get('guild_id')->entity;
  }

  /**
   * Gets the skill.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The skill term.
   */
  public function getSkill(): ?TermInterface {
    return $this->get('skill_id')->entity;
  }

  /**
   * Gets the credits.
   *
   * @return int
   *   The credit amount.
   */
  public function getCredits(): int {
    return (int) $this->get('credits')->value;
  }

  /**
   * Gets the source type.
   *
   * @return string
   *   The source type.
   */
  public function getSourceType(): string {
    return $this->get('source_type')->value ?? '';
  }

  /**
   * Gets the source ID.
   *
   * @return int|null
   *   The source entity ID or NULL.
   */
  public function getSourceId(): ?int {
    $value = $this->get('source_id')->value;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * Gets the reviewer.
   *
   * @return \Drupal\user\UserInterface|null
   *   The reviewer user entity.
   */
  public function getReviewer(): ?UserInterface {
    return $this->get('reviewer_id')->entity;
  }

}
```

---

### 1.4 LevelVerification Entity

**File**: `src/Entity/LevelVerification.php`

```php
<?php

namespace Drupal\avc_guild\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Level Verification entity.
 *
 * Records verification/confirmation of level advancement.
 *
 * @ContentEntityType(
 *   id = "level_verification",
 *   label = @Translation("Level Verification"),
 *   label_collection = @Translation("Level Verifications"),
 *   label_singular = @Translation("level verification"),
 *   label_plural = @Translation("level verifications"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\avc_guild\LevelVerificationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "vote" = "Drupal\avc_guild\Form\LevelVerificationVoteForm",
 *     },
 *     "access" = "Drupal\avc_guild\LevelVerificationAccessControlHandler",
 *   },
 *   base_table = "level_verification",
 *   admin_permission = "administer level verifications",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/avc/guild/verifications",
 *     "canonical" = "/guild/{group}/verification/{level_verification}",
 *     "vote-form" = "/guild/{group}/verification/{level_verification}/vote",
 *   },
 * )
 */
class LevelVerification extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * Status values.
   */
  const STATUS_PENDING = 'pending';
  const STATUS_APPROVED = 'approved';
  const STATUS_DENIED = 'denied';
  const STATUS_DEFERRED = 'deferred';
  const STATUS_EXPIRED = 'expired';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // User being verified.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user being verified for level advancement.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Guild reference.
    $fields['guild_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Guild'))
      ->setDescription(t('The guild context.'))
      ->setSetting('target_type', 'group')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Skill reference.
    $fields['skill_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Skill'))
      ->setDescription(t('The skill being verified.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => ['guild_skills' => 'guild_skills'],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Target level.
    $fields['target_level'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Target Level'))
      ->setDescription(t('The level being verified for.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Status.
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The verification status.'))
      ->setRequired(TRUE)
      ->setDefaultValue(self::STATUS_PENDING)
      ->setSettings([
        'allowed_values' => [
          self::STATUS_PENDING => 'Pending',
          self::STATUS_APPROVED => 'Approved',
          self::STATUS_DENIED => 'Denied',
          self::STATUS_DEFERRED => 'Deferred',
          self::STATUS_EXPIRED => 'Expired',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Verification type (copied from SkillLevel at creation).
    $fields['verification_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Verification Type'))
      ->setDescription(t('The type of verification required.'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          SkillLevel::VERIFICATION_AUTO => 'Automatic',
          SkillLevel::VERIFICATION_MENTOR => 'Mentor Approval',
          SkillLevel::VERIFICATION_PEER => 'Peer Votes',
          SkillLevel::VERIFICATION_COMMITTEE => 'Committee Vote',
          SkillLevel::VERIFICATION_ASSESSMENT => 'Formal Assessment',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Votes required.
    $fields['votes_required'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Votes Required'))
      ->setDescription(t('Number of approval votes needed.'))
      ->setDefaultValue(1)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Vote tallies.
    $fields['votes_approve'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Approve Votes'))
      ->setDescription(t('Number of approval votes received.'))
      ->setDefaultValue(0);

    $fields['votes_deny'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Deny Votes'))
      ->setDescription(t('Number of denial votes received.'))
      ->setDefaultValue(0);

    $fields['votes_defer'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Defer Votes'))
      ->setDescription(t('Number of defer votes received.'))
      ->setDefaultValue(0);

    // Feedback.
    $fields['feedback'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Feedback'))
      ->setDescription(t('Collected feedback from verifiers.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the verification was initiated.'));

    // Changed timestamp.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the verification was last updated.'));

    // Completed timestamp.
    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDescription(t('The time the verification was completed.'));

    return $fields;
  }

  /**
   * Gets the user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user entity.
   */
  public function getUser(): ?UserInterface {
    return $this->get('user_id')->entity;
  }

  /**
   * Gets the guild.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The guild entity.
   */
  public function getGuild(): ?GroupInterface {
    return $this->get('guild_id')->entity;
  }

  /**
   * Gets the skill.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The skill term.
   */
  public function getSkill(): ?TermInterface {
    return $this->get('skill_id')->entity;
  }

  /**
   * Gets the target level.
   *
   * @return int
   *   The target level.
   */
  public function getTargetLevel(): int {
    return (int) $this->get('target_level')->value;
  }

  /**
   * Gets the status.
   *
   * @return string
   *   The status.
   */
  public function getStatus(): string {
    return $this->get('status')->value ?? self::STATUS_PENDING;
  }

  /**
   * Sets the status.
   *
   * @param string $status
   *   The status.
   *
   * @return $this
   */
  public function setStatus(string $status): self {
    $this->set('status', $status);
    if (in_array($status, [self::STATUS_APPROVED, self::STATUS_DENIED, self::STATUS_EXPIRED])) {
      $this->set('completed', \Drupal::time()->getRequestTime());
    }
    return $this;
  }

  /**
   * Checks if pending.
   *
   * @return bool
   *   TRUE if pending.
   */
  public function isPending(): bool {
    return $this->getStatus() === self::STATUS_PENDING;
  }

  /**
   * Checks if approved.
   *
   * @return bool
   *   TRUE if approved.
   */
  public function isApproved(): bool {
    return $this->getStatus() === self::STATUS_APPROVED;
  }

  /**
   * Gets approval votes.
   *
   * @return int
   *   The count.
   */
  public function getApproveVotes(): int {
    return (int) $this->get('votes_approve')->value;
  }

  /**
   * Gets denial votes.
   *
   * @return int
   *   The count.
   */
  public function getDenyVotes(): int {
    return (int) $this->get('votes_deny')->value;
  }

  /**
   * Gets defer votes.
   *
   * @return int
   *   The count.
   */
  public function getDeferVotes(): int {
    return (int) $this->get('votes_defer')->value;
  }

  /**
   * Gets votes required.
   *
   * @return int
   *   The count.
   */
  public function getVotesRequired(): int {
    return (int) $this->get('votes_required')->value ?: 1;
  }

  /**
   * Gets total votes cast.
   *
   * @return int
   *   The total.
   */
  public function getTotalVotes(): int {
    return $this->getApproveVotes() + $this->getDenyVotes() + $this->getDeferVotes();
  }

  /**
   * Increments a vote count.
   *
   * @param string $vote_type
   *   One of 'approve', 'deny', 'defer'.
   *
   * @return $this
   */
  public function incrementVote(string $vote_type): self {
    $field_name = 'votes_' . $vote_type;
    if ($this->hasField($field_name)) {
      $current = (int) $this->get($field_name)->value;
      $this->set($field_name, $current + 1);
    }
    return $this;
  }

  /**
   * Appends feedback.
   *
   * @param string $feedback
   *   The feedback to append.
   * @param \Drupal\user\UserInterface|null $verifier
   *   The verifier who gave feedback.
   *
   * @return $this
   */
  public function appendFeedback(string $feedback, ?UserInterface $verifier = NULL): self {
    $existing = $this->get('feedback')->value ?? '';
    $prefix = $verifier ? $verifier->getDisplayName() . ': ' : '';
    $timestamp = date('Y-m-d H:i');

    $new_feedback = $existing;
    if ($new_feedback) {
      $new_feedback .= "\n\n---\n\n";
    }
    $new_feedback .= "[$timestamp] $prefix$feedback";

    $this->set('feedback', $new_feedback);
    return $this;
  }

}
```

---

## 2. Service Definitions

### 2.1 SkillProgressionService

**File**: `src/Service/SkillProgressionService.php`

```php
<?php

namespace Drupal\avc_guild\Service;

use Drupal\avc_guild\Entity\LevelVerification;
use Drupal\avc_guild\Entity\MemberSkillProgress;
use Drupal\avc_guild\Entity\SkillCredit;
use Drupal\avc_guild\Entity\SkillLevel;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;

/**
 * Service for managing skill progression.
 */
class SkillProgressionService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The skill configuration service.
   *
   * @var \Drupal\avc_guild\Service\SkillConfigurationService
   */
  protected SkillConfigurationService $configService;

  /**
   * Constructs a SkillProgressionService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\avc_guild\Service\SkillConfigurationService $config_service
   *   The skill configuration service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    SkillConfigurationService $config_service
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configService = $config_service;
  }

  /**
   * Awards credits to a user for a skill.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to award credits to.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild context.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   * @param int $credits
   *   The credits to award.
   * @param string $source_type
   *   The source type.
   * @param int|null $source_id
   *   Optional source entity ID.
   * @param \Drupal\user\UserInterface|null $reviewer
   *   Optional reviewer who granted credits.
   * @param string|null $notes
   *   Optional notes.
   *
   * @return \Drupal\avc_guild\Entity\SkillCredit
   *   The created credit entity.
   */
  public function awardCredits(
    UserInterface $user,
    GroupInterface $guild,
    TermInterface $skill,
    int $credits,
    string $source_type,
    ?int $source_id = NULL,
    ?UserInterface $reviewer = NULL,
    ?string $notes = NULL
  ): SkillCredit {
    // Create credit record.
    $values = [
      'user_id' => $user->id(),
      'guild_id' => $guild->id(),
      'skill_id' => $skill->id(),
      'credits' => $credits,
      'source_type' => $source_type,
    ];

    if ($source_id !== NULL) {
      $values['source_id'] = $source_id;
    }

    if ($reviewer) {
      $values['reviewer_id'] = $reviewer->id();
    }

    if ($notes) {
      $values['notes'] = $notes;
    }

    /** @var \Drupal\avc_guild\Entity\SkillCredit $credit */
    $credit = $this->entityTypeManager
      ->getStorage('skill_credit')
      ->create($values);
    $credit->save();

    // Update progress.
    $progress = MemberSkillProgress::loadOrCreate($user, $guild, $skill);
    $progress->addCredits($credits);
    $progress->save();

    // Check eligibility for next level.
    $this->checkAndInitiateVerification($user, $guild, $skill);

    return $credit;
  }

  /**
   * Checks if user is eligible for level advancement.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   *
   * @return int|null
   *   The target level if eligible, NULL otherwise.
   */
  public function checkEligibility(
    UserInterface $user,
    GroupInterface $guild,
    TermInterface $skill
  ): ?int {
    $progress = MemberSkillProgress::loadOrCreate($user, $guild, $skill);

    // Already pending verification?
    if ($progress->isPendingVerification()) {
      return NULL;
    }

    $current_level = $progress->getCurrentLevel();
    $next_level = $current_level + 1;

    // Get next level config.
    $level_config = $this->configService->getLevelConfig($guild, $skill, $next_level);
    if (!$level_config) {
      // No next level defined.
      return NULL;
    }

    // Check credits.
    if ($progress->getCurrentCredits() < $level_config->getCreditsRequired()) {
      return NULL;
    }

    // Check time.
    $days = $progress->getDaysAtCurrentLevel();
    if ($days < $level_config->getTimeMinimumDays()) {
      return NULL;
    }

    return $next_level;
  }

  /**
   * Checks eligibility and initiates verification if appropriate.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   */
  protected function checkAndInitiateVerification(
    UserInterface $user,
    GroupInterface $guild,
    TermInterface $skill
  ): void {
    $target_level = $this->checkEligibility($user, $guild, $skill);

    if ($target_level === NULL) {
      return;
    }

    $level_config = $this->configService->getLevelConfig($guild, $skill, $target_level);

    // Auto-verification: grant immediately.
    if ($level_config->isAutoVerified()) {
      $this->grantLevel($user, $guild, $skill, $target_level);
      return;
    }

    // Otherwise, initiate verification.
    $this->initiateVerification($user, $guild, $skill, $target_level);
  }

  /**
   * Initiates level verification process.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   * @param int $target_level
   *   The target level.
   *
   * @return \Drupal\avc_guild\Entity\LevelVerification
   *   The created verification entity.
   */
  public function initiateVerification(
    UserInterface $user,
    GroupInterface $guild,
    TermInterface $skill,
    int $target_level
  ): LevelVerification {
    $level_config = $this->configService->getLevelConfig($guild, $skill, $target_level);

    /** @var \Drupal\avc_guild\Entity\LevelVerification $verification */
    $verification = $this->entityTypeManager
      ->getStorage('level_verification')
      ->create([
        'user_id' => $user->id(),
        'guild_id' => $guild->id(),
        'skill_id' => $skill->id(),
        'target_level' => $target_level,
        'status' => LevelVerification::STATUS_PENDING,
        'verification_type' => $level_config->getVerificationType(),
        'votes_required' => $level_config->getVotesRequired(),
      ]);
    $verification->save();

    // Mark progress as pending.
    $progress = MemberSkillProgress::loadOrCreate($user, $guild, $skill);
    $progress->setPendingVerification(TRUE);
    $progress->save();

    // Trigger hook for notifications.
    \Drupal::moduleHandler()->invokeAll('avc_guild_verification_initiated', [
      $verification,
      $user,
      $guild,
      $skill,
      $target_level,
    ]);

    return $verification;
  }

  /**
   * Records a verification vote.
   *
   * @param \Drupal\avc_guild\Entity\LevelVerification $verification
   *   The verification.
   * @param \Drupal\user\UserInterface $verifier
   *   The verifier.
   * @param string $vote
   *   The vote: 'approve', 'deny', 'defer'.
   * @param string|null $feedback
   *   Optional feedback.
   */
  public function recordVote(
    LevelVerification $verification,
    UserInterface $verifier,
    string $vote,
    ?string $feedback = NULL
  ): void {
    if (!$verification->isPending()) {
      throw new \LogicException('Cannot vote on non-pending verification.');
    }

    // Check if verifier has already voted.
    if ($this->hasVoted($verification, $verifier)) {
      throw new \LogicException('User has already voted on this verification.');
    }

    // Record vote in separate table.
    $this->saveVoteRecord($verification, $verifier, $vote, $feedback);

    // Update vote tallies.
    $verification->incrementVote($vote);

    if ($feedback) {
      $verification->appendFeedback($feedback, $verifier);
    }

    $verification->save();

    // Check if verification is complete.
    $this->evaluateVerification($verification);
  }

  /**
   * Saves a vote record.
   *
   * @param \Drupal\avc_guild\Entity\LevelVerification $verification
   *   The verification.
   * @param \Drupal\user\UserInterface $verifier
   *   The verifier.
   * @param string $vote
   *   The vote.
   * @param string|null $feedback
   *   Optional feedback.
   */
  protected function saveVoteRecord(
    LevelVerification $verification,
    UserInterface $verifier,
    string $vote,
    ?string $feedback
  ): void {
    $database = \Drupal::database();
    $database->insert('level_verification_vote')
      ->fields([
        'verification_id' => $verification->id(),
        'verifier_id' => $verifier->id(),
        'vote' => $vote,
        'feedback' => $feedback,
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Checks if a user has voted on a verification.
   *
   * @param \Drupal\avc_guild\Entity\LevelVerification $verification
   *   The verification.
   * @param \Drupal\user\UserInterface $verifier
   *   The verifier.
   *
   * @return bool
   *   TRUE if already voted.
   */
  public function hasVoted(LevelVerification $verification, UserInterface $verifier): bool {
    $database = \Drupal::database();
    $count = $database->select('level_verification_vote', 'v')
      ->condition('v.verification_id', $verification->id())
      ->condition('v.verifier_id', $verifier->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count > 0;
  }

  /**
   * Evaluates a verification to determine if it's complete.
   *
   * @param \Drupal\avc_guild\Entity\LevelVerification $verification
   *   The verification.
   */
  protected function evaluateVerification(LevelVerification $verification): void {
    $votes_required = $verification->getVotesRequired();
    $approve = $verification->getApproveVotes();
    $deny = $verification->getDenyVotes();

    // Enough approvals?
    if ($approve >= $votes_required) {
      $this->approveVerification($verification);
      return;
    }

    // Enough denials to make approval impossible?
    // (Simple majority model - can be made more sophisticated.)
    if ($deny >= $votes_required) {
      $this->denyVerification($verification);
    }
  }

  /**
   * Approves a verification.
   *
   * @param \Drupal\avc_guild\Entity\LevelVerification $verification
   *   The verification.
   */
  protected function approveVerification(LevelVerification $verification): void {
    $verification->setStatus(LevelVerification::STATUS_APPROVED);
    $verification->save();

    // Grant the level.
    $this->grantLevel(
      $verification->getUser(),
      $verification->getGuild(),
      $verification->getSkill(),
      $verification->getTargetLevel()
    );

    // Trigger hook.
    \Drupal::moduleHandler()->invokeAll('avc_guild_verification_approved', [
      $verification,
    ]);
  }

  /**
   * Denies a verification.
   *
   * @param \Drupal\avc_guild\Entity\LevelVerification $verification
   *   The verification.
   */
  protected function denyVerification(LevelVerification $verification): void {
    $verification->setStatus(LevelVerification::STATUS_DENIED);
    $verification->save();

    // Clear pending flag.
    $progress = MemberSkillProgress::loadOrCreate(
      $verification->getUser(),
      $verification->getGuild(),
      $verification->getSkill()
    );
    $progress->setPendingVerification(FALSE);
    $progress->save();

    // Trigger hook.
    \Drupal::moduleHandler()->invokeAll('avc_guild_verification_denied', [
      $verification,
    ]);
  }

  /**
   * Grants a level to a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   * @param int $level
   *   The level to grant.
   */
  protected function grantLevel(
    UserInterface $user,
    GroupInterface $guild,
    TermInterface $skill,
    int $level
  ): void {
    $progress = MemberSkillProgress::loadOrCreate($user, $guild, $skill);
    $progress->setCurrentLevel($level);
    $progress->setPendingVerification(FALSE);
    $progress->resetCredits(); // Reset credits for next level.
    $progress->save();

    // Trigger hook.
    \Drupal::moduleHandler()->invokeAll('avc_guild_level_granted', [
      $user,
      $guild,
      $skill,
      $level,
    ]);
  }

  /**
   * Gets a user's skill profile.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return array
   *   Array keyed by skill_id with level, credits, next_level info.
   */
  public function getSkillProfile(UserInterface $user, GroupInterface $guild): array {
    $profile = [];

    $ids = $this->entityTypeManager
      ->getStorage('member_skill_progress')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_id', $user->id())
      ->condition('guild_id', $guild->id())
      ->execute();

    if (empty($ids)) {
      return $profile;
    }

    $progress_entities = $this->entityTypeManager
      ->getStorage('member_skill_progress')
      ->loadMultiple($ids);

    foreach ($progress_entities as $progress) {
      $skill = $progress->getSkill();
      if (!$skill) {
        continue;
      }

      $current_level = $progress->getCurrentLevel();
      $next_level = $current_level + 1;
      $next_level_config = $this->configService->getLevelConfig($guild, $skill, $next_level);

      $profile[$skill->id()] = [
        'skill' => $skill,
        'level' => $current_level,
        'level_name' => $this->getLevelName($guild, $skill, $current_level),
        'credits' => $progress->getCurrentCredits(),
        'credits_required' => $next_level_config ? $next_level_config->getCreditsRequired() : NULL,
        'days_at_level' => $progress->getDaysAtCurrentLevel(),
        'days_required' => $next_level_config ? $next_level_config->getTimeMinimumDays() : NULL,
        'pending_verification' => $progress->isPendingVerification(),
        'next_level' => $next_level_config ? $next_level : NULL,
        'next_level_name' => $next_level_config ? $next_level_config->getName() : NULL,
      ];
    }

    return $profile;
  }

  /**
   * Gets the name of a level.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   * @param int $level
   *   The level.
   *
   * @return string
   *   The level name or "Level N".
   */
  protected function getLevelName(GroupInterface $guild, TermInterface $skill, int $level): string {
    if ($level === 0) {
      return 'None';
    }

    $config = $this->configService->getLevelConfig($guild, $skill, $level);
    return $config ? $config->getName() : "Level $level";
  }

  /**
   * Gets pending verifications for a verifier.
   *
   * @param \Drupal\user\UserInterface $verifier
   *   The verifier.
   * @param \Drupal\group\Entity\GroupInterface|null $guild
   *   Optional guild filter.
   *
   * @return \Drupal\avc_guild\Entity\LevelVerification[]
   *   Array of pending verifications.
   */
  public function getPendingVerifications(
    UserInterface $verifier,
    ?GroupInterface $guild = NULL
  ): array {
    $query = $this->entityTypeManager
      ->getStorage('level_verification')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', LevelVerification::STATUS_PENDING);

    if ($guild) {
      $query->condition('guild_id', $guild->id());
    }

    // TODO: Filter by verifier eligibility (level, role).

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $verifications = $this->entityTypeManager
      ->getStorage('level_verification')
      ->loadMultiple($ids);

    // Filter to only those the verifier can vote on.
    $filtered = [];
    foreach ($verifications as $verification) {
      if ($this->canVerify($verifier, $verification) && !$this->hasVoted($verification, $verifier)) {
        $filtered[] = $verification;
      }
    }

    return $filtered;
  }

  /**
   * Checks if a user can verify a specific verification.
   *
   * @param \Drupal\user\UserInterface $verifier
   *   The verifier.
   * @param \Drupal\avc_guild\Entity\LevelVerification $verification
   *   The verification.
   *
   * @return bool
   *   TRUE if can verify.
   */
  public function canVerify(UserInterface $verifier, LevelVerification $verification): bool {
    // Can't verify yourself.
    if ($verifier->id() === $verification->getUser()->id()) {
      return FALSE;
    }

    $guild = $verification->getGuild();
    $skill = $verification->getSkill();
    $target_level = $verification->getTargetLevel();

    // Get level config.
    $level_config = $this->configService->getLevelConfig($guild, $skill, $target_level);
    if (!$level_config) {
      return FALSE;
    }

    // Check verifier's level in this skill.
    $progress = MemberSkillProgress::loadOrCreate($verifier, $guild, $skill);
    $verifier_level = $progress->getCurrentLevel();

    return $verifier_level >= $level_config->getVerifierMinimumLevel();
  }

  /**
   * Gets credit history for a user/guild/skill.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface|null $skill
   *   Optional skill filter.
   * @param int $limit
   *   Maximum records.
   *
   * @return \Drupal\avc_guild\Entity\SkillCredit[]
   *   Array of credit entities.
   */
  public function getCreditHistory(
    UserInterface $user,
    GroupInterface $guild,
    ?TermInterface $skill = NULL,
    int $limit = 20
  ): array {
    $query = $this->entityTypeManager
      ->getStorage('skill_credit')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_id', $user->id())
      ->condition('guild_id', $guild->id())
      ->sort('created', 'DESC')
      ->range(0, $limit);

    if ($skill) {
      $query->condition('skill_id', $skill->id());
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    return $this->entityTypeManager
      ->getStorage('skill_credit')
      ->loadMultiple($ids);
  }

}
```

---

### 2.2 SkillConfigurationService

**File**: `src/Service/SkillConfigurationService.php`

```php
<?php

namespace Drupal\avc_guild\Service;

use Drupal\avc_guild\Entity\SkillLevel;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Service for managing skill level configuration.
 */
class SkillConfigurationService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a SkillConfigurationService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets all skill levels for a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return array
   *   Array keyed by skill_id, containing arrays of SkillLevel entities.
   */
  public function getGuildSkillLevels(GroupInterface $guild): array {
    $levels_by_skill = [];

    $ids = $this->entityTypeManager
      ->getStorage('skill_level')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('guild_id', $guild->id())
      ->sort('skill_id')
      ->sort('level')
      ->execute();

    if (empty($ids)) {
      return $levels_by_skill;
    }

    $levels = $this->entityTypeManager
      ->getStorage('skill_level')
      ->loadMultiple($ids);

    foreach ($levels as $level) {
      $skill_id = $level->get('skill_id')->target_id;
      if (!isset($levels_by_skill[$skill_id])) {
        $levels_by_skill[$skill_id] = [];
      }
      $levels_by_skill[$skill_id][$level->getLevel()] = $level;
    }

    return $levels_by_skill;
  }

  /**
   * Gets level configuration for a specific skill/level.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   * @param int $level
   *   The level number.
   *
   * @return \Drupal\avc_guild\Entity\SkillLevel|null
   *   The level config or NULL.
   */
  public function getLevelConfig(
    GroupInterface $guild,
    TermInterface $skill,
    int $level
  ): ?SkillLevel {
    $ids = $this->entityTypeManager
      ->getStorage('skill_level')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('guild_id', $guild->id())
      ->condition('skill_id', $skill->id())
      ->condition('level', $level)
      ->execute();

    if (empty($ids)) {
      return NULL;
    }

    return $this->entityTypeManager
      ->getStorage('skill_level')
      ->load(reset($ids));
  }

  /**
   * Gets all levels for a skill in a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   *
   * @return \Drupal\avc_guild\Entity\SkillLevel[]
   *   Array of level configs, keyed by level number.
   */
  public function getSkillLevels(GroupInterface $guild, TermInterface $skill): array {
    $ids = $this->entityTypeManager
      ->getStorage('skill_level')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('guild_id', $guild->id())
      ->condition('skill_id', $skill->id())
      ->sort('level')
      ->execute();

    if (empty($ids)) {
      return [];
    }

    $levels = $this->entityTypeManager
      ->getStorage('skill_level')
      ->loadMultiple($ids);

    $keyed = [];
    foreach ($levels as $level) {
      $keyed[$level->getLevel()] = $level;
    }

    return $keyed;
  }

  /**
   * Gets maximum level for a skill in a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   *
   * @return int
   *   The maximum level, or 0 if no levels defined.
   */
  public function getMaxLevel(GroupInterface $guild, TermInterface $skill): int {
    $levels = $this->getSkillLevels($guild, $skill);

    if (empty($levels)) {
      return 0;
    }

    return max(array_keys($levels));
  }

  /**
   * Creates default skill levels for a guild/skill.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   *
   * @return \Drupal\avc_guild\Entity\SkillLevel[]
   *   The created levels.
   */
  public function createDefaultLevels(GroupInterface $guild, TermInterface $skill): array {
    $defaults = [
      1 => [
        'name' => 'Apprentice',
        'credits_required' => 0,
        'verification_type' => SkillLevel::VERIFICATION_AUTO,
        'verifier_minimum_level' => 0,
        'time_minimum_days' => 0,
      ],
      2 => [
        'name' => 'Contributor',
        'credits_required' => 50,
        'verification_type' => SkillLevel::VERIFICATION_MENTOR,
        'verifier_minimum_level' => 3,
        'time_minimum_days' => 30,
      ],
      3 => [
        'name' => 'Mentor',
        'credits_required' => 150,
        'verification_type' => SkillLevel::VERIFICATION_PEER,
        'verifier_minimum_level' => 3,
        'votes_required' => 2,
        'time_minimum_days' => 90,
      ],
      4 => [
        'name' => 'Master',
        'credits_required' => 400,
        'verification_type' => SkillLevel::VERIFICATION_COMMITTEE,
        'verifier_minimum_level' => 4,
        'votes_required' => 3,
        'time_minimum_days' => 180,
      ],
    ];

    $created = [];
    $storage = $this->entityTypeManager->getStorage('skill_level');

    foreach ($defaults as $level_num => $config) {
      $values = array_merge($config, [
        'guild_id' => $guild->id(),
        'skill_id' => $skill->id(),
        'level' => $level_num,
        'weight' => $level_num,
      ]);

      $level = $storage->create($values);
      $level->save();
      $created[$level_num] = $level;
    }

    return $created;
  }

  /**
   * Deletes all skill levels for a guild/skill.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   */
  public function deleteSkillLevels(GroupInterface $guild, TermInterface $skill): void {
    $ids = $this->entityTypeManager
      ->getStorage('skill_level')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('guild_id', $guild->id())
      ->condition('skill_id', $skill->id())
      ->execute();

    if (!empty($ids)) {
      $levels = $this->entityTypeManager
        ->getStorage('skill_level')
        ->loadMultiple($ids);

      $this->entityTypeManager
        ->getStorage('skill_level')
        ->delete($levels);
    }
  }

  /**
   * Gets credit source configuration for a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return array
   *   Array of source_type => credits.
   */
  public function getCreditSources(GroupInterface $guild): array {
    // TODO: Make this configurable per guild via a config entity or field.
    // For now, return defaults.
    return [
      'task_completed' => 5,
      'task_reviewed_approved' => 10,
      'task_reviewed_exceptional' => 15,
      'review_given' => 3,
      'endorsement_received' => 15,
      'endorsement_given' => 2,
    ];
  }

}
```

---

## 3. Install/Update Hooks

**File**: Add to `avc_guild.install`:

```php
/**
 * Implements hook_update_N().
 *
 * Create skill level system tables.
 */
function avc_guild_update_9001() {
  // The entity system will create tables automatically.
  // This update just ensures the entity types are installed.
  $entity_type_manager = \Drupal::entityTypeManager();
  $entity_definition_update_manager = \Drupal::entityDefinitionUpdateManager();

  // Install new entity types.
  $entity_types = [
    'skill_level',
    'member_skill_progress',
    'skill_credit',
    'level_verification',
  ];

  foreach ($entity_types as $entity_type_id) {
    $entity_type = $entity_type_manager->getDefinition($entity_type_id);
    $entity_definition_update_manager->installEntityType($entity_type);
  }
}

/**
 * Implements hook_update_N().
 *
 * Create level_verification_vote table.
 */
function avc_guild_update_9002() {
  $schema = [
    'description' => 'Stores individual votes on level verifications.',
    'fields' => [
      'id' => [
        'type' => 'serial',
        'unsigned' => TRUE,
        'not null' => TRUE,
      ],
      'verification_id' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
      ],
      'verifier_id' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
      ],
      'vote' => [
        'type' => 'varchar',
        'length' => 16,
        'not null' => TRUE,
      ],
      'feedback' => [
        'type' => 'text',
        'size' => 'medium',
      ],
      'created' => [
        'type' => 'int',
        'not null' => TRUE,
      ],
    ],
    'primary key' => ['id'],
    'unique keys' => [
      'verification_verifier' => ['verification_id', 'verifier_id'],
    ],
    'indexes' => [
      'verification_id' => ['verification_id'],
    ],
  ];

  \Drupal::database()->schema()->createTable('level_verification_vote', $schema);
}
```

---

## 4. Updated Services Configuration

**File**: Add to `avc_guild.services.yml`:

```yaml
  avc_guild.skill_configuration:
    class: Drupal\avc_guild\Service\SkillConfigurationService
    arguments: ['@entity_type.manager']

  avc_guild.skill_progression:
    class: Drupal\avc_guild\Service\SkillProgressionService
    arguments:
      - '@entity_type.manager'
      - '@avc_guild.skill_configuration'
```

---

## 5. Permissions

**File**: Add to `avc_guild.permissions.yml`:

```yaml
administer skill levels:
  title: 'Administer skill levels'
  description: 'Create and configure skill levels for guilds.'
  restrict access: TRUE

administer member skill progress:
  title: 'Administer member skill progress'
  description: 'View and modify member skill progress records.'
  restrict access: TRUE

administer skill credits:
  title: 'Administer skill credits'
  description: 'View and manage skill credit records.'
  restrict access: TRUE

administer level verifications:
  title: 'Administer level verifications'
  description: 'View and manage level verification records.'
  restrict access: TRUE

view own skill progress:
  title: 'View own skill progress'
  description: 'View your own skill progress in guilds.'

view guild skill progress:
  title: 'View guild skill progress'
  description: 'View skill progress of guild members.'

vote on level verifications:
  title: 'Vote on level verifications'
  description: 'Cast votes on pending level verifications.'
```

---

## 6. Access Control Handlers

**File**: `src/SkillLevelAccessControlHandler.php`

```php
<?php

namespace Drupal\avc_guild;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for SkillLevel entities.
 */
class SkillLevelAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        // Anyone in the guild can view skill levels.
        return AccessResult::allowedIfHasPermission($account, 'view guild skill progress');

      case 'update':
      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer skill levels');
    }

    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer skill levels');
  }

}
```

Create similar handlers for:
- `MemberSkillProgressAccessControlHandler.php`
- `SkillCreditAccessControlHandler.php`
- `LevelVerificationAccessControlHandler.php`

---

## 7. File Structure Summary

```
modules/avc_features/avc_guild/
├── avc_guild.install              (modified: add update hooks)
├── avc_guild.permissions.yml      (modified: add new permissions)
├── avc_guild.services.yml         (modified: add new services)
└── src/
    ├── Entity/
    │   ├── SkillLevel.php                    (new)
    │   ├── MemberSkillProgress.php           (new)
    │   ├── SkillCredit.php                   (new)
    │   └── LevelVerification.php             (new)
    ├── Service/
    │   ├── SkillProgressionService.php       (new)
    │   └── SkillConfigurationService.php     (new)
    ├── SkillLevelAccessControlHandler.php           (new)
    ├── MemberSkillProgressAccessControlHandler.php  (new)
    ├── SkillCreditAccessControlHandler.php          (new)
    ├── LevelVerificationAccessControlHandler.php    (new)
    ├── SkillLevelListBuilder.php                    (new)
    ├── MemberSkillProgressListBuilder.php           (new)
    ├── SkillCreditListBuilder.php                   (new)
    └── LevelVerificationListBuilder.php             (new)
```

---

## 8. Integration Hooks

Add to `avc_guild.module`:

```php
/**
 * Implements hook_avc_guild_verification_initiated().
 */
function avc_guild_avc_guild_verification_initiated($verification, $user, $guild, $skill, $level) {
  // Queue notification to potential verifiers.
  if (\Drupal::moduleHandler()->moduleExists('avc_notification')) {
    \Drupal::service('avc_notification.service')
      ->queueVerificationPending($verification);
  }
}

/**
 * Implements hook_avc_guild_level_granted().
 */
function avc_guild_avc_guild_level_granted($user, $guild, $skill, $level) {
  // Queue notification to user.
  if (\Drupal::moduleHandler()->moduleExists('avc_notification')) {
    \Drupal::service('avc_notification.service')
      ->queueLevelGranted($user, $guild, $skill, $level);
  }

  // Log the achievement.
  \Drupal::logger('avc_guild')->info('User @user granted level @level in @skill for guild @guild.', [
    '@user' => $user->getDisplayName(),
    '@level' => $level,
    '@skill' => $skill->getName(),
    '@guild' => $guild->label(),
  ]);
}
```

---

## 9. Testing Checklist

### Unit Tests

- [ ] `SkillLevel` entity CRUD operations
- [ ] `MemberSkillProgress` entity CRUD operations
- [ ] `SkillCredit` entity CRUD operations
- [ ] `LevelVerification` entity CRUD operations
- [ ] `SkillConfigurationService::getLevelConfig()`
- [ ] `SkillConfigurationService::createDefaultLevels()`
- [ ] `SkillProgressionService::awardCredits()`
- [ ] `SkillProgressionService::checkEligibility()`
- [ ] `SkillProgressionService::recordVote()`
- [ ] Vote counting and verification evaluation

### Integration Tests

- [ ] Credit award updates MemberSkillProgress
- [ ] Auto-verification grants level immediately
- [ ] Manual verification creates pending verification
- [ ] Vote recording updates tallies
- [ ] Sufficient approve votes grants level
- [ ] Sufficient deny votes denies verification
- [ ] Hooks fire at appropriate times

### Kernel Tests

- [ ] Entity schemas created correctly
- [ ] Services injectable
- [ ] Permissions defined

---

## 10. Development Tasks

| Task | Estimate | Dependencies |
|------|----------|--------------|
| Create SkillLevel entity | 2h | - |
| Create MemberSkillProgress entity | 2h | - |
| Create SkillCredit entity | 1.5h | - |
| Create LevelVerification entity | 2.5h | - |
| Create SkillConfigurationService | 2h | SkillLevel |
| Create SkillProgressionService | 4h | All entities, SkillConfigurationService |
| Create access handlers (4) | 2h | Entities |
| Create list builders (4) | 2h | Entities |
| Update install hooks | 1h | Entities |
| Update services.yml | 0.5h | Services |
| Update permissions.yml | 0.5h | - |
| Add integration hooks | 1h | Services |
| Write unit tests | 4h | All above |
| Write integration tests | 3h | All above |
| **Total** | **~27h** | |

---

## Next Phase Preview

**Phase 2: Configuration UI** will add:
- Guild admin form for skill level configuration
- Default level seeding when guild enables a skill
- Migration of existing endorsements to credits
