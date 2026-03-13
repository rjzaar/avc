<?php

namespace Drupal\Tests\avc_guild\Unit\Service;

use Drupal\avc_guild\Service\EndorsementService;
use Drupal\avc_guild\Service\ScoringService;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for EndorsementService.
 *
 * @group avc_guild
 * @coversDefaultClass \Drupal\avc_guild\Service\EndorsementService
 */
class EndorsementServiceTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The scoring service mock.
   *
   * @var \Drupal\avc_guild\Service\ScoringService|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $scoringService;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_guild\Service\EndorsementService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->scoringService = $this->createMock(ScoringService::class);

    $this->service = new EndorsementService(
      $this->entityTypeManager,
      $this->scoringService
    );
  }

  /**
   * Creates a mock user.
   *
   * @param int $id
   *   The user ID.
   *
   * @return \Drupal\Core\Session\AccountInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mock user.
   */
  protected function createMockUser(int $id = 1) {
    $user = $this->createMock(AccountInterface::class);
    $user->method('id')->willReturn($id);
    $user->method('getDisplayName')->willReturn('Test User ' . $id);
    return $user;
  }

  /**
   * Creates a mock guild (group).
   *
   * @param int $id
   *   The guild ID.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mock guild.
   */
  protected function createMockGuild(int $id = 1) {
    $guild = $this->createMock(GroupInterface::class);
    $guild->method('id')->willReturn($id);
    return $guild;
  }

  /**
   * Creates a mock skill term.
   *
   * @param int $id
   *   The term ID.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mock term.
   */
  protected function createMockSkill(int $id = 1) {
    $skill = $this->createMock(TermInterface::class);
    $skill->method('id')->willReturn($id);
    $skill->method('label')->willReturn('Skill ' . $id);
    return $skill;
  }

  /**
   * Creates a mock entity storage with query support.
   *
   * @param mixed $query_result
   *   The value returned by query execute().
   * @param array $loaded_entities
   *   The entities returned by loadMultiple().
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mock storage.
   */
  protected function createMockStorage($query_result = [], array $loaded_entities = []) {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($query_result);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn($loaded_entities);

    return $storage;
  }

  /**
   * Tests hasEndorsed returns false when no endorsement exists.
   *
   * @covers ::hasEndorsed
   */
  public function testHasEndorsedReturnsFalseWhenNoEndorsement(): void {
    $endorser = $this->createMockUser(1);
    $endorsed = $this->createMockUser(2);
    $skill = $this->createMockSkill(1);
    $guild = $this->createMockGuild(1);

    // Count query returns 0 (no existing endorsements).
    $storage = $this->createMockStorage(0);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('skill_endorsement')
      ->willReturn($storage);

    $result = $this->service->hasEndorsed($endorser, $endorsed, $skill, $guild);

    $this->assertFalse($result);
  }

  /**
   * Tests hasEndorsed returns true when an endorsement exists.
   *
   * @covers ::hasEndorsed
   */
  public function testHasEndorsedReturnsTrueWhenExists(): void {
    $endorser = $this->createMockUser(1);
    $endorsed = $this->createMockUser(2);
    $skill = $this->createMockSkill(1);
    $guild = $this->createMockGuild(1);

    // Count query returns 1 (endorsement exists).
    $storage = $this->createMockStorage(1);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('skill_endorsement')
      ->willReturn($storage);

    $result = $this->service->hasEndorsed($endorser, $endorsed, $skill, $guild);

    $this->assertTrue($result);
  }

  /**
   * Tests getEndorsementsFor returns an array.
   *
   * @covers ::getEndorsementsFor
   */
  public function testGetEndorsementsForReturnsArray(): void {
    $user = $this->createMockUser(1);
    $guild = $this->createMockGuild(1);

    // Empty query result, no endorsements found.
    $storage = $this->createMockStorage([], []);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('skill_endorsement')
      ->willReturn($storage);

    $result = $this->service->getEndorsementsFor($user, $guild);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests getEndorsementCountsBySkill returns an array.
   *
   * @covers ::getEndorsementCountsBySkill
   */
  public function testGetEndorsementCountsBySkillReturnsArray(): void {
    $user = $this->createMockUser(1);
    $guild = $this->createMockGuild(1);

    // Empty query result, no endorsements found.
    $storage = $this->createMockStorage([], []);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('skill_endorsement')
      ->willReturn($storage);

    $result = $this->service->getEndorsementCountsBySkill($user, $guild);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests getEndorsementsBy returns an array.
   *
   * @covers ::getEndorsementsBy
   */
  public function testGetEndorsementsByReturnsArray(): void {
    $user = $this->createMockUser(1);
    $guild = $this->createMockGuild(1);

    // Empty query result, no endorsements given.
    $storage = $this->createMockStorage([], []);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('skill_endorsement')
      ->willReturn($storage);

    $result = $this->service->getEndorsementsBy($user, $guild);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

}
