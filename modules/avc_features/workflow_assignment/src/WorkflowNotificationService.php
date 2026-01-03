<?php

namespace Drupal\workflow_assignment;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Service for sending workflow notification emails.
 */
class WorkflowNotificationService {

  use StringTranslationTrait;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a WorkflowNotificationService object.
   */
  public function __construct(
    MailManagerInterface $mail_manager,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    AccountInterface $current_user
  ) {
    $this->mailManager = $mail_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
  }

  /**
   * Sends notification when workflows are assigned to a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param array $added_workflows
   *   Array of workflow IDs that were added.
   * @param array $removed_workflows
   *   Array of workflow IDs that were removed.
   */
  public function sendAssignmentNotifications(NodeInterface $node, array $added_workflows, array $removed_workflows) {
    $config = $this->configFactory->get('workflow_assignment.settings');

    // Check if notifications are enabled.
    if (!$config->get('enable_notifications')) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('workflow_list');

    // Send notifications for added workflows.
    foreach ($added_workflows as $workflow_id) {
      $workflow = $storage->load($workflow_id);
      if ($workflow) {
        $this->sendNotification($node, $workflow, 'assigned');
      }
    }

    // Send notifications for removed workflows.
    foreach ($removed_workflows as $workflow_id) {
      $workflow = $storage->load($workflow_id);
      if ($workflow) {
        $this->sendNotification($node, $workflow, 'unassigned');
      }
    }
  }

  /**
   * Sends a notification email.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param object $workflow
   *   The workflow entity.
   * @param string $action
   *   The action (assigned or unassigned).
   */
  protected function sendNotification(NodeInterface $node, $workflow, $action) {
    $assigned_type = $workflow->getAssignedType();
    $assigned_id = $workflow->getAssignedId();

    if (!$assigned_type || !$assigned_id) {
      return;
    }

    $recipients = $this->getRecipients($assigned_type, $assigned_id);

    if (empty($recipients)) {
      return;
    }

    $params = [
      'node' => $node,
      'workflow' => $workflow,
      'action' => $action,
      'assigned_by' => $this->currentUser->getDisplayName(),
    ];

    foreach ($recipients as $email) {
      $this->mailManager->mail(
        'workflow_assignment',
        'workflow_notification',
        $email,
        'en',
        $params,
        NULL,
        TRUE
      );
    }
  }

  /**
   * Gets recipient email addresses based on assignment type.
   *
   * @param string $type
   *   The assignment type (user, group, destination).
   * @param int|string $id
   *   The assigned entity ID.
   *
   * @return array
   *   Array of email addresses.
   */
  protected function getRecipients($type, $id) {
    $emails = [];

    switch ($type) {
      case 'user':
        $user = $this->entityTypeManager->getStorage('user')->load($id);
        if ($user && $user->getEmail()) {
          $emails[] = $user->getEmail();
        }
        break;

      case 'group':
        // Get all members of the group if Group module is available.
        if (\Drupal::moduleHandler()->moduleExists('group')) {
          try {
            $group = $this->entityTypeManager->getStorage('group')->load($id);
            if ($group) {
              $members = $group->getMembers();
              foreach ($members as $membership) {
                $user = $membership->getUser();
                if ($user && $user->getEmail()) {
                  $emails[] = $user->getEmail();
                }
              }
            }
          }
          catch (\Exception $e) {
            // Group module not properly configured.
          }
        }
        break;

      case 'destination':
        // For destinations, we could potentially notify users with a specific role
        // or users who have subscribed to that destination.
        // This would require additional configuration.
        break;
    }

    return array_unique($emails);
  }

}
