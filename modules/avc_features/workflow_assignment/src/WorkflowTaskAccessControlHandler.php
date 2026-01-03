<?php

namespace Drupal\workflow_assignment;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Workflow Task entity.
 */
class WorkflowTaskAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\workflow_assignment\Entity\WorkflowTask $entity */

    switch ($operation) {
      case 'view':
        // Allow if user has permission or is assigned to this task.
        if ($account->hasPermission('view workflow tasks')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        // Check if user is assigned to this task.
        $assigned_type = $entity->get('assigned_type')->value;
        if ($assigned_type === 'user') {
          $assigned_user = $entity->get('assigned_user')->target_id;
          if ($assigned_user == $account->id()) {
            return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
          }
        }
        return AccessResult::neutral();

      case 'update':
        // Allow if user has admin permission or is assigned to this task.
        if ($account->hasPermission('administer workflow tasks')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($account->hasPermission('edit own workflow tasks')) {
          $assigned_type = $entity->get('assigned_type')->value;
          if ($assigned_type === 'user') {
            $assigned_user = $entity->get('assigned_user')->target_id;
            if ($assigned_user == $account->id()) {
              return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
            }
          }
        }
        return AccessResult::neutral();

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer workflow tasks');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer workflow tasks',
      'create workflow tasks',
    ], 'OR');
  }

}
