<?php

namespace Drupal\avc_guild;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for skill endorsements.
 */
class SkillEndorsementListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['endorser'] = $this->t('Endorser');
    $header['endorsed'] = $this->t('Endorsed');
    $header['skill'] = $this->t('Skill');
    $header['guild'] = $this->t('Guild');
    $header['created'] = $this->t('Date');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\avc_guild\Entity\SkillEndorsement $entity */
    $endorser = $entity->getEndorser();
    $endorsed = $entity->getEndorsedUser();
    $skill = $entity->getSkill();
    $guild = $entity->getGuild();

    $row['endorser'] = $endorser ? $endorser->getDisplayName() : '-';
    $row['endorsed'] = $endorsed ? $endorsed->getDisplayName() : '-';
    $row['skill'] = $skill ? $skill->label() : '-';
    $row['guild'] = $guild ? $guild->label() : '-';
    $row['created'] = date('Y-m-d H:i', $entity->get('created')->value);

    return $row + parent::buildRow($entity);
  }

}
