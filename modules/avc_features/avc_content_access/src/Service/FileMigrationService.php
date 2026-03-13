<?php

namespace Drupal\avc_content_access\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * Service for migrating files between public:// and private:// schemes.
 *
 * When content completes its workflow and reaches a destination, files may
 * need to be moved between public and private storage based on the
 * destination's file_scheme field.
 */
class FileMigrationService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected FileSystemInterface $fileSystem;
  protected $logger;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('avc_content_access');
  }

  /**
   * Migrate all files attached to a node to a new scheme.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node whose files should be migrated.
   * @param string $target_scheme
   *   The target scheme (e.g., 'public://' or 'private://').
   *
   * @return array
   *   Array of migration results with 'success' and 'failed' counts.
   */
  public function migrateNodeFiles(NodeInterface $node, string $target_scheme): array {
    $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

    // Get all file fields on the node.
    $file_fields = $this->getFileFields($node);

    foreach ($file_fields as $field_name) {
      if (!$node->hasField($field_name)) {
        continue;
      }

      foreach ($node->get($field_name) as $item) {
        if (!$item->target_id) {
          continue;
        }

        $file = $this->entityTypeManager->getStorage('file')->load($item->target_id);
        if (!$file) {
          continue;
        }

        $current_uri = $file->getFileUri();
        $current_scheme = strstr($current_uri, '://', TRUE) ?: '';

        // Skip if already in target scheme.
        $target_scheme_name = rtrim($target_scheme, ':/');
        if ($current_scheme === $target_scheme_name) {
          $results['skipped']++;
          continue;
        }

        $result = $this->migrateFile($file, $target_scheme);
        if ($result) {
          $results['success']++;
        }
        else {
          $results['failed']++;
        }
      }
    }

    if ($results['success'] > 0 || $results['failed'] > 0) {
      $this->logger->info('File migration for node @nid: @success migrated, @failed failed, @skipped skipped.', [
        '@nid' => $node->id(),
        '@success' => $results['success'],
        '@failed' => $results['failed'],
        '@skipped' => $results['skipped'],
      ]);
    }

    return $results;
  }

  /**
   * Migrate a single file to a new scheme.
   */
  protected function migrateFile(object $file, string $target_scheme): bool {
    $current_uri = $file->getFileUri();
    $filename = $this->fileSystem->basename($current_uri);

    // Build new URI preserving directory structure after scheme.
    $path_parts = explode('://', $current_uri, 2);
    $relative_path = $path_parts[1] ?? $filename;
    $new_uri = $target_scheme . $relative_path;

    // Ensure the target directory exists.
    $directory = $this->fileSystem->dirname($new_uri);
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    try {
      $new_uri = $this->fileSystem->move($current_uri, $new_uri, FileSystemInterface::EXISTS_RENAME);
      if ($new_uri) {
        $file->setFileUri($new_uri);
        $file->save();
        return TRUE;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to migrate file @fid from @old to @new: @message', [
        '@fid' => $file->id(),
        '@old' => $current_uri,
        '@new' => $new_uri,
        '@message' => $e->getMessage(),
      ]);
    }

    return FALSE;
  }

  /**
   * Get the file reference field names for a node.
   */
  protected function getFileFields(NodeInterface $node): array {
    $file_fields = [];
    $field_definitions = $node->getFieldDefinitions();

    foreach ($field_definitions as $field_name => $definition) {
      $type = $definition->getType();
      if (in_array($type, ['file', 'image'])) {
        $file_fields[] = $field_name;
      }
    }

    return $file_fields;
  }

}
