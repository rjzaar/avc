<?php

namespace Drupal\avc_guild;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Skill Endorsement entity.
 */
class SkillEndorsementAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowed();

      case 'delete':
        // Only the endorser or admin can delete.
        if ($entity->get('endorser_id')->target_id == $account->id()) {
          return AccessResult::allowed()->addCacheableDependency($entity);
        }
        return AccessResult::allowedIfHasPermission($account, 'administer skill endorsements');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'administer skill endorsements');

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'endorse skills');
  }

}
