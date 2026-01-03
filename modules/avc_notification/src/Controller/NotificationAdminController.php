<?php

namespace Drupal\avc_notification\Controller;

use Drupal\avc_notification\Service\NotificationProcessor;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for notification administration.
 */
class NotificationAdminController extends ControllerBase {

  /**
   * The notification processor.
   *
   * @var \Drupal\avc_notification\Service\NotificationProcessor
   */
  protected $processor;

  /**
   * Constructs a NotificationAdminController.
   *
   * @param \Drupal\avc_notification\Service\NotificationProcessor $processor
   *   The notification processor.
   */
  public function __construct(NotificationProcessor $processor) {
    $this->processor = $processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_notification.processor')
    );
  }

  /**
   * Process notification queue.
   */
  public function process() {
    $this->processor->processQueue();
    $this->messenger()->addStatus($this->t('Notification queue processed.'));
    return new RedirectResponse(Url::fromRoute('avc_notification.queue')->toString());
  }

  /**
   * Force run daily digest.
   */
  public function forceDailyDigest() {
    $this->processor->forceRunDailyDigest();
    $this->messenger()->addStatus($this->t('Daily digest processed.'));
    return new RedirectResponse(Url::fromRoute('avc_notification.queue')->toString());
  }

  /**
   * Force run weekly digest.
   */
  public function forceWeeklyDigest() {
    $this->processor->forceRunWeeklyDigest();
    $this->messenger()->addStatus($this->t('Weekly digest processed.'));
    return new RedirectResponse(Url::fromRoute('avc_notification.queue')->toString());
  }

}
