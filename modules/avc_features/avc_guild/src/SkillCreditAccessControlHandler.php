<?php

namespace Drupal\avc_guild;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Skill Credit entity.
 */
class SkillCreditAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\avc_guild\Entity\SkillCredit $entity */
    switch ($operation) {
      case 'view':
        // Users can view their own credits, admins can view all.
        if ($account->hasPermission('administer skill credits')) {
          return AccessResult::allowed();
        }
        if ($entity->getUser() && $entity->getUser()->id() == $account->id()) {
          return AccessResult::allowed();
        }
        // Guild members can view each other's credits.
        return AccessResult::allowedIfHasPermission($account, 'view skill credits');

      case 'update':
      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer skill credits');

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer skill credits');
  }

}
