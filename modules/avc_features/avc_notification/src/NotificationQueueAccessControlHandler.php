<?php

namespace Drupal\avc_notification;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Notification Queue entity.
 */
class NotificationQueueAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\avc_notification\Entity\NotificationQueue $entity */

    switch ($operation) {
      case 'view':
        // Users can view their own notifications.
        if ($entity->get('target_user')->target_id == $account->id()) {
          return AccessResult::allowed()->addCacheableDependency($entity);
        }
        // Admins can view all.
        return AccessResult::allowedIfHasPermission($account, 'administer notification queue');

      case 'update':
      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer notification queue');

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer notification queue');
  }

}
