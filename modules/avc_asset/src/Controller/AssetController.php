<?php

namespace Drupal\avc_asset\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for asset management pages.
 */
class AssetController extends ControllerBase {

  /**
   * The asset manager service.
   *
   * @var \Drupal\avc_asset\Service\AssetManager
   */
  protected $assetManager;

  /**
   * The workflow processor service.
   *
   * @var \Drupal\avc_asset\Service\WorkflowProcessor
   */
  protected $workflowProcessor;

  /**
   * The workflow checker service.
   *
   * @var \Drupal\avc_asset\Service\WorkflowChecker
   */
  protected $workflowChecker;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->assetManager = $container->get('avc_asset.manager');
    $instance->workflowProcessor = $container->get('avc_asset.workflow_processor');
    $instance->workflowChecker = $container->get('avc_asset.workflow_checker');
    return $instance;
  }

  /**
   * Displays the asset administration list.
   *
   * @return array
   *   A render array.
   */
  public function adminList() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['avc-asset-admin']],
    ];

    // Projects section.
    $projects = $this->assetManager->getAssetsByType('project', ['limit' => 25]);
    $build['projects'] = $this->buildAssetTable('Projects', $projects);

    // Documents section.
    $documents = $this->assetManager->getAssetsByType('document', ['limit' => 25]);
    $build['documents'] = $this->buildAssetTable('Documents', $documents);

    // Resources section.
    $resources = $this->assetManager->getAssetsByType('resource', ['limit' => 25]);
    $build['resources'] = $this->buildAssetTable('Resources', $resources);

    $build['#attached']['library'][] = 'avc_asset/admin';

    return $build;
  }

  /**
   * Checks the workflow for an asset.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array|\Symfony\Component\HttpFoundation\JsonResponse
   *   A render array or JSON response.
   */
  public function checkAsset(NodeInterface $node, Request $request) {
    $result = $this->workflowChecker->check($node);

    // Return JSON if AJAX request.
    if ($request->isXmlHttpRequest()) {
      return new JsonResponse($result);
    }

    // Build render array.
    $build = [
      '#theme' => 'avc_workflow_check_result',
      '#node' => $node,
      '#result' => $result,
      '#attached' => [
        'library' => ['avc_asset/workflow'],
      ],
    ];

    return $build;
  }

  /**
   * Processes the workflow for an asset.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with result.
   */
  public function processAsset(NodeInterface $node, Request $request) {
    $result = $this->workflowProcessor->process($node);

    if ($request->isXmlHttpRequest()) {
      return new JsonResponse($result);
    }

    if ($result['success']) {
      $this->messenger()->addStatus($result['message']);
    }
    else {
      $this->messenger()->addError($result['message']);
    }

    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

  /**
   * Advances the workflow to the next stage.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with result.
   */
  public function advanceWorkflow(NodeInterface $node, Request $request) {
    $comment = $request->request->get('comment', '');
    $result = $this->workflowProcessor->advance($node, $comment);

    if ($request->isXmlHttpRequest()) {
      return new JsonResponse($result);
    }

    if ($result['success']) {
      $this->messenger()->addStatus($result['message']);
    }
    else {
      $this->messenger()->addError($result['message']);
    }

    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

  /**
   * Resends notification for current stage.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The asset node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with result.
   */
  public function resendNotification(NodeInterface $node, Request $request) {
    $result = $this->workflowProcessor->resendNotification($node);

    if ($request->isXmlHttpRequest()) {
      return new JsonResponse($result);
    }

    if ($result['success']) {
      $this->messenger()->addStatus($result['message']);
    }
    else {
      $this->messenger()->addError($result['message']);
    }

    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

  /**
   * Builds a table of assets.
   *
   * @param string $title
   *   The section title.
   * @param array $assets
   *   Array of asset nodes.
   *
   * @return array
   *   A render array.
   */
  protected function buildAssetTable($title, array $assets) {
    $build = [
      '#type' => 'details',
      '#title' => $this->t('@title (@count)', [
        '@title' => $title,
        '@count' => count($assets),
      ]),
      '#open' => TRUE,
    ];

    if (empty($assets)) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('No @title found.', ['@title' => strtolower($title)]) . '</p>',
      ];
      return $build;
    }

    $header = [
      $this->t('ID'),
      $this->t('Title'),
      $this->t('Status'),
      $this->t('Created'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($assets as $node) {
      $status = 'draft';
      if ($node->hasField('field_process_status')) {
        $status = $node->get('field_process_status')->value ?? 'draft';
      }

      $rows[] = [
        $node->id(),
        [
          'data' => [
            '#type' => 'link',
            '#title' => $node->getTitle(),
            '#url' => $node->toUrl(),
          ],
        ],
        $this->getStatusBadge($status),
        \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'short'),
        [
          'data' => [
            '#type' => 'operations',
            '#links' => $this->getOperations($node),
          ],
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No assets found.'),
      '#attributes' => ['class' => ['avc-asset-table']],
    ];

    return $build;
  }

  /**
   * Gets a status badge render array.
   *
   * @param string $status
   *   The status.
   *
   * @return array
   *   A render array.
   */
  protected function getStatusBadge($status) {
    return [
      'data' => [
        '#markup' => '<span class="status-badge status-' . $status . '">' . ucfirst($status) . '</span>',
      ],
    ];
  }

  /**
   * Gets operations links for an asset.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return array
   *   Array of operation links.
   */
  protected function getOperations(NodeInterface $node) {
    $operations = [];

    $operations['view'] = [
      'title' => $this->t('View'),
      'url' => $node->toUrl(),
    ];

    if ($node->access('update')) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $node->toUrl('edit-form'),
      ];
    }

    $operations['workflow'] = [
      'title' => $this->t('Workflow'),
      'url' => Url::fromRoute('avc_asset.check', ['node' => $node->id()]),
    ];

    return $operations;
  }

}
