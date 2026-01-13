<?php

namespace Drupal\avc_guild;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for skill levels.
 */
class SkillLevelListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['guild'] = $this->t('Guild');
    $header['skill'] = $this->t('Skill');
    $header['level'] = $this->t('Level');
    $header['name'] = $this->t('Name');
    $header['credits'] = $this->t('Credits Required');
    $header['verification'] = $this->t('Verification Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\avc_guild\Entity\SkillLevel $entity */
    $guild = $entity->getGuild();
    $skill = $entity->getSkill();

    $row['guild'] = $guild ? $guild->label() : '-';
    $row['skill'] = $skill ? $skill->label() : '-';
    $row['level'] = $entity->getLevel();
    $row['name'] = $entity->getName();
    $row['credits'] = $entity->getCreditsRequired();
    $row['verification'] = $entity->get('verification_type')->value;

    return $row + parent::buildRow($entity);
  }

}
