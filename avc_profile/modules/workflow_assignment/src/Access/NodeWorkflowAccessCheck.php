<?php

namespace Drupal\workflow_assignment\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access for displaying the workflow tab on nodes.
 */
class NodeWorkflowAccessCheck implements AccessInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a NodeWorkflowAccessCheck object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Checks access to the workflow tab.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    // Get the node from the route.
    $node = $route_match->getParameter('node');

    if (!$node) {
      return AccessResult::forbidden();
    }

    // Check if workflow is enabled for this content type.
    $config = $this->configFactory->get('workflow_assignment.settings');
    $enabled_types = $config->get('enabled_content_types') ?: [];

    if (!in_array($node->bundle(), $enabled_types)) {
      return AccessResult::forbidden()->addCacheableDependency($config);
    }

    // Allow access if the user has the view permission.
    return AccessResult::allowedIfHasPermission($account, 'view workflow list assignments')
      ->addCacheableDependency($config)
      ->addCacheableDependency($node);
  }

}
