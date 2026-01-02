<?php

namespace Drupal\avc_member\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\avc_member\Service\MemberWorklistService;

/**
 * Controller for member dashboard.
 */
class MemberDashboardController extends ControllerBase {

  /**
   * The worklist service.
   *
   * @var \Drupal\avc_member\Service\MemberWorklistService
   */
  protected $worklistService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a MemberDashboardController object.
   *
   * @param \Drupal\avc_member\Service\MemberWorklistService $worklist_service
   *   The worklist service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(MemberWorklistService $worklist_service, AccountInterface $current_user) {
    $this->worklistService = $worklist_service;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_member.worklist_service'),
      $container->get('current_user')
    );
  }

  /**
   * Access check for the member dashboard.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose dashboard is being viewed.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(UserInterface $user) {
    $current_user = $this->currentUser;

    // Anonymous users cannot access.
    if ($current_user->isAnonymous()) {
      return AccessResult::forbidden()->cachePerUser();
    }

    // Allow users to view their own dashboard.
    // Note: Use == for comparison to handle both integer and string UIDs.
    if ($current_user->id() == $user->id()) {
      return AccessResult::allowed()->cachePerUser();
    }

    // Allow admins to view any dashboard.
    if ($current_user->hasPermission('administer users')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return AccessResult::forbidden()->cachePerUser();
  }

  /**
   * Displays the member dashboard.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return array
   *   A render array.
   */
  public function dashboard(UserInterface $user) {
    // Get personal worklist (assets where user is in workflow).
    $worklist = $this->worklistService->getUserWorklist($user);

    // Get group worklists (assets assigned to groups user belongs to).
    $group_worklists = $this->worklistService->getUserGroupWorklists($user);

    // Get notification settings.
    $notification_settings = $this->worklistService->getUserNotificationSettings($user);

    return [
      '#theme' => 'member_dashboard',
      '#user' => $user,
      '#worklist' => $worklist,
      '#group_worklists' => $group_worklists,
      '#notification_settings' => $notification_settings,
      '#attached' => [
        'library' => [
          'avc_member/dashboard',
        ],
      ],
      '#cache' => [
        'tags' => ['user:' . $user->id()],
        'contexts' => ['user'],
      ],
    ];
  }

}
