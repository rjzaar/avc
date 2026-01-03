<?php

namespace Drupal\workflow_assignment\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Workflow Task entity.
 *
 * A workflow task represents a single step/stage in a workflow that is
 * assigned to a specific piece of content (node). Tasks can be assigned
 * to users, groups, or destinations.
 *
 * @ContentEntityType(
 *   id = "workflow_task",
 *   label = @Translation("Workflow Task"),
 *   label_collection = @Translation("Workflow Tasks"),
 *   label_singular = @Translation("workflow task"),
 *   label_plural = @Translation("workflow tasks"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\workflow_assignment\WorkflowTaskListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\workflow_assignment\Form\WorkflowTaskForm",
 *       "add" = "Drupal\workflow_assignment\Form\WorkflowTaskForm",
 *       "edit" = "Drupal\workflow_assignment\Form\WorkflowTaskForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\workflow_assignment\WorkflowTaskAccessControlHandler",
 *   },
 *   base_table = "workflow_task",
 *   revision_table = "workflow_task_revision",
 *   data_table = "workflow_task_field_data",
 *   revision_data_table = "workflow_task_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = FALSE,
 *   admin_permission = "administer workflow tasks",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/workflow-task/{workflow_task}",
 *     "add-form" = "/workflow-task/add",
 *     "edit-form" = "/workflow-task/{workflow_task}/edit",
 *     "delete-form" = "/workflow-task/{workflow_task}/delete",
 *     "version-history" = "/workflow-task/{workflow_task}/revisions",
 *     "revision" = "/workflow-task/{workflow_task}/revisions/{workflow_task_revision}/view",
 *   },
 * )
 */
class WorkflowTask extends ContentEntityBase {

  use EntityChangedTrait;
  use EntityOwnerTrait;
  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Workflow Task entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Workflow Task entity.'))
      ->setReadOnly(TRUE);

    // Revision ID.
    $fields['vid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The revision ID of the workflow task.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    // Owner field - who created this task.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Workflow Task entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Node reference - the asset this task is assigned to.
    $fields['node_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Asset'))
      ->setDescription(t('The node (asset) this workflow task is assigned to.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -6,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Title field.
    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the Workflow Task (e.g., "Editorial Review").'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    // Description field.
    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('A description of what this workflow task involves.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 0,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Weight field for ordering tasks.
    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('The order of this task in the workflow sequence.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Assignment type (user, group, or destination).
    $fields['assigned_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Assignment Type'))
      ->setDescription(t('The type of assignment: user, group, or destination.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'allowed_values' => [
          'user' => 'User',
          'group' => 'Group',
          'destination' => 'Destination',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    // Assigned User - shown when assignment type is 'user'.
    $fields['assigned_user'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Assigned User'))
      ->setDescription(t('The user assigned to this task.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Assigned Group - shown when assignment type is 'group'.
    $fields['assigned_group'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Assigned Group'))
      ->setDescription(t('The group assigned to this task.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'group')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Assigned Destination - shown when assignment type is 'destination'.
    $fields['assigned_destination'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Assigned Destination'))
      ->setDescription(t('The destination for this task (final step).'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'destination_locations' => 'destination_locations',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Comments field.
    $fields['comments'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Comments'))
      ->setDescription(t('Comments or notes about this task.'))
      ->setRevisionable(TRUE)
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

    // Completion status field.
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The completion status of this task.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'allowed_values' => [
          'pending' => 'Pending',
          'in_progress' => 'In Progress',
          'completed' => 'Completed',
          'skipped' => 'Skipped',
        ],
      ])
      ->setDefaultValue('pending')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    // Due date field.
    $fields['due_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Due Date'))
      ->setDescription(t('The date this task should be completed by.'))
      ->setRevisionable(TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Created timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the task was created.'))
      ->setRevisionable(TRUE);

    // Changed timestamp.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the task was last edited.'))
      ->setRevisionable(TRUE);

    return $fields;
  }

  /**
   * Gets the assigned entity label.
   *
   * @return string
   *   The label of the assigned entity.
   */
  public function getAssignedLabel() {
    $type = $this->get('assigned_type')->value;

    switch ($type) {
      case 'user':
        $user = $this->get('assigned_user')->entity;
        return $user ? $user->getDisplayName() : '';

      case 'group':
        $group = $this->get('assigned_group')->entity;
        return $group ? $group->label() : '';

      case 'destination':
        $term = $this->get('assigned_destination')->entity;
        return $term ? $term->getName() : '';

      default:
        return '';
    }
  }

  /**
   * Gets the assigned type.
   *
   * @return string
   *   The assigned type (user, group, or destination).
   */
  public function getAssignedType() {
    return $this->get('assigned_type')->value;
  }

  /**
   * Gets the referenced node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The referenced node or NULL.
   */
  public function getNode() {
    return $this->get('node_id')->entity;
  }

  /**
   * Gets the task status.
   *
   * @return string
   *   The status value.
   */
  public function getStatus() {
    return $this->get('status')->value ?? 'pending';
  }

  /**
   * Gets the task weight.
   *
   * @return int
   *   The weight value.
   */
  public function getWeight() {
    return (int) $this->get('weight')->value;
  }

  /**
   * Gets the description.
   *
   * @return string
   *   The description text.
   */
  public function getDescription() {
    $description = $this->get('description')->value;
    return $description ?? '';
  }

  /**
   * Gets the comments.
   *
   * @return string
   *   The comments text.
   */
  public function getComments() {
    $comments = $this->get('comments')->value;
    return $comments ?? '';
  }

}
