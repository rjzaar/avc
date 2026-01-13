<?php

namespace Drupal\avc_guild\Form;

use Drupal\avc_guild\Entity\LevelVerification;
use Drupal\avc_guild\Service\SkillProgressionService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for verifiers to vote on level verifications.
 */
class LevelVerificationVoteForm extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'level_verification_vote_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $group = NULL, ?LevelVerification $level_verification = NULL) {
    if (!$group || !$level_verification) {
      $form['error'] = [
        '#markup' => $this->t('Invalid verification request.'),
      ];
      return $form;
    }

    $form_state->set('group', $group);
    $form_state->set('level_verification', $level_verification);

    $candidate = $level_verification->getUser();
    $skill = $level_verification->getSkill();
    $target_level = $level_verification->getTargetLevel();
    $current_user = $this->currentUser();

    // Check if user has already voted.
    if ($this->skillProgressionService->hasVoted($level_verification, $current_user)) {
      $form['already_voted'] = [
        '#markup' => '<div class="messages messages--warning">' . $this->t('You have already voted on this verification.') . '</div>',
      ];
      return $form;
    }

    // Display candidate info.
    $form['candidate_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Verification Request'),
    ];

    $form['candidate_info']['candidate'] = [
      '#type' => 'item',
      '#title' => $this->t('Candidate'),
      '#markup' => $candidate ? $candidate->getDisplayName() : '-',
    ];

    $form['candidate_info']['skill'] = [
      '#type' => 'item',
      '#title' => $this->t('Skill'),
      '#markup' => $skill ? $skill->label() : '-',
    ];

    $form['candidate_info']['target_level'] = [
      '#type' => 'item',
      '#title' => $this->t('Target Level'),
      '#markup' => (string) $target_level,
    ];

    // Show current vote tallies.
    $form['candidate_info']['votes'] = [
      '#type' => 'item',
      '#title' => $this->t('Current Votes'),
      '#markup' => $this->t('Approve: @approve | Deny: @deny | Defer: @defer (Requires @required approvals)', [
        '@approve' => $level_verification->getApproveVotes(),
        '@deny' => $level_verification->getDenyVotes(),
        '@defer' => $level_verification->getDeferVotes(),
        '@required' => $level_verification->getVotesRequired(),
      ]),
    ];

    // Show evidence (recent credits/endorsements).
    $form['evidence'] = [
      '#type' => 'details',
      '#title' => $this->t('Evidence'),
      '#open' => TRUE,
    ];

    $credit_history = $this->skillProgressionService->getCreditHistory(
      $candidate,
      $group,
      $skill,
      10
    );

    if (!empty($credit_history)) {
      $rows = [];
      foreach ($credit_history as $credit) {
        $rows[] = [
          date('Y-m-d', $credit->getCreatedTime()),
          $credit->getCredits() . ' credits',
          $credit->getSourceType(),
          $credit->getNotes() ?? '-',
        ];
      }

      $form['evidence']['credit_history'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Date'),
          $this->t('Credits'),
          $this->t('Source'),
          $this->t('Notes'),
        ],
        '#rows' => $rows,
      ];
    }
    else {
      $form['evidence']['no_credits'] = [
        '#markup' => '<p>' . $this->t('No credit history available.') . '</p>',
      ];
    }

    // Vote section.
    $form['vote'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Your Vote'),
    ];

    $form['vote']['decision'] = [
      '#type' => 'radios',
      '#title' => $this->t('Decision'),
      '#options' => [
        'approve' => $this->t('Approve - Candidate demonstrates competency for this level'),
        'deny' => $this->t('Deny - Candidate does not yet demonstrate sufficient competency'),
        'defer' => $this->t('Defer - Need more information or evidence'),
      ],
      '#required' => TRUE,
    ];

    $form['vote']['feedback'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Feedback (Optional)'),
      '#description' => $this->t('Provide constructive feedback for the candidate.'),
      '#rows' => 4,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Vote'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('avc_guild.verification_queue', ['group' => $group->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $group = $form_state->get('group');
    $level_verification = $form_state->get('level_verification');
    $decision = $form_state->getValue('decision');
    $feedback = trim($form_state->getValue('feedback') ?? '');
    $verifier = $this->currentUser();

    try {
      $this->skillProgressionService->recordVote(
        $level_verification,
        $verifier,
        $decision,
        $feedback
      );

      $this->messenger()->addStatus($this->t('Your vote has been recorded.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error recording vote: @message', ['@message' => $e->getMessage()]));
      \Drupal::logger('avc_guild')->error('Vote error: @message', ['@message' => $e->getMessage()]);
    }

    $form_state->setRedirect('avc_guild.verification_queue', ['group' => $group->id()]);
  }

}
