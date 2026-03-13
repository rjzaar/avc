<?php

namespace Drupal\Tests\avc_guild\Unit\Service;

use Drupal\avc_guild\Service\ScoringService;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for ScoringService.
 *
 * @group avc_guild
 * @coversDefaultClass \Drupal\avc_guild\Service\ScoringService
 */
class ScoringServiceTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The service under test.
   *
   * @var \Drupal\avc_guild\Service\ScoringService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $this->service = new ScoringService(
      $this->entityTypeManager
    );
  }

  /**
   * Creates a mock user.
   *
   * @param int $id
   *   The user ID.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
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
    $guild->method('getMembers')->willReturn([]);
    $guild->method('hasField')->willReturn(FALSE);
    return $guild;
  }

  /**
   * Creates a mock score entity.
   *
   * @param int $points
   *   The points value.
   * @param string $action_type
   *   The action type.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mock score entity.
   */
  protected function createMockScore(int $points = 10, string $action_type = 'task_completed') {
    $score = $this->createMock(ContentEntityInterface::class);
    $score->method('id')->willReturn(1);
    return $score;
  }

  /**
   * Creates a mock entity storage with query support.
   *
   * @param array $query_result
   *   The IDs returned by query execute().
   * @param array $loaded_entities
   *   The entities returned by loadMultiple().
   * @param object|null $created_entity
   *   The entity returned by create().
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mock storage.
   */
  protected function createMockStorage(array $query_result = [], array $loaded_entities = [], $created_entity = NULL) {
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

    if ($created_entity !== NULL) {
      $storage->method('create')->willReturn($created_entity);
    }

    return $storage;
  }

  /**
   * Tests that awardPoints creates a score entity and saves it.
   *
   * @covers ::awardPoints
   */
  public function testAwardPointsCreatesScore(): void {
    $user = $this->createMockUser(1);
    $guild = $this->createMockGuild(1);

    // The created score entity mock.
    $score = $this->createMockScore(10, 'task_completed');
    $score->expects($this->once())->method('save');

    // Storage that returns the created score and supports the query
    // for checkPromotion's getTotalScore call.
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('create')->willReturn($score);
    $storage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('guild_score')
      ->willReturn($storage);

    // checkPromotion calls avc_guild_get_member_role which is a module
    // function. We avoid that by not having the guild report a 'junior' role.
    // The guild hasField returns FALSE (set in createMockGuild) so
    // checkPromotion returns early.
    $result = $this->service->awardPoints($user, $guild, 'task_completed', 10);

    $this->assertSame($score, $result);
  }

  /**
   * Tests getTotalScore returns zero for a user with no scores.
   *
   * @covers ::getTotalScore
   */
  public function testGetTotalScoreReturnsZeroForNewUser(): void {
    $user = $this->createMockUser(1);
    $guild = $this->createMockGuild(1);

    // Empty query result means no score entities.
    $storage = $this->createMockStorage([], []);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('guild_score')
      ->willReturn($storage);

    $result = $this->service->getTotalScore($user, $guild);

    $this->assertSame(0, $result);
  }

  /**
   * Tests getScoreBreakdown returns empty array for a new user.
   *
   * @covers ::getScoreBreakdown
   */
  public function testGetScoreBreakdownReturnsEmptyForNewUser(): void {
    $user = $this->createMockUser(1);
    $guild = $this->createMockGuild(1);

    // Empty query result means no score entities.
    $storage = $this->createMockStorage([], []);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('guild_score')
      ->willReturn($storage);

    $result = $this->service->getScoreBreakdown($user, $guild);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests getLeaderboard returns an array.
   *
   * @covers ::getLeaderboard
   */
  public function testGetLeaderboardReturnsArray(): void {
    // createMockGuild sets getMembers to return [], so leaderboard is empty.
    $guild = $this->createMockGuild(1);

    $result = $this->service->getLeaderboard($guild);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Tests getRecentActivity returns an array.
   *
   * @covers ::getRecentActivity
   */
  public function testGetRecentActivityReturnsArray(): void {
    $user = $this->createMockUser(1);
    $guild = $this->createMockGuild(1);

    // Empty query result means no recent activity.
    $storage = $this->createMockStorage([], []);

    $this->entityTypeManager
      ->method('getStorage')
      ->with('guild_score')
      ->willReturn($storage);

    $result = $this->service->getRecentActivity($user, $guild);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

}
