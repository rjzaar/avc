<?php

namespace Drupal\avc_guild\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Controller for guild resources overview page.
 */
class GuildResourcesController extends ControllerBase {

  /**
   * Guild resources overview page.
   *
   * @return array
   *   Render array.
   */
  public function overview() {
    $build = [];

    $build['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('This page provides an overview of all guild features and sample navigation links. Use these to explore guilds, skills tracking, endorsements, and leaderboards.'),
    ];

    // Get sample groups for links.
    $group_storage = $this->entityTypeManager()->getStorage('group');
    $group_ids = $group_storage->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 6)
      ->execute();
    $groups = $group_storage->loadMultiple($group_ids);

    // Guild Dashboard & Overview.
    $build['dashboard_section'] = [
      '#type' => 'details',
      '#title' => $this->t('🎯 Guild Dashboard & Overview'),
      '#open' => TRUE,
    ];

    $dashboard_items = [];
    foreach ($groups as $group) {
      $dashboard_items[] = Link::fromTextAndUrl(
        $this->t('@group Guild Dashboard', ['@group' => $group->label()]),
        Url::fromRoute('avc_guild.dashboard', ['group' => $group->id()])
      );
    }
    $build['dashboard_section']['list'] = [
      '#theme' => 'item_list',
      '#items' => $dashboard_items,
    ];

    // Leaderboard.
    $build['leaderboard_section'] = [
      '#type' => 'details',
      '#title' => $this->t('🏆 Leaderboard Pages'),
      '#open' => TRUE,
    ];

    $leaderboard_items = [];
    foreach ($groups as $group) {
      $leaderboard_items[] = Link::fromTextAndUrl(
        $this->t('@group Leaderboard', ['@group' => $group->label()]),
        Url::fromRoute('avc_guild.leaderboard', ['group' => $group->id()])
      );
    }
    $build['leaderboard_section']['list'] = [
      '#theme' => 'item_list',
      '#items' => $leaderboard_items,
    ];

    // Member Skill Progress.
    $build['skills_section'] = [
      '#type' => 'details',
      '#title' => $this->t('📊 Member Skill Progress'),
      '#open' => TRUE,
    ];

    $skills_items = [];
    foreach ($groups as $group) {
      $skills_items[] = Link::fromTextAndUrl(
        $this->t('My Skills - @group', ['@group' => $group->label()]),
        Url::fromRoute('avc_guild.my_skills', ['group' => $group->id()])
      );
    }

    // Get sample users with skill progress.
    $progress_storage = $this->entityTypeManager()->getStorage('member_skill_progress');
    $progress_ids = $progress_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('current_level', 0, '>')
      ->range(0, 3)
      ->execute();

    if ($progress_ids) {
      $progress_records = $progress_storage->loadMultiple($progress_ids);
      foreach ($progress_records as $progress) {
        $user = $progress->get('user_id')->entity;
        $group = $progress->get('guild_id')->entity;
        if ($user && $group) {
          $skills_items[] = Link::fromTextAndUrl(
            $this->t("@user's Skills (@group)", [
              '@user' => $user->getDisplayName(),
              '@group' => $group->label(),
            ]),
            Url::fromRoute('avc_guild.member_skills', [
              'group' => $group->id(),
              'user' => $user->id(),
            ])
          );
        }
      }
    }

    $build['skills_section']['list'] = [
      '#theme' => 'item_list',
      '#items' => $skills_items,
    ];

    // Member Profiles.
    $build['profiles_section'] = [
      '#type' => 'details',
      '#title' => $this->t('👤 Member Profile Pages'),
    ];

    $profile_items = [];
    foreach ($groups as $group) {
      // Get first 2 members of each group.
      $members = $group->getMembers();
      $count = 0;
      foreach ($members as $membership) {
        if ($count >= 2) {
          break;
        }
        $user = $membership->getUser();
        $profile_items[] = Link::fromTextAndUrl(
          $this->t('@user Profile (@group)', [
            '@user' => $user->getDisplayName(),
            '@group' => $group->label(),
          ]),
          Url::fromRoute('avc_guild.member_profile', [
            'group' => $group->id(),
            'user' => $user->id(),
          ])
        );
        $count++;
      }
    }
    $build['profiles_section']['list'] = [
      '#theme' => 'item_list',
      '#items' => $profile_items,
    ];

    // Verification & Voting.
    $build['verification_section'] = [
      '#type' => 'details',
      '#title' => $this->t('✅ Verification & Voting'),
    ];

    $verification_items = [];
    foreach ($groups as $group) {
      $verification_items[] = Link::fromTextAndUrl(
        $this->t('@group Pending Verifications', ['@group' => $group->label()]),
        Url::fromRoute('avc_guild.verification_queue', ['group' => $group->id()])
      );
    }
    $build['verification_section']['list'] = [
      '#theme' => 'item_list',
      '#items' => $verification_items,
    ];

    // Admin Pages.
    $build['admin_section'] = [
      '#type' => 'details',
      '#title' => $this->t('⚙️ Admin Pages (Requires Admin Permissions)'),
    ];

    $admin_items = [];
    foreach ($groups as $group) {
      $admin_items[] = Link::fromTextAndUrl(
        $this->t('@group Skills Administration', ['@group' => $group->label()]),
        Url::fromRoute('avc_guild.skill_admin', ['group' => $group->id()])
      );
      $admin_items[] = Link::fromTextAndUrl(
        $this->t('@group Skills Analytics Report', ['@group' => $group->label()]),
        Url::fromRoute('avc_guild.skills_report', ['group' => $group->id()])
      );
    }
    $admin_items[] = Link::fromTextAndUrl(
      $this->t('Global Guild Settings'),
      Url::fromRoute('avc_guild.settings')
    );

    $build['admin_section']['list'] = [
      '#theme' => 'item_list',
      '#items' => $admin_items,
    ];

    // Content Statistics.
    $build['stats_section'] = [
      '#type' => 'details',
      '#title' => $this->t('📈 Sample Content Statistics'),
    ];

    $connection = \Drupal::database();
    $stats = [];
    $stats[] = $this->t('Groups: @count', [
      '@count' => $connection->query('SELECT COUNT(*) FROM {groups_field_data}')->fetchField(),
    ]);
    $stats[] = $this->t('Skill Levels: @count', [
      '@count' => $connection->query('SELECT COUNT(*) FROM {skill_level}')->fetchField(),
    ]);
    $stats[] = $this->t('Member Skill Progress Records: @count', [
      '@count' => $connection->query('SELECT COUNT(*) FROM {member_skill_progress}')->fetchField(),
    ]);
    $stats[] = $this->t('Skill Credits: @count', [
      '@count' => $connection->query('SELECT COUNT(*) FROM {skill_credit}')->fetchField(),
    ]);
    $stats[] = $this->t('Skill Endorsements: @count', [
      '@count' => $connection->query('SELECT COUNT(*) FROM {skill_endorsement}')->fetchField(),
    ]);
    $stats[] = $this->t('Guild Scores: @count', [
      '@count' => $connection->query('SELECT COUNT(*) FROM {guild_score}')->fetchField(),
    ]);

    $build['stats_section']['list'] = [
      '#theme' => 'item_list',
      '#items' => $stats,
    ];

    return $build;
  }

}
