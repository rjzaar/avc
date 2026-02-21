<?php

namespace Drupal\workflow_assignment\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\workflow_assignment\Service\WorkflowParticipantResolver;

/**
 * Manages workflow-based access control for nodes.
 */
class WorkflowAccessManager {

  /**
   * The participant resolver.
   *
   * @var \Drupal\workflow_assignment\Service\WorkflowParticipantResolver
   */
  protected $participantResolver;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a WorkflowAccessManager.
   *
   * @param \Drupal\workflow_assignment\Service\WorkflowParticipantResolver $participant_resolver
   *   The participant resolver.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    WorkflowParticipantResolver $participant_resolver,
    ConfigFactoryInterface $config_factory
  ) {
    $this->participantResolver = $participant_resolver;
    $this->configFactory = $config_factory;
  }

  /**
   * Check if workflow-based access control applies to this node type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if workflow access control applies.
   */
  public function appliesTo(NodeInterface $node): bool {
    $config = $this->configFactory->get('workflow_assignment.settings');
    $enabled_types = $config->get('enabled_content_types') ?? [];
    $access_control_types = $config->get('workflow_access_control_types') ?? [];

    $bundle = $node->bundle();

    return in_array($bundle, $enabled_types) && in_array($bundle, $access_control_types);
  }

  /**
   * Check workflow-based access for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check access for.
   * @param string $operation
   *   The operation (view, update, delete).
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(NodeInterface $node, string $operation, AccountInterface $account): AccessResultInterface {
    if (!$this->appliesTo($node)) {
      return AccessResult::neutral()
        ->addCacheableDependency($node)
        ->addCacheTags(['config:workflow_assignment.settings']);
    }

    $active_tasks = $this->participantResolver->getActiveWorkflowTasks($node);

    if (empty($active_tasks)) {
      return AccessResult::neutral()
        ->addCacheableDependency($node)
        ->addCacheTags($this->participantResolver->getAccessCacheTags($node));
    }

    $cache_tags = $this->participantResolver->getAccessCacheTags($node);
    $cache_tags[] = 'config:workflow_assignment.settings';

    // 1. Node author always has access.
    if ((int) $node->getOwnerId() === (int) $account->id()) {
      return AccessResult::allowed()
        ->addCacheableDependency($node)
        ->addCacheContexts(['user'])
        ->addCacheTags($cache_tags);
    }

    // 2. Workflow administrators have access.
    if ($account->hasPermission('administer workflow tasks')) {
      return AccessResult::allowed()
        ->addCacheableDependency($node)
        ->addCacheContexts(['user.permissions'])
        ->addCacheTags($cache_tags);
    }

    // 3. Current task assignee has access.
    $current_task = $this->participantResolver->getCurrentTask($node);
    if ($current_task && $this->participantResolver->isAssignedToTask($current_task, $account)) {
      return $this->getAllowedWithEditCheck($node, $operation, $account, $cache_tags);
    }

    // 4. Past participants have view access.
    $config = $this->configFactory->get('workflow_assignment.settings');
    $allow_past_view = $config->get('allow_past_participants_view') ?? TRUE;

    if ($operation === 'view' && $allow_past_view && $this->participantResolver->isParticipant($node, $account, FALSE)) {
      return AccessResult::allowed()
        ->addCacheableDependency($node)
        ->addCacheContexts(['user'])
        ->addCacheTags($cache_tags);
    }

    // 5. Not a participant - deny access during active workflow.
    return AccessResult::forbidden('Access restricted to workflow participants.')
      ->addCacheableDependency($node)
      ->addCacheContexts(['user'])
      ->addCacheTags($cache_tags);
  }

  /**
   * Return allowed access with edit permission check.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $operation
   *   The operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param array $cache_tags
   *   Cache tags.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function getAllowedWithEditCheck(NodeInterface $node, string $operation, AccountInterface $account, array $cache_tags): AccessResultInterface {
    if ($operation === 'view') {
      return AccessResult::allowed()
        ->addCacheableDependency($node)
        ->addCacheContexts(['user'])
        ->addCacheTags($cache_tags);
    }

    if ($operation === 'update') {
      $can_edit = $account->hasPermission('edit own workflow tasks')
        || $account->hasPermission('edit any avc assets');

      return $can_edit
        ? AccessResult::allowed()
            ->addCacheableDependency($node)
            ->addCacheContexts(['user', 'user.permissions'])
            ->addCacheTags($cache_tags)
        : AccessResult::neutral()
            ->addCacheableDependency($node)
            ->addCacheContexts(['user.permissions'])
            ->addCacheTags($cache_tags);
    }

    if ($operation === 'delete') {
      $config = $this->configFactory->get('workflow_assignment.settings');
      $restrict_delete = $config->get('restrict_delete_during_workflow') ?? TRUE;

      if ($restrict_delete) {
        return AccessResult::forbidden('Cannot delete content with active workflow.')
          ->addCacheableDependency($node)
          ->addCacheTags($cache_tags);
      }
    }

    return AccessResult::neutral();
  }

}
