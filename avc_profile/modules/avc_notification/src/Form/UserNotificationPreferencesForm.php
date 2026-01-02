<?php

namespace Drupal\avc_notification\Form;

use Drupal\avc_notification\Service\NotificationPreferences;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for managing user notification preferences.
 */
class UserNotificationPreferencesForm extends FormBase {

  /**
   * The notification preferences service.
   *
   * @var \Drupal\avc_notification\Service\NotificationPreferences
   */
  protected $notificationPreferences;

  /**
   * The user being edited.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Constructs a UserNotificationPreferencesForm.
   *
   * @param \Drupal\avc_notification\Service\NotificationPreferences $notification_preferences
   *   The notification preferences service.
   */
  public function __construct(NotificationPreferences $notification_preferences) {
    $this->notificationPreferences = $notification_preferences;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_notification.preferences')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_notification_preferences_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    if (!$user) {
      $user = $this->currentUser();
    }

    $this->user = $user;

    // Get current preference.
    $current_preference = $this->notificationPreferences->getUserDefault($user);

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Configure how you would like to receive notifications about workflow events.') . '</p>',
    ];

    $form['default_notification'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default notification preference'),
      '#options' => NotificationPreferences::getOptions(),
      '#default_value' => $current_preference,
      '#required' => TRUE,
    ];

    // Get user's groups for group-specific overrides.
    $groups = $this->getUserGroups($user);

    if (!empty($groups)) {
      $form['group_overrides'] = [
        '#type' => 'details',
        '#title' => $this->t('Group-specific preferences'),
        '#description' => $this->t('Override your default preference for specific groups.'),
        '#open' => FALSE,
      ];

      foreach ($groups as $group) {
        $override = $this->notificationPreferences->getGroupOverride($user, $group);
        $form['group_overrides']['override_' . $group->id()] = [
          '#type' => 'select',
          '#title' => $this->t('Notifications for @group', ['@group' => $group->label()]),
          '#options' => NotificationPreferences::getOptions(TRUE),
          '#default_value' => $override ?: NotificationPreferences::PREF_PERSONAL,
        ];
      }
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
    $default = $form_state->getValue('default_notification');
    $this->notificationPreferences->setUserDefault($this->user, $default);

    // Save group overrides.
    $groups = $this->getUserGroups($this->user);
    foreach ($groups as $group) {
      $field_name = 'override_' . $group->id();
      if ($form_state->hasValue($field_name)) {
        $value = $form_state->getValue($field_name);
        $this->notificationPreferences->setGroupOverride($this->user, $group, $value);
      }
    }

    $this->messenger()->addStatus($this->t('Your notification preferences have been saved.'));
  }

  /**
   * Get all groups the user is a member of.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Array of groups.
   */
  protected function getUserGroups(AccountInterface $user) {
    $groups = [];

    // Get user entity.
    if (!$user instanceof UserInterface) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($user->id());
    }

    if (!$user) {
      return $groups;
    }

    // Get group memberships.
    $group_membership_service = \Drupal::service('group.membership_loader');
    $memberships = $group_membership_service->loadByUser($user);

    foreach ($memberships as $membership) {
      $group = $membership->getGroup();
      if ($group instanceof GroupInterface) {
        $groups[$group->id()] = $group;
      }
    }

    return $groups;
  }

}
