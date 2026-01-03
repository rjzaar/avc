<?php

namespace Drupal\avc_guild\Controller;

use Drupal\avc_guild\Service\RatificationService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for ratification queue pages.
 */
class RatificationQueueController extends ControllerBase {

  /**
   * The ratification service.
   *
   * @var \Drupal\avc_guild\Service\RatificationService
   */
  protected $ratificationService;

  /**
   * Constructs a RatificationQueueController.
   *
   * @param \Drupal\avc_guild\Service\RatificationService $ratification_service
   *   The ratification service.
   */
  public function __construct(RatificationService $ratification_service) {
    $this->ratificationService = $ratification_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_guild.ratification')
    );
  }

  /**
   * Ratification queue page.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return array
   *   Render array.
   */
  public function queue(GroupInterface $group) {
    $pending = $this->ratificationService->getPendingForGuild($group);

    $items = [];
    foreach ($pending as $ratification) {
      $junior = $ratification->getJunior();
      $asset = $ratification->getAsset();
      $mentor = $ratification->getMentor();
      $task = $ratification->getTask();

      $items[] = [
        'id' => $ratification->id(),
        'junior' => $junior ? $junior->getDisplayName() : '-',
        'junior_link' => $junior ? Url::fromRoute('avc_guild.member_profile', [
          'group' => $group->id(),
          'user' => $junior->id(),
        ]) : NULL,
        'asset' => $asset ? $asset->label() : '-',
        'asset_link' => $asset ? $asset->toUrl() : NULL,
        'task' => $task ? $task->label() : '-',
        'mentor' => $mentor ? $mentor->getDisplayName() : $this->t('Unclaimed'),
        'created' => date('Y-m-d H:i', $ratification->get('created')->value),
        'review_url' => Url::fromRoute('entity.ratification.edit_form', [
          'ratification' => $ratification->id(),
        ]),
        'claim_url' => $mentor ? NULL : Url::fromRoute('avc_guild.ratification_claim', [
          'group' => $group->id(),
          'ratification' => $ratification->id(),
        ]),
      ];
    }

    return [
      '#theme' => 'ratification_queue',
      '#items' => $items,
      '#empty_message' => $this->t('No pending ratifications.'),
      '#cache' => [
        'tags' => $group->getCacheTags(),
      ],
    ];
  }

  /**
   * Queue title callback.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   *
   * @return string
   *   The page title.
   */
  public function queueTitle(GroupInterface $group) {
    return $this->t('Ratification Queue - @guild', ['@guild' => $group->label()]);
  }

  /**
   * Claim a ratification.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param int $ratification
   *   The ratification ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the review form.
   */
  public function claim(GroupInterface $group, $ratification) {
    $ratification_entity = $this->entityTypeManager()
      ->getStorage('ratification')
      ->load($ratification);

    if ($ratification_entity && $ratification_entity->isPending()) {
      $this->ratificationService->claim($ratification_entity, $this->currentUser());
      $this->messenger()->addStatus($this->t('You have claimed this ratification.'));

      return new RedirectResponse(Url::fromRoute('entity.ratification.edit_form', [
        'ratification' => $ratification,
      ])->toString());
    }

    $this->messenger()->addError($this->t('Unable to claim ratification.'));
    return new RedirectResponse(Url::fromRoute('avc_guild.ratification_queue', [
      'group' => $group->id(),
    ])->toString());
  }

  /**
   * Access check for ratification queue.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The guild.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(GroupInterface $group, AccountInterface $account) {
    // Must be a guild.
    if (!avc_guild_is_guild($group)) {
      return AccessResult::forbidden('Not a guild.');
    }

    // Must be able to ratify (mentor or admin).
    if (avc_guild_can_ratify($group, $account)) {
      return AccessResult::allowed()->addCacheableDependency($group);
    }

    return AccessResult::forbidden()->addCacheableDependency($group);
  }

}
