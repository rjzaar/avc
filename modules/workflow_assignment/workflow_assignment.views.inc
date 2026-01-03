<?php

/**
 * @file
 * Provide views data for workflow_assignment module.
 */

/**
 * Implements hook_views_data().
 */
function workflow_assignment_views_data() {
  $data = [];

  // Workflow assignment history table.
  $data['workflow_assignment_history'] = [
    'table' => [
      'group' => t('Workflow History'),
      'base' => [
        'field' => 'id',
        'title' => t('Workflow Assignment History'),
        'help' => t('Contains workflow assignment history records.'),
      ],
    ],
    'id' => [
      'title' => t('ID'),
      'help' => t('The unique ID of the history record.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
    ],
    'workflow_id' => [
      'title' => t('Workflow ID'),
      'help' => t('The workflow list ID.'),
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ],
    'node_id' => [
      'title' => t('Node ID'),
      'help' => t('The node ID.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'relationship' => [
        'base' => 'node_field_data',
        'base field' => 'nid',
        'id' => 'standard',
        'label' => t('Node'),
      ],
    ],
    'action' => [
      'title' => t('Action'),
      'help' => t('The action performed (assign, unassign, update, etc.).'),
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'in_operator',
        'options callback' => 'workflow_assignment_get_action_options',
      ],
    ],
    'field_name' => [
      'title' => t('Field Name'),
      'help' => t('The field that was changed.'),
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
    ],
    'old_value' => [
      'title' => t('Old Value'),
      'help' => t('The old value before the change.'),
      'field' => [
        'id' => 'standard',
      ],
    ],
    'new_value' => [
      'title' => t('New Value'),
      'help' => t('The new value after the change.'),
      'field' => [
        'id' => 'standard',
      ],
    ],
    'uid' => [
      'title' => t('User ID'),
      'help' => t('The user who made the change.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'relationship' => [
        'base' => 'users_field_data',
        'base field' => 'uid',
        'id' => 'standard',
        'label' => t('User'),
      ],
    ],
    'timestamp' => [
      'title' => t('Timestamp'),
      'help' => t('When the change was made.'),
      'field' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
    ],
  ];

  // Add workflow field to nodes for views.
  $data['node__field_workflow_list'] = [
    'table' => [
      'group' => t('Content'),
      'join' => [
        'node_field_data' => [
          'left_field' => 'nid',
          'field' => 'entity_id',
        ],
      ],
    ],
    'field_workflow_list_target_id' => [
      'title' => t('Workflow List'),
      'help' => t('The assigned workflow list IDs.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ],
  ];

  return $data;
}

/**
 * Provides action options for views filter.
 */
function workflow_assignment_get_action_options() {
  return [
    'create' => t('Created'),
    'update' => t('Updated'),
    'delete' => t('Deleted'),
    'assign' => t('Assigned'),
    'unassign' => t('Unassigned'),
  ];
}
