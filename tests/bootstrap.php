<?php

/**
 * @file
 * Bootstrap file for AVC unit tests.
 */

// Load PHPUnit autoloader (has PHPUnit\Framework\TestCase etc).
$phpunit_autoloader = require '/tmp/phpunit_install/vendor/autoload.php';

// Load the project's Composer autoloader.
$project_autoloader = require __DIR__ . '/../../../../../vendor/autoload.php';

$html_dir = '/home/rob/nwp/sites/avc/html';

// Register both autoloaders with all needed namespaces.
$loaders = [$phpunit_autoloader, $project_autoloader];

$namespaces = [
  // Drupal core test classes.
  'Drupal\\Tests\\' => $html_dir . '/core/tests/Drupal/Tests',
  'Drupal\\TestTools\\' => $html_dir . '/core/tests/Drupal/TestTools',
  // Drupal core lib.
  'Drupal\\Core\\' => $html_dir . '/core/lib/Drupal/Core',
  'Drupal\\Component\\' => $html_dir . '/core/lib/Drupal/Component',
  // Core modules that our code references.
  'Drupal\\node\\' => $html_dir . '/core/modules/node/src',
  'Drupal\\user\\' => $html_dir . '/core/modules/user/src',
  'Drupal\\taxonomy\\' => $html_dir . '/core/modules/taxonomy/src',
  'Drupal\\file\\' => $html_dir . '/core/modules/file/src',
  'Drupal\\field\\' => $html_dir . '/core/modules/field/src',
  'Drupal\\views\\' => $html_dir . '/core/modules/views/src',
  'Drupal\\system\\' => $html_dir . '/core/modules/system/src',
  // Contrib modules.
  'Drupal\\group\\' => $html_dir . '/modules/contrib/group/src',
];

foreach ($loaders as $loader) {
  foreach ($namespaces as $prefix => $path) {
    if (is_dir($path)) {
      $loader->addPsr4($prefix, $path);
    }
  }
}

// Register all AVC module namespaces.
$modules_dir = $html_dir . '/profiles/custom/avc/modules/avc_features';
if (is_dir($modules_dir)) {
  $iterator = new DirectoryIterator($modules_dir);
  foreach ($iterator as $module) {
    if ($module->isDot() || !$module->isDir()) {
      continue;
    }
    $module_name = $module->getFilename();

    $src_dir = $module->getPathname() . '/src';
    if (is_dir($src_dir)) {
      foreach ($loaders as $loader) {
        $loader->addPsr4("Drupal\\{$module_name}\\", $src_dir);
      }
    }

    $test_dir = $module->getPathname() . '/tests/src';
    if (is_dir($test_dir)) {
      foreach ($loaders as $loader) {
        $loader->addPsr4("Drupal\\Tests\\{$module_name}\\", $test_dir);
      }
    }
  }
}

// Define avc_guild_get_member_role() stub for unit tests.
if (!function_exists('avc_guild_get_member_role')) {
  function avc_guild_get_member_role($guild, $user) {
    return NULL;
  }
}

// Define t() function.
if (!function_exists('t')) {
  function t($string, array $args = [], array $options = []) {
    if (class_exists('\Drupal\Core\StringTranslation\TranslatableMarkup')) {
      return new \Drupal\Core\StringTranslation\TranslatableMarkup($string, $args, $options);
    }
    return strtr($string, $args);
  }
}
