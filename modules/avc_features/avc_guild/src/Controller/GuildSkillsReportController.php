<?php

namespace Drupal\avc_guild\Controller;

use Drupal\avc_guild\Service\SkillConfigurationService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for guild skills analytics and reporting.
 */
class GuildSkillsReportController extends ControllerBase {

  /**
   * The skill configuration service.
   *
   * @var \Drupal\avc_guild\Service\SkillConfigurationService
   */
  protected SkillConfigurationService $skillConfigService;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->skillConfigService = $container->get('avc_guild.skill_configuration');
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * Displays skills analytics and reporting for a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return array
   *   Render array.
   */
  public function report(GroupInterface $group): array {
    $build = [];

    $build['title'] = [
      '#markup' => '<h1>' . $this->t('Skills Analytics: @guild', ['@guild' => $group->label()]) . '</h1>',
    ];

    // Get skill distribution.
    $skill_distribution = $this->getSkillDistribution($group);

    if (!empty($skill_distribution)) {
      $build['distribution'] = [
        '#type' => 'details',
        '#title' => $this->t('Skill Level Distribution'),
        '#open' => TRUE,
      ];

      foreach ($skill_distribution as $skill_id => $data) {
        $build['distribution']['skill_' . $skill_id] = [
          '#type' => 'fieldset',
          '#title' => $data['skill_name'],
        ];

        $rows = [];
        foreach ($data['levels'] as $level => $count) {
          $rows[] = [
            $this->t('Level @level', ['@level' => $level]),
            $count . ' ' . $this->formatPlural($count, 'member', 'members'),
          ];
        }

        $build['distribution']['skill_' . $skill_id]['table'] = [
          '#type' => 'table',
          '#header' => [$this->t('Level'), $this->t('Members')],
          '#rows' => $rows,
          '#empty' => $this->t('No members at any level.'),
        ];
      }
    }
    else {
      $build['distribution'] = [
        '#markup' => '<p>' . $this->t('No skill progress data available.') . '</p>',
      ];
    }

    // Recent level advancements.
    $recent_advancements = $this->getRecentAdvancements($group, 10);

    if (!empty($recent_advancements)) {
      $build['recent'] = [
        '#type' => 'details',
        '#title' => $this->t('Recent Level Advancements'),
        '#open' => TRUE,
      ];

      $rows = [];
      foreach ($recent_advancements as $advancement) {
        $rows[] = [
          $advancement['user_name'],
          $advancement['skill_name'],
          $this->t('Level @level', ['@level' => $advancement['level']]),
          date('Y-m-d', $advancement['achieved_date']),
        ];
      }

      $build['recent']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Member'),
          $this->t('Skill'),
          $this->t('Level'),
          $this->t('Date'),
        ],
        '#rows' => $rows,
      ];
    }

    // Pending verifications count.
    $pending_count = $this->getPendingVerificationsCount($group);

    $build['pending'] = [
      '#type' => 'item',
      '#title' => $this->t('Pending Verifications'),
      '#markup' => '<strong>' . $pending_count . '</strong> ' . $this->formatPlural($pending_count, 'verification pending', 'verifications pending'),
    ];

    if ($pending_count > 0) {
      $build['pending']['#markup'] .= ' - ' . \Drupal\Core\Link::createFromRoute(
        $this->t('View Queue'),
        'avc_guild.verification_queue',
        ['group' => $group->id()]
      )->toString();
    }

    // Top skill credit earners.
    $top_earners = $this->getTopCreditEarners($group, 10);

    if (!empty($top_earners)) {
      $build['top_earners'] = [
        '#type' => 'details',
        '#title' => $this->t('Top Skill Credit Earners (Last 30 Days)'),
        '#open' => FALSE,
      ];

      $rows = [];
      foreach ($top_earners as $earner) {
        $rows[] = [
          $earner['user_name'],
          $earner['total_credits'] . ' ' . $this->t('credits'),
        ];
      }

      $build['top_earners']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Member'),
          $this->t('Total Credits'),
        ],
        '#rows' => $rows,
      ];
    }

    return $build;
  }

  /**
   * Gets skill level distribution for a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return array
   *   Array of skill distributions.
   */
  protected function getSkillDistribution(GroupInterface $group): array {
    $distribution = [];

    $query = $this->database->select('member_skill_progress', 'msp')
      ->fields('msp', ['skill_id', 'current_level'])
      ->condition('guild_id', $group->id())
      ->condition('current_level', 0, '>')
      ->execute();

    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $skill_counts = [];

    foreach ($query as $row) {
      $skill_id = $row->skill_id;
      $level = $row->current_level;

      if (!isset($skill_counts[$skill_id])) {
        $skill_counts[$skill_id] = [];
      }

      if (!isset($skill_counts[$skill_id][$level])) {
        $skill_counts[$skill_id][$level] = 0;
      }

      $skill_counts[$skill_id][$level]++;
    }

    foreach ($skill_counts as $skill_id => $levels) {
      $skill = $term_storage->load($skill_id);
      if ($skill) {
        $distribution[$skill_id] = [
          'skill_name' => $skill->label(),
          'levels' => $levels,
        ];
      }
    }

    return $distribution;
  }

  /**
   * Gets recent level advancements.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param int $limit
   *   Number of results to return.
   *
   * @return array
   *   Array of recent advancements.
   */
  protected function getRecentAdvancements(GroupInterface $group, int $limit = 10): array {
    $advancements = [];

    $query = $this->database->select('member_skill_progress', 'msp')
      ->fields('msp', ['user_id', 'skill_id', 'current_level', 'level_achieved_date'])
      ->condition('guild_id', $group->id())
      ->condition('current_level', 0, '>')
      ->orderBy('level_achieved_date', 'DESC')
      ->range(0, $limit)
      ->execute();

    $user_storage = $this->entityTypeManager()->getStorage('user');
    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');

    foreach ($query as $row) {
      $user = $user_storage->load($row->user_id);
      $skill = $term_storage->load($row->skill_id);

      if ($user && $skill) {
        $advancements[] = [
          'user_name' => $user->getDisplayName(),
          'skill_name' => $skill->label(),
          'level' => $row->current_level,
          'achieved_date' => $row->level_achieved_date,
        ];
      }
    }

    return $advancements;
  }

  /**
   * Gets count of pending verifications.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return int
   *   Count of pending verifications.
   */
  protected function getPendingVerificationsCount(GroupInterface $group): int {
    return (int) $this->entityTypeManager()
      ->getStorage('level_verification')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('guild_id', $group->id())
      ->condition('status', 'pending')
      ->count()
      ->execute();
  }

  /**
   * Gets top skill credit earners.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param int $limit
   *   Number of results.
   *
   * @return array
   *   Array of top earners.
   */
  protected function getTopCreditEarners(GroupInterface $group, int $limit = 10): array {
    $earners = [];

    // Get credits from the last 30 days.
    $thirty_days_ago = strtotime('-30 days');

    $query = $this->database->select('skill_credit', 'sc')
      ->fields('sc', ['user_id'])
      ->condition('guild_id', $group->id())
      ->condition('created', $thirty_days_ago, '>=');

    $query->addExpression('SUM(credits)', 'total_credits');
    $query->groupBy('user_id');
    $query->orderBy('total_credits', 'DESC');
    $query->range(0, $limit);

    $result = $query->execute();

    $user_storage = $this->entityTypeManager()->getStorage('user');

    foreach ($result as $row) {
      $user = $user_storage->load($row->user_id);
      if ($user) {
        $earners[] = [
          'user_name' => $user->getDisplayName(),
          'total_credits' => $row->total_credits,
        ];
      }
    }

    return $earners;
  }

}
