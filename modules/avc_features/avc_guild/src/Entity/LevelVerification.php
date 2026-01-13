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
 * Defines the Level Verification entity.
 *
 * Records verification/confirmation of level advancement.
 *
 * @ContentEntityType(
 *   id = "level_verification",
 *   label = @Translation("Level Verification"),
 *   label_collection = @Translation("Level Verifications"),
 *   label_singular = @Translation("level verification"),
 *   label_plural = @Translation("level verifications"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\avc_guild\LevelVerificationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "vote" = "Drupal\avc_guild\Form\LevelVerificationVoteForm",
 *     },
 *     "access" = "Drupal\avc_guild\LevelVerificationAccessControlHandler",
 *   },
 *   base_table = "level_verification",
 *   admin_permission = "administer level verifications",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/avc/guild/verifications",
 *     "canonical" = "/guild/{group}/verification/{level_verification}",
 *     "vote-form" = "/guild/{group}/verification/{level_verification}/vote",
 *   },
 * )
 */
class LevelVerification extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * Status values.
   */
  const STATUS_PENDING = 'pending';
  const STATUS_APPROVED = 'approved';
  const STATUS_DENIED = 'denied';
  const STATUS_DEFERRED = 'deferred';
  const STATUS_EXPIRED = 'expired';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // User being verified.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user being verified for level advancement.'))
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
      ->setDescription(t('The guild context.'))
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
      ->setDescription(t('The skill being verified.'))
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

    // Target level.
    $fields['target_level'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Target Level'))
      ->setDescription(t('The level being verified for.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Status.
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The verification status.'))
      ->setRequired(TRUE)
      ->setDefaultValue(self::STATUS_PENDING)
      ->setSettings([
        'allowed_values' => [
          self::STATUS_PENDING => 'Pending',
          self::STATUS_APPROVED => 'Approved',
          self::STATUS_DENIED => 'Denied',
          self::STATUS_DEFERRED => 'Deferred',
          self::STATUS_EXPIRED => 'Expired',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Verification type (copied from SkillLevel at creation).
    $fields['verification_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Verification Type'))
      ->setDescription(t('The type of verification required.'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          SkillLevel::VERIFICATION_AUTO => 'Automatic',
          SkillLevel::VERIFICATION_MENTOR => 'Mentor Approval',
          SkillLevel::VERIFICATION_PEER => 'Peer Votes',
          SkillLevel::VERIFICATION_COMMITTEE => 'Committee Vote',
          SkillLevel::VERIFICATION_ASSESSMENT => 'Formal Assessment',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Votes required.
    $fields['votes_required'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Votes Required'))
      ->setDescription(t('Number of approval votes needed.'))
      ->setDefaultValue(1)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Vote tallies.
    $fields['votes_approve'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Approve Votes'))
      ->setDescription(t('Number of approval votes received.'))
      ->setDefaultValue(0);

    $fields['votes_deny'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Deny Votes'))
      ->setDescription(t('Number of denial votes received.'))
      ->setDefaultValue(0);

    $fields['votes_defer'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Defer Votes'))
      ->setDescription(t('Number of defer votes received.'))
      ->setDefaultValue(0);

    // Feedback.
    $fields['feedback'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Feedback'))
      ->setDescription(t('Collected feedback from verifiers.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the verification was initiated.'));

    // Changed timestamp.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the verification was last updated.'));

    // Completed timestamp.
    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDescription(t('The time the verification was completed.'));

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
   * Gets the target level.
   *
   * @return int
   *   The target level.
   */
  public function getTargetLevel(): int {
    return (int) $this->get('target_level')->value;
  }

  /**
   * Gets the status.
   *
   * @return string
   *   The status.
   */
  public function getStatus(): string {
    return $this->get('status')->value ?? self::STATUS_PENDING;
  }

  /**
   * Sets the status.
   *
   * @param string $status
   *   The status.
   *
   * @return $this
   */
  public function setStatus(string $status): self {
    $this->set('status', $status);
    if (in_array($status, [self::STATUS_APPROVED, self::STATUS_DENIED, self::STATUS_EXPIRED])) {
      $this->set('completed', \Drupal::time()->getRequestTime());
    }
    return $this;
  }

  /**
   * Checks if pending.
   *
   * @return bool
   *   TRUE if pending.
   */
  public function isPending(): bool {
    return $this->getStatus() === self::STATUS_PENDING;
  }

  /**
   * Checks if approved.
   *
   * @return bool
   *   TRUE if approved.
   */
  public function isApproved(): bool {
    return $this->getStatus() === self::STATUS_APPROVED;
  }

  /**
   * Gets approval votes.
   *
   * @return int
   *   The count.
   */
  public function getApproveVotes(): int {
    return (int) $this->get('votes_approve')->value;
  }

  /**
   * Gets denial votes.
   *
   * @return int
   *   The count.
   */
  public function getDenyVotes(): int {
    return (int) $this->get('votes_deny')->value;
  }

  /**
   * Gets defer votes.
   *
   * @return int
   *   The count.
   */
  public function getDeferVotes(): int {
    return (int) $this->get('votes_defer')->value;
  }

  /**
   * Gets votes required.
   *
   * @return int
   *   The count.
   */
  public function getVotesRequired(): int {
    return (int) $this->get('votes_required')->value ?: 1;
  }

  /**
   * Gets total votes cast.
   *
   * @return int
   *   The total.
   */
  public function getTotalVotes(): int {
    return $this->getApproveVotes() + $this->getDenyVotes() + $this->getDeferVotes();
  }

  /**
   * Increments a vote count.
   *
   * @param string $vote_type
   *   One of 'approve', 'deny', 'defer'.
   *
   * @return $this
   */
  public function incrementVote(string $vote_type): self {
    $field_name = 'votes_' . $vote_type;
    if ($this->hasField($field_name)) {
      $current = (int) $this->get($field_name)->value;
      $this->set($field_name, $current + 1);
    }
    return $this;
  }

  /**
   * Appends feedback.
   *
   * @param string $feedback
   *   The feedback to append.
   * @param \Drupal\user\UserInterface|null $verifier
   *   The verifier who gave feedback.
   *
   * @return $this
   */
  public function appendFeedback(string $feedback, ?UserInterface $verifier = NULL): self {
    $existing = $this->get('feedback')->value ?? '';
    $prefix = $verifier ? $verifier->getDisplayName() . ': ' : '';
    $timestamp = date('Y-m-d H:i');

    $new_feedback = $existing;
    if ($new_feedback) {
      $new_feedback .= "\n\n---\n\n";
    }
    $new_feedback .= "[$timestamp] $prefix$feedback";

    $this->set('feedback', $new_feedback);
    return $this;
  }

}
