<?php

namespace Drupal\avc_guild\Service;

use Drupal\avc_guild\Entity\GuildScore;
use Drupal\avc_guild\Entity\SkillEndorsement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Service for managing skill endorsements.
 */
class EndorsementService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The scoring service.
   *
   * @var \Drupal\avc_guild\Service\ScoringService
   */
  protected $scoringService;

  /**
   * Constructs an EndorsementService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\avc_guild\Service\ScoringService $scoring_service
   *   The scoring service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ScoringService $scoring_service
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->scoringService = $scoring_service;
  }

  /**
   * Create an endorsement.
   *
   * @param \Drupal\Core\Session\AccountInterface $endorser
   *   The user giving the endorsement.
   * @param \Drupal\Core\Session\AccountInterface $endorsed
   *   The user being endorsed.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill being endorsed.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild context.
   * @param string $comment
   *   Optional comment.
   *
   * @return \Drupal\avc_guild\Entity\SkillEndorsement|null
   *   The endorsement entity or NULL if validation fails.
   *
   * @throws \Exception
   *   If validation fails.
   */
  public function createEndorsement(
    AccountInterface $endorser,
    AccountInterface $endorsed,
    TermInterface $skill,
    GroupInterface $guild,
    string $comment = ''
  ) {
    // Validate: can't endorse yourself.
    if ($endorser->id() === $endorsed->id()) {
      throw new \Exception('You cannot endorse yourself.');
    }

    // Validate: endorser must be able to endorse.
    if (!avc_guild_can_endorse($guild, $endorser)) {
      throw new \Exception('You do not have permission to endorse in this guild.');
    }

    // Validate: endorsed user must be a guild member.
    if (!$guild->getMember($endorsed)) {
      throw new \Exception('The user must be a member of this guild.');
    }

    // Validate: one endorsement per skill per endorser.
    if ($this->hasEndorsed($endorser, $endorsed, $skill, $guild)) {
      throw new \Exception('You have already endorsed this user for this skill.');
    }

    // Create endorsement.
    /** @var \Drupal\avc_guild\Entity\SkillEndorsement $endorsement */
    $endorsement = $this->entityTypeManager
      ->getStorage('skill_endorsement')
      ->create([
        'endorser_id' => $endorser->id(),
        'endorsed_id' => $endorsed->id(),
        'skill_id' => $skill->id(),
        'guild_id' => $guild->id(),
        'comment' => $comment,
      ]);

    $endorsement->save();

    // Award points to endorsed user.
    $this->scoringService->awardPoints(
      $endorsed,
      $guild,
      GuildScore::ACTION_ENDORSEMENT_RECEIVED,
      NULL,
      $skill,
      'skill_endorsement',
      $endorsement->id()
    );

    // Award points to endorser.
    $this->scoringService->awardPoints(
      $endorser,
      $guild,
      GuildScore::ACTION_ENDORSEMENT_GIVEN,
      NULL,
      $skill,
      'skill_endorsement',
      $endorsement->id()
    );

    return $endorsement;
  }

  /**
   * Check if a user has endorsed another for a skill.
   *
   * @param \Drupal\Core\Session\AccountInterface $endorser
   *   The endorser.
   * @param \Drupal\Core\Session\AccountInterface $endorsed
   *   The endorsed user.
   * @param \Drupal\taxonomy\TermInterface $skill
   *   The skill.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return bool
   *   TRUE if already endorsed.
   */
  public function hasEndorsed(
    AccountInterface $endorser,
    AccountInterface $endorsed,
    TermInterface $skill,
    GroupInterface $guild
  ) {
    $count = $this->entityTypeManager
      ->getStorage('skill_endorsement')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('endorser_id', $endorser->id())
      ->condition('endorsed_id', $endorsed->id())
      ->condition('skill_id', $skill->id())
      ->condition('guild_id', $guild->id())
      ->count()
      ->execute();

    return $count > 0;
  }

  /**
   * Get endorsements for a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface|null $guild
   *   Optional guild filter.
   *
   * @return \Drupal\avc_guild\Entity\SkillEndorsement[]
   *   Array of endorsements.
   */
  public function getEndorsementsFor(AccountInterface $user, ?GroupInterface $guild = NULL) {
    $query = $this->entityTypeManager
      ->getStorage('skill_endorsement')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('endorsed_id', $user->id());

    if ($guild) {
      $query->condition('guild_id', $guild->id());
    }

    $ids = $query->execute();

    return $this->entityTypeManager
      ->getStorage('skill_endorsement')
      ->loadMultiple($ids);
  }

  /**
   * Get endorsement count by skill for a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface|null $guild
   *   Optional guild filter.
   *
   * @return array
   *   Array of counts keyed by skill ID.
   */
  public function getEndorsementCountsBySkill(AccountInterface $user, ?GroupInterface $guild = NULL) {
    $endorsements = $this->getEndorsementsFor($user, $guild);
    $counts = [];

    foreach ($endorsements as $endorsement) {
      $skill = $endorsement->getSkill();
      if ($skill) {
        $skill_id = $skill->id();
        if (!isset($counts[$skill_id])) {
          $counts[$skill_id] = [
            'skill' => $skill,
            'count' => 0,
          ];
        }
        $counts[$skill_id]['count']++;
      }
    }

    // Sort by count descending.
    uasort($counts, function ($a, $b) {
      return $b['count'] - $a['count'];
    });

    return $counts;
  }

  /**
   * Get endorsements given by a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The endorser.
   * @param \Drupal\group\Entity\GroupInterface|null $guild
   *   Optional guild filter.
   *
   * @return \Drupal\avc_guild\Entity\SkillEndorsement[]
   *   Array of endorsements.
   */
  public function getEndorsementsBy(AccountInterface $user, ?GroupInterface $guild = NULL) {
    $query = $this->entityTypeManager
      ->getStorage('skill_endorsement')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('endorser_id', $user->id());

    if ($guild) {
      $query->condition('guild_id', $guild->id());
    }

    $ids = $query->execute();

    return $this->entityTypeManager
      ->getStorage('skill_endorsement')
      ->loadMultiple($ids);
  }

  /**
   * Get available skills for a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Array of skill terms.
   */
  public function getGuildSkills(GroupInterface $guild) {
    if (!$guild->hasField('field_guild_skills')) {
      return [];
    }

    $skills = [];
    foreach ($guild->get('field_guild_skills') as $item) {
      if ($item->entity) {
        $skills[] = $item->entity;
      }
    }

    return $skills;
  }

}
