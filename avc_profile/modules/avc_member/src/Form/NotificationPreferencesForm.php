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
      '#title' => $this->t('Default notification preference'),
      '#description' => $this->t('This applies to all groups unless overridden.'),
      '#options' => [
        'n' => $this->t('Immediate (as they occur)'),
        'd' => $this->t('Daily digest'),
        'w' => $this->t('Weekly digest'),
        'x' => $this->t('No notifications'),
      ],
      '#default_value' => $this->getUserNotificationDefault($user),
    ];

    // Group-specific overrides.
    $form['group_overrides'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Group notification overrides'),
      '#description' => $this->t('Override the default for specific groups.'),
    ];

    // Get user's groups and add override options.
    $groups = $this->getUserGroups($user);
    if (!empty($groups)) {
      foreach ($groups as $group_id => $group_label) {
        $field_name = 'override_' . $group_label;
        $form['group_overrides'][$field_name] = [
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
          '#group_id' => $group_id,
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
    // For now, store in user data - in production would be in group_content fields.
    $user_data = \Drupal::service('user.data');
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, 'override_') === 0 && isset($form['group_overrides'][$key]['#group_id'])) {
        $group_id = $form['group_overrides'][$key]['#group_id'];
        $user_data->set('avc_notification', $user->id(), 'group_' . $group_id, $value);
      }
    }

    $this->messenger()->addStatus($this->t('Notification preferences saved'));
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
      $value = $user->get('field_notification_default')->value;
      return $value ?: 'n';
    }
    return 'n';
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
      $entity_type_manager = \Drupal::entityTypeManager();
      if ($entity_type_manager->hasDefinition('group_content')) {
        $membership_storage = $entity_type_manager->getStorage('group_content');
        $membership_ids = $membership_storage->getQuery()
          ->condition('entity_id', $user->id())
          ->condition('type', '%group_membership', 'LIKE')
          ->accessCheck(TRUE)
          ->execute();

        if (!empty($membership_ids)) {
          $memberships = $membership_storage->loadMultiple($membership_ids);
          foreach ($memberships as $membership) {
            $group = $membership->getGroup();
            if ($group) {
              $groups[$group->id()] = $group->label();
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      // Group module may not be available.
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
    $user_data = \Drupal::service('user.data');
    $value = $user_data->get('avc_notification', $user->id(), 'group_' . $group_id);
    return $value ?: 'p'; // Default to "use personal default".
  }

}
