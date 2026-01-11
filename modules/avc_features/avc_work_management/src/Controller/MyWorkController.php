<?php

namespace Drupal\avc_work_management\Controller;

use Drupal\avc_work_management\Service\WorkTaskQueryService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for My Work dashboard.
 */
class MyWorkController extends ControllerBase {

  protected WorkTaskQueryService $taskQuery;

  /**
   * Constructs a MyWorkController.
   */
  public function __construct(WorkTaskQueryService $task_query) {
    $this->taskQuery = $task_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_work_management.task_query')
    );
  }

  /**
   * Render the My Work dashboard.
   */
  public function dashboard(): array {
    $user = $this->currentUser();
    $config = $this->config('avc_work_management.settings');

    // Get summary counts by content type.
    $summary = $this->taskQuery->getSummaryCounts($user);

    // Get tasks for each section.
    $sections = [];
    $section_config = $config->get('sections') ?? [];

    foreach ($section_config as $section_id => $section_settings) {
      $tasks = $this->taskQuery->getTasksForSection($section_id, $user);
      $total = $this->taskQuery->countTasks(
        $user,
        NULL,
        $section_settings['status'] ?? NULL,
        $section_settings['assigned_to'] ?? 'user'
      );

      $sections[$section_id] = [
        'id' => $section_id,
        'label' => $section_settings['label'],
        'tasks' => $tasks,
        'total' => $total,
        'limit' => $section_settings['limit'] ?? 10,
        'show_view_all' => ($section_settings['show_view_all'] ?? FALSE) && $total > count($tasks),
        'show_claim' => $section_settings['show_claim'] ?? FALSE,
        'view_all_url' => '/my-work/' . $section_id,
      ];
    }

    // Calculate totals for available section.
    $available_count = $this->taskQuery->countTasks($user, NULL, 'pending', 'group');

    return [
      '#theme' => 'my_work_dashboard',
      '#summary' => $summary,
      '#sections' => $sections,
      '#available_count' => $available_count,
      '#user' => $user,
      '#attached' => [
        'library' => ['avc_work_management/dashboard'],
      ],
      '#cache' => [
        'tags' => $this->taskQuery->getDashboardCacheTags($user),
        'contexts' => ['user'],
        'max-age' => 300, // 5 minutes.
      ],
    ];
  }

  /**
   * Render a specific section (View All page).
   */
  public function section(string $section): array {
    $user = $this->currentUser();
    $config = $this->config('avc_work_management.settings');
    $section_config = $config->get('sections.' . $section);

    if (!$section_config) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Get all tasks for this section (no limit).
    $tasks = $this->taskQuery->getTasks(
      $user,
      NULL,
      $section_config['status'] ?? NULL,
      $section_config['assigned_to'] ?? 'user',
      NULL // No limit
    );

    return [
      '#theme' => 'my_work_section',
      '#section' => [
        'id' => $section,
        'label' => $section_config['label'],
        'tasks' => $tasks,
        'show_claim' => $section_config['show_claim'] ?? FALSE,
      ],
      '#attached' => [
        'library' => ['avc_work_management/dashboard'],
      ],
      '#cache' => [
        'tags' => $this->taskQuery->getDashboardCacheTags($user),
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Title callback for section pages.
   */
  public function sectionTitle(string $section): string {
    $config = $this->config('avc_work_management.settings');
    $label = $config->get('sections.' . $section . '.label') ?? 'Tasks';
    return 'My Work: ' . $label;
  }

}
