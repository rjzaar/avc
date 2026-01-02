<?php

namespace Drupal\avc_guild;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for guild scores.
 */
class GuildScoreListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['user'] = $this->t('User');
    $header['guild'] = $this->t('Guild');
    $header['action'] = $this->t('Action');
    $header['points'] = $this->t('Points');
    $header['created'] = $this->t('Date');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\avc_guild\Entity\GuildScore $entity */
    $user = $entity->getUser();
    $guild = $entity->getGuild();

    $row['user'] = $user ? $user->getDisplayName() : '-';
    $row['guild'] = $guild ? $guild->label() : '-';
    $row['action'] = $entity->get('action_type')->value;
    $row['points'] = $entity->getPoints();
    $row['created'] = date('Y-m-d H:i', $entity->get('created')->value);

    return $row + parent::buildRow($entity);
  }

}
