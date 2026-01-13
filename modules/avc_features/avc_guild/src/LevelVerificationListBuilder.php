<?php

namespace Drupal\avc_guild;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for level verifications.
 */
class LevelVerificationListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['user'] = $this->t('User');
    $header['guild'] = $this->t('Guild');
    $header['skill'] = $this->t('Skill');
    $header['target_level'] = $this->t('Target Level');
    $header['status'] = $this->t('Status');
    $header['votes'] = $this->t('Votes');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\avc_guild\Entity\LevelVerification $entity */
    $user = $entity->getUser();
    $guild = $entity->getGuild();
    $skill = $entity->getSkill();

    $row['user'] = $user ? $user->getDisplayName() : '-';
    $row['guild'] = $guild ? $guild->label() : '-';
    $row['skill'] = $skill ? $skill->label() : '-';
    $row['target_level'] = $entity->getTargetLevel();
    $row['status'] = $entity->getStatus();
    $row['votes'] = sprintf('%d/%d',
      $entity->getApproveVotes(),
      $entity->getVotesRequired()
    );
    $row['created'] = date('Y-m-d H:i', $entity->get('created')->value);

    return $row + parent::buildRow($entity);
  }

}
