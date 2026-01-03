<?php

namespace Drupal\workflow_assignment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Controller for AJAX operations on workflows.
 */
class WorkflowAjaxController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a WorkflowAjaxController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * AJAX handler to save workflow field changes.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error status.
   */
  public function save(Request $request) {
    $content = json_decode($request->getContent(), TRUE);

    if (empty($content['workflow_id']) || empty($content['field'])) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Missing required parameters.'),
      ], 400);
    }

    $workflow_id = $content['workflow_id'];
    $field = $content['field'];
    $value = $content['value'] ?? '';

    // Validate field name.
    $allowed_fields = ['description', 'comments'];
    if (!in_array($field, $allowed_fields)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Invalid field name.'),
      ], 400);
    }

    try {
      $workflow = $this->entityTypeManager
        ->getStorage('workflow_list')
        ->load($workflow_id);

      if (!$workflow) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => $this->t('Workflow not found.'),
        ], 404);
      }

      // Update the field.
      $workflow->set($field, $value);
      $workflow->save();

      // Log the change.
      $this->logWorkflowChange($workflow_id, 'update', $field, $value);

      return new JsonResponse([
        'success' => TRUE,
        'message' => $this->t('Changes saved successfully.'),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Error saving changes: @error', ['@error' => $e->getMessage()]),
      ], 500);
    }
  }

  /**
   * AJAX handler to reorder workflows on a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error status.
   */
  public function reorder(NodeInterface $node, Request $request) {
    $content = json_decode($request->getContent(), TRUE);

    if (empty($content['order']) || !is_array($content['order'])) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Missing or invalid order data.'),
      ], 400);
    }

    try {
      if (!$node->hasField('field_workflow_list')) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => $this->t('Node does not have workflow field.'),
        ], 400);
      }

      // Build the new field values in the specified order.
      $new_values = [];
      foreach ($content['order'] as $workflow_id) {
        $new_values[] = ['target_id' => $workflow_id];
      }

      $node->set('field_workflow_list', $new_values);
      $node->save();

      return new JsonResponse([
        'success' => TRUE,
        'message' => $this->t('Order saved successfully.'),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $this->t('Error saving order: @error', ['@error' => $e->getMessage()]),
      ], 500);
    }
  }

  /**
   * Logs workflow changes for audit trail.
   *
   * @param string $workflow_id
   *   The workflow ID.
   * @param string $action
   *   The action performed (create, update, delete, assign, unassign).
   * @param string $field
   *   The field that was changed.
   * @param mixed $value
   *   The new value.
   */
  protected function logWorkflowChange($workflow_id, $action, $field = NULL, $value = NULL) {
    $database = \Drupal::database();

    // Check if the table exists.
    if (!$database->schema()->tableExists('workflow_assignment_history')) {
      return;
    }

    $database->insert('workflow_assignment_history')
      ->fields([
        'workflow_id' => $workflow_id,
        'action' => $action,
        'field_name' => $field,
        'new_value' => is_string($value) ? substr($value, 0, 255) : NULL,
        'uid' => $this->currentUser()->id(),
        'timestamp' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

}
