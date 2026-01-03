<?php

namespace Drupal\avc_guild;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Ratification entity.
 */
class RatificationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\avc_guild\Entity\Ratification $entity */

    switch ($operation) {
      case 'view':
        // Junior, mentor, or admin can view.
        if ($entity->get('junior_id')->target_id == $account->id()) {
          return AccessResult::allowed()->addCacheableDependency($entity);
        }
        if ($entity->get('mentor_id')->target_id == $account->id()) {
          return AccessResult::allowed()->addCacheableDependency($entity);
        }
        $guild = $entity->getGuild();
        if ($guild && avc_guild_can_ratify($guild, $account)) {
          return AccessResult::allowed()->addCacheableDependency($entity);
        }
        return AccessResult::allowedIfHasPermission($account, 'administer ratifications');

      case 'update':
        // Only mentors can update (to approve/reject).
        $guild = $entity->getGuild();
        if ($guild && avc_guild_can_ratify($guild, $account)) {
          return AccessResult::allowed()->addCacheableDependency($entity);
        }
        return AccessResult::allowedIfHasPermission($account, 'administer ratifications');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer ratifications');

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Ratifications are created programmatically.
    return AccessResult::allowedIfHasPermission($account, 'administer ratifications');
  }

}
