<?php

namespace Drupal\avc_email_reply\Service;

use Drupal\comment\CommentInterface;

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
