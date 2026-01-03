<?php

namespace Drupal\avc_notification;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Provides a list controller for the notification_queue entity type.
 */
class NotificationQueueListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['event_type'] = $this->t('Event Type');
    $header['target_user'] = $this->t('User');
    $header['asset'] = $this->t('Asset');
    $header['status'] = $this->t('Status');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\avc_notification\Entity\NotificationQueue $entity */
    $row['id'] = $entity->id();
    $row['event_type'] = $entity->get('event_type')->value;

    $user = $entity->getTargetUser();
    $row['target_user'] = $user ? Link::createFromRoute(
      $user->getDisplayName(),
      'entity.user.canonical',
      ['user' => $user->id()]
    ) : '-';

    $asset = $entity->getAsset();
    $row['asset'] = $asset ? Link::createFromRoute(
      $asset->label(),
      'entity.node.canonical',
      ['node' => $asset->id()]
    ) : '-';

    $row['status'] = $entity->get('status')->value;
    $row['created'] = date('Y-m-d H:i', $entity->get('created')->value);

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    $operations['view'] = [
      'title' => $this->t('View'),
      'weight' => -10,
      'url' => $entity->toUrl('canonical'),
    ];

    return $operations;
  }

}
