<?php

namespace Drupal\avc_notification\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Access controller for user notification preferences.
 */
class UserNotificationPreferencesController {

  /**
   * Checks access for user notification preferences page.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose preferences are being viewed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(UserInterface $user, AccountInterface $account) {
    // Users can view their own preferences.
    if ($user->id() == $account->id()) {
      return AccessResult::allowed();
    }

    // Admins can view anyone's preferences.
    if ($account->hasPermission('administer notification settings')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
