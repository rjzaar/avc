<?php

namespace Drupal\avc_guild;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Member Skill Progress entity.
 */
class MemberSkillProgressAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\avc_guild\Entity\MemberSkillProgress $entity */
    switch ($operation) {
      case 'view':
        // Users can view their own progress, admins can view all.
        if ($account->hasPermission('administer member skill progress')) {
          return AccessResult::allowed();
        }
        if ($entity->getUser() && $entity->getUser()->id() == $account->id()) {
          return AccessResult::allowed();
        }
        // Guild members can view each other's progress.
        return AccessResult::allowedIfHasPermission($account, 'view member skill progress');

      case 'update':
      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer member skill progress');

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer member skill progress');
  }

}
