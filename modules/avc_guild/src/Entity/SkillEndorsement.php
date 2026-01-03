<?php

namespace Drupal\avc_guild\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Skill Endorsement entity.
 *
 * @ContentEntityType(
 *   id = "skill_endorsement",
 *   label = @Translation("Skill Endorsement"),
 *   label_collection = @Translation("Skill Endorsements"),
 *   label_singular = @Translation("skill endorsement"),
 *   label_plural = @Translation("skill endorsements"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\avc_guild\SkillEndorsementListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\avc_guild\Form\EndorseSkillForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\avc_guild\SkillEndorsementAccessControlHandler",
 *   },
 *   base_table = "skill_endorsement",
 *   admin_permission = "administer skill endorsements",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/avc/guild/endorsements",
 *     "delete-form" = "/guild/endorsement/{skill_endorsement}/delete",
 *   },
 * )
 */
class SkillEndorsement extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Endorser (the person giving the endorsement).
    $fields['endorser_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Endorser'))
      ->setDescription(t('The user giving the endorsement.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Endorsed user.
    $fields['endorsed_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Endorsed User'))
      ->setDescription(t('The user being endorsed.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Skill.
    $fields['skill_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Skill'))
      ->setDescription(t('The skill being endorsed.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Guild.
    $fields['guild_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Guild'))
      ->setDescription(t('The guild context for this endorsement.'))
      ->setSetting('target_type', 'group')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Comment.
    $fields['comment'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Comment'))
      ->setDescription(t('Optional comment about the endorsement.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 4,
        'settings' => [
          'rows' => 3,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the endorsement was given.'));

    // Changed timestamp.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the endorsement was last updated.'));

    return $fields;
  }

  /**
   * Gets the endorser.
   *
   * @return \Drupal\user\UserInterface|null
   *   The endorser user entity.
   */
  public function getEndorser() {
    return $this->get('endorser_id')->entity;
  }

  /**
   * Gets the endorsed user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The endorsed user entity.
   */
  public function getEndorsedUser() {
    return $this->get('endorsed_id')->entity;
  }

  /**
   * Gets the skill.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The skill term.
   */
  public function getSkill() {
    return $this->get('skill_id')->entity;
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
   * Gets the comment.
   *
   * @return string
   *   The comment text.
   */
  public function getComment() {
    return $this->get('comment')->value ?? '';
  }

}
