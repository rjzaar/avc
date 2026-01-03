<?php

namespace Drupal\avc_group\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for group workflow dashboard.
 */
class GroupWorkflowController extends ControllerBase {

  /**
   * The group workflow service.
   *
   * @var \Drupal\avc_group\Service\GroupWorkflowService
   */
  protected $workflowService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->workflowService = $container->get('avc_group.workflow');
    return $instance;
  }

  /**
   * Displays the group workflow dashboard.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return array
   *   A render array for the dashboard.
   */
  public function dashboard(GroupInterface $group) {
    $current_user = $this->currentUser();
    $is_manager = $this->workflowService->isGroupManager($group, $current_user);

    // Get workflow assignments for this group.
    $assignments = $this->workflowService->getGroupAssignments($group);

    // Categorize assignments by status.
    $categorized = [
      'current' => [],
      'upcoming' => [],
      'completed' => [],
    ];

    foreach ($assignments as $assignment) {
      $status = $assignment['status'] ?? 'upcoming';
      $categorized[$status][] = $assignment;
    }

    // Get group members for assignment.
    $members = $this->workflowService->getGroupMembers($group);

    $build = [
      '#theme' => 'avc_group_workflow_dashboard',
      '#group' => $group,
      '#is_manager' => $is_manager,
      '#current_assignments' => $categorized['current'],
      '#upcoming_assignments' => $categorized['upcoming'],
      '#completed_assignments' => $categorized['completed'],
      '#members' => $members,
      '#attached' => [
        'library' => ['avc_group/dashboard'],
      ],
    ];

    return $build;
  }

  /**
   * Displays form to add a workflow assignment.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return array
   *   A render array with the form.
   */
  public function addAssignment(GroupInterface $group) {
    $form = $this->formBuilder()->getForm(
      'Drupal\avc_group\Form\GroupAssignmentForm',
      $group
    );

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['avc-group-add-assignment']],
      'form' => $form,
    ];
  }

  /**
   * Access callback for adding assignments.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function addAssignmentAccess(GroupInterface $group, AccountInterface $account) {
    // Check if user is a group manager or admin.
    $membership = $group->getMember($account);
    if (!$membership) {
      return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($group);
    }

    // Check for group admin role or assign permission.
    $roles = $membership->getRoles();
    foreach ($roles as $role) {
      if ($role->hasPermission('assign workflow lists to content') ||
          $role->hasPermission('administer group')) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($group);
      }
    }

    return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($group);
  }

  /**
   * Access callback for workflow settings.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function settingsAccess(GroupInterface $group, AccountInterface $account) {
    $membership = $group->getMember($account);
    if (!$membership) {
      return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($group);
    }

    // Only group admins can access settings.
    $roles = $membership->getRoles();
    foreach ($roles as $role) {
      if ($role->hasPermission('administer group')) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($group);
      }
    }

    return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($group);
  }

}
