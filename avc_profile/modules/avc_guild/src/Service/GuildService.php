<?php

namespace Drupal\avc_guild\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Main service for guild operations.
 */
class GuildService {

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
   * The endorsement service.
   *
   * @var \Drupal\avc_guild\Service\EndorsementService
   */
  protected $endorsementService;

  /**
   * The ratification service.
   *
   * @var \Drupal\avc_guild\Service\RatificationService
   */
  protected $ratificationService;

  /**
   * Constructs a GuildService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\avc_guild\Service\ScoringService $scoring_service
   *   The scoring service.
   * @param \Drupal\avc_guild\Service\EndorsementService $endorsement_service
   *   The endorsement service.
   * @param \Drupal\avc_guild\Service\RatificationService $ratification_service
   *   The ratification service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ScoringService $scoring_service,
    EndorsementService $endorsement_service,
    RatificationService $ratification_service
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->scoringService = $scoring_service;
    $this->endorsementService = $endorsement_service;
    $this->ratificationService = $ratification_service;
  }

  /**
   * Get all guilds.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Array of guild entities.
   */
  public function getAllGuilds() {
    $ids = $this->entityTypeManager
      ->getStorage('group')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'guild')
      ->execute();

    return $this->entityTypeManager
      ->getStorage('group')
      ->loadMultiple($ids);
  }

  /**
   * Get guilds a user is a member of.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Array of guild entities.
   */
  public function getUserGuilds(AccountInterface $user) {
    $guilds = [];

    $memberships = $this->entityTypeManager
      ->getStorage('group_content')
      ->loadByProperties([
        'entity_id' => $user->id(),
        'type' => 'guild-group_membership',
      ]);

    foreach ($memberships as $membership) {
      $group = $membership->getGroup();
      if ($group && $group->bundle() === 'guild') {
        $guilds[] = $group;
      }
    }

    return $guilds;
  }

  /**
   * Get member profile data for a user in a guild.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return array
   *   Array of profile data.
   */
  public function getMemberProfile(AccountInterface $user, GroupInterface $guild) {
    return [
      'user' => $user,
      'guild' => $guild,
      'role' => avc_guild_get_member_role($guild, $user),
      'score' => $this->scoringService->getTotalScore($user, $guild),
      'score_breakdown' => $this->scoringService->getScoreBreakdown($user, $guild),
      'endorsements' => $this->endorsementService->getEndorsementCountsBySkill($user, $guild),
      'recent_activity' => $this->scoringService->getRecentActivity($user, $guild),
    ];
  }

  /**
   * Get guild dashboard data.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return array
   *   Array of dashboard data.
   */
  public function getGuildDashboard(GroupInterface $guild) {
    return [
      'guild' => $guild,
      'members' => $this->getGuildMembers($guild),
      'leaderboard' => $this->scoringService->getLeaderboard($guild),
      'pending_ratifications' => $this->ratificationService->getPendingForGuild($guild),
      'skills' => $this->endorsementService->getGuildSkills($guild),
    ];
  }

  /**
   * Get guild members with their roles.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return array
   *   Array of member data.
   */
  public function getGuildMembers(GroupInterface $guild) {
    $members = [];

    foreach ($guild->getMembers() as $membership) {
      $user = $membership->getUser();
      if (!$user) {
        continue;
      }

      $members[] = [
        'user' => $user,
        'role' => avc_guild_get_member_role($guild, $user),
        'score' => $this->scoringService->getTotalScore($user, $guild),
      ];
    }

    // Sort by score descending.
    usort($members, function ($a, $b) {
      return $b['score'] - $a['score'];
    });

    return $members;
  }

  /**
   * Promote a member to a new role.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to promote.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   * @param string $new_role
   *   The new role (endorsed, mentor).
   *
   * @return bool
   *   TRUE if successful.
   */
  public function promoteMember(AccountInterface $user, GroupInterface $guild, string $new_role) {
    $membership = $guild->getMember($user);
    if (!$membership) {
      return FALSE;
    }

    // Valid promotion paths.
    $valid_promotions = [
      'junior' => ['endorsed'],
      'endorsed' => ['mentor'],
    ];

    $current_role = avc_guild_get_member_role($guild, $user);
    if (!isset($valid_promotions[$current_role]) ||
        !in_array($new_role, $valid_promotions[$current_role])) {
      return FALSE;
    }

    // Get the group content entity.
    $group_content = $membership->getGroupRelationship();

    // Remove old role, add new role.
    $old_role_id = 'guild-' . $current_role;
    $new_role_id = 'guild-' . $new_role;

    // This would need to use the Group module's role assignment API.
    // For now, we'll trigger a hook that can be implemented.
    \Drupal::moduleHandler()->invokeAll('avc_guild_member_promoted', [
      $user,
      $guild,
      $current_role,
      $new_role,
    ]);

    return TRUE;
  }

  /**
   * Get the scoring service.
   *
   * @return \Drupal\avc_guild\Service\ScoringService
   *   The scoring service.
   */
  public function getScoringService() {
    return $this->scoringService;
  }

  /**
   * Get the endorsement service.
   *
   * @return \Drupal\avc_guild\Service\EndorsementService
   *   The endorsement service.
   */
  public function getEndorsementService() {
    return $this->endorsementService;
  }

  /**
   * Get the ratification service.
   *
   * @return \Drupal\avc_guild\Service\RatificationService
   *   The ratification service.
   */
  public function getRatificationService() {
    return $this->ratificationService;
  }

}
