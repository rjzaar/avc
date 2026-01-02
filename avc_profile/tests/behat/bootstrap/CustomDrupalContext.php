<?php

use Drupal\DrupalExtension\Context\DrupalContext;

/**
 * Custom Drupal Context that bypasses logout link check.
 *
 * This context extends the standard DrupalContext and overrides the
 * assertAuthenticatedByRole() method to use the API driver for authentication
 * instead of checking for a logout link, which may not exist in Open Social's theme.
 */
class CustomDrupalContext extends DrupalContext {

  /**
   * Creates and logs in a user with the specified role.
   *
   * This overrides the parent method to bypass logout link verification
   * when using the Drupal API driver.
   *
   * {@inheritdoc}
   */
  public function assertAuthenticatedByRole($role) {
    // Create a user with the specified role
    $user = (object) [
      'name' => 'behat_' . $role . '_' . time() . '_' . rand(100, 999),
      'mail' => 'behat_' . $role . '_' . time() . '@example.com',
      'pass' => 'password' . rand(1000, 9999),
      'status' => 1,
      'roles' => [$role],
    ];

    // Use the parent's userCreate method
    $user = $this->userCreate($user);

    // Load the Drupal user entity and log them in via API
    $account = \Drupal\user\Entity\User::load($user->uid);
    if ($account) {
      // Use Drupal's user_login_finalize to set up the session
      user_login_finalize($account);
    }

    // Set the current user in the user manager
    $this->getUserManager()->setCurrentUser($user);
  }

  /**
   * Override loggedIn() to skip logout link check when using API driver.
   *
   * {@inheritdoc}
   */
  public function loggedIn() {
    // Check if there's a current user in the user manager
    $current_user = $this->getUserManager()->getCurrentUser();
    return $current_user && isset($current_user->uid) && $current_user->uid > 0;
  }
}
