# Proposal: Guild Multiple Verification Types with OR Logic

**Status:** PROPOSED
**Author:** Claude Code
**Date:** 2026-01-24

## Executive Summary

Enable guild skill levels to support multiple verification methods where ANY method can grant advancement (OR logic). This provides flexibility for guilds to offer multiple pathways to skill level advancement, accommodating different verifier availability and guild governance structures.

**Example:** A level configured with `[mentor, peer]` would allow advancement if:
- A mentor approves, OR
- Sufficient peer votes are received

## Problem Statement

Currently, each skill level can only use a single verification type. This creates several limitations:

1. **Bottlenecks**: If a level requires mentor approval but mentors are unavailable, advancement stalls
2. **Inflexibility**: Different guilds have different governance needs that a single-method system cannot accommodate
3. **Scaling Issues**: As guilds grow, relying on a single verification pathway becomes unsustainable
4. **Lack of Redundancy**: No fallback when primary verification method is blocked

## Proposed Solution

Replace the single-select `verification_type` field with a multi-method configuration system that supports:

1. **Multiple Verification Pathways**: Any enabled method can independently grant advancement
2. **Per-Method Configuration**: Each method has its own vote threshold and requirements
3. **Relative Level Verification**: Verifier requirements based on target level, not absolute levels
4. **Committee Support**: Designated committee members as a verification option
5. **Verifier Role**: Optional guild role requirement for any verification method

### Verification Method Options

| Method | Description | Configuration |
|--------|-------------|---------------|
| Mentor | Higher-level member approval | Votes required, levels above target |
| Peer | Same-tier member votes | Votes required, levels above target |
| Committee | Designated committee members | Votes required |
| Verifier | Guild verifier role holders | Votes required |
| Assessment | External assessment required | (future) |
| Auto | Automatic when credits/time met | No votes needed |

## Technical Design

### Data Model Changes

#### SkillLevel Entity

Add new `verification_methods` field storing array of method configurations:

```php
[
  ['type' => 'mentor', 'votes_required' => 1, 'levels_above_target' => 2, 'require_verifier_role' => false],
  ['type' => 'peer', 'votes_required' => 3, 'levels_above_target' => 1, 'require_verifier_role' => false],
  ['type' => 'committee', 'votes_required' => 3, 'require_verifier_role' => false],
  ['type' => 'verifier', 'votes_required' => 1, 'require_verifier_role' => true],
]
```

New methods:
- `getVerificationMethods()` - Returns array of method configs
- `hasVerificationType($type)` - Helper to check if type is enabled
- `isAutoVerified()` - Check if 'auto' is in methods array

#### LevelVerification Entity

Add fields:
- `verification_methods` - Copy from SkillLevel at creation time
- `target_level` - Needed to calculate required verifier level
- `completed_via` - Record which method succeeded
- `votes_by_method` - Per-method vote tracking:
  ```php
  ['mentor' => ['approve' => 1, 'deny' => 0], 'peer' => ['approve' => 2, 'deny' => 1]]
  ```

New methods:
- `recordVoteForMethod($method, $vote)` - Track votes per method
- `getMethodStatus($method)` - Returns pending/approved/denied

#### Group Role Enhancement

Add new guild role: `guild-verifier`
- Configurable in `config/install/group.role.guild-verifier.yml`
- Add `is_committee_member` boolean field to group membership
- Admin UI for role and committee assignment

### Service Layer Changes

#### SkillProgressionService

**`checkAndInitiateVerification()`:**
- Check if 'auto' is in methods AND thresholds met → grant immediately
- Otherwise initiate multi-method verification

**`initiateVerification()`:**
- Copy all verification methods from SkillLevel to LevelVerification
- Store target_level for relative calculations
- Initialize vote tracking for each method

**`recordVote()`:**
- Determine verifier's applicable method(s) based on level and roles
- Route vote to highest-qualifying method
- Call `$verification->recordVoteForMethod($method, $vote)`

**`evaluateVerification()`:**
- Loop through each method
- Check if ANY method has met its threshold → approve via that method
- Only deny if ALL methods are denied

**`determineVerifierMethod($verifier, $verification)`:**
- Get verifier's current skill level
- Get target_level from verification
- For each method, check qualification:
  - `require_verifier_role == true`: check `hasVerifierRole($verifier, $guild)`
  - mentor/peer: `verifier_level >= target_level + method.levels_above_target`
  - committee: `verifier.is_committee_member == TRUE`
- Return highest-tier qualifying method or NULL

### UI Changes

#### GuildSkillLevelConfigForm

Replace single dropdown with expandable method configuration:

```
[ ] Mentor Approval
    |-- Votes required: [1]  Levels above target: [2]  [ ] Require verifier role
[ ] Peer Votes
    |-- Votes required: [3]  Levels above target: [1]  [ ] Require verifier role
[ ] Committee Vote
    |-- Votes required: [3]  (uses designated committee members)
[ ] Verifier Role
    |-- Votes required: [1]  (only guild-verifier role holders)
[ ] Assessment
[ ] Auto (when credits + time met)
```

Use Drupal `#states` for conditional visibility.

#### VerificationQueueController

Show all active methods with their status:

```
Mentor (requires Level 7+): Approved (1/1)
Peer (requires Level 6+): Pending (2/3 votes)
Committee: Pending (1/3 votes)
```

Show which method completed the verification.

#### LevelVerificationVoteForm

- Show verifier which method their vote will count toward
- Display: "Your vote will count as: Mentor approval (you are Level 8)"
- Show current status of all methods

### Database Migration

Update hook in `avc_guild.install`:
- Add new database columns for multi-value storage
- Migrate existing single values to arrays:
  ```php
  // Old: verification_type: 'mentor', votes_required: 1
  // New: [{type: 'mentor', votes_required: 1, levels_above_target: 2}]
  ```

## Design Decisions

### 1. Auto Verification Priority

When 'auto' is one of the allowed types AND credits/time thresholds are met, grant immediately. Auto takes priority over human verification methods.

### 2. Per-Method Vote Thresholds

Each verification type has its own `votes_required`. This allows fine-tuning (e.g., 1 mentor OR 3 peers).

### 3. Relative Level Verification

Verifier must be a configurable number of levels ABOVE the target level:
- Configure: "Verifier must be X levels above target"
- Example: Target is Level 5, config says "+2 levels" → verifier needs Level 7+
- This scales properly as members advance

### 4. Committee Member Designation

Committee verification uses explicit membership, not just levels:
- Guild admins designate specific members as "committee members"
- Stored as a flag on group membership entity

### 5. Verifier Role Option

Optional "Verifier" guild role for any/all verification methods:
- Can be combined with level requirements
- Useful for quality control and small guilds

### Backward Compatibility

- Keep existing `verification_type` and `votes_required` fields
- Add new `verification_methods` field (JSON array)
- Getters check new field first, fall back to old single-value fields

## Files to Modify

### Entity Layer
1. `modules/avc_features/avc_guild/src/Entity/SkillLevel.php`
2. `modules/avc_features/avc_guild/src/Entity/LevelVerification.php`
3. `config/install/group.role.guild-verifier.yml` (new)

### Service Layer
4. `modules/avc_features/avc_guild/src/Service/SkillProgressionService.php`
5. `modules/avc_features/avc_guild/src/Service/SkillConfigurationService.php`
6. `modules/avc_features/avc_guild/src/Service/GuildService.php`

### Form/UI Layer
7. `modules/avc_features/avc_guild/src/Form/GuildSkillLevelConfigForm.php`
8. `modules/avc_features/avc_guild/src/Controller/VerificationQueueController.php`
9. `modules/avc_features/avc_guild/src/SkillLevelListBuilder.php`
10. `modules/avc_features/avc_guild/src/Form/LevelVerificationVoteForm.php`

### Database
11. `modules/avc_features/avc_guild/avc_guild.install`

## Implementation Plan

### Phase 1: Entity Changes
- Add new fields to SkillLevel and LevelVerification
- Add getter/setter methods
- Write database update hook

### Phase 2: Service Logic
- Update SkillProgressionService for OR evaluation
- Update SkillConfigurationService for array handling
- Add verifier method determination logic

### Phase 3: UI Updates
- Convert form to multi-select with conditional fields
- Update display controllers/builders
- Add verifier/committee management UI

### Phase 4: Testing & Migration
- Run update hooks
- Test with existing data
- Verify backward compatibility

## Success Criteria

### Phase 1 (Entity Changes)
- [ ] SkillLevel entity has `verification_methods` field
- [ ] LevelVerification entity tracks votes per method
- [ ] Update hook migrates existing data correctly

### Phase 2 (Service Logic)
- [ ] Auto verification grants immediately when eligible
- [ ] Any method meeting threshold grants advancement
- [ ] Vote routing correctly determines verifier method
- [ ] Denial requires all methods to be denied

### Phase 3 (UI)
- [ ] Admin can configure multiple methods per level
- [ ] Verification queue shows all method statuses
- [ ] Verifiers see which method their vote counts toward

### Phase 4 (Testing)
- [ ] Existing single-method verifications still work
- [ ] New multi-method configuration functions correctly
- [ ] All verification scenarios tested:
  - Auto + Mentor: Auto grants immediately
  - Mentor + Peer: Either method can succeed
  - Single method: Backward compatibility

## Verification Steps

1. Run database updates: `drush updb`
2. Clear caches: `drush cr`
3. Configure a skill level with multiple verification types via admin UI
4. Test advancement scenarios with different verifier combinations
5. Check verification queue displays correctly
6. Verify existing verifications still work

## Example Workflow

1. Level configured: Mentor (+2 levels, 1 vote) + Peer (+1 level, 3 votes) + Committee (designated, 3 votes)
2. User A (Level 7+) votes → counts as Mentor (1/1) → **Level granted!**
3. OR: Users B, C, D (Level 6+) vote → counts as Peer (3/3) → **Level granted!**
4. OR: Committee members X, Y, Z vote → counts as Committee (3/3) → **Level granted!**

## Risks & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Migration breaks existing data | Low | High | Careful update hook with rollback |
| UI complexity overwhelms admins | Medium | Medium | Sensible defaults, clear documentation |
| Vote routing confusion | Medium | Low | Clear messaging about which method applies |
| Performance with multiple methods | Low | Low | Efficient OR evaluation, early exit on success |

## Dependencies

- Drupal Core: 10.x or 11.x
- Group module
- Existing avc_guild module

---

**Document Version:** 1.0
**Last Updated:** 2026-01-24
**Status:** PROPOSED
