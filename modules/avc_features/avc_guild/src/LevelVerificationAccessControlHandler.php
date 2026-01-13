<?php

namespace Drupal\avc_guild;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Level Verification entity.
 */
class LevelVerificationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\avc_guild\Entity\LevelVerification $entity */
    switch ($operation) {
      case 'view':
        // Users can view their own verifications, admins can view all.
        if ($account->hasPermission('administer level verifications')) {
          return AccessResult::allowed();
        }
        if ($entity->getUser() && $entity->getUser()->id() == $account->id()) {
          return AccessResult::allowed();
        }
        // Verifiers can view pending verifications.
        return AccessResult::allowedIfHasPermission($account, 'vote on level verifications');

      case 'update':
      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer level verifications');

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer level verifications');
  }

}
