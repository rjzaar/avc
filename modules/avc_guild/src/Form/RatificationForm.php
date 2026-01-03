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
      $this->ratificationService->approve($ratification, $mentor, $feedback);
      $this->messenger()->addStatus($this->t('Work has been approved.'));
    }
    elseif ($status === Ratification::STATUS_CHANGES_REQUESTED) {
      $this->ratificationService->requestChanges($ratification, $mentor, $feedback);
      $this->messenger()->addStatus($this->t('Changes have been requested.'));
    }

    $form_state->setRedirect('avc_guild.ratification_queue', [
      'group' => $ratification->getGuild()->id(),
    ]);
  }

}
