<?php

namespace Drupal\avc_guild\Controller;

use Drupal\avc_guild\Service\SkillConfigurationService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for skill administration pages.
 */
class SkillAdminController extends ControllerBase {

  /**
   * The skill configuration service.
   *
   * @var \Drupal\avc_guild\Service\SkillConfigurationService
   */
  protected SkillConfigurationService $skillConfigService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->skillConfigService = $container->get('avc_guild.skill_configuration');
    return $instance;
  }

  /**
   * Lists all skills for a guild with configuration options.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return array
   *   Render array.
   */
  public function skillsList(GroupInterface $group): array {
    $build = [];

    $build['title'] = [
      '#markup' => '<h1>' . $this->t('Skill Level Configuration for @guild', ['@guild' => $group->label()]) . '</h1>',
    ];

    // Get all guild skills.
    $vocabulary = 'guild_skills';
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree($vocabulary);

    if (empty($terms)) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('No skills found. Please create skills in the Guild Skills vocabulary first.') . '</p>',
      ];
      return $build;
    }

    // Get existing level configurations.
    $configured_skills = $this->skillConfigService->getGuildSkillLevels($group);

    $rows = [];
    foreach ($terms as $term) {
      $skill_id = $term->tid;
      $skill_name = $term->name;

      $has_config = isset($configured_skills[$skill_id]);
      $level_count = $has_config ? count($configured_skills[$skill_id]) : 0;

      $configure_url = Url::fromRoute('avc_guild.skill_configure', [
        'group' => $group->id(),
        'skill' => $skill_id,
      ]);

      $rows[] = [
        'skill' => $skill_name,
        'levels' => $level_count > 0 ? $this->t('@count levels configured', ['@count' => $level_count]) : $this->t('Not configured'),
        'operations' => [
          'data' => [
            '#type' => 'link',
            '#title' => $has_config ? $this->t('Edit Levels') : $this->t('Configure Levels'),
            '#url' => $configure_url,
            '#attributes' => ['class' => ['button', 'button--small']],
          ],
        ],
      ];
    }

    $build['skills_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Skill'),
        $this->t('Configuration Status'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No skills available.'),
    ];

    return $build;
  }

  /**
   * Access check for skill admin pages.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(GroupInterface $group) {
    // Must be a guild, not a regular group.
    if (!avc_guild_is_guild($group)) {
      return \Drupal\Core\Access\AccessResult::forbidden('Not a guild.');
    }

    $account = $this->currentUser();

    // Check if user is a guild admin.
    if ($group->getMember($account)) {
      $membership = $group->getMember($account);
      $roles = $membership->getRoles();

      foreach ($roles as $role) {
        if (strpos($role->id(), 'guild-admin') !== FALSE) {
          return \Drupal\Core\Access\AccessResult::allowed();
        }
      }
    }

    // Also allow site admins.
    if ($account->hasPermission('administer guilds')) {
      return \Drupal\Core\Access\AccessResult::allowed();
    }

    return \Drupal\Core\Access\AccessResult::forbidden();
  }

  /**
   * Title callback for skill admin pages.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return string
   *   The page title.
   */
  public function skillsTitle(GroupInterface $group): string {
    return $this->t('Skill Administration - @guild', ['@guild' => $group->label()]);
  }

}
