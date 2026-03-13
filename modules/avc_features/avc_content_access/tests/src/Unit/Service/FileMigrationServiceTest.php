<?php

namespace Drupal\Tests\avc_content_access\Unit\Service;

use Drupal\avc_content_access\Service\FileMigrationService;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for FileMigrationService.
 *
 * @group avc_content_access
 * @coversDefaultClass \Drupal\avc_content_access\Service\FileMigrationService
 */
class FileMigrationServiceTest extends UnitTestCase {

  protected FileMigrationService $service;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected FileSystemInterface $fileSystem;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->fileSystem = $this->createMock(FileSystemInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $this->service = new FileMigrationService(
      $this->entityTypeManager,
      $this->fileSystem,
      $loggerFactory
    );
  }

  /**
   * Tests migrateNodeFiles with no file fields.
   *
   * @covers ::migrateNodeFiles
   */
  public function testMigrateNodeFilesNoFileFields(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);
    $node->method('getFieldDefinitions')->willReturn([]);

    $results = $this->service->migrateNodeFiles($node, 'private://');

    $this->assertEquals(0, $results['success']);
    $this->assertEquals(0, $results['failed']);
    $this->assertEquals(0, $results['skipped']);
  }

  /**
   * Tests migrateNodeFiles skips files already in target scheme.
   *
   * @covers ::migrateNodeFiles
   */
  public function testMigrateNodeFilesSkipsAlreadyInScheme(): void {
    // Create a file field definition.
    $field_def = $this->createMock(FieldDefinitionInterface::class);
    $field_def->method('getType')->willReturn('file');

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(1);
    $node->method('getFieldDefinitions')->willReturn([
      'field_files' => $field_def,
    ]);
    $node->method('hasField')->with('field_files')->willReturn(TRUE);

    // File field item with a target_id.
    $fileItem = new \stdClass();
    $fileItem->target_id = 10;

    $fieldList = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['getIterator'])
      ->getMock();

    // Use an ArrayIterator so foreach works.
    $node->method('get')->with('field_files')->willReturn(new \ArrayObject([$fileItem]));

    // File entity already in private://.
    $file = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['getFileUri', 'id'])
      ->getMock();
    $file->method('getFileUri')->willReturn('private://documents/test.pdf');
    $file->method('id')->willReturn(10);

    $fileStorage = $this->createMock(EntityStorageInterface::class);
    $fileStorage->method('load')->with(10)->willReturn($file);

    $this->entityTypeManager->method('getStorage')
      ->with('file')
      ->willReturn($fileStorage);

    // No need to mock uriScheme - service now uses strstr() directly.

    $results = $this->service->migrateNodeFiles($node, 'private://');

    $this->assertEquals(0, $results['success']);
    $this->assertEquals(0, $results['failed']);
    $this->assertEquals(1, $results['skipped']);
  }

}
