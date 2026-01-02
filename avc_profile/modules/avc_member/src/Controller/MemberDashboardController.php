<?php

namespace Drupal\avc_member\Controller;

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
