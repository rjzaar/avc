<?php

namespace Drupal\avc_guild\Service;

use Drupal\avc_guild\Entity\GuildScore;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Service for managing guild scoring.
 */
class ScoringService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ScoringService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Award points to a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to award points to.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild context.
   * @param string $action_type
   *   The action type.
   * @param int|null $points
   *   Custom points or NULL for default.
   * @param \Drupal\taxonomy\TermInterface|null $skill
   *   Optional skill reference.
   * @param string|null $reference_type
   *   Optional reference entity type.
   * @param int|null $reference_id
   *   Optional reference entity ID.
   *
   * @return \Drupal\avc_guild\Entity\GuildScore
   *   The created score entity.
   */
  public function awardPoints(
    AccountInterface $user,
    GroupInterface $guild,
    string $action_type,
    ?int $points = NULL,
    ?TermInterface $skill = NULL,
    ?string $reference_type = NULL,
    ?int $reference_id = NULL
  ) {
    if ($points === NULL) {
      $points = GuildScore::getDefaultPoints($action_type);
    }

    $values = [
      'user_id' => $user->id(),
      'guild_id' => $guild->id(),
      'action_type' => $action_type,
      'points' => $points,
    ];

    if ($skill) {
      $values['skill_id'] = $skill->id();
    }

    if ($reference_type && $reference_id) {
      $values['reference_type'] = $reference_type;
      $values['reference_id'] = $reference_id;
    }

    /** @var \Drupal\avc_guild\Entity\GuildScore $score */
    $score = $this->entityTypeManager
      ->getStorage('guild_score')
      ->create($values);

    $score->save();

    // Check for automatic promotion.
    $this->checkPromotion($user, $guild);

    return $score;
  }

  /**
   * Get total score for a user in a guild.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return int
   *   The total score.
   */
  public function getTotalScore(AccountInterface $user, GroupInterface $guild) {
    $query = $this->entityTypeManager
      ->getStorage('guild_score')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_id', $user->id())
      ->condition('guild_id', $guild->id());

    $ids = $query->execute();

    if (empty($ids)) {
      return 0;
    }

    $scores = $this->entityTypeManager
      ->getStorage('guild_score')
      ->loadMultiple($ids);

    $total = 0;
    foreach ($scores as $score) {
      $total += $score->getPoints();
    }

    return $total;
  }

  /**
   * Get score breakdown by action type.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return array
   *   Array of scores keyed by action type.
   */
  public function getScoreBreakdown(AccountInterface $user, GroupInterface $guild) {
    $breakdown = [];

    $ids = $this->entityTypeManager
      ->getStorage('guild_score')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_id', $user->id())
      ->condition('guild_id', $guild->id())
      ->execute();

    if (empty($ids)) {
      return $breakdown;
    }

    $scores = $this->entityTypeManager
      ->getStorage('guild_score')
      ->loadMultiple($ids);

    foreach ($scores as $score) {
      $action_type = $score->getActionType();
      if (!isset($breakdown[$action_type])) {
        $breakdown[$action_type] = [
          'count' => 0,
          'points' => 0,
        ];
      }
      $breakdown[$action_type]['count']++;
      $breakdown[$action_type]['points'] += $score->getPoints();
    }

    return $breakdown;
  }

  /**
   * Get leaderboard for a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param int $limit
   *   Number of entries to return.
   *
   * @return array
   *   Array of leaderboard entries.
   */
  public function getLeaderboard(GroupInterface $guild, int $limit = 10) {
    $leaderboard = [];

    // Get all members of the guild.
    $members = $guild->getMembers();

    foreach ($members as $membership) {
      $user = $membership->getUser();
      if (!$user) {
        continue;
      }

      $score = $this->getTotalScore($user, $guild);

      $leaderboard[] = [
        'user' => $user,
        'score' => $score,
        'role' => avc_guild_get_member_role($guild, $user),
      ];
    }

    // Sort by score descending.
    usort($leaderboard, function ($a, $b) {
      return $b['score'] - $a['score'];
    });

    // Apply limit.
    return array_slice($leaderboard, 0, $limit);
  }

  /**
   * Check if user should be promoted.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   */
  protected function checkPromotion(AccountInterface $user, GroupInterface $guild) {
    // Get current role.
    $current_role = avc_guild_get_member_role($guild, $user);

    // Only juniors can be auto-promoted.
    if ($current_role !== 'junior') {
      return;
    }

    // Check if guild has promotion threshold.
    if (!$guild->hasField('field_promotion_threshold')) {
      return;
    }

    $threshold = (int) $guild->get('field_promotion_threshold')->value;
    if ($threshold <= 0) {
      return;
    }

    $score = $this->getTotalScore($user, $guild);

    if ($score >= $threshold) {
      // Queue promotion or auto-promote.
      // For now, we'll just trigger an event that can be handled.
      \Drupal::moduleHandler()->invokeAll('avc_guild_promotion_eligible', [
        $user,
        $guild,
        $score,
        $threshold,
      ]);
    }
  }

  /**
   * Get recent scoring activity for a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param int $limit
   *   Number of entries.
   *
   * @return \Drupal\avc_guild\Entity\GuildScore[]
   *   Array of score entities.
   */
  public function getRecentActivity(AccountInterface $user, GroupInterface $guild, int $limit = 10) {
    $ids = $this->entityTypeManager
      ->getStorage('guild_score')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_id', $user->id())
      ->condition('guild_id', $guild->id())
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();

    return $this->entityTypeManager
      ->getStorage('guild_score')
      ->loadMultiple($ids);
  }

}
