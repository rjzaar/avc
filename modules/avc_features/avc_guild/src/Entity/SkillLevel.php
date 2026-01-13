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
