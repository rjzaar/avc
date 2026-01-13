<?php

namespace Drupal\avc_devel\Generator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\node\NodeInterface;

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
    'workflow_tasks' => [],
    'taxonomy_terms' => [],
    'skill_levels' => [],
    'member_skill_progress' => [],
    'skill_credits' => [],
    'level_verifications' => [],
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

    // Generate assets first.
    $summary['projects'] = $this->generateProjects($options['projects']);
    $summary['documents'] = $this->generateDocuments($options['documents']);
    $summary['resources'] = $this->generateResources($options['resources']);

    // Generate workflow tasks for the assets.
    $summary['workflow_tasks'] = $this->generateWorkflowTasks();

    // Generate guild skill level content.
    if (\Drupal::moduleHandler()->moduleExists('avc_guild')) {
      $summary['skill_levels'] = $this->generateSkillLevels();
      $summary['member_skill_progress'] = $this->generateMemberSkillProgress();
      $summary['skill_credits'] = $this->generateSkillCredits();
      $summary['level_verifications'] = $this->generateLevelVerifications();
    }

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
   * Generates workflow tasks for test nodes.
   *
   * @return int
   *   Number of tasks created.
   */
  public function generateWorkflowTasks() {
    $created = 0;

    if (!$this->entityTypeManager->hasDefinition('workflow_task')) {
      $this->logger->warning('Workflow task entity type not available.');
      return 0;
    }

    $storage = $this->entityTypeManager->getStorage('workflow_task');
    $nodes = $this->getTestNodes();
    $users = $this->getTestUsers();
    $groups = $this->getTestGroups();

    if (empty($nodes)) {
      $this->logger->warning('No test nodes available for workflow tasks.');
      return 0;
    }

    // Get destination terms.
    $dest_terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'destination_locations']);
    $dest_ids = array_keys($dest_terms);

    // Define workflow stage templates.
    $stages = [
      [
        'title' => 'Initial Review',
        'description' => 'First review of the content by the submitter.',
        'type' => 'user',
      ],
      [
        'title' => 'Editorial Review',
        'description' => 'Editorial team reviews for style and clarity.',
        'type' => 'group',
      ],
      [
        'title' => 'Theological Review',
        'description' => 'Theological accuracy review.',
        'type' => 'group',
      ],
      [
        'title' => 'Final Approval',
        'description' => 'Final approval by authorized approver.',
        'type' => 'user',
      ],
      [
        'title' => 'Publication',
        'description' => 'Publish to final destination.',
        'type' => 'destination',
      ],
    ];

    $statuses = ['pending', 'in_progress', 'completed'];

    // Create workflow tasks for each test node.
    foreach ($nodes as $node_id) {
      // Randomly select 3-5 stages for this node.
      $num_stages = rand(3, min(5, count($stages)));
      $selected_stages = array_slice($stages, 0, $num_stages);

      $weight = 0;
      $all_completed = TRUE;

      foreach ($selected_stages as $index => $stage) {
        // Determine assigned ID based on type.
        $assigned_user = NULL;
        $assigned_group = NULL;
        $assigned_destination = NULL;

        switch ($stage['type']) {
          case 'user':
            $assigned_user = !empty($users) ? $users[array_rand($users)] : 1;
            break;

          case 'group':
            if (!empty($groups)) {
              $assigned_group = $groups[array_rand($groups)];
            }
            break;

          case 'destination':
            if (!empty($dest_ids)) {
              $assigned_destination = $dest_ids[array_rand($dest_ids)];
            }
            break;
        }

        // Determine status - earlier stages more likely to be completed.
        if ($all_completed && rand(0, 100) > 30 * $index) {
          $status = 'completed';
        }
        else {
          $all_completed = FALSE;
          $status = $index === array_key_last($selected_stages) ? 'pending' : $statuses[array_rand($statuses)];
        }

        $task_values = [
          'title' => '[TEST] ' . $stage['title'],
          'description' => [
            'value' => $stage['description'],
            'format' => 'basic_html',
          ],
          'node_id' => $node_id,
          'weight' => $weight,
          'assigned_type' => $stage['type'],
          'status' => $status,
          'uid' => 1,
        ];

        if ($assigned_user) {
          $task_values['assigned_user'] = $assigned_user;
        }
        if ($assigned_group) {
          $task_values['assigned_group'] = $assigned_group;
        }
        if ($assigned_destination) {
          $task_values['assigned_destination'] = $assigned_destination;
        }

        // Add a due date for some tasks.
        if (rand(0, 1)) {
          $days_ahead = rand(1, 30);
          $task_values['due_date'] = date('Y-m-d', strtotime("+{$days_ahead} days"));
        }

        $task = $storage->create($task_values);
        $task->save();
        $this->generated['workflow_tasks'][] = $task->id();
        $created++;
        $weight++;
      }
    }

    $this->logger->info('Generated @count test workflow tasks.', ['@count' => $created]);
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
    $delete_order = [
      'level_verifications',
      'skill_credits',
      'member_skill_progress',
      'skill_levels',
      'workflow_tasks',
      'nodes',
      'groups',
      'users',
      'taxonomy_terms',
    ];

    foreach ($delete_order as $type) {
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
      'workflow_tasks' => 'workflow_task',
      'taxonomy_terms' => 'taxonomy_term',
      'skill_levels' => 'skill_level',
      'member_skill_progress' => 'member_skill_progress',
      'skill_credits' => 'skill_credit',
      'level_verifications' => 'level_verification',
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

  /**
   * Generates skill level configurations for guilds.
   *
   * @return int
   *   Number of skill levels created.
   */
  public function generateSkillLevels() {
    $created = 0;

    if (!$this->entityTypeManager->hasDefinition('skill_level')) {
      $this->logger->warning('Skill level entity type not available.');
      return 0;
    }

    $groups = $this->getTestGroups();
    if (empty($groups)) {
      $this->logger->warning('No test groups available for skill levels.');
      return 0;
    }

    // Get guild_skills taxonomy terms, or create some if they don't exist.
    $skills = $this->getOrCreateGuildSkills();
    if (empty($skills)) {
      $this->logger->warning('No guild skills available.');
      return 0;
    }

    // Use the SkillConfigurationService to create default levels.
    $config_service = \Drupal::service('avc_guild.skill_configuration');
    $group_storage = $this->entityTypeManager->getStorage('group');

    foreach ($groups as $group_id) {
      $group = $group_storage->load($group_id);
      if (!$group) {
        continue;
      }

      // Create skill levels for 2-3 random skills per guild.
      $selected_skills = (array) array_rand(array_flip($skills), min(3, count($skills)));

      foreach ($selected_skills as $skill_id) {
        $skill = $this->entityTypeManager->getStorage('taxonomy_term')->load($skill_id);
        if (!$skill) {
          continue;
        }

        // Check if levels already exist.
        $existing = $config_service->getSkillLevels($group, $skill);
        if (!empty($existing)) {
          continue;
        }

        // Create default levels.
        $levels = $config_service->createDefaultLevels($group, $skill);
        foreach ($levels as $level) {
          $this->generated['skill_levels'][] = $level->id();
          $created++;
        }
      }
    }

    $this->logger->info('Generated @count skill level configurations.', ['@count' => $created]);
    return $created;
  }

  /**
   * Generates member skill progress records.
   *
   * @return int
   *   Number of progress records created.
   */
  public function generateMemberSkillProgress() {
    $created = 0;

    if (!$this->entityTypeManager->hasDefinition('member_skill_progress')) {
      $this->logger->warning('Member skill progress entity type not available.');
      return 0;
    }

    $groups = $this->getTestGroups();
    $users = $this->getTestUsers();

    if (empty($groups) || empty($users)) {
      $this->logger->warning('No test groups or users available for member skill progress.');
      return 0;
    }

    $storage = $this->entityTypeManager->getStorage('member_skill_progress');
    $group_storage = $this->entityTypeManager->getStorage('group');
    $config_service = \Drupal::service('avc_guild.skill_configuration');

    foreach ($groups as $group_id) {
      $group = $group_storage->load($group_id);
      if (!$group) {
        continue;
      }

      // Get skill levels for this guild.
      $guild_skills = $config_service->getGuildSkillLevels($group);
      if (empty($guild_skills)) {
        continue;
      }

      // Get members of this group.
      $members = $group->getMembers();
      if (empty($members)) {
        continue;
      }

      foreach ($members as $membership) {
        $user = $membership->getUser();
        if (!$user) {
          continue;
        }

        // Create progress for 1-2 random skills.
        $selected_skill_ids = array_rand($guild_skills, min(2, count($guild_skills)));
        if (!is_array($selected_skill_ids)) {
          $selected_skill_ids = [$selected_skill_ids];
        }

        foreach ($selected_skill_ids as $skill_id) {
          $skill = $this->entityTypeManager->getStorage('taxonomy_term')->load($skill_id);
          if (!$skill) {
            continue;
          }

          // Check if progress already exists.
          $existing = $storage->getQuery()
            ->accessCheck(FALSE)
            ->condition('user_id', $user->id())
            ->condition('guild_id', $group->id())
            ->condition('skill_id', $skill->id())
            ->execute();

          if (!empty($existing)) {
            continue;
          }

          // Randomly assign level (0-3) and credits.
          $current_level = rand(0, 3);
          $current_credits = $current_level === 0 ? rand(0, 30) : rand(0, 100);

          $progress = $storage->create([
            'user_id' => $user->id(),
            'guild_id' => $group->id(),
            'skill_id' => $skill->id(),
            'current_level' => $current_level,
            'current_credits' => $current_credits,
            'level_achieved_date' => strtotime('-' . rand(10, 180) . ' days'),
            'pending_verification' => FALSE,
          ]);

          $progress->save();
          $this->generated['member_skill_progress'][] = $progress->id();
          $created++;
        }
      }
    }

    $this->logger->info('Generated @count member skill progress records.', ['@count' => $created]);
    return $created;
  }

  /**
   * Generates sample skill credits.
   *
   * @return int
   *   Number of credits created.
   */
  public function generateSkillCredits() {
    $created = 0;

    if (!$this->entityTypeManager->hasDefinition('skill_credit')) {
      $this->logger->warning('Skill credit entity type not available.');
      return 0;
    }

    $storage = $this->entityTypeManager->getStorage('skill_credit');
    $progress_storage = $this->entityTypeManager->getStorage('member_skill_progress');

    // Get all test progress records.
    $progress_ids = $progress_storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    if (empty($progress_ids)) {
      $this->logger->warning('No member skill progress records available for credits.');
      return 0;
    }

    $progress_records = $progress_storage->loadMultiple($progress_ids);
    $source_types = ['task_review', 'endorsement', 'manual'];

    foreach ($progress_records as $progress) {
      // Create 5-10 credits per member skill.
      $num_credits = rand(5, 10);

      for ($i = 0; $i < $num_credits; $i++) {
        $source_type = $source_types[array_rand($source_types)];
        $credit_amount = rand(1, 15);

        $credit = $storage->create([
          'user_id' => $progress->get('user_id')->target_id,
          'guild_id' => $progress->get('guild_id')->target_id,
          'skill_id' => $progress->get('skill_id')->target_id,
          'credits' => $credit_amount,
          'source_type' => $source_type,
          'notes' => 'Test credit generated by avc_devel module.',
          'created' => strtotime('-' . rand(1, 180) . ' days'),
        ]);

        $credit->save();
        $this->generated['skill_credits'][] = $credit->id();
        $created++;
      }
    }

    $this->logger->info('Generated @count skill credits.', ['@count' => $created]);
    return $created;
  }

  /**
   * Generates sample level verifications.
   *
   * @return int
   *   Number of verifications created.
   */
  public function generateLevelVerifications() {
    $created = 0;

    if (!$this->entityTypeManager->hasDefinition('level_verification')) {
      $this->logger->warning('Level verification entity type not available.');
      return 0;
    }

    $storage = $this->entityTypeManager->getStorage('level_verification');
    $progress_storage = $this->entityTypeManager->getStorage('member_skill_progress');
    $config_service = \Drupal::service('avc_guild.skill_configuration');

    // Get progress records where level > 0 (members who could have verifications).
    $progress_ids = $progress_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('current_level', 0, '>')
      ->execute();

    if (empty($progress_ids)) {
      $this->logger->warning('No member skill progress records with levels for verifications.');
      return 0;
    }

    $progress_records = $progress_storage->loadMultiple($progress_ids);

    // Create 2-3 pending verifications.
    $pending_count = 0;
    foreach (array_slice($progress_records, 0, 3) as $progress) {
      if ($pending_count >= 3) {
        break;
      }

      $guild = $progress->getGuild();
      $skill = $progress->getSkill();
      if (!$guild || !$skill) {
        continue;
      }

      $current_level = $progress->getCurrentLevel();
      $target_level = $current_level + 1;

      // Get level config for target level.
      $level_config = $config_service->getLevelConfig($guild, $skill, $target_level);
      if (!$level_config) {
        continue;
      }

      // Check if verification already exists.
      $existing = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('user_id', $progress->get('user_id')->target_id)
        ->condition('guild_id', $guild->id())
        ->condition('skill_id', $skill->id())
        ->condition('target_level', $target_level)
        ->condition('status', 'pending')
        ->execute();

      if (!empty($existing)) {
        continue;
      }

      $verification = $storage->create([
        'user_id' => $progress->get('user_id')->target_id,
        'guild_id' => $guild->id(),
        'skill_id' => $skill->id(),
        'target_level' => $target_level,
        'status' => 'pending',
        'verification_type' => $level_config->getVerificationType(),
        'votes_required' => $level_config->getVotesRequired(),
        'votes_approve' => 0,
        'votes_deny' => 0,
        'votes_defer' => 0,
      ]);

      $verification->save();
      $this->generated['level_verifications'][] = $verification->id();
      $created++;
      $pending_count++;
    }

    // Create 2-3 approved verifications (historical).
    $approved_count = 0;
    foreach (array_slice($progress_records, 3, 3) as $progress) {
      if ($approved_count >= 3) {
        break;
      }

      $guild = $progress->getGuild();
      $skill = $progress->getSkill();
      if (!$guild || !$skill) {
        continue;
      }

      $current_level = $progress->getCurrentLevel();
      if ($current_level === 0) {
        continue;
      }

      // Get level config for current level (the one they achieved).
      $level_config = $config_service->getLevelConfig($guild, $skill, $current_level);
      if (!$level_config) {
        continue;
      }

      $verification = $storage->create([
        'user_id' => $progress->get('user_id')->target_id,
        'guild_id' => $guild->id(),
        'skill_id' => $skill->id(),
        'target_level' => $current_level,
        'status' => 'approved',
        'verification_type' => $level_config->getVerificationType(),
        'votes_required' => $level_config->getVotesRequired(),
        'votes_approve' => $level_config->getVotesRequired(),
        'votes_deny' => 0,
        'votes_defer' => 0,
        'created' => strtotime('-' . rand(30, 180) . ' days'),
        'completed' => strtotime('-' . rand(20, 170) . ' days'),
        'feedback' => 'Approved based on demonstrated competency. Good work!',
      ]);

      $verification->save();
      $this->generated['level_verifications'][] = $verification->id();
      $created++;
      $approved_count++;
    }

    $this->logger->info('Generated @count level verifications.', ['@count' => $created]);
    return $created;
  }

  /**
   * Gets or creates guild skills taxonomy terms.
   *
   * @return array
   *   Array of term IDs.
   */
  protected function getOrCreateGuildSkills() {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Check if guild_skills vocabulary exists.
    $vocab_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    if (!$vocab_storage->load('guild_skills')) {
      return [];
    }

    // Get existing terms.
    $existing = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'guild_skills')
      ->execute();

    if (!empty($existing)) {
      return array_values($existing);
    }

    // Create default skill terms.
    $skill_names = [
      'Technical Writing',
      'Translation',
      'Editing',
      'Proofreading',
      'Theology',
      'Research',
    ];

    $created_ids = [];
    foreach ($skill_names as $name) {
      $term = $term_storage->create([
        'vid' => 'guild_skills',
        'name' => $name,
      ]);
      $term->save();
      $created_ids[] = $term->id();
      $this->generated['taxonomy_terms'][] = $term->id();
    }

    $this->logger->info('Created @count guild skills terms.', ['@count' => count($created_ids)]);
    return $created_ids;
  }

}
