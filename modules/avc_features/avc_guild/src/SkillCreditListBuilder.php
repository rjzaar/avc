<?php

namespace Drupal\avc_guild;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for skill credits.
 */
class SkillCreditListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['user'] = $this->t('User');
    $header['guild'] = $this->t('Guild');
    $header['skill'] = $this->t('Skill');
    $header['credits'] = $this->t('Credits');
    $header['source'] = $this->t('Source');
    $header['reviewer'] = $this->t('Reviewer');
    $header['created'] = $this->t('Date');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\avc_guild\Entity\SkillCredit $entity */
    $user = $entity->getUser();
    $guild = $entity->getGuild();
    $skill = $entity->getSkill();
    $reviewer = $entity->getReviewer();

    $row['user'] = $user ? $user->getDisplayName() : '-';
    $row['guild'] = $guild ? $guild->label() : '-';
    $row['skill'] = $skill ? $skill->label() : '-';
    $row['credits'] = $entity->getCredits();
    $row['source'] = $entity->getSourceType();
    $row['reviewer'] = $reviewer ? $reviewer->getDisplayName() : '-';
    $row['created'] = date('Y-m-d H:i', $entity->get('created')->value);

    return $row + parent::buildRow($entity);
  }

}
