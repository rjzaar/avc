<?php

/**
 * @file
 * Post-install script to configure email reply settings.
 *
 * This script is run after site installation to configure the email reply
 * module based on the environment (dev, stage, live).
 *
 * Usage: drush php:script configure_email_reply.php
 */

use Drupal\Core\Site\Settings;

// Detect environment.
$env = getenv('ENV_TYPE') ?: 'development';
$site_name = getenv('DDEV_SITENAME') ?: \Drupal::config('system.site')->get('name');
$is_ddev = !empty(getenv('DDEV_SITENAME'));

// Map environment names.
$env_map = [
  'development' => 'dev',
  'staging' => 'stage',
  'production' => 'live',
];
$env_key = $env_map[$env] ?? 'dev';

echo "Configuring email reply for environment: $env_key\n";

// Get the config factory.
$config = \Drupal::configFactory()->getEditable('avc_email_reply.settings');

// Base configuration.
$settings = [
  'enabled' => TRUE,
  'email_provider' => 'sendgrid',
  'token_expiry_days' => 30,
  'spam_score_threshold' => 5.0,
  'rate_limits' => [
    'per_user_per_hour' => 10,
    'per_user_per_day' => 50,
    'per_group_per_hour' => 100,
  ],
  'allowed_content_types' => [],
  'debug_mode' => FALSE,
];

// Environment-specific overrides.
switch ($env_key) {
  case 'dev':
    $settings['enabled'] = TRUE;
    $settings['debug_mode'] = TRUE;
    // Higher rate limits for testing.
    $settings['rate_limits'] = [
      'per_user_per_hour' => 100,
      'per_user_per_day' => 500,
      'per_group_per_hour' => 1000,
    ];
    // Set reply domain based on DDEV site name.
    if ($is_ddev) {
      $settings['reply_domain'] = $site_name . '.ddev.site';
      echo "  Using DDEV domain: {$settings['reply_domain']}\n";
    }
    else {
      $settings['reply_domain'] = 'test.local';
    }
    break;

  case 'stage':
    $settings['enabled'] = TRUE;
    $settings['debug_mode'] = TRUE;
    // Get staging domain from environment or use default.
    $staging_domain = getenv('STAGING_DOMAIN') ?: '';
    if ($staging_domain) {
      $settings['reply_domain'] = 'reply.' . $staging_domain;
    }
    break;

  case 'live':
    $settings['enabled'] = TRUE;
    $settings['debug_mode'] = FALSE;
    // Production domain should be configured via cnwp.yml or environment.
    $live_domain = getenv('LIVE_DOMAIN') ?: '';
    if ($live_domain) {
      $settings['reply_domain'] = 'reply.' . $live_domain;
    }
    break;
}

// Apply all settings.
foreach ($settings as $key => $value) {
  if ($key === 'rate_limits') {
    foreach ($value as $rate_key => $rate_value) {
      $config->set("rate_limits.$rate_key", $rate_value);
    }
  }
  else {
    $config->set($key, $value);
  }
}

// Save configuration.
$config->save();

echo "Email reply configuration complete:\n";
echo "  - Enabled: " . ($settings['enabled'] ? 'Yes' : 'No') . "\n";
echo "  - Reply Domain: " . ($settings['reply_domain'] ?: '(not set)') . "\n";
echo "  - Debug Mode: " . ($settings['debug_mode'] ? 'Yes' : 'No') . "\n";
echo "  - Provider: {$settings['email_provider']}\n";

// Rebuild caches.
drupal_flush_all_caches();
echo "\nCaches cleared.\n";

// If in dev mode, offer to set up test data.
if ($env_key === 'dev') {
  echo "\nDevelopment mode detected.\n";
  echo "To test email reply:\n";
  echo "  1. Visit: /admin/config/avc/email-reply/test\n";
  echo "  2. Or run: drush email-reply:test\n";
  echo "  3. Or run: drush email-reply:setup-test\n";
}
