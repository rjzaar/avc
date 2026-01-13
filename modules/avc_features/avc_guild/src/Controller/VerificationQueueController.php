<?php

namespace Drupal\avc_guild\Controller;

use Drupal\avc_guild\Entity\LevelVerification;
use Drupal\avc_guild\Service\SkillProgressionService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for verification queue pages.
 */
class VerificationQueueController extends ControllerBase {

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
   * Displays the verification queue for a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return array
   *   Render array.
   */
  public function queue(GroupInterface $group): array {
    $build = [];
    $current_user_account = $this->currentUser();
    $current_user = $this->entityTypeManager()->getStorage('user')->load($current_user_account->id());

    $build['title'] = [
      '#markup' => '<h1>' . $this->t('Pending Verifications') . '</h1>',
    ];

    // Get pending verifications that this user can vote on.
    $verifications = $this->skillProgressionService->getPendingVerifications($current_user, $group);

    if (empty($verifications)) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('No pending verifications requiring your attention.') . '</p>',
      ];
      return $build;
    }

    $rows = [];
    foreach ($verifications as $verification) {
      $candidate = $verification->getUser();
      $skill = $verification->getSkill();
      $target_level = $verification->getTargetLevel();

      // Check if user has already voted.
      $has_voted = $this->skillProgressionService->hasVoted($verification, $current_user);

      $vote_url = Url::fromRoute('avc_guild.verification_vote', [
        'group' => $group->id(),
        'level_verification' => $verification->id(),
      ]);

      $rows[] = [
        'candidate' => $candidate ? $candidate->getDisplayName() : '-',
        'skill' => $skill ? $skill->label() : '-',
        'level' => (string) $target_level,
        'type' => $this->formatVerificationType($verification->get('verification_type')->value),
        'votes' => $this->t('@approve / @required', [
          '@approve' => $verification->getApproveVotes(),
          '@required' => $verification->getVotesRequired(),
        ]),
        'created' => date('Y-m-d', $verification->get('created')->value),
        'operations' => [
          'data' => [
            '#type' => 'link',
            '#title' => $has_voted ? $this->t('Already Voted') : $this->t('Vote'),
            '#url' => $vote_url,
            '#attributes' => [
              'class' => $has_voted ? ['button', 'button--small', 'button--disabled'] : ['button', 'button--small', 'button--primary'],
            ],
          ],
        ],
      ];
    }

    $build['verifications_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Candidate'),
        $this->t('Skill'),
        $this->t('Level'),
        $this->t('Type'),
        $this->t('Votes'),
        $this->t('Date'),
        $this->t('Action'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No pending verifications.'),
    ];

    return $build;
  }

  /**
   * Access check for verification pages.
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

    // Check if user is a member of the guild.
    if ($group->getMember($account)) {
      return \Drupal\Core\Access\AccessResult::allowed();
    }

    return \Drupal\Core\Access\AccessResult::forbidden();
  }

  /**
   * Access check for voting on a specific verification.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param \Drupal\avc_guild\Entity\LevelVerification $level_verification
   *   The verification.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function voteAccess(GroupInterface $group, LevelVerification $level_verification) {
    // Must be a guild, not a regular group.
    if (!avc_guild_is_guild($group)) {
      return \Drupal\Core\Access\AccessResult::forbidden('Not a guild.');
    }

    $account = $this->currentUser();
    $user = $this->entityTypeManager()->getStorage('user')->load($account->id());

    // Can't vote if not a guild member.
    if (!$group->getMember($account)) {
      return \Drupal\Core\Access\AccessResult::forbidden();
    }

    // Can't vote if not pending.
    if (!$level_verification->isPending()) {
      return \Drupal\Core\Access\AccessResult::forbidden();
    }

    // Can't vote on yourself.
    if ($level_verification->getUser()->id() == $account->id()) {
      return \Drupal\Core\Access\AccessResult::forbidden();
    }

    // Check if user is qualified to verify.
    if (!$this->skillProgressionService->canVerify($user, $level_verification)) {
      return \Drupal\Core\Access\AccessResult::forbidden();
    }

    // Check if already voted.
    if ($this->skillProgressionService->hasVoted($level_verification, $user)) {
      // Allow viewing, but form will show "already voted" message.
      return \Drupal\Core\Access\AccessResult::allowed();
    }

    return \Drupal\Core\Access\AccessResult::allowed();
  }

  /**
   * Formats verification type for display.
   *
   * @param string $type
   *   The verification type.
   *
   * @return string
   *   Formatted type.
   */
  protected function formatVerificationType(string $type): string {
    $types = [
      'auto' => $this->t('Auto'),
      'mentor' => $this->t('Mentor'),
      'peer' => $this->t('Peer'),
      'committee' => $this->t('Committee'),
      'assessment' => $this->t('Assessment'),
    ];

    return (string) ($types[$type] ?? $type);
  }

}
