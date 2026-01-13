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
