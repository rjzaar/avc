<?php

namespace Drupal\avc_work_management\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\node\NodeInterface;

/**
 * Service for querying workflow tasks.
 */
class WorkTaskQueryService {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * The group membership loader.
   */
  protected ?GroupMembershipLoaderInterface $groupMembershipLoader;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a WorkTaskQueryService.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    ?GroupMembershipLoaderInterface $group_membership_loader,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->groupMembershipLoader = $group_membership_loader;
    $this->configFactory = $config_factory;
  }

  /**
   * Get tracked content types from config.
   */
  public function getTrackedContentTypes(): array {
    $config = $this->configFactory->get('avc_work_management.settings');
    return $config->get('tracked_content_types') ?? [];
  }

  /**
   * Get summary counts by content type.
   */
  public function getSummaryCounts(?AccountInterface $user = NULL): array {
    $user = $user ?? $this->currentUser;
    $types = $this->getTrackedContentTypes();
    $summary = [];

    foreach ($types as $type_id => $type_config) {
      $summary[$type_id] = [
        'label' => $type_config['label'],
        'icon' => $type_config['icon'],
        'color' => $type_config['color'],
        'active' => $this->countTasks($user, $type_id, 'in_progress', 'user'),
        'upcoming' => $this->countTasks($user, $type_id, 'pending', 'user'),
        'completed' => $this->countTasks($user, $type_id, 'completed', 'user'),
      ];
    }

    return $summary;
  }

  /**
   * Count tasks matching criteria.
   */
  public function countTasks(
    AccountInterface $user,
    ?string $content_type = NULL,
    ?string $status = NULL,
    string $assigned_to = 'user'
  ): int {
    $query = $this->buildBaseQuery($user, $assigned_to);

    if ($status) {
      $query->condition('status', $status);
    }

    if ($content_type) {
      $node_ids = $this->getNodeIdsByType($content_type);
      if (empty($node_ids)) {
        return 0;
      }
      $query->condition('node_id', $node_ids, 'IN');
    }

    return (int) $query->count()->execute();
  }

  /**
   * Get tasks for a section.
   */
  public function getTasksForSection(
    string $section,
    ?AccountInterface $user = NULL,
    ?int $limit = NULL
  ): array {
    $user = $user ?? $this->currentUser;
    $config = $this->configFactory->get('avc_work_management.settings');
    $section_config = $config->get('sections.' . $section);

    if (!$section_config) {
      return [];
    }

    $status = $section_config['status'] ?? NULL;
    $assigned_to = $section_config['assigned_to'] ?? 'user';
    $limit = $limit ?? ($section_config['limit'] ?? 10);

    return $this->getTasks($user, NULL, $status, $assigned_to, $limit);
  }

  /**
   * Get tasks matching criteria.
   */
  public function getTasks(
    AccountInterface $user,
    ?string $content_type = NULL,
    ?string $status = NULL,
    string $assigned_to = 'user',
    ?int $limit = NULL
  ): array {
    $query = $this->buildBaseQuery($user, $assigned_to);

    if ($status) {
      $query->condition('status', $status);
    }

    if ($content_type) {
      $node_ids = $this->getNodeIdsByType($content_type);
      if (empty($node_ids)) {
        return [];
      }
      $query->condition('node_id', $node_ids, 'IN');
    }

    // Sort by weight (priority), then due date.
    $query->sort('weight', 'ASC');

    if ($limit) {
      $query->range(0, $limit);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $tasks = $this->entityTypeManager->getStorage('workflow_task')->loadMultiple($ids);

    // Enrich with node data.
    return $this->enrichTasksWithNodeData($tasks);
  }

  /**
   * Build base query for tasks.
   */
  protected function buildBaseQuery(AccountInterface $user, string $assigned_to): object {
    $storage = $this->entityTypeManager->getStorage('workflow_task');
    $query = $storage->getQuery()->accessCheck(TRUE);

    if ($assigned_to === 'user') {
      $query->condition('assigned_type', 'user');
      $query->condition('assigned_user', $user->id());
    }
    elseif ($assigned_to === 'group') {
      $group_ids = $this->getUserGroupIds($user);
      if (empty($group_ids)) {
        // Return impossible condition if user has no groups.
        $query->condition('id', 0);
      }
      else {
        $query->condition('assigned_type', 'group');
        $query->condition('assigned_group', $group_ids, 'IN');
      }
    }

    return $query;
  }

  /**
   * Get IDs of groups user belongs to.
   */
  protected function getUserGroupIds(AccountInterface $user): array {
    if (!$this->groupMembershipLoader) {
      return [];
    }

    $memberships = $this->groupMembershipLoader->loadByUser($user);
    $group_ids = [];

    foreach ($memberships as $membership) {
      $group_ids[] = $membership->getGroup()->id();
    }

    return $group_ids;
  }

  /**
   * Get node IDs by content type.
   */
  protected function getNodeIdsByType(string $content_type): array {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $content_type);

    return $query->execute();
  }

  /**
   * Enrich tasks with related node data.
   */
  protected function enrichTasksWithNodeData(array $tasks): array {
    $enriched = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $types = $this->getTrackedContentTypes();

    foreach ($tasks as $task) {
      $node_id = $task->get('node_id')->target_id;
      $node = $node_id ? $node_storage->load($node_id) : NULL;

      $content_type = $node ? $node->bundle() : 'unknown';
      $type_config = $types[$content_type] ?? [
        'label' => 'Content',
        'icon' => 'file',
        'color' => '#999',
      ];

      $enriched[] = [
        'task' => $task,
        'task_id' => $task->id(),
        'node' => $node,
        'title' => $node ? $node->label() : $task->label(),
        'node_url' => $node ? $node->toUrl()->toString() : NULL,
        'content_type' => $content_type,
        'content_type_label' => $type_config['label'],
        'content_type_icon' => $type_config['icon'],
        'content_type_color' => $type_config['color'],
        'status' => $task->get('status')->value,
        'due_date' => $task->hasField('due_date') ? $task->get('due_date')->value : NULL,
        'assigned_type' => $task->get('assigned_type')->value,
        'assigned_label' => $this->getAssignedLabel($task),
        'completed_date' => $task->get('status')->value === 'completed'
          ? $task->getChangedTime()
          : NULL,
      ];
    }

    return $enriched;
  }

  /**
   * Get human-readable assigned label.
   */
  protected function getAssignedLabel($task): string {
    $type = $task->get('assigned_type')->value;

    switch ($type) {
      case 'user':
        $user_id = $task->get('assigned_user')->target_id;
        $user = $user_id
          ? $this->entityTypeManager->getStorage('user')->load($user_id)
          : NULL;
        return $user ? $user->getDisplayName() : 'Unknown user';

      case 'group':
        $group_id = $task->get('assigned_group')->target_id;
        $group = $group_id
          ? $this->entityTypeManager->getStorage('group')->load($group_id)
          : NULL;
        return $group ? $group->label() : 'Unknown group';

      case 'destination':
        $term_id = $task->get('assigned_destination')->target_id;
        $term = $term_id
          ? $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id)
          : NULL;
        return $term ? $term->label() : 'Unknown destination';
    }

    return 'Unassigned';
  }

  /**
   * Get cache tags for the dashboard.
   */
  public function getDashboardCacheTags(AccountInterface $user): array {
    $tags = [
      'user:' . $user->id(),
      'workflow_task_list',
    ];

    // Add group tags.
    foreach ($this->getUserGroupIds($user) as $group_id) {
      $tags[] = 'group:' . $group_id;
    }

    return $tags;
  }

}
