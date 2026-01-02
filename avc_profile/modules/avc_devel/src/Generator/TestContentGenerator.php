<?php

namespace Drupal\avc_devel\Generator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;

/**
 * Service for generating test content for AVC.
 */
class TestContentGenerator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The password generator.
   *
   * @var \Drupal\Core\Password\PasswordGeneratorInterface
   */
  protected $passwordGenerator;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Generated content tracking.
   *
   * @var array
   */
  protected $generated = [
    'users' => [],
    'groups' => [],
    'nodes' => [],
    'workflow_assignments' => [],
    'taxonomy_terms' => [],
  ];

  /**
   * Constructs a TestContentGenerator object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PasswordGeneratorInterface $password_generator,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordGenerator = $password_generator;
    $this->logger = $logger_factory->get('avc_devel');
  }

  /**
   * Generates all test content.
   *
   * @param array $options
   *   Generation options.
   *
   * @return array
   *   Summary of generated content.
   */
  public function generateAll(array $options = []) {
    $defaults = [
      'users' => 10,
      'groups' => 3,
      'projects' => 2,
      'documents' => 5,
      'resources' => 3,
      'workflow_assignments' => 10,
    ];
    $options = array_merge($defaults, $options);

    $summary = [];

    // Generate users first.
    $summary['users'] = $this->generateUsers($options['users']);

    // Generate groups with members.
    $summary['groups'] = $this->generateGroups($options['groups']);

    // Generate assets.
    $summary['projects'] = $this->generateProjects($options['projects']);
    $summary['documents'] = $this->generateDocuments($options['documents']);
    $summary['resources'] = $this->generateResources($options['resources']);

    // Generate workflow assignments.
    $summary['workflow_assignments'] = $this->generateWorkflowAssignments($options['workflow_assignments']);

    // Store generated IDs for cleanup.
    $this->saveGeneratedIds();

    return $summary;
  }

  /**
   * Generates test users.
   *
   * @param int $count
   *   Number of users to generate.
   *
   * @return int
   *   Number of users created.
   */
  public function generateUsers($count = 10) {
    $created = 0;
    $storage = $this->entityTypeManager->getStorage('user');

    $skills = ['translation', 'editing', 'proofreading', 'theology', 'technical', 'design'];
    $levels = ['disciple', 'aspirant', 'sojourner', 'none'];

    for ($i = 1; $i <= $count; $i++) {
      $username = 'avc_test_user_' . $i;

      // Check if user already exists.
      $existing = $storage->loadByProperties(['name' => $username]);
      if (!empty($existing)) {
        continue;
      }

      $user = $storage->create([
        'name' => $username,
        'mail' => "avc_test_{$i}@example.com",
        'pass' => 'test123',
        'status' => 1,
        'roles' => ['authenticated'],
      ]);

      // Add profile fields if they exist.
      if ($user->hasField('field_profile_first_name')) {
        $user->set('field_profile_first_name', $this->getRandomFirstName());
      }
      if ($user->hasField('field_profile_last_name')) {
        $user->set('field_profile_last_name', $this->getRandomLastName());
      }
      if ($user->hasField('field_av_level')) {
        $user->set('field_av_level', $levels[array_rand($levels)]);
      }
      if ($user->hasField('field_notification_default')) {
        $user->set('field_notification_default', ['n', 'd', 'w', 'x'][array_rand(['n', 'd', 'w', 'x'])]);
      }

      $user->save();
      $this->generated['users'][] = $user->id();
      $created++;
    }

    $this->logger->info('Generated @count test users.', ['@count' => $created]);
    return $created;
  }

  /**
   * Generates test groups.
   *
   * @param int $count
   *   Number of groups to generate.
   *
   * @return int
   *   Number of groups created.
   */
  public function generateGroups($count = 3) {
    $created = 0;

    if (!$this->entityTypeManager->hasDefinition('group')) {
      $this->logger->warning('Group entity type not available.');
      return 0;
    }

    $storage = $this->entityTypeManager->getStorage('group');

    $group_names = [
      'Translation Team' => 'Translates AV Commons content into multiple languages.',
      'Editorial Board' => 'Reviews and approves content before publication.',
      'Technical Team' => 'Handles technical documentation and development.',
      'Theology Group' => 'Reviews theological content for accuracy.',
      'Media Team' => 'Creates and edits videos and visual content.',
      'Prayer Warriors' => 'Provides prayer support for the community.',
    ];

    $i = 0;
    foreach ($group_names as $name => $description) {
      if ($i >= $count) {
        break;
      }

      // Check if group exists.
      $existing = $storage->loadByProperties(['label' => $name]);
      if (!empty($existing)) {
        $i++;
        continue;
      }

      // Get available group type.
      $group_type = $this->getAvailableGroupType();
      if (!$group_type) {
        $this->logger->warning('No group type available.');
        break;
      }

      $group = $storage->create([
        'type' => $group_type,
        'label' => $name,
        'field_group_description' => ['value' => $description, 'format' => 'basic_html'],
        'uid' => 1,
      ]);

      $group->save();
      $this->generated['groups'][] = $group->id();

      // Add random members.
      $this->addGroupMembers($group);

      $created++;
      $i++;
    }

    $this->logger->info('Generated @count test groups.', ['@count' => $created]);
    return $created;
  }

  /**
   * Generates test projects.
   *
   * @param int $count
   *   Number of projects to generate.
   *
   * @return int
   *   Number of projects created.
   */
  public function generateProjects($count = 2) {
    return $this->generateAssets('project', $count, [
      'AV Commons Mobile App' => 'Development of mobile application for iOS and Android.',
      'Liturgy Translation Project' => 'Translating liturgical texts into modern languages.',
      'Formation Course Development' => 'Creating online formation courses for new members.',
      'Community Guidelines Revision' => 'Updating community guidelines and policies.',
    ]);
  }

  /**
   * Generates test documents.
   *
   * @param int $count
   *   Number of documents to generate.
   *
   * @return int
   *   Number of documents created.
   */
  public function generateDocuments($count = 5) {
    return $this->generateAssets('document', $count, [
      'Prayer of the Day Guide' => 'Daily prayer guide for community members.',
      'Technical Documentation' => 'System architecture and API documentation.',
      'Member Handbook' => 'Comprehensive guide for new community members.',
      'Theology of Community' => 'Foundational document on community spirituality.',
      'Workflow Best Practices' => 'Guidelines for effective workflow management.',
      'Translation Style Guide' => 'Standards for consistent translation across languages.',
      'Meeting Minutes Template' => 'Template for recording group meeting minutes.',
      'Annual Report 2024' => 'Summary of community activities and achievements.',
    ]);
  }

  /**
   * Generates test resources.
   *
   * @param int $count
   *   Number of resources to generate.
   *
   * @return int
   *   Number of resources created.
   */
  public function generateResources($count = 3) {
    return $this->generateAssets('resource', $count, [
      'Vatican II Documents' => 'Link to official Vatican II documents.',
      'Catechism Online' => 'Official Catechism of the Catholic Church.',
      'Liturgy of the Hours App' => 'Mobile app for praying the Divine Office.',
      'Scripture Study Tools' => 'Collection of biblical study resources.',
      'Formation Videos' => 'Video library for spiritual formation.',
    ]);
  }

  /**
   * Generates assets of a given type.
   *
   * @param string $type
   *   The asset type (project, document, resource).
   * @param int $count
   *   Number to generate.
   * @param array $samples
   *   Sample titles and descriptions.
   *
   * @return int
   *   Number created.
   */
  protected function generateAssets($type, $count, array $samples) {
    $created = 0;
    $bundle = 'avc_' . $type;

    // Check if bundle exists, otherwise use 'page'.
    $bundles = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    if (!isset($bundles[$bundle])) {
      $bundle = 'page';
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $users = $this->getTestUsers();

    $i = 0;
    foreach ($samples as $title => $description) {
      if ($i >= $count) {
        break;
      }

      // Check if exists.
      $existing = $storage->loadByProperties(['title' => '[TEST] ' . $title]);
      if (!empty($existing)) {
        $i++;
        continue;
      }

      $author = !empty($users) ? $users[array_rand($users)] : 1;

      $node = $storage->create([
        'type' => $bundle,
        'title' => '[TEST] ' . $title,
        'body' => [
          'value' => '<p>' . $description . '</p><p>This is test content generated by AVC Development module.</p>',
          'format' => 'basic_html',
        ],
        'uid' => $author,
        'status' => 1,
      ]);

      // Add asset-specific fields if they exist.
      if ($node->hasField('field_asset_type')) {
        $node->set('field_asset_type', $type);
      }
      if ($node->hasField('field_asset_number')) {
        $prefix = strtoupper(substr($type, 0, 3));
        $node->set('field_asset_number', $prefix . '-' . date('ymd') . '-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT));
      }
      if ($node->hasField('field_process_status')) {
        $statuses = ['draft', 'in_progress', 'review', 'approved'];
        $node->set('field_process_status', $statuses[array_rand($statuses)]);
      }

      $node->save();
      $this->generated['nodes'][] = $node->id();
      $created++;
      $i++;
    }

    $this->logger->info('Generated @count test @type assets.', [
      '@count' => $created,
      '@type' => $type,
    ]);

    return $created;
  }

  /**
   * Generates workflow assignments.
   *
   * @param int $count
   *   Number to generate.
   *
   * @return int
   *   Number created.
   */
  public function generateWorkflowAssignments($count = 10) {
    $created = 0;

    if (!$this->entityTypeManager->hasDefinition('workflow_assignment')) {
      $this->logger->warning('Workflow assignment entity type not available.');
      return 0;
    }

    $storage = $this->entityTypeManager->getStorage('workflow_assignment');
    $users = $this->getTestUsers();
    $nodes = $this->getTestNodes();
    $groups = $this->getTestGroups();

    if (empty($users) && empty($groups)) {
      $this->logger->warning('No users or groups available for assignments.');
      return 0;
    }

    $stages = [
      'Initial Review',
      'Editorial Review',
      'Theological Review',
      'Translation',
      'Proofreading',
      'Final Approval',
      'Publication',
    ];

    $statuses = ['proposed', 'accepted', 'completed'];

    for ($i = 0; $i < $count; $i++) {
      $label = '[TEST] ' . $stages[array_rand($stages)] . ' - ' . ($i + 1);

      // Randomly assign to user or group.
      $assigned_type = rand(0, 1) ? 'user' : 'group';
      $owner_uid = !empty($users) ? $users[array_rand($users)] : 1;
      $values = [
        'title' => $label,
        'description' => ['value' => 'Test workflow assignment generated for development.', 'format' => 'basic_html'],
        'completion' => $statuses[array_rand($statuses)],
        'uid' => $owner_uid,
      ];

      if ($assigned_type === 'user' && !empty($users)) {
        $values['assigned_type'] = 'user';
        $values['assigned_user'] = $users[array_rand($users)];
      }
      elseif (!empty($groups)) {
        $values['assigned_type'] = 'group';
        $values['assigned_group'] = $groups[array_rand($groups)];
      }
      else {
        continue;
      }

      // Link to a node if available.
      if (!empty($nodes)) {
        $values['node_id'] = $nodes[array_rand($nodes)];
      }

      $assignment = $storage->create($values);
      $assignment->save();
      $this->generated['workflow_assignments'][] = $assignment->id();
      $created++;
    }

    $this->logger->info('Generated @count test workflow assignments.', ['@count' => $created]);
    return $created;
  }

  /**
   * Adds random members to a group.
   *
   * @param mixed $group
   *   The group entity.
   */
  protected function addGroupMembers($group) {
    $users = $this->getTestUsers();
    if (empty($users)) {
      return;
    }

    // Add 3-7 random members.
    $member_count = rand(3, min(7, count($users)));
    $selected = array_rand(array_flip($users), $member_count);
    if (!is_array($selected)) {
      $selected = [$selected];
    }

    foreach ($selected as $uid) {
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      if ($user && method_exists($group, 'addMember')) {
        try {
          $group->addMember($user);
        }
        catch (\Exception $e) {
          // Member might already exist.
        }
      }
    }
  }

  /**
   * Gets available group type.
   *
   * @return string|null
   *   The group type ID.
   */
  protected function getAvailableGroupType() {
    if (!$this->entityTypeManager->hasDefinition('group_type')) {
      return NULL;
    }

    $types = $this->entityTypeManager->getStorage('group_type')->loadMultiple();

    // Prefer flexible_group or open_group.
    foreach (['flexible_group', 'open_group', 'closed_group'] as $preferred) {
      if (isset($types[$preferred])) {
        return $preferred;
      }
    }

    // Return first available.
    return !empty($types) ? key($types) : NULL;
  }

  /**
   * Gets test user IDs.
   *
   * @return array
   *   Array of user IDs.
   */
  protected function getTestUsers() {
    if (!empty($this->generated['users'])) {
      return $this->generated['users'];
    }

    $storage = $this->entityTypeManager->getStorage('user');
    $query = $storage->getQuery()
      ->condition('name', 'avc_test_user_%', 'LIKE')
      ->accessCheck(FALSE);
    return $query->execute();
  }

  /**
   * Gets test node IDs.
   *
   * @return array
   *   Array of node IDs.
   */
  protected function getTestNodes() {
    if (!empty($this->generated['nodes'])) {
      return $this->generated['nodes'];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('title', '[TEST]%', 'LIKE')
      ->accessCheck(FALSE);
    return $query->execute();
  }

  /**
   * Gets test group IDs.
   *
   * @return array
   *   Array of group IDs.
   */
  protected function getTestGroups() {
    if (!empty($this->generated['groups'])) {
      return $this->generated['groups'];
    }

    if (!$this->entityTypeManager->hasDefinition('group')) {
      return [];
    }

    // Return all groups for now.
    $storage = $this->entityTypeManager->getStorage('group');
    $query = $storage->getQuery()->accessCheck(FALSE);
    return $query->execute();
  }

  /**
   * Saves generated IDs for later cleanup.
   */
  protected function saveGeneratedIds() {
    \Drupal::state()->set('avc_devel.generated', $this->generated);
  }

  /**
   * Cleans up all generated test content.
   *
   * @return array
   *   Summary of deleted content.
   */
  public function cleanupAll() {
    $summary = [];
    $generated = \Drupal::state()->get('avc_devel.generated', []);

    // Delete in reverse order of creation.
    foreach (['workflow_assignments', 'nodes', 'groups', 'users'] as $type) {
      $count = 0;
      $ids = $generated[$type] ?? [];

      if (empty($ids)) {
        continue;
      }

      $entity_type = $this->getEntityType($type);
      if (!$entity_type || !$this->entityTypeManager->hasDefinition($entity_type)) {
        continue;
      }

      $storage = $this->entityTypeManager->getStorage($entity_type);
      $entities = $storage->loadMultiple($ids);

      foreach ($entities as $entity) {
        $entity->delete();
        $count++;
      }

      $summary[$type] = $count;
    }

    // Clear state.
    \Drupal::state()->delete('avc_devel.generated');

    $this->logger->info('Cleaned up test content: @summary', ['@summary' => json_encode($summary)]);
    return $summary;
  }

  /**
   * Maps type keys to entity types.
   */
  protected function getEntityType($key) {
    $map = [
      'users' => 'user',
      'groups' => 'group',
      'nodes' => 'node',
      'workflow_assignments' => 'workflow_assignment',
      'taxonomy_terms' => 'taxonomy_term',
    ];
    return $map[$key] ?? NULL;
  }

  /**
   * Gets a random first name.
   */
  protected function getRandomFirstName() {
    $names = ['John', 'Mary', 'James', 'Sarah', 'Michael', 'Elizabeth', 'David', 'Anna', 'Peter', 'Catherine', 'Thomas', 'Teresa', 'Francis', 'Clare', 'Joseph', 'Maria'];
    return $names[array_rand($names)];
  }

  /**
   * Gets a random last name.
   */
  protected function getRandomLastName() {
    $names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore'];
    return $names[array_rand($names)];
  }

}
