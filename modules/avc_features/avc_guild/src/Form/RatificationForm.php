<?php

namespace Drupal\avc_guild\Form;

use Drupal\avc_guild\Entity\Ratification;
use Drupal\avc_guild\Service\RatificationService;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for reviewing and completing ratifications.
 */
class RatificationForm extends ContentEntityForm {

  /**
   * The ratification service.
   *
   * @var \Drupal\avc_guild\Service\RatificationService
   */
  protected $ratificationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->ratificationService = $container->get('avc_guild.ratification');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\avc_guild\Entity\Ratification $ratification */
    $ratification = $this->entity;

    // Display task info.
    $form['info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Task Information'),
      '#weight' => -10,
    ];

    $junior = $ratification->getJunior();
    $asset = $ratification->getAsset();
    $guild = $ratification->getGuild();

    $form['info']['junior'] = [
      '#type' => 'item',
      '#title' => $this->t('Junior'),
      '#markup' => $junior ? $junior->getDisplayName() : '-',
    ];

    $form['info']['asset'] = [
      '#type' => 'item',
      '#title' => $this->t('Asset'),
      '#markup' => $asset ? $asset->toLink()->toString() : '-',
    ];

    $form['info']['guild'] = [
      '#type' => 'item',
      '#title' => $this->t('Guild'),
      '#markup' => $guild ? $guild->label() : '-',
    ];

    // Only show decision fields if pending.
    if ($ratification->isPending()) {
      $form['decision'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Your Decision'),
        '#weight' => 0,
      ];

      $form['decision']['status'] = [
        '#type' => 'radios',
        '#title' => $this->t('Decision'),
        '#options' => [
          Ratification::STATUS_APPROVED => $this->t('Approve - Work is satisfactory'),
          Ratification::STATUS_CHANGES_REQUESTED => $this->t('Request Changes - Work needs revision'),
        ],
        '#required' => TRUE,
      ];

      $form['decision']['feedback'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Feedback'),
        '#description' => $this->t('Provide feedback for the junior member. Required when requesting changes.'),
        '#rows' => 4,
      ];

      // Add skill credits section.
      $form['skill_credits'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Award Skill Credits'),
        '#description' => $this->t('Award credits for demonstrated skills in this work.'),
        '#weight' => 1,
        '#states' => [
          'visible' => [
            ':input[name="status"]' => ['value' => Ratification::STATUS_APPROVED],
          ],
        ],
      ];

      // Get available skills.
      $skills = $this->getAvailableSkills($guild);
      if (!empty($skills)) {
        foreach ($skills as $skill_id => $skill_name) {
          $form['skill_credits']['skill_' . $skill_id] = [
            '#type' => 'radios',
            '#title' => $skill_name,
            '#options' => [
              0 => $this->t('None'),
              5 => $this->t('Standard (+5 credits)'),
              10 => $this->t('Good (+10 credits)'),
              15 => $this->t('Exceptional (+15 credits)'),
            ],
            '#default_value' => 0,
          ];
        }
      }
      else {
        $form['skill_credits']['no_skills'] = [
          '#markup' => '<p>' . $this->t('No skills configured for this guild.') . '</p>',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $status = $form_state->getValue('status');
    $feedback = trim($form_state->getValue('feedback') ?? '');

    // Require feedback when requesting changes.
    if ($status === Ratification::STATUS_CHANGES_REQUESTED && empty($feedback)) {
      $form_state->setErrorByName('feedback', $this->t('Feedback is required when requesting changes.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\avc_guild\Entity\Ratification $ratification */
    $ratification = $this->entity;

    $status = $form_state->getValue('status');
    $feedback = trim($form_state->getValue('feedback') ?? '');
    $mentor = $this->currentUser();

    if ($status === Ratification::STATUS_APPROVED) {
      // Collect skill credits.
      $skill_credits = [];
      $guild = $ratification->getGuild();
      $skills = $this->getAvailableSkills($guild);

      foreach ($skills as $skill_id => $skill_name) {
        $field_name = 'skill_' . $skill_id;
        $credits = $form_state->getValue($field_name);
        if ($credits > 0) {
          $skill_credits[$skill_id] = $credits;
        }
      }

      $this->ratificationService->approve($ratification, $mentor, $feedback, $skill_credits);
      $this->messenger()->addStatus($this->t('Work has been approved.'));

      if (!empty($skill_credits)) {
        $count = count($skill_credits);
        $this->messenger()->addStatus($this->t('Awarded skill credits for @count skills.', ['@count' => $count]));
      }
    }
    elseif ($status === Ratification::STATUS_CHANGES_REQUESTED) {
      $this->ratificationService->requestChanges($ratification, $mentor, $feedback);
      $this->messenger()->addStatus($this->t('Changes have been requested.'));
    }

    $form_state->setRedirect('avc_guild.ratification_queue', [
      'group' => $ratification->getGuild()->id(),
    ]);
  }

  /**
   * Gets available skills for a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return array
   *   Array of skill_id => skill_name.
   */
  protected function getAvailableSkills($guild) {
    $skills = [];

    try {
      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $query = $term_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', 'guild_skills')
        ->sort('name');

      $term_ids = $query->execute();

      if (!empty($term_ids)) {
        $terms = $term_storage->loadMultiple($term_ids);
        foreach ($terms as $term) {
          $skills[$term->id()] = $term->label();
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('avc_guild')->error('Error loading skills: @message', ['@message' => $e->getMessage()]);
    }

    return $skills;
  }

}
