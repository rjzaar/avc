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
