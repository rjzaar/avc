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
