<?php

namespace Drupal\avc_member\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Form for managing notification preferences.
 */
class NotificationPreferencesForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a NotificationPreferencesForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'avc_member_notification_preferences';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    $form['#user'] = $user;

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Configure how and when you receive notifications from AV Commons.') . '</p>',
    ];

    $form['default_notification'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default Notification Setting'),
      '#description' => $this->t('This applies to all groups unless overridden.'),
      '#options' => [
        'n' => $this->t('Immediate - Receive alerts as soon as resources become relevant'),
        'd' => $this->t('Daily digest - Receive a single daily summary'),
        'w' => $this->t('Weekly digest - Receive a single weekly summary'),
        'x' => $this->t('None - No notifications (check dashboard manually)'),
      ],
      '#default_value' => $this->getUserNotificationDefault($user),
    ];

    // Group-specific overrides.
    $form['group_overrides'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Group-specific Settings'),
      '#description' => $this->t('Override the default for specific groups.'),
    ];

    // Get user's groups and add override options.
    $groups = $this->getUserGroups($user);
    if (!empty($groups)) {
      foreach ($groups as $group_id => $group_label) {
        $form['group_overrides']['group_' . $group_id] = [
          '#type' => 'select',
          '#title' => $group_label,
          '#options' => [
            'p' => $this->t('Use default'),
            'n' => $this->t('Immediate'),
            'd' => $this->t('Daily'),
            'w' => $this->t('Weekly'),
            'x' => $this->t('None'),
          ],
          '#default_value' => $this->getGroupNotificationOverride($user, $group_id),
        ];
      }
    }
    else {
      $form['group_overrides']['no_groups'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('You are not a member of any groups yet.') . '</p>',
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save preferences'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = $form['#user'];

    // Save default notification setting.
    $default = $form_state->getValue('default_notification');

    // Save to user profile field if it exists.
    if ($user->hasField('field_notification_default')) {
      $user->set('field_notification_default', $default);
      $user->save();
    }

    // Save group overrides.
    // This would save to group_content entities or a custom storage.
    // Implementation depends on how group membership stores extra data.

    $this->messenger()->addStatus($this->t('Your notification preferences have been saved.'));
  }

  /**
   * Gets the user's default notification setting.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return string
   *   The notification setting code.
   */
  protected function getUserNotificationDefault(UserInterface $user) {
    if ($user->hasField('field_notification_default')) {
      return $user->get('field_notification_default')->value ?? 'x';
    }
    return 'x';
  }

  /**
   * Gets groups the user belongs to.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return array
   *   Array of group labels keyed by group ID.
   */
  protected function getUserGroups(UserInterface $user) {
    $groups = [];

    try {
      if (\Drupal::moduleHandler()->moduleExists('social_group')) {
        $group_helper = \Drupal::service('social_group.helper_service');
        $user_groups = $group_helper->getAllGroupsForUser($user->id());
        foreach ($user_groups as $group) {
          $groups[$group->id()] = $group->label();
        }
      }
    }
    catch (\Exception $e) {
      // Service may not exist.
    }

    return $groups;
  }

  /**
   * Gets the notification override for a specific group.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param int $group_id
   *   The group ID.
   *
   * @return string
   *   The notification setting code.
   */
  protected function getGroupNotificationOverride(UserInterface $user, $group_id) {
    // TODO: Load from group_content field or custom storage.
    return 'p'; // Default to "use personal default".
  }

}
