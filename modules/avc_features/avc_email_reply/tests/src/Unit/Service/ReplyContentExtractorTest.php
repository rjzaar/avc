<?php

namespace Drupal\Tests\avc_email_reply\Unit\Service;

use Drupal\avc_email_reply\Service\ReplyContentExtractor;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for ReplyContentExtractor.
 *
 * @group avc_email_reply
 * @coversDefaultClass \Drupal\avc_email_reply\Service\ReplyContentExtractor
 */
class ReplyContentExtractorTest extends UnitTestCase {

  /**
   * The service under test.
   *
   * @var \Drupal\avc_email_reply\Service\ReplyContentExtractor
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->service = new ReplyContentExtractor();
  }

  /**
   * Tests extract removes lines starting with >.
   *
   * @covers ::extract
   * @covers ::isQuotedLine
   */
  public function testExtractRemovesQuotedLines() {
    $text = "This is my reply.\n> This is quoted\n>> This is double quoted\n  > This is also quoted";
    $result = $this->service->extract($text);

    $this->assertStringContainsString('This is my reply.', $result);
    $this->assertStringNotContainsString('This is quoted', $result);
    $this->assertStringNotContainsString('This is double quoted', $result);
    $this->assertStringNotContainsString('This is also quoted', $result);
  }

  /**
   * Tests extract removes "On ... wrote:" patterns.
   *
   * @covers ::extract
   * @covers ::isAttributionLine
   */
  public function testExtractRemovesAttributionLines() {
    $text = <<<EOT
This is my reply.
On Jan 13, 2026, at 10:00 AM, John Doe wrote:
This should be removed.
EOT;

    $result = $this->service->extract($text);

    $this->assertStringContainsString('This is my reply.', $result);
    $this->assertStringNotContainsString('On Jan 13, 2026', $result);
    $this->assertStringNotContainsString('This should be removed.', $result);
  }

  /**
   * Tests extract removes "On ... <email> wrote:" patterns.
   *
   * @covers ::extract
   * @covers ::isAttributionLine
   */
  public function testExtractRemovesAttributionLinesWithEmail() {
    $text = <<<EOT
This is my reply.
On Jan 13, 2026, John Doe <john@example.com> wrote:
This should be removed.
EOT;

    $result = $this->service->extract($text);

    $this->assertStringContainsString('This is my reply.', $result);
    $this->assertStringNotContainsString('john@example.com', $result);
    $this->assertStringNotContainsString('This should be removed.', $result);
  }

  /**
   * Tests extract removes signature after "--".
   *
   * @covers ::extract
   * @covers ::isSignatureLine
   */
  public function testExtractRemovesStandardSignature() {
    $text = <<<EOT
This is my reply.
--
John Doe
Senior Developer
Company Inc.
EOT;

    $result = $this->service->extract($text);

    $this->assertStringContainsString('This is my reply.', $result);
    $this->assertStringNotContainsString('John Doe', $result);
    $this->assertStringNotContainsString('Senior Developer', $result);
    $this->assertStringNotContainsString('Company Inc.', $result);
  }

  /**
   * Tests extract handles "Sent from my iPhone" patterns.
   *
   * @covers ::extract
   * @covers ::isSignatureLine
   * @dataProvider providerMobileSignatures
   */
  public function testExtractRemovesMobileSignatures($signature) {
    $text = "This is my reply.\n$signature";
    $result = $this->service->extract($text);

    $this->assertStringContainsString('This is my reply.', $result);
    $this->assertStringNotContainsString($signature, $result);
  }

  /**
   * Data provider for mobile signature tests.
   *
   * @return array
   *   Array of mobile signature patterns.
   */
  public function providerMobileSignatures() {
    return [
      ['Sent from my iPhone'],
      ['Sent from my iPad'],
      ['Sent from my Android'],
      ['Sent from my BlackBerry'],
      ['Get Outlook for iOS'],
      ['Get Outlook for Android'],
      ['Sent from Mail for Windows 10'],
      ['Sent from Yahoo Mail'],
      ['Sent from AOL Mail'],
      ['Sent from Samsung Galaxy'],
      ['Sent from Huawei P30'],
      ['Sent from Xiaomi Mi 9'],
      ['Sent from OnePlus 8'],
    ];
  }

  /**
   * Tests extract removes email header blocks.
   *
   * @covers ::extract
   * @covers ::isEmailHeaderLine
   */
  public function testExtractRemovesEmailHeaders() {
    $text = <<<EOT
This is my reply.
From: John Doe <john@example.com>
Sent: Monday, January 13, 2026 10:00 AM
To: Jane Smith <jane@example.com>
Subject: Re: Test Subject
This should be removed.
EOT;

    $result = $this->service->extract($text);

    $this->assertStringContainsString('This is my reply.', $result);
    $this->assertStringNotContainsString('From: John Doe', $result);
    $this->assertStringNotContainsString('Sent: Monday', $result);
    $this->assertStringNotContainsString('To: Jane Smith', $result);
    $this->assertStringNotContainsString('Subject: Re: Test Subject', $result);
    $this->assertStringNotContainsString('This should be removed.', $result);
  }

  /**
   * Tests extract removes divider lines.
   *
   * @covers ::extract
   * @covers ::isDividerLine
   * @dataProvider providerDividerLines
   */
  public function testExtractRemovesDividerLines($divider) {
    $text = "This is my reply.\n$divider\nThis should be removed.";
    $result = $this->service->extract($text);

    $this->assertStringContainsString('This is my reply.', $result);
    $this->assertStringNotContainsString('This should be removed.', $result);
  }

  /**
   * Data provider for divider line tests.
   *
   * @return array
   *   Array of divider patterns.
   */
  public function providerDividerLines() {
    return [
      ['---------- Original Message ----------'],
      ['________________________________'],
      [str_repeat('_', 50)],
      [str_repeat('=', 30)],
      ['========================================'],
    ];
  }

  /**
   * Tests extract handles complex email with multiple patterns.
   *
   * @covers ::extract
   */
  public function testExtractHandlesComplexEmail() {
    $text = <<<EOT
This is my actual reply.
It has multiple lines.
And some formatting.

On Jan 13, 2026, at 10:00 AM, John Doe <john@example.com> wrote:

> This is quoted content
> that should be removed.
>
> Multiple lines of quotes.

--
John Doe
Senior Developer
Sent from my iPhone
EOT;

    $result = $this->service->extract($text);

    // Should contain only the reply.
    $this->assertStringContainsString('This is my actual reply.', $result);
    $this->assertStringContainsString('It has multiple lines.', $result);
    $this->assertStringContainsString('And some formatting.', $result);

    // Should not contain quoted or signature content.
    $this->assertStringNotContainsString('john@example.com', $result);
    $this->assertStringNotContainsString('This is quoted content', $result);
    $this->assertStringNotContainsString('Senior Developer', $result);
    $this->assertStringNotContainsString('Sent from my iPhone', $result);
  }

  /**
   * Tests sanitize limits content to 10000 characters.
   *
   * @covers ::sanitize
   */
  public function testSanitizeLimitsContentLength() {
    // Create a string longer than 10000 characters.
    $long_content = str_repeat('a', 12000);
    $result = $this->service->sanitize($long_content);

    $this->assertEquals(10000, mb_strlen(strip_tags($result)));
  }

  /**
   * Tests sanitize converts newlines to <br> tags.
   *
   * @covers ::sanitize
   */
  public function testSanitizeConvertsNewlinesToBr() {
    $content = "Line 1\nLine 2\nLine 3";
    $result = $this->service->sanitize($content);

    $this->assertStringContainsString('<br />', $result);
    $this->assertStringContainsString('Line 1', $result);
    $this->assertStringContainsString('Line 2', $result);
    $this->assertStringContainsString('Line 3', $result);
  }

  /**
   * Tests sanitize filters dangerous HTML.
   *
   * @covers ::sanitize
   */
  public function testSanitizeFiltersDangerousHtml() {
    $content = '<script>alert("xss")</script><p>Safe content</p><a href="javascript:void(0)">Link</a>';
    $result = $this->service->sanitize($content);

    // Should remove script tags and javascript: protocol.
    $this->assertStringNotContainsString('<script>', $result);
    $this->assertStringNotContainsString('alert', $result);
    $this->assertStringNotContainsString('javascript:', $result);

    // Should keep safe content.
    $this->assertStringContainsString('Safe content', $result);
  }

  /**
   * Tests sanitize trims whitespace.
   *
   * @covers ::sanitize
   */
  public function testSanitizeTrimsWhitespace() {
    $content = "   \n\n  Content with whitespace  \n\n   ";
    $result = $this->service->sanitize($content);

    // Should not start or end with whitespace.
    $this->assertStringStartsNotWith(' ', $result);
    $this->assertStringEndsNotWith(' ', $result);
    $this->assertStringContainsString('Content with whitespace', $result);
  }

  /**
   * Tests isEmpty returns true for whitespace only.
   *
   * @covers ::isEmpty
   * @dataProvider providerEmptyContent
   */
  public function testIsEmptyReturnsTrueForWhitespaceOnly($content) {
    $result = $this->service->isEmpty($content);
    $this->assertTrue($result, "Failed to identify empty content: " . json_encode($content));
  }

  /**
   * Data provider for empty content tests.
   *
   * @return array
   *   Array of empty content examples.
   */
  public function providerEmptyContent() {
    return [
      [''],
      [' '],
      ['   '],
      ["\n"],
      ["\n\n\n"],
      ["\t"],
      ["\t\t\t"],
      ["  \n  \t  \n  "],
      [" \r\n \r\n "],
    ];
  }

  /**
   * Tests isEmpty returns false for content with text.
   *
   * @covers ::isEmpty
   */
  public function testIsEmptyReturnsFalseForContentWithText() {
    $content = "  Some actual content  ";
    $result = $this->service->isEmpty($content);
    $this->assertFalse($result);
  }

  /**
   * Tests extract preserves reply content before quoted sections.
   *
   * @covers ::extract
   */
  public function testExtractPreservesContentBeforeQuotes() {
    $text = <<<EOT
First paragraph.

Second paragraph.

Third paragraph.

> Quoted content
> More quoted content
EOT;

    $result = $this->service->extract($text);

    $this->assertStringContainsString('First paragraph.', $result);
    $this->assertStringContainsString('Second paragraph.', $result);
    $this->assertStringContainsString('Third paragraph.', $result);
    $this->assertStringNotContainsString('Quoted content', $result);
  }

  /**
   * Tests extract handles From: attribution line pattern.
   *
   * @covers ::extract
   * @covers ::isAttributionLine
   */
  public function testExtractRemovesFromAttributionLine() {
    $text = <<<EOT
This is my reply.
From: John Doe <john@example.com>
This should be removed.
EOT;

    $result = $this->service->extract($text);

    $this->assertStringContainsString('This is my reply.', $result);
    $this->assertStringNotContainsString('From: John Doe', $result);
    $this->assertStringNotContainsString('This should be removed.', $result);
  }

  /**
   * Tests extract handles *From:* attribution line pattern (Outlook).
   *
   * @covers ::extract
   * @covers ::isAttributionLine
   */
  public function testExtractRemovesOutlookFromAttributionLine() {
    $text = <<<EOT
This is my reply.
*From:* John Doe <john@example.com>
This should be removed.
EOT;

    $result = $this->service->extract($text);

    $this->assertStringContainsString('This is my reply.', $result);
    $this->assertStringNotContainsString('*From:*', $result);
    $this->assertStringNotContainsString('This should be removed.', $result);
  }

}
