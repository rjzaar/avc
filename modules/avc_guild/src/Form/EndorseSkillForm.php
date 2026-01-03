<?php

namespace Drupal\avc_guild\Form;

use Drupal\avc_guild\Service\EndorsementService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for endorsing a skill.
 */
class EndorseSkillForm extends FormBase {

  /**
   * The endorsement service.
   *
   * @var \Drupal\avc_guild\Service\EndorsementService
   */
  protected $endorsementService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an EndorseSkillForm.
   *
   * @param \Drupal\avc_guild\Service\EndorsementService $endorsement_service
   *   The endorsement service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EndorsementService $endorsement_service,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->endorsementService = $endorsement_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_guild.endorsement'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_guild_endorse_skill';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $group = NULL, ?UserInterface $user = NULL) {
    if (!$group || !$user) {
      $form['error'] = [
        '#markup' => $this->t('Invalid guild or user.'),
      ];
      return $form;
    }

    $form['#group'] = $group;
    $form['#user'] = $user;

    // Check if current user can endorse.
    if (!avc_guild_can_endorse($group, $this->currentUser())) {
      $form['error'] = [
        '#markup' => $this->t('You do not have permission to endorse skills in this guild.'),
      ];
      return $form;
    }

    // Get available skills.
    $skills = $this->endorsementService->getGuildSkills($group);
    $skill_options = [];
    foreach ($skills as $skill) {
      // Check if already endorsed.
      if (!$this->endorsementService->hasEndorsed($this->currentUser(), $user, $skill, $group)) {
        $skill_options[$skill->id()] = $skill->label();
      }
    }

    if (empty($skill_options)) {
      $form['error'] = [
        '#markup' => $this->t('You have already endorsed all available skills for this user.'),
      ];
      return $form;
    }

    $form['endorsing'] = [
      '#type' => 'item',
      '#title' => $this->t('Endorsing'),
      '#markup' => $user->getDisplayName() . ' ' . $this->t('in') . ' ' . $group->label(),
    ];

    $form['skill_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Skill'),
      '#options' => $skill_options,
      '#required' => TRUE,
    ];

    $form['comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comment'),
      '#description' => $this->t('Optional comment about this endorsement.'),
      '#rows' => 3,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Endorse'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $group = $form['#group'];
    $user = $form['#user'];
    $skill_id = $form_state->getValue('skill_id');
    $comment = $form_state->getValue('comment');

    $skill = $this->entityTypeManager->getStorage('taxonomy_term')->load($skill_id);

    try {
      $this->endorsementService->createEndorsement(
        $this->currentUser(),
        $user,
        $skill,
        $group,
        $comment
      );

      $this->messenger()->addStatus($this->t('You have endorsed @user for @skill.', [
        '@user' => $user->getDisplayName(),
        '@skill' => $skill->label(),
      ]));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }

    $form_state->setRedirect('avc_guild.member_profile', [
      'group' => $group->id(),
      'user' => $user->id(),
    ]);
  }

}
