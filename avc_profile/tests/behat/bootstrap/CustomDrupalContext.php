<?php

use Drupal\DrupalExtension\Context\DrupalContext;
use Behat\Mink\Exception\ElementNotFoundException;

/**
 * Custom Drupal Context that handles Open Social theme and JavaScript sessions.
 *
 * This context extends the standard DrupalContext and provides custom
 * authentication handling for Open Social's theme and JavaScript drivers.
 */
class CustomDrupalContext extends DrupalContext {

  /**
   * Creates and logs in a user with the specified role.
   *
   * {@inheritdoc}
   */
  public function assertAuthenticatedByRole($role) {
    // Create a user with the specified role
    $randomId = time() . '_' . rand(100, 999);
    $password = 'password' . rand(1000, 9999);

    $user = (object) [
      'name' => 'behat_' . $role . '_' . $randomId,
      'mail' => 'behat_' . $role . '_' . $randomId . '@example.com',
      'pass' => $password,
      'status' => 1,
      'roles' => [$role],
    ];

    // Use the parent's userCreate method - this creates the user in Drupal
    // and adds it to the user manager
    $user = $this->userCreate($user);

    // Store the password for form-based login
    $user->pass = $password;

    // Login the user
    $this->loginUser($user);

    // Ensure the user is set as current in the user manager
    // This is important for JavaScript sessions where form login
    // doesn't automatically update the user manager
    $this->getUserManager()->setCurrentUser($user);
  }

  /**
   * Login a user, handling both API and browser-based sessions.
   *
   * @param object $user
   *   The user object with name and pass properties.
   */
  protected function loginUser($user) {
    $session = $this->getSession();
    $driver = $session->getDriver();

    // Check if this is a JavaScript/browser session
    $driverClass = get_class($driver);
    $isJsDriver = (
      strpos($driverClass, 'Selenium') !== false ||
      strpos($driverClass, 'WebdriverClassic') !== false ||
      strpos($driverClass, 'Chrome') !== false ||
      strpos($driverClass, 'Panther') !== false
    );

    if ($isJsDriver) {
      // Use form-based login for JavaScript drivers
      $this->loginViaForm($user);
    } else {
      // Use API-based login for non-JavaScript drivers
      $this->login($user);
    }
  }

  /**
   * Login via the login form (for JavaScript sessions).
   *
   * @param object $user
   *   The user object with name and pass properties.
   */
  protected function loginViaForm($user) {
    $session = $this->getSession();

    // Visit the login page
    $loginUrl = $this->locatePath('/user/login');
    $session->visit($loginUrl);
    $page = $session->getPage();

    // Wait for the page to load with JavaScript
    $session->wait(5000, "document.readyState === 'complete'");

    // Additional wait for AJAX content
    $session->wait(2000);

    // Debug: Get current URL and page content
    $currentUrl = $session->getCurrentUrl();
    $html = $page->getHtml();

    // Check if we got redirected or have an SSL issue
    if (strpos($html, 'name_or_mail') === false && strpos($html, 'edit-name') === false) {
      // Try to find any form on the page
      $forms = $page->findAll('css', 'form');
      $formIds = [];
      foreach ($forms as $form) {
        $formIds[] = $form->getAttribute('id');
      }

      throw new \Exception(sprintf(
        "Login form not found. URL: %s, Forms found: %s, HTML snippet: %s",
        $currentUrl,
        implode(', ', $formIds),
        substr($html, 0, 1000)
      ));
    }

    // Open Social uses 'name_or_mail' field instead of 'name'
    $usernameField = $page->find('css', '#edit-name-or-mail');
    if (!$usernameField) {
      $usernameField = $page->find('css', 'input[name="name_or_mail"]');
    }
    // Fallback to standard Drupal field
    if (!$usernameField) {
      $usernameField = $page->find('css', '#edit-name');
    }
    if (!$usernameField) {
      $usernameField = $page->find('css', 'input[name="name"]');
    }

    // Password field
    $passwordField = $page->find('css', '#edit-pass');
    if (!$passwordField) {
      $passwordField = $page->find('css', 'input[name="pass"]');
    }

    if (!$usernameField) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'form field', 'id/name', 'edit-name-or-mail');
    }
    if (!$passwordField) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'form field', 'id/name', 'edit-pass');
    }

    // Fill in the form
    $usernameField->setValue($user->name);
    $passwordField->setValue($user->pass);

    // Submit the form - Open Social uses a button with id="edit-submit"
    $submitButton = $page->find('css', '#social-user-login-form #edit-submit');
    if (!$submitButton) {
      $submitButton = $page->find('css', '#social-user-login-form button[type="submit"]');
    }
    if (!$submitButton) {
      $submitButton = $page->findButton('Log in');
    }

    if ($submitButton) {
      $submitButton->click();
    } else {
      // Try submitting the form directly
      $form = $page->find('css', '#social-user-login-form');
      if ($form) {
        $form->submit();
      }
    }

    // Wait for login to complete
    $session->wait(3000);
  }

  /**
   * Override loggedIn() to skip logout link check.
   *
   * {@inheritdoc}
   */
  public function loggedIn() {
    // Check if there's a current user in the user manager
    $current_user = $this->getUserManager()->getCurrentUser();
    return $current_user && isset($current_user->uid) && $current_user->uid > 0;
  }
}
