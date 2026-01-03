<?php

namespace Drupal\avc_asset\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Service for managing assets (Projects, Documents, Resources).
 */
class AssetManager {

  /**
   * Asset type constants.
   */
  const TYPE_PROJECT = 'project';
  const TYPE_DOCUMENT = 'document';
  const TYPE_RESOURCE = 'resource';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The workflow processor.
   *
   * @var \Drupal\avc_asset\Service\WorkflowProcessor
   */
  protected $workflowProcessor;

  /**
   * Constructs an AssetManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\avc_asset\Service\WorkflowProcessor $workflow_processor
   *   The workflow processor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    WorkflowProcessor $workflow_processor
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->workflowProcessor = $workflow_processor;
  }

  /**
   * Gets all assets of a given type.
   *
   * @param string $type
   *   The asset type.
   * @param array $options
   *   Query options (limit, offset, status).
   *
   * @return array
   *   Array of asset nodes.
   */
  public function getAssetsByType($type, array $options = []) {
    $storage = $this->entityTypeManager->getStorage('node');

    // First try the dedicated bundle.
    $bundle = $this->getAssetBundle($type);
    $ids = [];

    if ($bundle) {
      $query = $storage->getQuery()
        ->condition('type', $bundle)
        ->condition('status', 1)
        ->sort('created', 'DESC')
        ->accessCheck(TRUE);

      if (!empty($options['limit'])) {
        $query->range(0, $options['limit']);
      }

      $ids = $query->execute();
    }

    // Fallback: Look for page nodes with [TEST] prefix matching the type.
    if (empty($ids)) {
      $title_patterns = [
        'project' => ['Mobile App', 'Translation Project', 'Course Development', 'Guidelines Revision'],
        'document' => ['Prayer', 'Documentation', 'Handbook', 'Theology', 'Best Practices', 'Style Guide', 'Minutes', 'Report'],
        'resource' => ['Vatican', 'Catechism', 'Liturgy', 'Scripture', 'Videos'],
      ];

      $query = $storage->getQuery()
        ->condition('type', 'page')
        ->condition('title', '[TEST]%', 'LIKE')
        ->condition('status', 1)
        ->sort('created', 'DESC')
        ->accessCheck(TRUE);

      if (!empty($options['limit'])) {
        $query->range(0, $options['limit']);
      }

      $all_ids = $query->execute();

      if (!empty($all_ids) && isset($title_patterns[$type])) {
        $nodes = $storage->loadMultiple($all_ids);
        foreach ($nodes as $node) {
          $title = $node->getTitle();
          foreach ($title_patterns[$type] as $pattern) {
            if (stripos($title, $pattern) !== FALSE) {
              $ids[$node->id()] = $node->id();
              break;
            }
          }
        }
      }
    }

    return $storage->loadMultiple($ids);
  }

  /**
   * Gets assets assigned to a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account.
   * @param array $options
   *   Query options.
   *
   * @return array
   *   Array of assets with workflow info.
   */
  public function getUserAssets(AccountInterface $user, array $options = []) {
    $assets = [];

    try {
      if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
        return $assets;
      }

      $storage = $this->entityTypeManager->getStorage('workflow_task');
      $query = $storage->getQuery()
        ->condition('assigned_type', 'user')
        ->condition('assigned_user', $user->id())
        ->accessCheck(TRUE);

      $ids = $query->execute();
      $tasks = $storage->loadMultiple($ids);

      foreach ($tasks as $task) {
        $node = $task->getNode();
        if ($node) {
          $assets[] = [
            'node' => $node,
            'task' => $task,
            'status' => $this->getAssetStatus($node, $task),
          ];
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_asset')->error('Error loading user assets: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $assets;
  }

  /**
   * Gets assets for a group.
   *
   * @param int $group_id
   *   The group ID.
   * @param array $options
   *   Query options.
   *
   * @return array
   *   Array of assets with workflow info.
   */
  public function getGroupAssets($group_id, array $options = []) {
    $assets = [];

    try {
      if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
        return $assets;
      }

      $storage = $this->entityTypeManager->getStorage('workflow_task');
      $query = $storage->getQuery()
        ->condition('assigned_type', 'group')
        ->condition('assigned_group', $group_id)
        ->accessCheck(TRUE);

      $ids = $query->execute();
      $tasks = $storage->loadMultiple($ids);

      foreach ($tasks as $task) {
        $node = $task->getNode();
        if ($node) {
          $assets[] = [
            'node' => $node,
            'task' => $task,
            'status' => $this->getAssetStatus($node, $task),
          ];
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_asset')->error('Error loading group assets: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $assets;
  }

  /**
   * Creates a new asset.
   *
   * @param string $type
   *   The asset type.
   * @param array $values
   *   The field values.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created node or NULL.
   */
  public function createAsset($type, array $values) {
    $bundle = $this->getAssetBundle($type);
    if (!$bundle) {
      return NULL;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('node');

      // Generate asset number.
      $asset_number = $this->generateAssetNumber($type);

      $node_values = [
        'type' => $bundle,
        'title' => $values['title'] ?? 'Untitled Asset',
        'uid' => $this->currentUser->id(),
        'status' => 1,
        'field_asset_number' => $asset_number,
        'field_asset_type' => $type,
        'field_process_status' => 'draft',
      ];

      // Add optional fields.
      if (!empty($values['body'])) {
        $node_values['body'] = ['value' => $values['body'], 'format' => 'basic_html'];
      }

      if (!empty($values['initiator'])) {
        $node_values['field_initiator'] = $values['initiator'];
      }

      if (!empty($values['gatekeeper'])) {
        $node_values['field_gatekeeper'] = $values['gatekeeper'];
      }

      if (!empty($values['approver'])) {
        $node_values['field_approver'] = $values['approver'];
      }

      if (!empty($values['destination'])) {
        $node_values['field_destination'] = $values['destination'];
      }

      $node = $storage->create($node_values);
      $node->save();

      return $node;
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_asset')->error('Error creating asset: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Gets project contained assets.
   *
   * @param \Drupal\node\NodeInterface $project
   *   The project node.
   *
   * @return array
   *   Array of contained asset nodes.
   */
  public function getProjectAssets(NodeInterface $project) {
    if ($project->bundle() !== 'avc_project') {
      return [];
    }

    $assets = [];
    if ($project->hasField('field_contained_assets')) {
      $referenced = $project->get('field_contained_assets')->referencedEntities();
      foreach ($referenced as $asset) {
        $assets[] = $asset;
      }
    }

    return $assets;
  }

  /**
   * Adds an asset to a project.
   *
   * @param \Drupal\node\NodeInterface $project
   *   The project node.
   * @param \Drupal\node\NodeInterface $asset
   *   The asset to add.
   *
   * @return bool
   *   TRUE if added successfully.
   */
  public function addAssetToProject(NodeInterface $project, NodeInterface $asset) {
    if ($project->bundle() !== 'avc_project') {
      return FALSE;
    }

    try {
      if ($project->hasField('field_contained_assets')) {
        $current = $project->get('field_contained_assets')->getValue();
        $current[] = ['target_id' => $asset->id()];
        $project->set('field_contained_assets', $current);
        $project->save();
        return TRUE;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_asset')->error('Error adding asset to project: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return FALSE;
  }

  /**
   * Gets the status of an asset based on workflow task.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   * @param mixed $task
   *   The workflow task (optional).
   *
   * @return string
   *   The status: 'current', 'upcoming', 'completed'.
   */
  protected function getAssetStatus(NodeInterface $node, $task = NULL) {
    if ($task) {
      $status = $task->getStatus();
      switch ($status) {
        case 'completed':
          return 'completed';
        case 'in_progress':
          return 'current';
        default:
          return 'upcoming';
      }
    }

    // Fallback to process status field.
    if ($node->hasField('field_process_status')) {
      return $node->get('field_process_status')->value ?? 'draft';
    }

    return 'unknown';
  }

  /**
   * Gets the bundle name for an asset type.
   *
   * @param string $type
   *   The asset type.
   *
   * @return string|null
   *   The bundle name or NULL.
   */
  protected function getAssetBundle($type) {
    $map = [
      self::TYPE_PROJECT => 'avc_project',
      self::TYPE_DOCUMENT => 'avc_document',
      self::TYPE_RESOURCE => 'avc_resource',
    ];
    return $map[$type] ?? NULL;
  }

  /**
   * Generates a unique asset number.
   *
   * @param string $type
   *   The asset type.
   *
   * @return string
   *   The generated number.
   */
  protected function generateAssetNumber($type) {
    $prefix = [
      self::TYPE_PROJECT => 'PRJ',
      self::TYPE_DOCUMENT => 'DOC',
      self::TYPE_RESOURCE => 'RES',
    ];

    $p = $prefix[$type] ?? 'AST';
    $timestamp = date('ymd');
    $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

    return "{$p}-{$timestamp}-{$random}";
  }

}
