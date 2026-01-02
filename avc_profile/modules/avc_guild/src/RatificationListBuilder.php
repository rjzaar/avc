<?php

namespace Drupal\avc_guild;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for ratifications.
 */
class RatificationListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['junior'] = $this->t('Junior');
    $header['asset'] = $this->t('Asset');
    $header['guild'] = $this->t('Guild');
    $header['status'] = $this->t('Status');
    $header['mentor'] = $this->t('Mentor');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\avc_guild\Entity\Ratification $entity */
    $junior = $entity->getJunior();
    $asset = $entity->getAsset();
    $guild = $entity->getGuild();
    $mentor = $entity->getMentor();

    $row['junior'] = $junior ? $junior->getDisplayName() : '-';
    $row['asset'] = $asset ? $asset->label() : '-';
    $row['guild'] = $guild ? $guild->label() : '-';
    $row['status'] = $entity->getStatus();
    $row['mentor'] = $mentor ? $mentor->getDisplayName() : '-';
    $row['created'] = date('Y-m-d H:i', $entity->get('created')->value);

    return $row + parent::buildRow($entity);
  }

}
