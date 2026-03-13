<?php

namespace Drupal\avc_content_access\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Service for checking file access based on destination access levels.
 *
 * When files are stored in private://, Drupal calls hook_file_download() to
 * determine access. This service checks whether the requesting user can
 * access the file based on the destination of its parent node.
 */
class FileAccessService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountInterface $currentUser;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Check access to a private file based on parent node's destination.
   *
   * @return array|int
   *   An array of headers if access is granted, or -1 to deny access.
   *   Returns NULL (no opinion) if the file isn't managed by this module.
   */
  public function checkFileAccess(string $uri, AccountInterface $account): mixed {
    if ($account->hasPermission('bypass destination access')) {
      return NULL;
    }

    // Find the file entity for this URI.
    $files = $this->entityTypeManager->getStorage('file')
      ->loadByProperties(['uri' => $uri]);

    if (empty($files)) {
      return NULL;
    }

    $file = reset($files);

    // Find nodes referencing this file.
    $node = $this->findParentNode($file);
    if (!$node) {
      return NULL;
    }

    // Check destination access.
    /** @var \Drupal\avc_content_access\Access\DestinationAccessManager $manager */
    $manager = \Drupal::service('avc_content_access.destination_access_manager');
    $access = $manager->checkAccess($node, 'view', $account);

    if ($access->isForbidden()) {
      return -1;
    }

    // No opinion - let other modules decide.
    return NULL;
  }

  /**
   * Find the parent node for a file entity.
   */
  protected function findParentNode(object $file): ?object {
    // Check common file reference fields on nodes.
    $file_fields = ['field_files', 'field_document', 'field_image', 'field_media'];

    foreach ($file_fields as $field_name) {
      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition($field_name, $file->id())
        ->range(0, 1);

      try {
        $nids = $query->execute();
        if (!empty($nids)) {
          return $this->entityTypeManager->getStorage('node')->load(reset($nids));
        }
      }
      catch (\Exception $e) {
        // Field doesn't exist on any node type, continue.
        continue;
      }
    }

    return NULL;
  }

}
