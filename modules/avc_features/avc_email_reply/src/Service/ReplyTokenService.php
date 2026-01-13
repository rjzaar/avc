<?php

namespace Drupal\avc_email_reply\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;

/**
 * Service for generating and validating secure reply tokens.
 *
 * Tokens are used to authenticate email replies and associate them with
 * specific content entities, users, and groups.
 */
class ReplyTokenService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ReplyTokenService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Generates a secure reply token.
   *
   * Token format: base64 encoded string containing:
   * - 16 character random hex
   * - content_type (node, comment, etc)
   * - entity_id
   * - user_id
   * - group_id (0 if none)
   * - expiry timestamp
   * - HMAC-SHA256 signature (first 16 chars)
   *
   * @param string $content_type
   *   The entity type (e.g., 'node', 'comment').
   * @param int $entity_id
   *   The entity ID.
   * @param int $user_id
   *   The user ID.
   * @param int|null $group_id
   *   The group ID, or NULL if not associated with a group.
   *
   * @return string
   *   The generated token as a base64 encoded string.
   */
  public function generateToken(string $content_type, int $entity_id, int $user_id, ?int $group_id = NULL): string {
    // Generate random component.
    $random = bin2hex(random_bytes(8));

    // Get expiry setting (default 30 days).
    $config = $this->configFactory->get('avc_email_reply.settings');
    $expiry_days = $config->get('token_expiry_days') ?? 30;
    $expiry = time() + ($expiry_days * 86400);

    // Normalize group_id.
    $group_id = $group_id ?? 0;

    // Build token data string.
    $data = sprintf(
      '%s|%s|%d|%d|%d|%d',
      $random,
      $content_type,
      $entity_id,
      $user_id,
      $group_id,
      $expiry
    );

    // Generate HMAC signature.
    $signature = $this->generateSignature($data);

    // Append signature (first 16 chars).
    $token_string = $data . '|' . substr($signature, 0, 16);

    // Return base64 encoded token.
    return base64_encode($token_string);
  }

  /**
   * Validates a reply token.
   *
   * @param string $token
   *   The base64 encoded token string.
   *
   * @return array|null
   *   An array containing the decoded token data with keys:
   *   - random: The random component
   *   - content_type: The entity type
   *   - entity_id: The entity ID
   *   - user_id: The user ID
   *   - group_id: The group ID (0 if none)
   *   - expiry: The expiry timestamp
   *   Returns NULL if the token is invalid or expired.
   */
  public function validateToken(string $token): ?array {
    // Decode base64.
    $token_string = base64_decode($token, TRUE);
    if ($token_string === FALSE) {
      return NULL;
    }

    // Split token components.
    $parts = explode('|', $token_string);
    if (count($parts) !== 7) {
      return NULL;
    }

    [$random, $content_type, $entity_id, $user_id, $group_id, $expiry, $provided_signature] = $parts;

    // Validate types and format.
    if (!ctype_xdigit($random) || strlen($random) !== 16) {
      return NULL;
    }
    if (!is_numeric($entity_id) || !is_numeric($user_id) || !is_numeric($group_id) || !is_numeric($expiry)) {
      return NULL;
    }

    // Convert to integers.
    $entity_id = (int) $entity_id;
    $user_id = (int) $user_id;
    $group_id = (int) $group_id;
    $expiry = (int) $expiry;

    // Check expiry.
    if (time() > $expiry) {
      return NULL;
    }

    // Rebuild data string for signature verification.
    $data = sprintf(
      '%s|%s|%d|%d|%d|%d',
      $random,
      $content_type,
      $entity_id,
      $user_id,
      $group_id,
      $expiry
    );

    // Verify signature.
    $expected_signature = substr($this->generateSignature($data), 0, 16);
    if (!hash_equals($expected_signature, $provided_signature)) {
      return NULL;
    }

    // Return decoded data.
    return [
      'random' => $random,
      'content_type' => $content_type,
      'entity_id' => $entity_id,
      'user_id' => $user_id,
      'group_id' => $group_id,
      'expiry' => $expiry,
    ];
  }

  /**
   * Generates an HMAC-SHA256 signature for the given data.
   *
   * @param string $data
   *   The data to sign.
   *
   * @return string
   *   The hex-encoded HMAC signature.
   */
  protected function generateSignature(string $data): string {
    // Get hash salt from settings.
    $hash_salt = Settings::getHashSalt();

    // Generate HMAC-SHA256 signature.
    return hash_hmac('sha256', $data, $hash_salt);
  }

}
