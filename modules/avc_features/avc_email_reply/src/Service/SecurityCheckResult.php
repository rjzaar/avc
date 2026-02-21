<?php

namespace Drupal\avc_email_reply\Service;

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
