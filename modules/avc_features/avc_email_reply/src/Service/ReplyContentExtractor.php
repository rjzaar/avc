<?php

namespace Drupal\avc_email_reply\Service;

use Drupal\Component\Utility\Xss;

/**
 * Service for extracting reply content from email text.
 *
 * This service strips quoted content, signatures, and other email metadata
 * to extract only the actual reply content from an email message.
 */
class ReplyContentExtractor {

  /**
   * Extract reply content from email text.
   *
   * Removes quoted content, signatures, and email metadata to extract only
   * the actual reply content written by the sender.
   *
   * @param string $text
   *   The raw email text content.
   *
   * @return string
   *   The extracted reply content.
   */
  public function extract(string $text): string {
    $lines = explode("\n", $text);
    $result = [];
    $in_quote = FALSE;
    $in_signature = FALSE;

    foreach ($lines as $line) {
      $trimmed = trim($line);

      // Check for signature delimiters.
      if ($this->isSignatureLine($line)) {
        $in_signature = TRUE;
        continue;
      }

      // Skip if we're in a signature block.
      if ($in_signature) {
        continue;
      }

      // Check for quoted content patterns.
      if ($this->isQuotedLine($line)) {
        $in_quote = TRUE;
        continue;
      }

      // Check for email header blocks (From:...Sent:...To:...Subject:).
      if ($this->isEmailHeaderLine($trimmed)) {
        $in_quote = TRUE;
        continue;
      }

      // Check for divider lines.
      if ($this->isDividerLine($trimmed)) {
        $in_quote = TRUE;
        continue;
      }

      // Check for "On ... wrote:" patterns.
      if ($this->isAttributionLine($trimmed)) {
        $in_quote = TRUE;
        continue;
      }

      // If we hit a quote marker, stop processing.
      if ($in_quote) {
        break;
      }

      // Keep this line as part of the reply.
      $result[] = $line;
    }

    return implode("\n", $result);
  }

  /**
   * Sanitize reply content for display.
   *
   * Filters HTML, converts newlines to breaks, and limits length.
   *
   * @param string $content
   *   The extracted reply content.
   *
   * @return string
   *   The sanitized content ready for display.
   */
  public function sanitize(string $content): string {
    // Trim whitespace.
    $content = trim($content);

    // Limit to 10000 characters.
    if (mb_strlen($content) > 10000) {
      $content = mb_substr($content, 0, 10000);
    }

    // Filter potentially dangerous HTML.
    $content = Xss::filter($content);

    // Convert newlines to <br> tags.
    $content = nl2br($content);

    return $content;
  }

  /**
   * Check if content is empty after extraction.
   *
   * @param string $content
   *   The extracted content to check.
   *
   * @return bool
   *   TRUE if the content is empty or only whitespace.
   */
  public function isEmpty(string $content): bool {
    return trim($content) === '';
  }

  /**
   * Check if a line is a signature delimiter.
   *
   * @param string $line
   *   The line to check.
   *
   * @return bool
   *   TRUE if the line marks the start of a signature.
   */
  protected function isSignatureLine(string $line): bool {
    $trimmed = trim($line);

    // Standard signature delimiter.
    if ($line === '-- ') {
      return TRUE;
    }

    // Mobile signature patterns.
    $mobile_patterns = [
      '/^Sent from my (iPhone|iPad|Android|BlackBerry)/i',
      '/^Get Outlook for (iOS|Android)/i',
      '/^Sent from (Mail|Yahoo Mail|AOL Mail)/i',
      '/^Sent from (Samsung|Huawei|Xiaomi|OnePlus)/i',
    ];

    foreach ($mobile_patterns as $pattern) {
      if (preg_match($pattern, $trimmed)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if a line is quoted content.
   *
   * @param string $line
   *   The line to check.
   *
   * @return bool
   *   TRUE if the line is quoted content.
   */
  protected function isQuotedLine(string $line): bool {
    // Lines starting with > (with optional spaces).
    return preg_match('/^\s*>/', $line) === 1;
  }

  /**
   * Check if a line is an email header (From:, Sent:, To:, Subject:).
   *
   * @param string $line
   *   The line to check.
   *
   * @return bool
   *   TRUE if the line is an email header.
   */
  protected function isEmailHeaderLine(string $line): bool {
    $header_patterns = [
      '/^From:\s*.+/i',
      '/^Sent:\s*.+/i',
      '/^To:\s*.+/i',
      '/^Subject:\s*.+/i',
      '/^Date:\s*.+/i',
      '/^Cc:\s*.+/i',
    ];

    foreach ($header_patterns as $pattern) {
      if (preg_match($pattern, $line)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if a line is a divider.
   *
   * @param string $line
   *   The line to check.
   *
   * @return bool
   *   TRUE if the line is a divider.
   */
  protected function isDividerLine(string $line): bool {
    // "---------- Original Message ----------"
    if (preg_match('/^-+\s*Original Message\s*-+$/i', $line)) {
      return TRUE;
    }

    // "________________________________" (30+ underscores).
    if (preg_match('/^_{30,}$/', $line)) {
      return TRUE;
    }

    // "========" dividers.
    if (preg_match('/^={30,}$/', $line)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if a line is an attribution line.
   *
   * @param string $line
   *   The line to check.
   *
   * @return bool
   *   TRUE if the line is an attribution (e.g., "On ... wrote:").
   */
  protected function isAttributionLine(string $line): bool {
    // "On ... wrote:" pattern.
    if (preg_match('/^On\s+.+\s+wrote:$/i', $line)) {
      return TRUE;
    }

    // "On ... <email> wrote:" pattern.
    if (preg_match('/^On\s+.+\s+<.+>\s+wrote:$/i', $line)) {
      return TRUE;
    }

    // "From: Name <email>" at start of line (common in forwarded emails).
    if (preg_match('/^\*?From:\*?\s+.+\s+<.+>$/i', $line)) {
      return TRUE;
    }

    return FALSE;
  }

}
