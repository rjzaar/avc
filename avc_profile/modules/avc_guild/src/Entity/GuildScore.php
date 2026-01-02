<?php

namespace Drupal\avc_guild\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Guild Score entity.
 *
 * Tracks points earned by users in guilds.
 *
 * @ContentEntityType(
 *   id = "guild_score",
 *   label = @Translation("Guild Score"),
 *   label_collection = @Translation("Guild Scores"),
 *   label_singular = @Translation("guild score"),
 *   label_plural = @Translation("guild scores"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\avc_guild\GuildScoreListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\avc_guild\GuildScoreAccessControlHandler",
 *   },
 *   base_table = "guild_score",
 *   admin_permission = "administer guild scores",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/avc/guild/scores",
 *   },
 * )
 */
class GuildScore extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * Action types for scoring.
   */
  const ACTION_TASK_COMPLETED = 'task_completed';
  const ACTION_TASK_RATIFIED = 'task_ratified';
  const ACTION_RATIFICATION_GIVEN = 'ratification_given';
  const ACTION_ENDORSEMENT_RECEIVED = 'endorsement_received';
  const ACTION_ENDORSEMENT_GIVEN = 'endorsement_given';

  /**
   * Default point values.
   */
  const POINTS_TASK_COMPLETED = 10;
  const POINTS_TASK_RATIFIED = 15;
  const POINTS_RATIFICATION_GIVEN = 5;
  const POINTS_ENDORSEMENT_RECEIVED = 20;
  const POINTS_ENDORSEMENT_GIVEN = 5;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // User reference.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user who earned the points.'))
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
      ->setDescription(t('The guild where points were earned.'))
      ->setSetting('target_type', 'group')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Skill reference (optional).
    $fields['skill_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Skill'))
      ->setDescription(t('The specific skill related to this score (optional).'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Points.
    $fields['points'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Points'))
      ->setDescription(t('The number of points earned.'))
      ->setRequired(TRUE)
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Action type.
    $fields['action_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Action Type'))
      ->setDescription(t('The type of action that earned points.'))
      ->setSettings([
        'allowed_values' => [
          self::ACTION_TASK_COMPLETED => 'Task Completed',
          self::ACTION_TASK_RATIFIED => 'Task Ratified',
          self::ACTION_RATIFICATION_GIVEN => 'Ratification Given',
          self::ACTION_ENDORSEMENT_RECEIVED => 'Endorsement Received',
          self::ACTION_ENDORSEMENT_GIVEN => 'Endorsement Given',
        ],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Reference entity (the task, endorsement, etc. that triggered this).
    $fields['reference_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reference Type'))
      ->setDescription(t('The entity type of the reference.'))
      ->setSettings([
        'max_length' => 64,
      ]);

    $fields['reference_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Reference ID'))
      ->setDescription(t('The entity ID of the reference.'));

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the score was recorded.'));

    // Changed timestamp.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the score was last updated.'));

    return $fields;
  }

  /**
   * Gets the user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user entity.
   */
  public function getUser() {
    return $this->get('user_id')->entity;
  }

  /**
   * Gets the guild.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The guild entity.
   */
  public function getGuild() {
    return $this->get('guild_id')->entity;
  }

  /**
   * Gets the skill.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The skill term or NULL.
   */
  public function getSkill() {
    return $this->get('skill_id')->entity;
  }

  /**
   * Gets the points.
   *
   * @return int
   *   The points value.
   */
  public function getPoints() {
    return (int) $this->get('points')->value;
  }

  /**
   * Gets the action type.
   *
   * @return string
   *   The action type.
   */
  public function getActionType() {
    return $this->get('action_type')->value;
  }

  /**
   * Get default points for an action type.
   *
   * @param string $action_type
   *   The action type.
   *
   * @return int
   *   The default points.
   */
  public static function getDefaultPoints(string $action_type) {
    $points_map = [
      self::ACTION_TASK_COMPLETED => self::POINTS_TASK_COMPLETED,
      self::ACTION_TASK_RATIFIED => self::POINTS_TASK_RATIFIED,
      self::ACTION_RATIFICATION_GIVEN => self::POINTS_RATIFICATION_GIVEN,
      self::ACTION_ENDORSEMENT_RECEIVED => self::POINTS_ENDORSEMENT_RECEIVED,
      self::ACTION_ENDORSEMENT_GIVEN => self::POINTS_ENDORSEMENT_GIVEN,
    ];

    return $points_map[$action_type] ?? 0;
  }

}
