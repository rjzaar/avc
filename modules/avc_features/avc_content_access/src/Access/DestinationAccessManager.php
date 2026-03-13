<?php

namespace Drupal\avc_content_access\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Manages destination-based access control for completed workflow content.
 *
 * After content completes its workflow and reaches a destination, this manager
 * enforces access based on the destination's access_level field:
 * - public: Anyone can view
 * - authenticated: Only logged-in users
 * - group: Only members of specified groups
 * - private: Only the author and admins
 */
class DestinationAccessManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ConfigFactoryInterface $configFactory;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Check destination-based access for a node.
   *
   * Only applies to nodes that have completed their workflow and been assigned
   * to a destination. Nodes with active workflows are handled by
   * WorkflowAccessManager instead.
   */
  public function checkAccess(NodeInterface $node, string $operation, AccountInterface $account): AccessResultInterface {
    $config = $this->configFactory->get('workflow_assignment.settings');
    $enabled_types = $config->get('enabled_content_types') ?? [];

    if (!in_array($node->bundle(), $enabled_types)) {
      return AccessResult::neutral()
        ->addCacheableDependency($node)
        ->addCacheTags(['config:workflow_assignment.settings']);
    }

    // Find the destination task for this node.
    $destination_term = $this->getCompletedDestination($node);
    if (!$destination_term) {
      return AccessResult::neutral()
        ->addCacheableDependency($node);
    }

    $access_level = $this->getTermFieldValue($destination_term, 'field_access_level');
    if (!$access_level) {
      return AccessResult::neutral()
        ->addCacheableDependency($destination_term);
    }

    $cache_tags = [
      'taxonomy_term:' . $destination_term->id(),
      'config:workflow_assignment.settings',
    ];

    switch ($access_level) {
      case 'public':
        return AccessResult::allowed()
          ->addCacheableDependency($node)
          ->addCacheTags($cache_tags);

      case 'authenticated':
        if ($account->isAuthenticated()) {
          return AccessResult::allowed()
            ->addCacheableDependency($node)
            ->addCacheContexts(['user.roles:authenticated'])
            ->addCacheTags($cache_tags);
        }
        return AccessResult::forbidden('This content requires authentication.')
          ->addCacheableDependency($node)
          ->addCacheContexts(['user.roles:authenticated'])
          ->addCacheTags($cache_tags);

      case 'group':
        return $this->checkGroupAccess($node, $account, $destination_term, $cache_tags);

      case 'private':
        if ((int) $node->getOwnerId() === (int) $account->id()) {
          return AccessResult::allowed()
            ->addCacheableDependency($node)
            ->addCacheContexts(['user'])
            ->addCacheTags($cache_tags);
        }
        if ($account->hasPermission('administer content access')) {
          return AccessResult::allowed()
            ->addCacheableDependency($node)
            ->addCacheContexts(['user.permissions'])
            ->addCacheTags($cache_tags);
        }
        return AccessResult::forbidden('This content is private.')
          ->addCacheableDependency($node)
          ->addCacheContexts(['user'])
          ->addCacheTags($cache_tags);

      default:
        return AccessResult::neutral()
          ->addCacheableDependency($node)
          ->addCacheTags($cache_tags);
    }
  }

  /**
   * Find the completed destination for a node.
   *
   * Looks for a completed workflow task of type 'destination' assigned to
   * this node. Returns the destination taxonomy term if found.
   */
  public function getCompletedDestination(NodeInterface $node): ?object {
    $storage = $this->entityTypeManager->getStorage('workflow_task');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('node_id', $node->id())
      ->condition('assigned_type', 'destination')
      ->condition('status', 'completed')
      ->sort('changed', 'DESC')
      ->range(0, 1)
      ->execute();

    if (empty($ids)) {
      return NULL;
    }

    $task = $storage->load(reset($ids));
    if (!$task) {
      return NULL;
    }

    $term_id = $task->get('assigned_destination')->target_id;
    if (!$term_id) {
      return NULL;
    }

    return $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
  }

  /**
   * Check group-based access.
   */
  protected function checkGroupAccess(NodeInterface $node, AccountInterface $account, object $destination_term, array $cache_tags): AccessResultInterface {
    // Author always has access.
    if ((int) $node->getOwnerId() === (int) $account->id()) {
      return AccessResult::allowed()
        ->addCacheableDependency($node)
        ->addCacheContexts(['user'])
        ->addCacheTags($cache_tags);
    }

    if (!$destination_term->hasField('field_access_groups')) {
      return AccessResult::neutral()
        ->addCacheableDependency($node)
        ->addCacheTags($cache_tags);
    }

    $group_ids = [];
    foreach ($destination_term->get('field_access_groups') as $item) {
      if ($item->target_id) {
        $group_ids[] = $item->target_id;
      }
    }

    if (empty($group_ids)) {
      return AccessResult::neutral()
        ->addCacheableDependency($node)
        ->addCacheTags($cache_tags);
    }

    // Check if user is a member of any of the specified groups.
    if ($this->userInAnyGroup($account, $group_ids)) {
      return AccessResult::allowed()
        ->addCacheableDependency($node)
        ->addCacheContexts(['user'])
        ->addCacheTags($cache_tags);
    }

    return AccessResult::forbidden('Access restricted to group members.')
      ->addCacheableDependency($node)
      ->addCacheContexts(['user'])
      ->addCacheTags($cache_tags);
  }

  /**
   * Check if a user belongs to any of the given groups.
   */
  protected function userInAnyGroup(AccountInterface $account, array $group_ids): bool {
    $membership_loader = \Drupal::service('group.membership_loader');
    $group_storage = $this->entityTypeManager->getStorage('group');

    foreach ($group_ids as $group_id) {
      $group = $group_storage->load($group_id);
      if ($group && $membership_loader->load($group, $account)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Get a field value from a taxonomy term.
   */
  protected function getTermFieldValue(object $term, string $field_name): ?string {
    if (!$term->hasField($field_name)) {
      return NULL;
    }
    return $term->get($field_name)->value;
  }

}
