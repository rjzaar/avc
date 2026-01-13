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
