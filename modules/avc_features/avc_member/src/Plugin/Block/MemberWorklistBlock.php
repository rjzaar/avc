<?php

namespace Drupal\avc_member\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\avc_member\Service\MemberWorklistService;

/**
 * Provides a member worklist block.
 *
 * @Block(
 *   id = "avc_member_worklist",
 *   admin_label = @Translation("Member Worklist"),
 *   category = @Translation("AV Commons")
 * )
 */
class MemberWorklistBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The worklist service.
   *
   * @var \Drupal\avc_member\Service\MemberWorklistService
   */
  protected $worklistService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a MemberWorklistBlock object.
   *
   * @param array $configuration
   *   A configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\avc_member\Service\MemberWorklistService $worklist_service
   *   The worklist service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MemberWorklistService $worklist_service,
    AccountInterface $current_user,
    RouteMatchInterface $route_match
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->worklistService = $worklist_service;
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('avc_member.worklist_service'),
      $container->get('current_user'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get user from route or current user.
    $user = $this->routeMatch->getParameter('user');
    if (!$user) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser->id());
    }

    if (!$user) {
      return [];
    }

    $worklist = $this->worklistService->getUserWorklist($user);

    // Filter to show only current/actionable items.
    $actionable = array_filter($worklist, function ($item) {
      return $item['status'] === 'current';
    });

    return [
      '#theme' => 'member_worklist',
      '#items' => $actionable,
      '#empty_message' => $this->t('No tasks requiring your attention.'),
      '#cache' => [
        'tags' => ['user:' . $user->id()],
        'contexts' => ['user'],
      ],
    ];
  }

}
