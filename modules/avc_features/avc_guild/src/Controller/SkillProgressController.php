<?php

namespace Drupal\avc_guild\Controller;

use Drupal\avc_guild\Service\SkillProgressionService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for skill progress pages.
 */
class SkillProgressController extends ControllerBase {

  /**
   * The skill progression service.
   *
   * @var \Drupal\avc_guild\Service\SkillProgressionService
   */
  protected SkillProgressionService $skillProgressionService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->skillProgressionService = $container->get('avc_guild.skill_progression');
    return $instance;
  }

  /**
   * Displays the current user's skill progress.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return array
   *   Render array.
   */
  public function mySkills(GroupInterface $group): array {
    $user = \Drupal::currentUser();
    return $this->buildSkillProfile($group, $user);
  }

  /**
   * Displays another member's skill progress.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to view.
   *
   * @return array
   *   Render array.
   */
  public function memberSkills(GroupInterface $group, AccountInterface $user): array {
    return $this->buildSkillProfile($group, $user);
  }

  /**
   * Builds skill profile render array.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   *
   * @return array
   *   Render array.
   */
  protected function buildSkillProfile(GroupInterface $group, AccountInterface $user): array {
    $build = [];

    $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($user->id());

    $build['#theme'] = 'skill_progress_dashboard';
    $build['#group'] = [
      'id' => $group->id(),
      'label' => $group->label(),
    ];
    $build['#user'] = [
      'id' => $user_entity->id(),
      'displayname' => $user_entity->getDisplayName(),
    ];

    // Get skill profile.
    $profile = $this->skillProgressionService->getSkillProfile($user_entity, $group);

    if (empty($profile)) {
      $build['#skills'] = [];
      $build['#empty_message'] = $this->t('No skill progress to display.');
      return $build;
    }

    // Format skills for template.
    $skills_data = [];
    foreach ($profile as $skill_id => $data) {
      $progress_percentage = 0;
      if ($data['credits_required'] && $data['credits_required'] > 0) {
        $progress_percentage = min(100, ($data['credits'] / $data['credits_required']) * 100);
      }

      $days_percentage = 0;
      if ($data['days_required'] && $data['days_required'] > 0) {
        $days_percentage = min(100, ($data['days_at_level'] / $data['days_required']) * 100);
      }

      $skill_entity = $data['skill'];
      $skills_data[] = [
        'skill' => [
          'id' => $skill_entity->id(),
          'name' => $skill_entity->label(),
        ],
        'level' => $data['level'],
        'level_name' => $data['level_name'],
        'credits' => $data['credits'],
        'credits_required' => $data['credits_required'],
        'progress_percentage' => $progress_percentage,
        'days_at_level' => $data['days_at_level'],
        'days_required' => $data['days_required'],
        'days_percentage' => $days_percentage,
        'pending_verification' => $data['pending_verification'],
        'next_level' => $data['next_level'],
        'next_level_name' => $data['next_level_name'],
      ];
    }

    $build['#skills'] = $skills_data;

    // Get recent credit history.
    $credit_history_entities = $this->skillProgressionService->getCreditHistory($user_entity, $group, NULL, 20);

    // Convert credit entities to simple arrays.
    $credit_history = [];
    foreach ($credit_history_entities as $credit) {
      $skill = $credit->getSkill();
      $credit_history[] = [
        'created_time' => $credit->getCreatedTime(),
        'skill_name' => $skill ? $skill->label() : '-',
        'credits' => $credit->getCredits(),
        'source_type' => $credit->getSourceType(),
        'notes' => $credit->getNotes() ?: '-',
      ];
    }

    $build['#credit_history'] = $credit_history;

    return $build;
  }

  /**
   * Title callback for member skills page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   *
   * @return string
   *   The page title.
   */
  public function memberSkillsTitle(GroupInterface $group, AccountInterface $user): string {
    $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($user->id());
    return $this->t('Skills: @user', ['@user' => $user_entity ? $user_entity->getDisplayName() : 'Unknown']);
  }

}
