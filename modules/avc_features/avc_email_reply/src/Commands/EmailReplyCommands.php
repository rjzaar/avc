<?php

namespace Drupal\avc_email_reply\Commands;

use Drupal\avc_email_reply\Service\EmailReplyProcessor;
use Drupal\avc_email_reply\Service\ReplyTokenService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

/**
 * Drush commands for the AVC Email Reply module.
 */
class EmailReplyCommands extends DrushCommands {

  /**
   * The reply token service.
   *
   * @var \Drupal\avc_email_reply\Service\ReplyTokenService
   */
  protected $replyTokenService;

  /**
   * The email processor service.
   *
   * @var \Drupal\avc_email_reply\Service\EmailReplyProcessor
   */
  protected $emailProcessor;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an EmailReplyCommands object.
   */
  public function __construct(
    ReplyTokenService $reply_token_service,
    EmailReplyProcessor $email_processor,
    EntityTypeManagerInterface $entity_type_manager,
    QueueFactory $queue_factory,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct();
    $this->replyTokenService = $reply_token_service;
    $this->emailProcessor = $email_processor;
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
    $this->configFactory = $config_factory;
  }

  /**
   * Check the status of the email reply system.
   *
   * @command email-reply:status
   * @aliases er-status
   * @usage email-reply:status
   *   Show the status of the email reply system.
   */
  public function status() {
    $config = $this->configFactory->get('avc_email_reply.settings');

    $enabled = $config->get('enabled') ? 'Enabled' : 'Disabled';
    $reply_domain = $config->get('reply_domain') ?: '(not configured)';
    $provider = $config->get('email_provider') ?: 'sendgrid';
    $webhook_secret = $config->get('webhook_secret') ? 'Set' : 'Not set';
    $token_expiry = $config->get('token_expiry_days') ?: 30;
    $spam_threshold = $config->get('spam_score_threshold') ?: 5.0;
    $allowed_types = $config->get('allowed_content_types') ?: [];
    $debug_mode = $config->get('debug_mode') ? 'On' : 'Off';

    $queue = $this->queueFactory->get('avc_email_reply');
    $queue_count = $queue->numberOfItems();

    $this->output()->writeln('');
    $this->output()->writeln('<info>Email Reply System Status</info>');
    $this->output()->writeln('═══════════════════════════════════════');
    $this->output()->writeln('');
    $this->output()->writeln("  Status:          <comment>{$enabled}</comment>");
    $this->output()->writeln("  Reply Domain:    <comment>{$reply_domain}</comment>");
    $this->output()->writeln("  Email Provider:  <comment>{$provider}</comment>");
    $this->output()->writeln("  Webhook Secret:  <comment>{$webhook_secret}</comment>");
    $this->output()->writeln("  Token Expiry:    <comment>{$token_expiry} days</comment>");
    $this->output()->writeln("  Spam Threshold:  <comment>{$spam_threshold}</comment>");
    $this->output()->writeln("  Debug Mode:      <comment>{$debug_mode}</comment>");
    $this->output()->writeln("  Queue Items:     <comment>{$queue_count}</comment>");
    $this->output()->writeln('');

    if (!empty($allowed_types)) {
      $this->output()->writeln('  Allowed Content Types:');
      foreach ($allowed_types as $type) {
        $this->output()->writeln("    - {$type}");
      }
    }
    else {
      $this->output()->writeln('  Allowed Content Types: <comment>(none configured)</comment>');
    }

    $this->output()->writeln('');

    return self::EXIT_SUCCESS;
  }

  /**
   * Enable the email reply system.
   *
   * @command email-reply:enable
   * @aliases er-enable
   * @option domain The reply email domain (e.g., reply.example.com).
   * @usage email-reply:enable --domain=reply.example.com
   *   Enable email reply with the specified domain.
   */
  public function enable($options = ['domain' => NULL]) {
    $config = $this->configFactory->getEditable('avc_email_reply.settings');

    if ($options['domain']) {
      $config->set('reply_domain', $options['domain']);
    }

    $config->set('enabled', TRUE);
    $config->save();

    $domain = $config->get('reply_domain') ?: '(using site domain)';
    $this->output()->writeln("<info>Email reply system enabled with domain: {$domain}</info>");

    return self::EXIT_SUCCESS;
  }

  /**
   * Disable the email reply system.
   *
   * @command email-reply:disable
   * @aliases er-disable
   * @usage email-reply:disable
   *   Disable the email reply system.
   */
  public function disable() {
    $config = $this->configFactory->getEditable('avc_email_reply.settings');
    $config->set('enabled', FALSE);
    $config->save();

    $this->output()->writeln('<info>Email reply system disabled.</info>');

    return self::EXIT_SUCCESS;
  }

  /**
   * Configure email reply settings.
   *
   * @command email-reply:configure
   * @aliases er-config
   * @option domain The reply email domain.
   * @option provider Email provider (sendgrid or mailgun).
   * @option secret Webhook verification secret.
   * @option expiry Token expiry in days.
   * @option spam-threshold Spam score threshold.
   * @option debug Enable debug mode.
   * @usage email-reply:configure --domain=reply.example.com --provider=sendgrid
   *   Configure email reply settings.
   */
  public function configure($options = [
    'domain' => NULL,
    'provider' => NULL,
    'secret' => NULL,
    'expiry' => NULL,
    'spam-threshold' => NULL,
    'debug' => NULL,
  ]) {
    $config = $this->configFactory->getEditable('avc_email_reply.settings');

    $changes = [];

    if ($options['domain'] !== NULL) {
      $config->set('reply_domain', $options['domain']);
      $changes[] = "reply_domain = {$options['domain']}";
    }

    if ($options['provider'] !== NULL) {
      $config->set('email_provider', $options['provider']);
      $changes[] = "email_provider = {$options['provider']}";
    }

    if ($options['secret'] !== NULL) {
      $config->set('webhook_secret', $options['secret']);
      $changes[] = 'webhook_secret = (set)';
    }

    if ($options['expiry'] !== NULL) {
      $config->set('token_expiry_days', (int) $options['expiry']);
      $changes[] = "token_expiry_days = {$options['expiry']}";
    }

    if ($options['spam-threshold'] !== NULL) {
      $config->set('spam_score_threshold', (float) $options['spam-threshold']);
      $changes[] = "spam_score_threshold = {$options['spam-threshold']}";
    }

    if ($options['debug'] !== NULL) {
      $debug = strtolower($options['debug']) === 'true' || $options['debug'] === '1';
      $config->set('debug_mode', $debug);
      $changes[] = 'debug_mode = ' . ($debug ? 'true' : 'false');
    }

    if (empty($changes)) {
      $this->output()->writeln('<comment>No configuration changes specified.</comment>');
      return self::EXIT_SUCCESS;
    }

    $config->save();

    $this->output()->writeln('<info>Configuration updated:</info>');
    foreach ($changes as $change) {
      $this->output()->writeln("  - {$change}");
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Generate a reply token for testing.
   *
   * @command email-reply:generate-token
   * @aliases er-token
   * @param int $entity_id The entity ID (node ID).
   * @param int $user_id The user ID.
   * @option entity-type The entity type (default: node).
   * @option group-id Optional group ID.
   * @usage email-reply:generate-token 1 2
   *   Generate a token for node 1 and user 2.
   */
  public function generateToken($entity_id, $user_id, $options = [
    'entity-type' => 'node',
    'group-id' => NULL,
  ]) {
    $entity_type = $options['entity-type'];
    $group_id = $options['group-id'] ? (int) $options['group-id'] : NULL;

    // Validate entity exists.
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      $this->output()->writeln("<error>Entity {$entity_type}:{$entity_id} not found.</error>");
      return self::EXIT_FAILURE;
    }

    // Validate user exists.
    $user = $this->entityTypeManager->getStorage('user')->load($user_id);
    if (!$user) {
      $this->output()->writeln("<error>User {$user_id} not found.</error>");
      return self::EXIT_FAILURE;
    }

    try {
      $token = $this->replyTokenService->generateToken(
        $entity_type,
        (int) $entity_id,
        (int) $user_id,
        $group_id
      );

      $config = $this->configFactory->get('avc_email_reply.settings');
      $reply_domain = $config->get('reply_domain') ?: 'example.com';

      $this->output()->writeln('');
      $this->output()->writeln('<info>Generated Reply Token</info>');
      $this->output()->writeln('═══════════════════════════════════════');
      $this->output()->writeln('');
      $this->output()->writeln("  Entity:     {$entity_type}:{$entity_id} ({$entity->label()})");
      $this->output()->writeln("  User:       {$user_id} ({$user->getDisplayName()})");
      $this->output()->writeln("  User Email: {$user->getEmail()}");
      if ($group_id) {
        $group = $this->entityTypeManager->getStorage('group')->load($group_id);
        $this->output()->writeln("  Group:      {$group_id}" . ($group ? " ({$group->label()})" : ''));
      }
      $this->output()->writeln('');
      $this->output()->writeln("  <comment>Token:</comment>");
      $this->output()->writeln("  {$token}");
      $this->output()->writeln('');
      $this->output()->writeln("  <comment>Reply-To Address:</comment>");
      $this->output()->writeln("  reply+{$token}@{$reply_domain}");
      $this->output()->writeln('');

      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->output()->writeln("<error>Failed to generate token: {$e->getMessage()}</error>");
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Simulate an email reply for testing.
   *
   * @command email-reply:simulate
   * @aliases er-simulate
   * @param string $token The reply token.
   * @param string $from_email The sender email address.
   * @param string $text The reply text content.
   * @usage email-reply:simulate TOKEN user@example.com "This is my reply"
   *   Simulate an email reply with the given parameters.
   */
  public function simulate($token, $from_email, $text) {
    // Validate token first.
    $token_data = $this->replyTokenService->validateToken($token);
    if (!$token_data) {
      $this->output()->writeln('<error>Invalid or expired token.</error>');
      return self::EXIT_FAILURE;
    }

    $this->output()->writeln('Simulating email reply...');
    $this->output()->writeln('');

    $processor_data = [
      'token' => $token,
      'from' => $from_email,
      'text_content' => $text,
      'spam_score' => 0.0,
      'spf_result' => 'pass',
      'dkim_result' => 'pass',
    ];

    try {
      $result = $this->emailProcessor->process($processor_data);

      if ($result->isSuccess()) {
        $comment = $result->getComment();
        $this->output()->writeln('<info>Email reply processed successfully!</info>');
        $this->output()->writeln('');
        $this->output()->writeln("  Message:    {$result->getMessage()}");
        if ($comment) {
          $this->output()->writeln("  Comment ID: {$comment->id()}");
          $this->output()->writeln("  Comment URL: {$comment->toUrl('canonical', ['absolute' => TRUE])->toString()}");
        }
        $this->output()->writeln('');
        return self::EXIT_SUCCESS;
      }
      else {
        $this->output()->writeln("<error>Failed: {$result->getMessage()}</error>");
        return self::EXIT_FAILURE;
      }
    }
    catch (\Exception $e) {
      $this->output()->writeln("<error>Exception: {$e->getMessage()}</error>");
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Process the email reply queue.
   *
   * @command email-reply:process-queue
   * @aliases er-queue
   * @option limit Maximum number of items to process.
   * @usage email-reply:process-queue
   *   Process all items in the email reply queue.
   * @usage email-reply:process-queue --limit=10
   *   Process up to 10 items from the queue.
   */
  public function processQueue($options = ['limit' => 0]) {
    $queue = $this->queueFactory->get('avc_email_reply');
    $count = $queue->numberOfItems();

    if ($count === 0) {
      $this->output()->writeln('<info>Queue is empty.</info>');
      return self::EXIT_SUCCESS;
    }

    $limit = (int) $options['limit'];
    $limit_text = $limit > 0 ? " (limit: {$limit})" : '';
    $this->output()->writeln("Processing {$count} queue items{$limit_text}...");

    $worker = \Drupal::service('plugin.manager.queue_worker')
      ->createInstance('avc_email_reply');

    $processed = 0;
    $errors = 0;

    while ($item = $queue->claimItem()) {
      if ($limit > 0 && $processed >= $limit) {
        $queue->releaseItem($item);
        break;
      }

      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
        $this->output()->writeln("  [OK] Processed item from {$item->data['from']}");
      }
      catch (\Exception $e) {
        $errors++;
        $queue->releaseItem($item);
        $this->output()->writeln("  [ERROR] {$e->getMessage()}");
      }
    }

    $this->output()->writeln('');
    $this->output()->writeln("<info>Processed: {$processed}, Errors: {$errors}</info>");

    return $errors === 0 ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
  }

  /**
   * Set up test infrastructure for email reply testing.
   *
   * @command email-reply:setup-test
   * @aliases er-setup
   * @option enable Enable the email reply system.
   * @option domain Reply domain to use.
   * @usage email-reply:setup-test --enable --domain=test.example.com
   *   Set up test infrastructure and enable email reply.
   */
  public function setupTest($options = ['enable' => FALSE, 'domain' => 'test.local']) {
    $this->output()->writeln('');
    $this->output()->writeln('<info>Setting up Email Reply Test Infrastructure</info>');
    $this->output()->writeln('═══════════════════════════════════════════════');
    $this->output()->writeln('');

    // Check if we have test users.
    $test_users = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('uid', 1, '>')
      ->range(0, 1)
      ->execute();

    if (empty($test_users)) {
      $this->output()->writeln('<comment>No test users found. Creating test users...</comment>');
      $this->createTestUsers();
    }
    else {
      $this->output()->writeln('<info>Test users already exist.</info>');
    }

    // Check if we have test nodes.
    $test_nodes = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->range(0, 1)
      ->execute();

    if (empty($test_nodes)) {
      $this->output()->writeln('<comment>No test nodes found. Creating test nodes...</comment>');
      $this->createTestNodes();
    }
    else {
      $this->output()->writeln('<info>Test nodes already exist.</info>');
    }

    // Configure email reply.
    if ($options['enable']) {
      $config = $this->configFactory->getEditable('avc_email_reply.settings');
      $config->set('enabled', TRUE);
      $config->set('reply_domain', $options['domain']);
      $config->set('debug_mode', TRUE);
      $config->save();

      $this->output()->writeln('');
      $this->output()->writeln("<info>Email reply enabled with domain: {$options['domain']}</info>");
    }

    $this->output()->writeln('');
    $this->output()->writeln('<info>Test infrastructure ready!</info>');
    $this->output()->writeln('');
    $this->output()->writeln('Next steps:');
    $this->output()->writeln('  1. Run: drush email-reply:status');
    $this->output()->writeln('  2. Generate a token: drush email-reply:generate-token <node_id> <user_id>');
    $this->output()->writeln('  3. Simulate a reply: drush email-reply:simulate <token> <email> "text"');
    $this->output()->writeln('');
    $this->output()->writeln('Or visit: /admin/config/avc/email-reply/test');
    $this->output()->writeln('');

    return self::EXIT_SUCCESS;
  }

  /**
   * Create test users.
   */
  protected function createTestUsers() {
    $user_storage = $this->entityTypeManager->getStorage('user');

    $test_users = [
      ['name' => 'testuser1', 'email' => 'testuser1@test.local'],
      ['name' => 'testuser2', 'email' => 'testuser2@test.local'],
      ['name' => 'testuser3', 'email' => 'testuser3@test.local'],
    ];

    foreach ($test_users as $user_data) {
      // Check if user exists.
      $existing = $user_storage->loadByProperties(['name' => $user_data['name']]);
      if (!empty($existing)) {
        $this->output()->writeln("  - User {$user_data['name']} already exists.");
        continue;
      }

      $user = $user_storage->create([
        'name' => $user_data['name'],
        'mail' => $user_data['email'],
        'pass' => 'test123',
        'status' => 1,
      ]);
      $user->save();

      $this->output()->writeln("  - Created user: {$user_data['name']} ({$user->id()})");
    }
  }

  /**
   * Create test nodes.
   */
  protected function createTestNodes() {
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Get available content types.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $content_type = 'page';

    // Prefer article or post types if available.
    foreach (['article', 'post', 'page'] as $preferred) {
      if (isset($node_types[$preferred])) {
        $content_type = $preferred;
        break;
      }
    }

    // Get a user to be the author.
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('uid', 1, '>')
      ->range(0, 1)
      ->execute();
    $author_id = !empty($users) ? reset($users) : 1;

    $test_nodes = [
      ['title' => 'Email Reply Test Node 1', 'body' => 'This is a test node for email reply testing.'],
      ['title' => 'Email Reply Test Node 2', 'body' => 'Another test node for email reply functionality.'],
    ];

    foreach ($test_nodes as $node_data) {
      $node = $node_storage->create([
        'type' => $content_type,
        'title' => $node_data['title'],
        'body' => [
          'value' => $node_data['body'],
          'format' => 'basic_html',
        ],
        'uid' => $author_id,
        'status' => 1,
      ]);
      $node->save();

      $this->output()->writeln("  - Created node: {$node_data['title']} ({$node->id()})");
    }
  }

  /**
   * Run a full end-to-end test of the email reply system.
   *
   * @command email-reply:test
   * @aliases er-test
   * @usage email-reply:test
   *   Run a full end-to-end test of the email reply system.
   */
  public function test() {
    $this->output()->writeln('');
    $this->output()->writeln('<info>Running Email Reply End-to-End Test</info>');
    $this->output()->writeln('═══════════════════════════════════════════');
    $this->output()->writeln('');

    // Step 1: Check configuration.
    $this->output()->writeln('Step 1: Checking configuration...');
    $config = $this->configFactory->get('avc_email_reply.settings');
    $enabled = $config->get('enabled');

    if (!$enabled) {
      $this->output()->writeln('<comment>  Email reply is disabled. Enabling for test...</comment>');
      $this->configFactory->getEditable('avc_email_reply.settings')
        ->set('enabled', TRUE)
        ->set('reply_domain', 'test.local')
        ->save();
    }
    $this->output()->writeln('  <info>[OK] Configuration valid</info>');

    // Step 2: Find or create a test user.
    $this->output()->writeln('');
    $this->output()->writeln('Step 2: Finding test user...');
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('uid', 1, '>')
      ->range(0, 1)
      ->execute();

    if (empty($users)) {
      $this->output()->writeln('  <comment>No users found. Creating test user...</comment>');
      $user = $this->entityTypeManager->getStorage('user')->create([
        'name' => 'emailtest_' . time(),
        'mail' => 'emailtest@test.local',
        'pass' => 'test123',
        'status' => 1,
      ]);
      $user->save();
    }
    else {
      $user = $this->entityTypeManager->getStorage('user')->load(reset($users));
    }
    $this->output()->writeln("  <info>[OK] Using user: {$user->getDisplayName()} ({$user->getEmail()})</info>");

    // Step 3: Find or create a test node.
    $this->output()->writeln('');
    $this->output()->writeln('Step 3: Finding test node...');
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->range(0, 1)
      ->execute();

    if (empty($nodes)) {
      $this->output()->writeln('  <comment>No nodes found. Creating test node...</comment>');
      $node = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'page',
        'title' => 'Email Reply Test ' . time(),
        'uid' => $user->id(),
        'status' => 1,
      ]);
      $node->save();
    }
    else {
      $node = $this->entityTypeManager->getStorage('node')->load(reset($nodes));
    }
    $this->output()->writeln("  <info>[OK] Using node: {$node->label()} (ID: {$node->id()})</info>");

    // Step 4: Generate token.
    $this->output()->writeln('');
    $this->output()->writeln('Step 4: Generating reply token...');
    try {
      $token = $this->replyTokenService->generateToken(
        'node',
        (int) $node->id(),
        (int) $user->id(),
        NULL
      );
      $this->output()->writeln('  <info>[OK] Token generated</info>');
    }
    catch (\Exception $e) {
      $this->output()->writeln("  <error>[FAIL] {$e->getMessage()}</error>");
      return self::EXIT_FAILURE;
    }

    // Step 5: Validate token.
    $this->output()->writeln('');
    $this->output()->writeln('Step 5: Validating token...');
    $token_data = $this->replyTokenService->validateToken($token);
    if (!$token_data) {
      $this->output()->writeln('  <error>[FAIL] Token validation failed</error>');
      return self::EXIT_FAILURE;
    }
    $this->output()->writeln('  <info>[OK] Token validated</info>');

    // Step 6: Simulate email reply.
    $this->output()->writeln('');
    $this->output()->writeln('Step 6: Simulating email reply...');
    $test_message = 'This is an automated test reply from drush email-reply:test at ' . date('Y-m-d H:i:s');

    $processor_data = [
      'token' => $token,
      'from' => $user->getEmail(),
      'text_content' => $test_message,
      'spam_score' => 0.0,
      'spf_result' => 'pass',
      'dkim_result' => 'pass',
    ];

    try {
      $result = $this->emailProcessor->process($processor_data);

      if ($result->isSuccess()) {
        $comment = $result->getComment();
        $this->output()->writeln('  <info>[OK] Email reply processed successfully</info>');
        if ($comment) {
          $this->output()->writeln("  Comment ID: {$comment->id()}");
        }
      }
      else {
        $this->output()->writeln("  <error>[FAIL] {$result->getMessage()}</error>");
        return self::EXIT_FAILURE;
      }
    }
    catch (\Exception $e) {
      $this->output()->writeln("  <error>[FAIL] Exception: {$e->getMessage()}</error>");
      return self::EXIT_FAILURE;
    }

    $this->output()->writeln('');
    $this->output()->writeln('═══════════════════════════════════════════');
    $this->output()->writeln('<info>ALL TESTS PASSED!</info>');
    $this->output()->writeln('');

    return self::EXIT_SUCCESS;
  }

}
