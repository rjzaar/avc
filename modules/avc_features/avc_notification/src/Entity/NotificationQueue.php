<?php

namespace Drupal\avc_notification\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Notification Queue entity.
 *
 * @ContentEntityType(
 *   id = "notification_queue",
 *   label = @Translation("Notification Queue"),
 *   label_collection = @Translation("Notification Queue"),
 *   label_singular = @Translation("notification"),
 *   label_plural = @Translation("notifications"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\avc_notification\NotificationQueueListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\avc_notification\NotificationQueueAccessControlHandler",
 *   },
 *   base_table = "notification_queue",
 *   admin_permission = "administer notification queue",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "target_user",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/avc/notifications/queue/{notification_queue}",
 *     "collection" = "/admin/config/avc/notifications/queue",
 *     "delete-form" = "/admin/config/avc/notifications/queue/{notification_queue}/delete",
 *   },
 * )
 */
class NotificationQueue extends ContentEntityBase {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Notification event types.
   */
  const EVENT_WORKFLOW_ADVANCE = 'workflow_advance';
  const EVENT_ASSIGNMENT = 'assignment';
  const EVENT_RATIFICATION_NEEDED = 'ratification_needed';
  const EVENT_RATIFICATION_COMPLETE = 'ratification_complete';
  const EVENT_ENDORSEMENT = 'endorsement';
  const EVENT_GUILD_PROMOTION = 'guild_promotion';
  const EVENT_GROUP_COMMENT = 'group_comment';

  /**
   * Notification statuses.
   */
  const STATUS_PENDING = 'pending';
  const STATUS_SENT = 'sent';
  const STATUS_FAILED = 'failed';
  const STATUS_SKIPPED = 'skipped';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Event type field.
    $fields['event_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Event Type'))
      ->setDescription(t('The type of notification event.'))
      ->setSettings([
        'allowed_values' => [
          self::EVENT_WORKFLOW_ADVANCE => 'Workflow Advance',
          self::EVENT_ASSIGNMENT => 'Assignment',
          self::EVENT_RATIFICATION_NEEDED => 'Ratification Needed',
          self::EVENT_RATIFICATION_COMPLETE => 'Ratification Complete',
          self::EVENT_ENDORSEMENT => 'Endorsement',
          self::EVENT_GUILD_PROMOTION => 'Guild Promotion',
          self::EVENT_GROUP_COMMENT => 'Group Comment',
        ],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Target user field.
    $fields['target_user'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Target User'))
      ->setDescription(t('The user who should receive this notification.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Target group field (optional).
    $fields['target_group'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Target Group'))
      ->setDescription(t('The group context for this notification (optional).'))
      ->setSetting('target_type', 'group')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Asset reference field.
    $fields['asset_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Asset'))
      ->setDescription(t('The asset this notification relates to.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Message content field.
    $fields['message'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Message'))
      ->setDescription(t('The notification message content.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Additional data (JSON).
    $fields['data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Data'))
      ->setDescription(t('Additional notification data in JSON format.'));

    // Status field.
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The notification status.'))
      ->setSettings([
        'allowed_values' => [
          self::STATUS_PENDING => 'Pending',
          self::STATUS_SENT => 'Sent',
          self::STATUS_FAILED => 'Failed',
          self::STATUS_SKIPPED => 'Skipped',
        ],
      ])
      ->setDefaultValue(self::STATUS_PENDING)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the notification was queued.'));

    // Changed timestamp.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the notification was last updated.'));

    // Sent timestamp.
    $fields['sent'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Sent'))
      ->setDescription(t('The time the notification was sent.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Gets the event type.
   *
   * @return string
   *   The event type.
   */
  public function getEventType() {
    return $this->get('event_type')->value;
  }

  /**
   * Gets the target user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The target user entity.
   */
  public function getTargetUser() {
    return $this->get('target_user')->entity;
  }

  /**
   * Gets the target group.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The target group entity or NULL.
   */
  public function getTargetGroup() {
    return $this->get('target_group')->entity;
  }

  /**
   * Gets the asset.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The asset node or NULL.
   */
  public function getAsset() {
    return $this->get('asset_id')->entity;
  }

  /**
   * Gets the message.
   *
   * @return string
   *   The message content.
   */
  public function getMessage() {
    return $this->get('message')->value ?? '';
  }

  /**
   * Gets additional data.
   *
   * @return array
   *   The decoded data array.
   */
  public function getData() {
    $data = $this->get('data')->value;
    return $data ? json_decode($data, TRUE) : [];
  }

  /**
   * Sets additional data.
   *
   * @param array $data
   *   The data to store.
   *
   * @return $this
   */
  public function setData(array $data) {
    $this->set('data', json_encode($data));
    return $this;
  }

  /**
   * Gets the status.
   *
   * @return string
   *   The notification status.
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * Marks the notification as sent.
   *
   * @return $this
   */
  public function markSent() {
    $this->set('status', self::STATUS_SENT);
    $this->set('sent', \Drupal::time()->getRequestTime());
    return $this;
  }

  /**
   * Marks the notification as failed.
   *
   * @return $this
   */
  public function markFailed() {
    $this->set('status', self::STATUS_FAILED);
    return $this;
  }

  /**
   * Marks the notification as skipped.
   *
   * @return $this
   */
  public function markSkipped() {
    $this->set('status', self::STATUS_SKIPPED);
    return $this;
  }

}
