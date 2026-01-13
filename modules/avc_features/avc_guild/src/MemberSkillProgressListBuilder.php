<?php

namespace Drupal\avc_guild;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for member skill progress.
 */
class MemberSkillProgressListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['user'] = $this->t('User');
    $header['guild'] = $this->t('Guild');
    $header['skill'] = $this->t('Skill');
    $header['level'] = $this->t('Current Level');
    $header['credits'] = $this->t('Current Credits');
    $header['pending'] = $this->t('Pending');
    $header['updated'] = $this->t('Last Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\avc_guild\Entity\MemberSkillProgress $entity */
    $user = $entity->getUser();
    $guild = $entity->getGuild();
    $skill = $entity->getSkill();

    $row['user'] = $user ? $user->getDisplayName() : '-';
    $row['guild'] = $guild ? $guild->label() : '-';
    $row['skill'] = $skill ? $skill->label() : '-';
    $row['level'] = $entity->getCurrentLevel();
    $row['credits'] = $entity->getCurrentCredits();
    $row['pending'] = $entity->isPendingVerification() ? $this->t('Yes') : $this->t('No');
    $row['updated'] = date('Y-m-d H:i', $entity->get('changed')->value);

    return $row + parent::buildRow($entity);
  }

}
