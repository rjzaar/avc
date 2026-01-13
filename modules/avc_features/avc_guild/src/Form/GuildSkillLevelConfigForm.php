<?php

namespace Drupal\avc_guild\Form;

use Drupal\avc_guild\Entity\SkillLevel;
use Drupal\avc_guild\Service\SkillConfigurationService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for configuring skill levels for a guild skill.
 */
class GuildSkillLevelConfigForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->skillConfigService = $container->get('avc_guild.skill_configuration');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'guild_skill_level_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $group = NULL, ?TermInterface $skill = NULL) {
    if (!$group || !$skill) {
      $form['error'] = [
        '#markup' => $this->t('Invalid guild or skill.'),
      ];
      return $form;
    }

    $form_state->set('group', $group);
    $form_state->set('skill', $skill);

    $form['info'] = [
      '#type' => 'item',
      '#markup' => $this->t('<h2>Configure Skill Levels: @skill in @guild</h2>', [
        '@skill' => $skill->label(),
        '@guild' => $group->label(),
      ]),
    ];

    // Load existing levels.
    $existing_levels = $this->skillConfigService->getSkillLevels($group, $skill);

    // If no levels exist, offer to create defaults.
    if (empty($existing_levels)) {
      $form['no_levels'] = [
        '#markup' => '<p>' . $this->t('No skill levels configured for this skill.') . '</p>',
      ];

      $form['create_defaults'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Default Levels'),
        '#name' => 'create_defaults',
        '#submit' => ['::createDefaults'],
      ];

      return $form;
    }

    // Display existing levels in a table format.
    $form['levels'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Level'),
        $this->t('Name'),
        $this->t('Credits Required'),
        $this->t('Verification Type'),
        $this->t('Verifier Min Level'),
        $this->t('Votes Required'),
        $this->t('Time Min (Days)'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No levels configured.'),
    ];

    foreach ($existing_levels as $level_num => $level_entity) {
      $row_key = 'level_' . $level_entity->id();

      $form['levels'][$row_key]['level'] = [
        '#type' => 'number',
        '#default_value' => $level_entity->getLevel(),
        '#min' => 1,
        '#max' => 10,
        '#size' => 5,
      ];

      $form['levels'][$row_key]['name'] = [
        '#type' => 'textfield',
        '#default_value' => $level_entity->getName(),
        '#size' => 20,
        '#required' => TRUE,
      ];

      $form['levels'][$row_key]['credits_required'] = [
        '#type' => 'number',
        '#default_value' => $level_entity->getCreditsRequired(),
        '#min' => 0,
        '#size' => 10,
      ];

      $form['levels'][$row_key]['verification_type'] = [
        '#type' => 'select',
        '#options' => [
          SkillLevel::VERIFICATION_AUTO => $this->t('Auto'),
          SkillLevel::VERIFICATION_MENTOR => $this->t('Mentor'),
          SkillLevel::VERIFICATION_PEER => $this->t('Peer'),
          SkillLevel::VERIFICATION_COMMITTEE => $this->t('Committee'),
          SkillLevel::VERIFICATION_ASSESSMENT => $this->t('Assessment'),
        ],
        '#default_value' => $level_entity->getVerificationType(),
      ];

      $form['levels'][$row_key]['verifier_minimum_level'] = [
        '#type' => 'number',
        '#default_value' => $level_entity->getVerifierMinimumLevel(),
        '#min' => 0,
        '#max' => 10,
        '#size' => 5,
      ];

      $form['levels'][$row_key]['votes_required'] = [
        '#type' => 'number',
        '#default_value' => $level_entity->getVotesRequired(),
        '#min' => 1,
        '#size' => 5,
      ];

      $form['levels'][$row_key]['time_minimum_days'] = [
        '#type' => 'number',
        '#default_value' => $level_entity->getTimeMinimumDays(),
        '#min' => 0,
        '#size' => 10,
      ];

      $form['levels'][$row_key]['id'] = [
        '#type' => 'hidden',
        '#value' => $level_entity->id(),
      ];

      $form['levels'][$row_key]['delete'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Delete'),
        '#title_display' => 'invisible',
      ];
    }

    // Add new level section.
    $form['add_level'] = [
      '#type' => 'details',
      '#title' => $this->t('Add New Level'),
      '#open' => FALSE,
    ];

    $form['add_level']['new_level'] = [
      '#type' => 'number',
      '#title' => $this->t('Level Number'),
      '#min' => 1,
      '#max' => 10,
    ];

    $form['add_level']['new_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Level Name'),
      '#size' => 30,
    ];

    $form['add_level']['new_credits_required'] = [
      '#type' => 'number',
      '#title' => $this->t('Credits Required'),
      '#min' => 0,
      '#default_value' => 0,
    ];

    $form['add_level']['new_verification_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Verification Type'),
      '#options' => [
        SkillLevel::VERIFICATION_AUTO => $this->t('Automatic'),
        SkillLevel::VERIFICATION_MENTOR => $this->t('Mentor Approval'),
        SkillLevel::VERIFICATION_PEER => $this->t('Peer Votes'),
        SkillLevel::VERIFICATION_COMMITTEE => $this->t('Committee Vote'),
        SkillLevel::VERIFICATION_ASSESSMENT => $this->t('Formal Assessment'),
      ],
      '#default_value' => SkillLevel::VERIFICATION_MENTOR,
    ];

    $form['add_level']['new_verifier_minimum_level'] = [
      '#type' => 'number',
      '#title' => $this->t('Verifier Minimum Level'),
      '#min' => 0,
      '#max' => 10,
      '#default_value' => 0,
    ];

    $form['add_level']['new_votes_required'] = [
      '#type' => 'number',
      '#title' => $this->t('Votes Required'),
      '#min' => 1,
      '#default_value' => 1,
    ];

    $form['add_level']['new_time_minimum_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Time Minimum (Days)'),
      '#min' => 0,
      '#default_value' => 0,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Submit handler for creating default levels.
   */
  public function createDefaults(array &$form, FormStateInterface $form_state) {
    $group = $form_state->get('group');
    $skill = $form_state->get('skill');

    $this->skillConfigService->createDefaultLevels($group, $skill);

    $this->messenger()->addStatus($this->t('Default skill levels have been created.'));

    // Rebuild the form to show the new levels.
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $group = $form_state->get('group');
    $skill = $form_state->get('skill');
    $storage = $this->entityTypeManager->getStorage('skill_level');

    // Process existing levels.
    $levels_values = $form_state->getValue('levels') ?? [];
    foreach ($levels_values as $row_key => $row_data) {
      if (!isset($row_data['id'])) {
        continue;
      }

      $level_id = $row_data['id'];
      $level_entity = $storage->load($level_id);

      if (!$level_entity) {
        continue;
      }

      // Delete if requested.
      if (!empty($row_data['delete'])) {
        $level_entity->delete();
        continue;
      }

      // Update the level.
      $level_entity->set('level', $row_data['level']);
      $level_entity->set('name', $row_data['name']);
      $level_entity->set('credits_required', $row_data['credits_required']);
      $level_entity->set('verification_type', $row_data['verification_type']);
      $level_entity->set('verifier_minimum_level', $row_data['verifier_minimum_level']);
      $level_entity->set('votes_required', $row_data['votes_required']);
      $level_entity->set('time_minimum_days', $row_data['time_minimum_days']);
      $level_entity->save();
    }

    // Add new level if provided.
    $new_level = $form_state->getValue('new_level');
    $new_name = $form_state->getValue('new_name');

    if ($new_level && $new_name) {
      $new_level_entity = $storage->create([
        'guild_id' => $group->id(),
        'skill_id' => $skill->id(),
        'level' => $new_level,
        'name' => $new_name,
        'credits_required' => $form_state->getValue('new_credits_required'),
        'verification_type' => $form_state->getValue('new_verification_type'),
        'verifier_minimum_level' => $form_state->getValue('new_verifier_minimum_level'),
        'votes_required' => $form_state->getValue('new_votes_required'),
        'time_minimum_days' => $form_state->getValue('new_time_minimum_days'),
        'weight' => $new_level,
      ]);
      $new_level_entity->save();
      $this->messenger()->addStatus($this->t('New level "@name" has been added.', ['@name' => $new_name]));
    }

    $this->messenger()->addStatus($this->t('Skill level configuration has been saved.'));

    // Redirect back to skill admin page.
    $form_state->setRedirect('avc_guild.skill_admin', ['group' => $group->id()]);
  }

}
