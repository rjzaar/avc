<?php

namespace Drupal\avc_guild\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Ratification entity.
 *
 * Tracks ratification of junior work by mentors.
 *
 * @ContentEntityType(
 *   id = "ratification",
 *   label = @Translation("Ratification"),
 *   label_collection = @Translation("Ratifications"),
 *   label_singular = @Translation("ratification"),
 *   label_plural = @Translation("ratifications"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\avc_guild\RatificationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\avc_guild\Form\RatificationForm",
 *       "review" = "Drupal\avc_guild\Form\RatificationForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\avc_guild\RatificationAccessControlHandler",
 *   },
 *   base_table = "ratification",
 *   admin_permission = "administer ratifications",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/guild/ratification/{ratification}",
 *     "collection" = "/admin/config/avc/guild/ratifications",
 *     "edit-form" = "/guild/ratification/{ratification}/review",
 *     "delete-form" = "/guild/ratification/{ratification}/delete",
 *   },
 * )
 */
class Ratification extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * Ratification statuses.
   */
  const STATUS_PENDING = 'pending';
  const STATUS_APPROVED = 'approved';
  const STATUS_CHANGES_REQUESTED = 'changes_requested';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Task reference (workflow_task).
    $fields['task_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Task'))
      ->setDescription(t('The workflow task being ratified.'))
      ->setSetting('target_type', 'workflow_task')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Asset reference (node).
    $fields['asset_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Asset'))
      ->setDescription(t('The asset the task is for.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Junior user.
    $fields['junior_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Junior'))
      ->setDescription(t('The junior member whose work needs ratification.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Mentor user (assigned or claimed).
    $fields['mentor_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Mentor'))
      ->setDescription(t('The mentor reviewing this work.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Guild.
    $fields['guild_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Guild'))
      ->setDescription(t('The guild context.'))
      ->setSetting('target_type', 'group')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Status.
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The ratification status.'))
      ->setSettings([
        'allowed_values' => [
          self::STATUS_PENDING => 'Pending',
          self::STATUS_APPROVED => 'Approved',
          self::STATUS_CHANGES_REQUESTED => 'Changes Requested',
        ],
      ])
      ->setDefaultValue(self::STATUS_PENDING)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Feedback.
    $fields['feedback'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Feedback'))
      ->setDescription(t('Mentor feedback on the work.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 6,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the ratification request was created.'));

    // Changed timestamp.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the ratification was last updated.'));

    // Completed timestamp.
    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDescription(t('The time the ratification was completed.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Gets the task.
   *
   * @return \Drupal\workflow_assignment\Entity\WorkflowTask|null
   *   The workflow task entity.
   */
  public function getTask() {
    return $this->get('task_id')->entity;
  }

  /**
   * Gets the asset.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The asset node.
   */
  public function getAsset() {
    return $this->get('asset_id')->entity;
  }

  /**
   * Gets the junior.
   *
   * @return \Drupal\user\UserInterface|null
   *   The junior user.
   */
  public function getJunior() {
    return $this->get('junior_id')->entity;
  }

  /**
   * Gets the mentor.
   *
   * @return \Drupal\user\UserInterface|null
   *   The mentor user or NULL if not yet assigned.
   */
  public function getMentor() {
    return $this->get('mentor_id')->entity;
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
   * Gets the status.
   *
   * @return string
   *   The ratification status.
   */
  public function getStatus() {
    return $this->get('status')->value ?? self::STATUS_PENDING;
  }

  /**
   * Gets the feedback.
   *
   * @return string
   *   The feedback text.
   */
  public function getFeedback() {
    return $this->get('feedback')->value ?? '';
  }

  /**
   * Check if ratification is pending.
   *
   * @return bool
   *   TRUE if pending.
   */
  public function isPending() {
    return $this->getStatus() === self::STATUS_PENDING;
  }

  /**
   * Check if ratification was approved.
   *
   * @return bool
   *   TRUE if approved.
   */
  public function isApproved() {
    return $this->getStatus() === self::STATUS_APPROVED;
  }

  /**
   * Approve the ratification.
   *
   * @param \Drupal\Core\Session\AccountInterface $mentor
   *   The mentor approving.
   * @param string $feedback
   *   Optional feedback.
   *
   * @return $this
   */
  public function approve($mentor, string $feedback = '') {
    $this->set('status', self::STATUS_APPROVED);
    $this->set('mentor_id', $mentor->id());
    $this->set('feedback', $feedback);
    $this->set('completed', \Drupal::time()->getRequestTime());
    return $this;
  }

  /**
   * Request changes.
   *
   * @param \Drupal\Core\Session\AccountInterface $mentor
   *   The mentor requesting changes.
   * @param string $feedback
   *   Required feedback explaining what needs to change.
   *
   * @return $this
   */
  public function requestChanges($mentor, string $feedback) {
    $this->set('status', self::STATUS_CHANGES_REQUESTED);
    $this->set('mentor_id', $mentor->id());
    $this->set('feedback', $feedback);
    $this->set('completed', \Drupal::time()->getRequestTime());
    return $this;
  }

}
