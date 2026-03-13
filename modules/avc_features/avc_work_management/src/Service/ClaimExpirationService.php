<?php

namespace Drupal\avc_work_management\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for processing expired claims and sending warnings.
 *
 * Called by cron to automatically release tasks whose claims have expired
 * and to send warning notifications before expiry.
 */
class ClaimExpirationService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected WorkTaskActionService $taskAction;
  protected TimeInterface $time;
  protected $logger;
  protected ConfigFactoryInterface $configFactory;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    WorkTaskActionService $task_action,
    TimeInterface $time,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->taskAction = $task_action;
    $this->time = $time;
    $this->logger = $logger_factory->get('avc_work_management');
    $this->configFactory = $config_factory;
  }

  /**
   * Process all expired claims - release them back to their groups.
   *
   * @return int
   *   Number of claims released.
   */
  public function processExpiredClaims(): int {
    $now = $this->time->getCurrentTime();
    $storage = $this->entityTypeManager->getStorage('workflow_task');

    // Find tasks with expired claims.
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('assigned_type', 'user')
      ->condition('status', 'in_progress')
      ->condition('claim_expires', $now, '<')
      ->condition('claim_expires', 0, '>')
      ->execute();

    $released = 0;
    foreach ($ids as $id) {
      $task = $storage->load($id);
      if (!$task) {
        continue;
      }

      $result = $this->taskAction->releaseTask($task, NULL, 'claim_expired');
      if ($result) {
        $released++;
        $this->logger->info('Auto-released expired claim on task @id', [
          '@id' => $id,
        ]);
      }
    }

    if ($released > 0) {
      $this->logger->info('Cron: Released @count expired claims.', [
        '@count' => $released,
      ]);
    }

    return $released;
  }

  /**
   * Send warning notifications for claims about to expire.
   *
   * @return int
   *   Number of warnings sent.
   */
  public function sendExpiryWarnings(): int {
    $config = $this->configFactory->get('avc_work_management.settings');
    $warning_hours = $config->get('claim_settings.warning_threshold') ?? 4;
    $now = $this->time->getCurrentTime();
    $warning_threshold = $now + ($warning_hours * 3600);

    $storage = $this->entityTypeManager->getStorage('workflow_task');

    // Find tasks expiring within the warning threshold that haven't been warned.
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('assigned_type', 'user')
      ->condition('status', 'in_progress')
      ->condition('claim_expires', $now, '>')
      ->condition('claim_expires', $warning_threshold, '<')
      ->condition('expiry_warning_sent', FALSE)
      ->execute();

    $warned = 0;
    foreach ($ids as $id) {
      $task = $storage->load($id);
      if (!$task) {
        continue;
      }

      // Mark warning as sent.
      $task->set('expiry_warning_sent', TRUE);
      $task->save();

      // Send notification via avc_notification if available.
      $this->sendExpiryWarningNotification($task);

      $warned++;
    }

    if ($warned > 0) {
      $this->logger->info('Cron: Sent @count claim expiry warnings.', [
        '@count' => $warned,
      ]);
    }

    return $warned;
  }

  /**
   * Send an expiry warning notification for a task.
   */
  protected function sendExpiryWarningNotification(object $task): void {
    $assigned_user_id = $task->get('assigned_user')->target_id;
    if (!$assigned_user_id) {
      return;
    }

    $remaining = $this->taskAction->getClaimTimeRemaining($task);
    $hours = round($remaining / 3600, 1);

    // Use avc_notification if available, otherwise log.
    if (\Drupal::moduleHandler()->moduleExists('avc_notification')) {
      try {
        $notification_service = \Drupal::service('avc_notification.processor');
        $notification_service->createNotification([
          'type' => 'claim_expiry_warning',
          'recipient' => $assigned_user_id,
          'message' => sprintf(
            'Your claim on task "%s" expires in %.1f hours. Extend or complete it before it is released.',
            $task->get('title')->value,
            $hours
          ),
          'entity_type' => 'workflow_task',
          'entity_id' => $task->id(),
        ]);
      }
      catch (\Exception $e) {
        $this->logger->warning('Could not send expiry warning notification for task @id: @message', [
          '@id' => $task->id(),
          '@message' => $e->getMessage(),
        ]);
      }
    }
  }

}
