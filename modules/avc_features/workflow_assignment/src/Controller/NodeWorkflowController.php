<?php

namespace Drupal\workflow_assignment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Controller for the workflow tab on nodes.
 */
class NodeWorkflowController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a NodeWorkflowController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    FormBuilderInterface $form_builder
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('form_builder')
    );
  }

  /**
   * Displays the workflow tab content.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   A render array.
   */
  public function workflowTab(NodeInterface $node) {
    // Get workflow tasks for this node.
    $tasks = $this->getNodeWorkflowTasks($node);

    $can_edit = $this->currentUser()->hasPermission('assign workflow lists to content')
      || $this->currentUser()->hasPermission('create workflow tasks');
    $can_administer = $this->currentUser()->hasPermission('administer workflow lists')
      || $this->currentUser()->hasPermission('administer workflow tasks');

    // Render using template.
    $build = [
      '#theme' => 'workflow_tab_content',
      '#workflows' => $tasks,
      '#node' => $node,
      '#can_edit' => $can_edit,
      '#can_administer' => $can_administer,
      '#attached' => [
        'library' => ['workflow_assignment/workflow_tab'],
        'drupalSettings' => [
          'workflowAssignment' => [
            'nodeId' => $node->id(),
            'canEdit' => $can_administer,
            'canReorder' => $can_edit,
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Gets workflow tasks for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   Array of workflow task entities, sorted by weight.
   */
  protected function getNodeWorkflowTasks(NodeInterface $node) {
    if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('workflow_task');
    $query = $storage->getQuery()
      ->condition('node_id', $node->id())
      ->sort('weight', 'ASC')
      ->sort('created', 'ASC')
      ->accessCheck(TRUE);

    $ids = $query->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Adds a workflow task to a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   A render array with the add form.
   */
  public function addTask(NodeInterface $node) {
    // Create a new workflow task with the node pre-filled.
    $task = $this->entityTypeManager->getStorage('workflow_task')->create([
      'node_id' => $node->id(),
    ]);

    $form = $this->entityTypeManager
      ->getFormObject('workflow_task', 'add')
      ->setEntity($task);

    return $this->formBuilder->getForm($form);
  }

}
