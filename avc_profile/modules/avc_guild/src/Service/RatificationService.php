<?php

namespace Drupal\avc_guild\Service;

use Drupal\avc_guild\Entity\GuildScore;
use Drupal\avc_guild\Entity\Ratification;
use Drupal\avc_notification\Service\NotificationService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\workflow_assignment\Entity\WorkflowTask;

/**
 * Service for managing ratification workflow.
 */
class RatificationService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The scoring service.
   *
   * @var \Drupal\avc_guild\Service\ScoringService
   */
  protected $scoringService;

  /**
   * The notification service.
   *
   * @var \Drupal\avc_notification\Service\NotificationService|null
   */
  protected $notificationService;

  /**
   * Constructs a RatificationService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\avc_guild\Service\ScoringService $scoring_service
   *   The scoring service.
   * @param \Drupal\avc_notification\Service\NotificationService|null $notification_service
   *   The notification service (optional).
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ScoringService $scoring_service,
    ?NotificationService $notification_service = NULL
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->scoringService = $scoring_service;
    $this->notificationService = $notification_service;
  }

  /**
   * Create a ratification request.
   *
   * @param \Drupal\workflow_assignment\Entity\WorkflowTask $task
   *   The workflow task.
   * @param \Drupal\Core\Session\AccountInterface $junior
   *   The junior who completed the work.
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild context.
   *
   * @return \Drupal\avc_guild\Entity\Ratification
   *   The ratification entity.
   */
  public function createRatificationRequest(
    WorkflowTask $task,
    AccountInterface $junior,
    GroupInterface $guild
  ) {
    $asset = $task->getNode();

    /** @var \Drupal\avc_guild\Entity\Ratification $ratification */
    $ratification = $this->entityTypeManager
      ->getStorage('ratification')
      ->create([
        'task_id' => $task->id(),
        'asset_id' => $asset ? $asset->id() : NULL,
        'junior_id' => $junior->id(),
        'guild_id' => $guild->id(),
        'status' => Ratification::STATUS_PENDING,
      ]);

    $ratification->save();

    // Notify mentors.
    $this->notifyMentors($ratification);

    return $ratification;
  }

  /**
   * Approve a ratification.
   *
   * @param \Drupal\avc_guild\Entity\Ratification $ratification
   *   The ratification.
   * @param \Drupal\Core\Session\AccountInterface $mentor
   *   The mentor approving.
   * @param string $feedback
   *   Optional feedback.
   */
  public function approve(
    Ratification $ratification,
    AccountInterface $mentor,
    string $feedback = ''
  ) {
    $ratification->approve($mentor, $feedback);
    $ratification->save();

    $guild = $ratification->getGuild();
    $junior = $ratification->getJunior();
    $asset = $ratification->getAsset();

    // Award points to junior (ratified bonus).
    $this->scoringService->awardPoints(
      $junior,
      $guild,
      GuildScore::ACTION_TASK_RATIFIED,
      NULL,
      NULL,
      'ratification',
      $ratification->id()
    );

    // Award points to mentor for giving ratification.
    $this->scoringService->awardPoints(
      $mentor,
      $guild,
      GuildScore::ACTION_RATIFICATION_GIVEN,
      NULL,
      NULL,
      'ratification',
      $ratification->id()
    );

    // Notify junior.
    if ($this->notificationService && $asset) {
      $this->notificationService->queueRatificationComplete(
        $junior,
        $mentor,
        $asset,
        TRUE,
        $feedback,
        $guild
      );
    }

    // Update task status.
    $task = $ratification->getTask();
    if ($task) {
      $task->set('status', 'completed');
      $task->save();
    }
  }

  /**
   * Request changes to a ratification.
   *
   * @param \Drupal\avc_guild\Entity\Ratification $ratification
   *   The ratification.
   * @param \Drupal\Core\Session\AccountInterface $mentor
   *   The mentor requesting changes.
   * @param string $feedback
   *   Required feedback.
   */
  public function requestChanges(
    Ratification $ratification,
    AccountInterface $mentor,
    string $feedback
  ) {
    $ratification->requestChanges($mentor, $feedback);
    $ratification->save();

    $guild = $ratification->getGuild();
    $junior = $ratification->getJunior();
    $asset = $ratification->getAsset();

    // Notify junior.
    if ($this->notificationService && $asset) {
      $this->notificationService->queueRatificationComplete(
        $junior,
        $mentor,
        $asset,
        FALSE,
        $feedback,
        $guild
      );
    }

    // Update task status back to in_progress.
    $task = $ratification->getTask();
    if ($task) {
      $task->set('status', 'in_progress');
      $task->save();
    }
  }

  /**
   * Claim a ratification for review.
   *
   * @param \Drupal\avc_guild\Entity\Ratification $ratification
   *   The ratification.
   * @param \Drupal\Core\Session\AccountInterface $mentor
   *   The mentor claiming.
   */
  public function claim(Ratification $ratification, AccountInterface $mentor) {
    $ratification->set('mentor_id', $mentor->id());
    $ratification->save();
  }

  /**
   * Get pending ratifications for a guild.
   *
   * @param \Drupal\group\Entity\GroupInterface $guild
   *   The guild.
   *
   * @return \Drupal\avc_guild\Entity\Ratification[]
   *   Array of pending ratifications.
   */
  public function getPendingForGuild(GroupInterface $guild) {
    $ids = $this->entityTypeManager
      ->getStorage('ratification')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('guild_id', $guild->id())
      ->condition('status', Ratification::STATUS_PENDING)
      ->sort('created', 'ASC')
      ->execute();

    return $this->entityTypeManager
      ->getStorage('ratification')
      ->loadMultiple($ids);
  }

  /**
   * Get pending ratifications for a mentor.
   *
   * @param \Drupal\Core\Session\AccountInterface $mentor
   *   The mentor.
   * @param \Drupal\group\Entity\GroupInterface|null $guild
   *   Optional guild filter.
   *
   * @return \Drupal\avc_guild\Entity\Ratification[]
   *   Array of pending ratifications.
   */
  public function getPendingForMentor(AccountInterface $mentor, ?GroupInterface $guild = NULL) {
    $query = $this->entityTypeManager
      ->getStorage('ratification')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', Ratification::STATUS_PENDING);

    if ($guild) {
      $query->condition('guild_id', $guild->id());
    }

    // Include unclaimed or claimed by this mentor.
    $or_group = $query->orConditionGroup()
      ->notExists('mentor_id')
      ->condition('mentor_id', $mentor->id());
    $query->condition($or_group);

    $ids = $query->sort('created', 'ASC')->execute();

    return $this->entityTypeManager
      ->getStorage('ratification')
      ->loadMultiple($ids);
  }

  /**
   * Get ratifications for a junior.
   *
   * @param \Drupal\Core\Session\AccountInterface $junior
   *   The junior.
   * @param string|null $status
   *   Optional status filter.
   *
   * @return \Drupal\avc_guild\Entity\Ratification[]
   *   Array of ratifications.
   */
  public function getForJunior(AccountInterface $junior, ?string $status = NULL) {
    $query = $this->entityTypeManager
      ->getStorage('ratification')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('junior_id', $junior->id());

    if ($status) {
      $query->condition('status', $status);
    }

    $ids = $query->sort('created', 'DESC')->execute();

    return $this->entityTypeManager
      ->getStorage('ratification')
      ->loadMultiple($ids);
  }

  /**
   * Check if a task has pending ratification.
   *
   * @param \Drupal\workflow_assignment\Entity\WorkflowTask $task
   *   The task.
   *
   * @return bool
   *   TRUE if there's pending ratification.
   */
  public function hasPendingRatification(WorkflowTask $task) {
    $count = $this->entityTypeManager
      ->getStorage('ratification')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('task_id', $task->id())
      ->condition('status', Ratification::STATUS_PENDING)
      ->count()
      ->execute();

    return $count > 0;
  }

  /**
   * Notify mentors about a new ratification request.
   *
   * @param \Drupal\avc_guild\Entity\Ratification $ratification
   *   The ratification.
   */
  protected function notifyMentors(Ratification $ratification) {
    if (!$this->notificationService) {
      return;
    }

    $guild = $ratification->getGuild();
    $junior = $ratification->getJunior();
    $asset = $ratification->getAsset();

    if (!$guild || !$junior || !$asset) {
      return;
    }

    // Get all mentors in the guild.
    $members = $guild->getMembers();
    foreach ($members as $membership) {
      $user = $membership->getUser();
      if (!$user) {
        continue;
      }

      if (avc_guild_can_ratify($guild, $user)) {
        $this->notificationService->queueRatificationNeeded(
          $user,
          $junior,
          $asset,
          $guild
        );
      }
    }
  }

}
