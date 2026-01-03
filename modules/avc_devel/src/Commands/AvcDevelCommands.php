<?php

namespace Drupal\avc_devel\Commands;

use Drupal\avc_devel\Generator\TestContentGenerator;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for AVC development.
 */
class AvcDevelCommands extends DrushCommands {

  /**
   * The test content generator.
   *
   * @var \Drupal\avc_devel\Generator\TestContentGenerator
   */
  protected $generator;

  /**
   * Constructs an AvcDevelCommands object.
   */
  public function __construct(TestContentGenerator $generator) {
    parent::__construct();
    $this->generator = $generator;
  }

  /**
   * Generate AVC test content.
   *
   * @command avc:generate
   * @aliases avc-gen
   * @option users Number of test users to create.
   * @option groups Number of test groups to create.
   * @option projects Number of test projects to create.
   * @option documents Number of test documents to create.
   * @option resources Number of test resources to create.
   * @option assignments Number of workflow assignments to create.
   * @usage avc:generate
   *   Generate test content with default values.
   * @usage avc:generate --users=20 --groups=5
   *   Generate 20 users and 5 groups.
   */
  public function generate($options = [
    'users' => 10,
    'groups' => 3,
    'projects' => 2,
    'documents' => 5,
    'resources' => 3,
    'assignments' => 10,
  ]) {
    $this->output()->writeln('Generating AVC test content...');

    $config = [
      'users' => $options['users'],
      'groups' => $options['groups'],
      'projects' => $options['projects'],
      'documents' => $options['documents'],
      'resources' => $options['resources'],
      'workflow_assignments' => $options['assignments'],
    ];

    $summary = $this->generator->generateAll($config);

    $this->output()->writeln('');
    $this->output()->writeln('<info>Generated content:</info>');

    foreach ($summary as $type => $count) {
      $this->output()->writeln("  - {$type}: {$count}");
    }

    $this->output()->writeln('');
    $this->output()->writeln('<info>Test content generation complete!</info>');

    return self::EXIT_SUCCESS;
  }

  /**
   * Cleanup AVC test content.
   *
   * @command avc:cleanup
   * @aliases avc-clean
   * @usage avc:cleanup
   *   Remove all generated test content.
   */
  public function cleanup() {
    if (!$this->io()->confirm('This will delete all AVC test content. Continue?')) {
      $this->output()->writeln('Aborted.');
      return self::EXIT_FAILURE;
    }

    $this->output()->writeln('Cleaning up AVC test content...');

    $summary = $this->generator->cleanupAll();

    $this->output()->writeln('');
    $this->output()->writeln('<info>Deleted content:</info>');

    foreach ($summary as $type => $count) {
      $this->output()->writeln("  - {$type}: {$count}");
    }

    $this->output()->writeln('');
    $this->output()->writeln('<info>Cleanup complete!</info>');

    return self::EXIT_SUCCESS;
  }

  /**
   * Generate test users only.
   *
   * @command avc:generate-users
   * @aliases avc-users
   * @param int $count Number of users to create.
   * @usage avc:generate-users 20
   *   Generate 20 test users.
   */
  public function generateUsers($count = 10) {
    $this->output()->writeln("Generating {$count} test users...");

    $created = $this->generator->generateUsers($count);

    $this->output()->writeln("<info>Created {$created} test users.</info>");

    return self::EXIT_SUCCESS;
  }

  /**
   * Generate test groups only.
   *
   * @command avc:generate-groups
   * @aliases avc-groups
   * @param int $count Number of groups to create.
   * @usage avc:generate-groups 5
   *   Generate 5 test groups.
   */
  public function generateGroups($count = 3) {
    $this->output()->writeln("Generating {$count} test groups...");

    $created = $this->generator->generateGroups($count);

    $this->output()->writeln("<info>Created {$created} test groups.</info>");

    return self::EXIT_SUCCESS;
  }

  /**
   * Generate workflow assignments only.
   *
   * @command avc:generate-assignments
   * @aliases avc-assign
   * @param int $count Number of assignments to create.
   * @usage avc:generate-assignments 20
   *   Generate 20 workflow assignments.
   */
  public function generateAssignments($count = 10) {
    $this->output()->writeln("Generating {$count} workflow assignments...");

    $created = $this->generator->generateWorkflowAssignments($count);

    $this->output()->writeln("<info>Created {$created} workflow assignments.</info>");

    return self::EXIT_SUCCESS;
  }

}
