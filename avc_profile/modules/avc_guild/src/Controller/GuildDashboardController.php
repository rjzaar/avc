<?php

namespace Drupal\avc_guild\Controller;

use Drupal\avc_guild\Service\GuildService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for guild dashboard pages.
 */
class GuildDashboardController extends ControllerBase {

  /**
   * The guild service.
   *
   * @var \Drupal\avc_guild\Service\GuildService
   */
  protected $guildService;

  /**
   * Constructs a GuildDashboardController.
   *
   * @param \Drupal\avc_guild\Service\GuildService $guild_service
   *   The guild service.
   */
  public function __construct(GuildService $guild_service) {
    $this->guildService = $guild_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_guild.service')
    );
  }

  /**
   * Guild dashboard page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return array
   *   Render array.
   */
  public function dashboard(GroupInterface $group) {
    $data = $this->guildService->getGuildDashboard($group);

    return [
      '#theme' => 'guild_dashboard',
      '#guild' => $data['guild'],
      '#members' => $data['members'],
      '#leaderboard' => $data['leaderboard'],
      '#ratification_queue' => $data['pending_ratifications'],
      '#recent_activity' => [],
      '#cache' => [
        'tags' => $group->getCacheTags(),
      ],
    ];
  }

  /**
   * Guild dashboard title callback.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return string
   *   The page title.
   */
  public function dashboardTitle(GroupInterface $group) {
    return $this->t('@guild Dashboard', ['@guild' => $group->label()]);
  }

  /**
   * Member profile page in guild context.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return array
   *   Render array.
   */
  public function memberProfile(GroupInterface $group, UserInterface $user) {
    $data = $this->guildService->getMemberProfile($user, $group);

    return [
      '#theme' => 'guild_member_profile',
      '#user' => $data['user'],
      '#guild' => $data['guild'],
      '#role' => $data['role'],
      '#score' => $data['score'],
      '#skills' => $data['endorsements'],
      '#endorsements' => $data['endorsements'],
      '#cache' => [
        'tags' => array_merge($group->getCacheTags(), $user->getCacheTags()),
      ],
    ];
  }

  /**
   * Member profile title callback.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return string
   *   The page title.
   */
  public function memberProfileTitle(GroupInterface $group, UserInterface $user) {
    return $this->t('@user in @guild', [
      '@user' => $user->getDisplayName(),
      '@guild' => $group->label(),
    ]);
  }

  /**
   * Leaderboard page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return array
   *   Render array.
   */
  public function leaderboard(GroupInterface $group) {
    $leaderboard = $this->guildService->getScoringService()->getLeaderboard($group, 50);

    return [
      '#theme' => 'guild_leaderboard',
      '#guild' => $group,
      '#entries' => $leaderboard,
      '#limit' => 50,
      '#cache' => [
        'tags' => $group->getCacheTags(),
      ],
    ];
  }

  /**
   * Access check for guild pages.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(GroupInterface $group, AccountInterface $account) {
    // Must be a guild.
    if (!avc_guild_is_guild($group)) {
      return AccessResult::forbidden('Not a guild.');
    }

    // Must be a member or have admin permission.
    if ($group->getMember($account) || $account->hasPermission('administer guilds')) {
      return AccessResult::allowed()->addCacheableDependency($group);
    }

    return AccessResult::forbidden()->addCacheableDependency($group);
  }

  /**
   * Access check for member profile.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param \Drupal\user\UserInterface $user
   *   The user being viewed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function memberAccess(GroupInterface $group, UserInterface $user, AccountInterface $account) {
    // Must be a guild.
    if (!avc_guild_is_guild($group)) {
      return AccessResult::forbidden('Not a guild.');
    }

    // Target user must be a member.
    if (!$group->getMember($user)) {
      return AccessResult::forbidden('User is not a guild member.');
    }

    // Current user must be a member or admin.
    if ($group->getMember($account) || $account->hasPermission('administer guilds')) {
      return AccessResult::allowed()
        ->addCacheableDependency($group)
        ->addCacheableDependency($user);
    }

    return AccessResult::forbidden()
      ->addCacheableDependency($group)
      ->addCacheableDependency($user);
  }

}
