<?php

namespace Drupal\avc_content_access\EventSubscriber;

use Drupal\avc_content_access\Service\FileMigrationService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Subscribes to workflow task completion events.
 *
 * When a destination task is completed, this subscriber:
 * 1. Reads the destination's file_scheme field
 * 2. Migrates the node's files to the appropriate scheme
 * 3. Optionally publishes the node if auto_publish is enabled
 *
 * Note: Since WorkflowTask doesn't dispatch events, we hook into entity
 * presave in the .module file and use a static flag to trigger migration.
 */
class WorkflowCompletionSubscriber implements EventSubscriberInterface {

  protected FileMigrationService $fileMigration;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected $logger;

  /**
   * Queue of nodes pending file migration after destination task completion.
   */
  protected static array $pendingMigrations = [];

  public function __construct(
    FileMigrationService $file_migration,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->fileMigration = $file_migration;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('avc_content_access');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::TERMINATE => ['onTerminate', 0],
    ];
  }

  /**
   * Queue a node for file migration after a destination task completes.
   */
  public static function queueMigration(int $node_id, int $destination_term_id): void {
    self::$pendingMigrations[] = [
      'node_id' => $node_id,
      'term_id' => $destination_term_id,
    ];
  }

  /**
   * Process pending migrations on request terminate.
   */
  public function onTerminate(TerminateEvent $event): void {
    foreach (self::$pendingMigrations as $migration) {
      $this->processMigration($migration['node_id'], $migration['term_id']);
    }
    self::$pendingMigrations = [];
  }

  /**
   * Process a single file migration.
   */
  protected function processMigration(int $node_id, int $term_id): void {
    $node = $this->entityTypeManager->getStorage('node')->load($node_id);
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);

    if (!$node || !$term) {
      return;
    }

    // Get the target file scheme from the destination term.
    $file_scheme = 'public://';
    if ($term->hasField('field_file_scheme')) {
      $scheme_value = $term->get('field_file_scheme')->value;
      if ($scheme_value) {
        $file_scheme = $scheme_value;
      }
    }

    // Migrate files.
    $this->fileMigration->migrateNodeFiles($node, $file_scheme);

    // Auto-publish if enabled on the destination.
    if ($term->hasField('field_auto_publish') && $term->get('field_auto_publish')->value) {
      if (!$node->isPublished()) {
        $node->setPublished();
        $node->save();
        $this->logger->info('Auto-published node @nid for destination @dest.', [
          '@nid' => $node_id,
          '@dest' => $term->getName(),
        ]);
      }
    }
  }

}
