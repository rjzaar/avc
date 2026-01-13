<?php

namespace Drupal\Tests\avc_email_reply\Unit\Service;

use Drupal\avc_email_reply\Service\ReplyTokenService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for ReplyTokenService.
 *
 * @group avc_email_reply
 * @coversDefaultClass \Drupal\avc_email_reply\Service\ReplyTokenService
 */
class ReplyTokenServiceTest extends UnitTestCase {

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The config object mock.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_email_reply\Service\ReplyTokenService
   */
  protected $service;

  /**
   * The hash salt for testing.
   *
   * @var string
   */
  protected $hashSalt = 'test-hash-salt-12345';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the config.
    $this->config = $this->createMock(ImmutableConfig::class);
    $this->config->method('get')
      ->willReturnMap([
        ['token_expiry_days', 30],
      ]);

    // Mock the config factory.
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('avc_email_reply.settings')
      ->willReturn($this->config);

    // Mock Settings::getHashSalt().
    $settings = new Settings(['hash_salt' => $this->hashSalt]);
    new Settings(['hash_salt' => $this->hashSalt]);

    $this->service = new ReplyTokenService($this->configFactory);
  }

  /**
   * Tests generateToken creates a valid base64 token.
   *
   * @covers ::generateToken
   * @covers ::generateSignature
   */
  public function testGenerateTokenCreatesValidBase64Token() {
    $token = $this->service->generateToken('node', 123, 456, 789);

    // Token should be a non-empty string.
    $this->assertIsString($token);
    $this->assertNotEmpty($token);

    // Token should be valid base64.
    $decoded = base64_decode($token, TRUE);
    $this->assertNotFalse($decoded);

    // Decoded token should contain expected components.
    $parts = explode('|', $decoded);
    $this->assertCount(7, $parts);
    $this->assertEquals('node', $parts[1]);
    $this->assertEquals('123', $parts[2]);
    $this->assertEquals('456', $parts[3]);
    $this->assertEquals('789', $parts[4]);
  }

  /**
   * Tests generateToken handles null group_id.
   *
   * @covers ::generateToken
   */
  public function testGenerateTokenHandlesNullGroupId() {
    $token = $this->service->generateToken('comment', 100, 200, NULL);

    $decoded = base64_decode($token, TRUE);
    $parts = explode('|', $decoded);

    // Group ID should be 0 when NULL is passed.
    $this->assertEquals('0', $parts[4]);
  }

  /**
   * Tests validateToken returns correct data for valid token.
   *
   * @covers ::validateToken
   * @covers ::generateSignature
   */
  public function testValidateTokenReturnsCorrectDataForValidToken() {
    // Generate a token.
    $token = $this->service->generateToken('node', 123, 456, 789);

    // Validate the token.
    $result = $this->service->validateToken($token);

    // Should return an array with correct data.
    $this->assertIsArray($result);
    $this->assertEquals('node', $result['content_type']);
    $this->assertEquals(123, $result['entity_id']);
    $this->assertEquals(456, $result['user_id']);
    $this->assertEquals(789, $result['group_id']);
    $this->assertArrayHasKey('random', $result);
    $this->assertArrayHasKey('expiry', $result);

    // Random should be 16 character hex string.
    $this->assertEquals(16, strlen($result['random']));
    $this->assertTrue(ctype_xdigit($result['random']));

    // Expiry should be in the future.
    $this->assertGreaterThan(time(), $result['expiry']);
  }

  /**
   * Tests validateToken returns NULL for expired token.
   *
   * @covers ::validateToken
   */
  public function testValidateTokenReturnsNullForExpiredToken() {
    // Create a token with expiry in the past.
    $random = bin2hex(random_bytes(8));
    $expiry = time() - 3600; // 1 hour ago.
    $data = sprintf('%s|node|123|456|789|%d', $random, $expiry);
    $signature = substr(hash_hmac('sha256', $data, $this->hashSalt), 0, 16);
    $token_string = $data . '|' . $signature;
    $token = base64_encode($token_string);

    // Validation should fail.
    $result = $this->service->validateToken($token);
    $this->assertNull($result);
  }

  /**
   * Tests validateToken returns NULL for tampered signature.
   *
   * @covers ::validateToken
   */
  public function testValidateTokenReturnsNullForTamperedSignature() {
    // Generate a valid token.
    $token = $this->service->generateToken('node', 123, 456, 789);

    // Decode and tamper with the signature.
    $decoded = base64_decode($token, TRUE);
    $parts = explode('|', $decoded);
    $parts[6] = 'tampered12345678'; // Change signature.
    $tampered_token = base64_encode(implode('|', $parts));

    // Validation should fail.
    $result = $this->service->validateToken($tampered_token);
    $this->assertNull($result);
  }

  /**
   * Tests validateToken returns NULL for malformed tokens.
   *
   * @covers ::validateToken
   * @dataProvider providerMalformedTokens
   */
  public function testValidateTokenReturnsNullForMalformedToken($malformed_token, $description) {
    $result = $this->service->validateToken($malformed_token);
    $this->assertNull($result, "Failed to reject malformed token: $description");
  }

  /**
   * Data provider for malformed token tests.
   *
   * @return array
   *   Array of test cases.
   */
  public function providerMalformedTokens() {
    return [
      ['not-base64!!!', 'Invalid base64'],
      [base64_encode('too|few|parts'), 'Too few parts'],
      [base64_encode('1|2|3|4|5|6|7|8|9'), 'Too many parts'],
      [base64_encode('short|node|123|456|789|' . time() . '|abc123'), 'Invalid random (too short)'],
      [base64_encode('notahexstring!!!|node|123|456|789|' . time() . '|abc123'), 'Invalid random (not hex)'],
      [base64_encode(bin2hex(random_bytes(8)) . '|node|abc|456|789|' . time() . '|abc123'), 'Invalid entity_id (not numeric)'],
      [base64_encode(bin2hex(random_bytes(8)) . '|node|123|abc|789|' . time() . '|abc123'), 'Invalid user_id (not numeric)'],
      [base64_encode(bin2hex(random_bytes(8)) . '|node|123|456|abc|' . time() . '|abc123'), 'Invalid group_id (not numeric)'],
      [base64_encode(bin2hex(random_bytes(8)) . '|node|123|456|789|abc|abc123'), 'Invalid expiry (not numeric)'],
    ];
  }

  /**
   * Tests validateToken handles data tampering in content_type.
   *
   * @covers ::validateToken
   */
  public function testValidateTokenRejectsDataTampering() {
    // Generate a valid token.
    $token = $this->service->generateToken('node', 123, 456, 789);

    // Decode and tamper with the content_type.
    $decoded = base64_decode($token, TRUE);
    $parts = explode('|', $decoded);
    $parts[1] = 'comment'; // Change content_type.
    $tampered_token = base64_encode(implode('|', $parts));

    // Validation should fail due to signature mismatch.
    $result = $this->service->validateToken($tampered_token);
    $this->assertNull($result);
  }

  /**
   * Tests token expiry uses config value.
   *
   * @covers ::generateToken
   */
  public function testTokenExpiryUsesConfigValue() {
    // Mock config to return 60 days.
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnMap([
        ['token_expiry_days', 60],
      ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('avc_email_reply.settings')
      ->willReturn($config);

    $service = new ReplyTokenService($configFactory);

    // Generate token and validate.
    $token = $service->generateToken('node', 123, 456, 789);
    $result = $service->validateToken($token);

    // Calculate expected expiry range (60 days from now, +/- 5 seconds for test execution time).
    $expected_expiry = time() + (60 * 86400);
    $this->assertGreaterThanOrEqual($expected_expiry - 5, $result['expiry']);
    $this->assertLessThanOrEqual($expected_expiry + 5, $result['expiry']);
  }

}
