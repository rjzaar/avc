<?php

namespace Drupal\avc_email_reply\Service;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Service for processing queued email replies and creating comments.
 */
class EmailReplyProcessor {

  /**
   * The reply token service.
   *
   * @var \Drupal\avc_email_reply\Service\ReplyTokenService
   */
  protected $replyTokenService;

  /**
   * The rate limiter service.
   *
   * @var \Drupal\avc_email_reply\Service\EmailRateLimiter
   */
  protected $rateLimiter;

  /**
   * The content extractor service.
   *
   * @var \Drupal\avc_email_reply\Service\ReplyContentExtractor
   */
  protected $contentExtractor;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The notification service.
   *
   * @var \Drupal\avc_notification\Service\NotificationService|null
   */
  protected $notificationService;

  /**
   * Constructs an EmailReplyProcessor object.
   *
   * @param \Drupal\avc_email_reply\Service\ReplyTokenService $reply_token_service
   *   The reply token service.
   * @param \Drupal\avc_email_reply\Service\EmailRateLimiter $rate_limiter
   *   The rate limiter service.
   * @param \Drupal\avc_email_reply\Service\ReplyContentExtractor $content_extractor
   *   The content extractor service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\avc_notification\Service\NotificationService|null $notification_service
   *   The notification service, if available.
   */
  public function __construct(
    ReplyTokenService $reply_token_service,
    EmailRateLimiter $rate_limiter,
    ReplyContentExtractor $content_extractor,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    $notification_service = NULL
  ) {
    $this->replyTokenService = $reply_token_service;
    $this->rateLimiter = $rate_limiter;
    $this->contentExtractor = $content_extractor;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->notificationService = $notification_service;
  }

  /**
   * Process a queued email reply item.
   *
   * @param array $item
   *   The queue item containing:
   *   - token: The reply token
   *   - from: The sender email address
   *   - text_content: The email text content
   *   - spam_score: The spam score
   *   - spf_result: The SPF check result
   *   - dkim_result: The DKIM check result
   *
   * @return \Drupal\avc_email_reply\Service\ProcessResult
   *   The processing result.
   */
  public function process(array $item): ProcessResult {
    $logger = $this->loggerFactory->get('avc_email_reply');

    // Validate required fields.
    if (empty($item['token']) || empty($item['from']) || empty($item['text_content'])) {
      $logger->error('Missing required fields in queue item.');
      return new ProcessResult(FALSE, 'Missing required fields');
    }

    // Validate token.
    $token_data = $this->replyTokenService->validateToken($item['token']);
    if (!$token_data) {
      $logger->warning('Invalid or expired token in email reply from @from', [
        '@from' => $item['from'],
      ]);
      return new ProcessResult(FALSE, 'Invalid or expired token');
    }

    // Perform security checks.
    $security_result = $this->performSecurityChecks($item, $token_data);
    if (!$security_result->isSuccess()) {
      $logger->warning('Security check failed: @reason', [
        '@reason' => $security_result->getReason(),
      ]);
      return new ProcessResult(FALSE, $security_result->getReason());
    }

    // Load and verify sender.
    $sender = $this->loadSenderFromEmail($item['from'], $token_data['user_id']);
    if (!$sender) {
      $logger->warning('Could not verify sender @from against user ID @uid', [
        '@from' => $item['from'],
        '@uid' => $token_data['user_id'],
      ]);
      return new ProcessResult(FALSE, 'Sender email does not match registered user');
    }

    // Load target entity.
    $entity = $this->loadEntity($token_data['content_type'], $token_data['entity_id']);
    if (!$entity) {
      $logger->error('Target entity @type:@id not found', [
        '@type' => $token_data['content_type'],
        '@id' => $token_data['entity_id'],
      ]);
      return new ProcessResult(FALSE, 'Target entity not found');
    }

    // Verify group membership if group_id is set.
    if ($token_data['group_id'] > 0) {
      $is_member = $this->verifyGroupMembership($sender, $token_data['group_id']);
      if (!$is_member) {
        $logger->warning('User @uid is not a member of group @gid', [
          '@uid' => $sender->id(),
          '@gid' => $token_data['group_id'],
        ]);
        return new ProcessResult(FALSE, 'User is not a member of the group');
      }
    }

    // Extract and sanitize reply content.
    $extracted_content = $this->contentExtractor->extract($item['text_content']);
    if ($this->contentExtractor->isEmpty($extracted_content)) {
      $logger->warning('Empty reply content after extraction from @from', [
        '@from' => $item['from'],
      ]);
      return new ProcessResult(FALSE, 'Empty reply content');
    }

    $sanitized_content = $this->contentExtractor->sanitize($extracted_content);

    // Create comment.
    try {
      $comment = $this->createComment(
        $entity,
        $sender,
        $sanitized_content,
        $token_data['group_id'] > 0 ? $token_data['group_id'] : NULL
      );

      // Record reply for rate limiting.
      $this->rateLimiter->recordReply(
        $sender->id(),
        $token_data['group_id'] > 0 ? $token_data['group_id'] : NULL
      );

      $logger->info('Successfully created comment @cid from email reply by @user', [
        '@cid' => $comment->id(),
        '@user' => $sender->getDisplayName(),
      ]);

      return new ProcessResult(TRUE, 'Comment created successfully', $comment);
    }
    catch (\Exception $e) {
      $logger->error('Failed to create comment: @message', [
        '@message' => $e->getMessage(),
      ]);
      return new ProcessResult(FALSE, 'Failed to create comment: ' . $e->getMessage());
    }
  }

  /**
   * Perform security checks on the email reply.
   *
   * @param array $email_data
   *   The email data containing spam_score, spf_result, dkim_result.
   * @param array $token_data
   *   The decoded token data.
   *
   * @return \Drupal\avc_email_reply\Service\SecurityCheckResult
   *   The security check result.
   */
  public function performSecurityChecks(array $email_data, array $token_data): SecurityCheckResult {
    $logger = $this->loggerFactory->get('avc_email_reply');

    // Check spam score (reject if > 5.0).
    if (isset($email_data['spam_score']) && $email_data['spam_score'] > 5.0) {
      return new SecurityCheckResult(FALSE, 'Spam score too high: ' . $email_data['spam_score']);
    }

    // Check rate limiting.
    $is_limited = $this->rateLimiter->isLimited(
      $token_data['user_id'],
      $token_data['group_id'] > 0 ? $token_data['group_id'] : NULL
    );

    if ($is_limited) {
      return new SecurityCheckResult(FALSE, 'Rate limit exceeded');
    }

    // Log SPF/DKIM failures but don't reject.
    if (isset($email_data['spf_result']) && $email_data['spf_result'] !== 'pass') {
      $logger->warning('SPF check failed: @result', [
        '@result' => $email_data['spf_result'],
      ]);
    }

    if (isset($email_data['dkim_result']) && $email_data['dkim_result'] !== 'pass') {
      $logger->warning('DKIM check failed: @result', [
        '@result' => $email_data['dkim_result'],
      ]);
    }

    return new SecurityCheckResult(TRUE, 'Security checks passed');
  }

  /**
   * Load sender user account from email address.
   *
   * @param string $from_address
   *   The from address (may be in "Name <email>" format).
   * @param int $expected_user_id
   *   The expected user ID from the token.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The user account if verified, NULL otherwise.
   */
  public function loadSenderFromEmail(string $from_address, int $expected_user_id): ?AccountInterface {
    // Extract email from "Name <email>" format.
    if (preg_match('/<([^>]+)>/', $from_address, $matches)) {
      $email = trim($matches[1]);
    }
    else {
      $email = trim($from_address);
    }

    // Load user by ID.
    $user_storage = $this->entityTypeManager->getStorage('user');
    $user = $user_storage->load($expected_user_id);

    if (!$user) {
      return NULL;
    }

    // Verify email matches (case-insensitive).
    $user_email = $user->getEmail();
    if (strcasecmp($email, $user_email) !== 0) {
      return NULL;
    }

    return $user;
  }

  /**
   * Create a comment on the target entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The target entity.
   * @param \Drupal\Core\Session\AccountInterface $author
   *   The comment author.
   * @param string $content
   *   The sanitized comment content.
   * @param int|null $group_id
   *   The group ID, if applicable.
   *
   * @return \Drupal\comment\CommentInterface
   *   The created comment.
   *
   * @throws \Exception
   *   If the comment cannot be created.
   */
  public function createComment(EntityInterface $entity, AccountInterface $author, string $content, ?int $group_id): CommentInterface {
    $comment_storage = $this->entityTypeManager->getStorage('comment');

    // Determine the comment field name based on entity type.
    $field_name = $this->getCommentFieldName($entity);

    if (!$field_name) {
      throw new \Exception('No comment field found for entity type: ' . $entity->getEntityTypeId());
    }

    // Create the comment.
    $comment = $comment_storage->create([
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'field_name' => $field_name,
      'uid' => $author->id(),
      'comment_type' => 'comment',
      'subject' => '',
      'comment_body' => [
        'value' => $content,
        'format' => 'basic_html',
      ],
      'status' => 1,
    ]);

    $comment->save();

    return $comment;
  }

  /**
   * Get the comment field name for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the comment field for.
   *
   * @return string|null
   *   The comment field name, or NULL if not found.
   */
  protected function getCommentFieldName(EntityInterface $entity): ?string {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    // Get field definitions for the entity bundle.
    $field_definitions = $this->entityTypeManager
      ->getDefinition($entity_type_id)
      ->get('field_ui_base_route')
      ? \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle)
      : [];

    // Look for a comment field.
    foreach ($field_definitions as $field_name => $field_definition) {
      if ($field_definition->getType() === 'comment') {
        return $field_name;
      }
    }

    // Common fallback field names.
    $common_fields = ['field_comments', 'comment', 'field_comment'];
    foreach ($common_fields as $field_name) {
      if ($entity->hasField($field_name)) {
        return $field_name;
      }
    }

    return NULL;
  }

  /**
   * Load an entity by type and ID.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded entity, or NULL if not found.
   */
  protected function loadEntity(string $entity_type, int $entity_id): ?EntityInterface {
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      return $storage->load($entity_id);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Verify that a user is a member of a group.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account.
   * @param int $group_id
   *   The group ID.
   *
   * @return bool
   *   TRUE if the user is a member, FALSE otherwise.
   */
  protected function verifyGroupMembership(AccountInterface $user, int $group_id): bool {
    // Load the group entity.
    try {
      $group_storage = $this->entityTypeManager->getStorage('group');
      $group = $group_storage->load($group_id);

      if (!$group) {
        return FALSE;
      }

      // Check if user is a member.
      $membership = $group->getMember($user);
      return $membership !== FALSE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}

/**
 * Value object representing the result of processing an email reply.
 */
class ProcessResult {

  /**
   * Whether processing was successful.
   *
   * @var bool
   */
  protected $success;

  /**
   * The result message.
   *
   * @var string
   */
  protected $message;

  /**
   * The created comment, if any.
   *
   * @var \Drupal\comment\CommentInterface|null
   */
  protected $comment;

  /**
   * Constructs a ProcessResult object.
   *
   * @param bool $success
   *   Whether processing was successful.
   * @param string $message
   *   The result message.
   * @param \Drupal\comment\CommentInterface|null $comment
   *   The created comment, if any.
   */
  public function __construct(bool $success, string $message, ?CommentInterface $comment = NULL) {
    $this->success = $success;
    $this->message = $message;
    $this->comment = $comment;
  }

  /**
   * Check if processing was successful.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function isSuccess(): bool {
    return $this->success;
  }

  /**
   * Get the result message.
   *
   * @return string
   *   The message.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Get the created comment.
   *
   * @return \Drupal\comment\CommentInterface|null
   *   The comment, or NULL if none was created.
   */
  public function getComment(): ?CommentInterface {
    return $this->comment;
  }

}

/**
 * Value object representing the result of security checks.
 */
class SecurityCheckResult {

  /**
   * Whether security checks passed.
   *
   * @var bool
   */
  protected $success;

  /**
   * The reason for failure, if any.
   *
   * @var string
   */
  protected $reason;

  /**
   * Constructs a SecurityCheckResult object.
   *
   * @param bool $success
   *   Whether security checks passed.
   * @param string $reason
   *   The reason for the result.
   */
  public function __construct(bool $success, string $reason) {
    $this->success = $success;
    $this->reason = $reason;
  }

  /**
   * Check if security checks passed.
   *
   * @return bool
   *   TRUE if passed, FALSE otherwise.
   */
  public function isSuccess(): bool {
    return $this->success;
  }

  /**
   * Get the reason for the result.
   *
   * @return string
   *   The reason.
   */
  public function getReason(): string {
    return $this->reason;
  }

}
