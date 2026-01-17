<?php

namespace Drupal\avc_email_reply\Controller;

use Drupal\avc_email_reply\Service\EmailReplyProcessor;
use Drupal\avc_email_reply\Service\ReplyTokenService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for testing email reply functionality.
 *
 * This controller provides endpoints for testing the email reply system
 * without requiring an actual email server.
 */
class EmailReplyTestController extends ControllerBase {

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
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs an EmailReplyTestController object.
   */
  public function __construct(
    ReplyTokenService $reply_token_service,
    EmailReplyProcessor $email_processor,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    QueueFactory $queue_factory
  ) {
    $this->replyTokenService = $reply_token_service;
    $this->emailProcessor = $email_processor;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('avc_email_reply');
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('avc_email_reply.reply_token'),
      $container->get('avc_email_reply.email_processor'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('queue')
    );
  }

  /**
   * Test page that shows status and provides test forms.
   */
  public function testPage(): array {
    $config = $this->config('avc_email_reply.settings');
    $enabled = $config->get('enabled');
    $reply_domain = $config->get('reply_domain');

    // Get test data options.
    $users = $this->getTestUsers();
    $groups = $this->getTestGroups();
    $nodes = $this->getTestNodes();

    $build = [
      '#theme' => 'email_reply_test_page',
      '#enabled' => $enabled,
      '#reply_domain' => $reply_domain,
      '#users' => $users,
      '#groups' => $groups,
      '#nodes' => $nodes,
      '#attached' => [
        'library' => ['avc_email_reply/test-page'],
      ],
    ];

    // If theme doesn't exist, use inline render array.
    if (!$this->themeExists('email_reply_test_page')) {
      $build = $this->buildTestPageMarkup($enabled, $reply_domain, $users, $groups, $nodes);
    }

    return $build;
  }

  /**
   * Builds inline markup for the test page.
   */
  protected function buildTestPageMarkup(bool $enabled, ?string $reply_domain, array $users, array $groups, array $nodes): array {
    $status_class = $enabled ? 'messages--status' : 'messages--warning';
    $status_text = $enabled ? 'Email reply is ENABLED' : 'Email reply is DISABLED';

    $user_options = '';
    foreach ($users as $id => $name) {
      $user_options .= "<option value=\"{$id}\">{$name}</option>";
    }

    $group_options = '<option value="">-- No Group --</option>';
    foreach ($groups as $id => $name) {
      $group_options .= "<option value=\"{$id}\">{$name}</option>";
    }

    $node_options = '';
    foreach ($nodes as $id => $title) {
      $node_options .= "<option value=\"{$id}\">{$title}</option>";
    }

    return [
      '#type' => 'markup',
      '#markup' => "
        <h1>Email Reply System Test</h1>

        <div class=\"messages {$status_class}\">{$status_text}</div>

        <p><strong>Reply Domain:</strong> " . ($reply_domain ?: '<em>Not configured</em>') . "</p>

        <h2>1. Generate Reply Token</h2>
        <form id=\"generate-token-form\" class=\"email-reply-test-form\">
          <div class=\"form-item\">
            <label for=\"entity-type\">Entity Type:</label>
            <select id=\"entity-type\" name=\"entity_type\">
              <option value=\"node\">Node</option>
              <option value=\"comment\">Comment</option>
            </select>
          </div>
          <div class=\"form-item\">
            <label for=\"entity-id\">Entity (Node):</label>
            <select id=\"entity-id\" name=\"entity_id\">{$node_options}</select>
          </div>
          <div class=\"form-item\">
            <label for=\"user-id\">User:</label>
            <select id=\"user-id\" name=\"user_id\">{$user_options}</select>
          </div>
          <div class=\"form-item\">
            <label for=\"group-id\">Group:</label>
            <select id=\"group-id\" name=\"group_id\">{$group_options}</select>
          </div>
          <button type=\"submit\" class=\"button button--primary\">Generate Token</button>
        </form>
        <div id=\"token-result\" class=\"result-box\"></div>

        <h2>2. Simulate Email Reply</h2>
        <form id=\"simulate-reply-form\" class=\"email-reply-test-form\">
          <div class=\"form-item\">
            <label for=\"reply-token\">Reply Token:</label>
            <input type=\"text\" id=\"reply-token\" name=\"token\" placeholder=\"Paste token from step 1\" style=\"width: 100%;\">
          </div>
          <div class=\"form-item\">
            <label for=\"from-email\">From Email:</label>
            <input type=\"email\" id=\"from-email\" name=\"from\" placeholder=\"user@example.com\">
          </div>
          <div class=\"form-item\">
            <label for=\"reply-text\">Reply Text:</label>
            <textarea id=\"reply-text\" name=\"text\" rows=\"4\" placeholder=\"This is my reply comment...\"></textarea>
          </div>
          <button type=\"submit\" class=\"button button--primary\">Simulate Reply</button>
        </form>
        <div id=\"reply-result\" class=\"result-box\"></div>

        <h2>3. Process Queue</h2>
        <p>Process any pending email replies in the queue.</p>
        <button id=\"process-queue\" class=\"button\">Process Queue</button>
        <div id=\"queue-result\" class=\"result-box\"></div>

        <style>
          .email-reply-test-form { margin: 1em 0; padding: 1em; background: #f5f5f5; border-radius: 5px; }
          .email-reply-test-form .form-item { margin-bottom: 1em; }
          .email-reply-test-form label { display: block; font-weight: bold; margin-bottom: 0.3em; }
          .email-reply-test-form input, .email-reply-test-form select, .email-reply-test-form textarea { width: 100%; max-width: 400px; padding: 0.5em; }
          .result-box { margin: 1em 0; padding: 1em; background: #e8f4e8; border: 1px solid #090; border-radius: 5px; display: none; }
          .result-box.error { background: #f4e8e8; border-color: #900; }
          .result-box pre { white-space: pre-wrap; word-wrap: break-word; }
        </style>

        <script>
          document.getElementById('generate-token-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const result = document.getElementById('token-result');
            try {
              const response = await fetch('/admin/config/avc/email-reply/test/generate-token?' + new URLSearchParams({
                entity_type: form.entity_type.value,
                entity_id: form.entity_id.value,
                user_id: form.user_id.value,
                group_id: form.group_id.value || ''
              }));
              const data = await response.json();
              result.style.display = 'block';
              result.className = data.success ? 'result-box' : 'result-box error';
              result.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
              if (data.token) {
                document.getElementById('reply-token').value = data.token;
              }
            } catch (err) {
              result.style.display = 'block';
              result.className = 'result-box error';
              result.innerHTML = '<pre>Error: ' + err.message + '</pre>';
            }
          });

          document.getElementById('simulate-reply-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const result = document.getElementById('reply-result');
            try {
              const response = await fetch('/admin/config/avc/email-reply/test/simulate-reply', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  token: form.token.value,
                  from: form.from.value,
                  text: form.text.value
                })
              });
              const data = await response.json();
              result.style.display = 'block';
              result.className = data.success ? 'result-box' : 'result-box error';
              result.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            } catch (err) {
              result.style.display = 'block';
              result.className = 'result-box error';
              result.innerHTML = '<pre>Error: ' + err.message + '</pre>';
            }
          });

          document.getElementById('process-queue').addEventListener('click', async () => {
            const result = document.getElementById('queue-result');
            try {
              const response = await fetch('/admin/config/avc/email-reply/test/process-queue', { method: 'POST' });
              const data = await response.json();
              result.style.display = 'block';
              result.className = data.success ? 'result-box' : 'result-box error';
              result.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            } catch (err) {
              result.style.display = 'block';
              result.className = 'result-box error';
              result.innerHTML = '<pre>Error: ' + err.message + '</pre>';
            }
          });
        </script>
      ",
    ];
  }

  /**
   * Check if a theme hook exists.
   */
  protected function themeExists(string $hook): bool {
    return \Drupal::service('theme.registry')->has($hook);
  }

  /**
   * Generate a reply token for testing.
   */
  public function generateToken(Request $request): JsonResponse {
    $entity_type = $request->query->get('entity_type', 'node');
    $entity_id = (int) $request->query->get('entity_id');
    $user_id = (int) $request->query->get('user_id');
    $group_id = $request->query->get('group_id');
    $group_id = $group_id ? (int) $group_id : NULL;

    if (!$entity_id || !$user_id) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Entity ID and User ID are required',
      ], 400);
    }

    // Verify entities exist.
    $user = $this->entityTypeManager->getStorage('user')->load($user_id);
    if (!$user) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => "User {$user_id} not found",
      ], 400);
    }

    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => "Entity {$entity_type}:{$entity_id} not found",
      ], 400);
    }

    if ($group_id) {
      $group = $this->entityTypeManager->getStorage('group')->load($group_id);
      if (!$group) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => "Group {$group_id} not found",
        ], 400);
      }
    }

    try {
      $token = $this->replyTokenService->generateToken(
        $entity_type,
        $entity_id,
        $user_id,
        $group_id
      );

      $config = $this->config('avc_email_reply.settings');
      $reply_domain = $config->get('reply_domain') ?: 'example.com';

      return new JsonResponse([
        'success' => TRUE,
        'token' => $token,
        'reply_to_address' => "reply+{$token}@{$reply_domain}",
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'entity_title' => $entity->label(),
        'user_id' => $user_id,
        'user_email' => $user->getEmail(),
        'group_id' => $group_id,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Simulate an email reply for testing.
   */
  public function simulateReply(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);

    if (!$data) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid JSON data',
      ], 400);
    }

    $token = $data['token'] ?? '';
    $from = $data['from'] ?? '';
    $text = $data['text'] ?? '';

    if (!$token || !$from || !$text) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Token, from email, and text are required',
      ], 400);
    }

    // Validate the token first.
    $token_data = $this->replyTokenService->validateToken($token);
    if (!$token_data) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid or expired token',
      ], 400);
    }

    // Process directly (not via queue).
    $processor_data = [
      'token' => $token,
      'from' => $from,
      'text_content' => $text,
      'spam_score' => 0.0,
      'spf_result' => 'pass',
      'dkim_result' => 'pass',
    ];

    try {
      $result = $this->emailProcessor->process($processor_data);

      if ($result->isSuccess()) {
        $comment = $result->getComment();
        return new JsonResponse([
          'success' => TRUE,
          'message' => $result->getMessage(),
          'comment_id' => $comment ? $comment->id() : NULL,
          'comment_url' => $comment ? $comment->toUrl('canonical', ['absolute' => TRUE])->toString() : NULL,
        ]);
      }
      else {
        return new JsonResponse([
          'success' => FALSE,
          'error' => $result->getMessage(),
        ], 400);
      }
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Process the email reply queue.
   */
  public function processQueue(): JsonResponse {
    $queue = $this->queueFactory->get('avc_email_reply');
    $count = $queue->numberOfItems();

    if ($count === 0) {
      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Queue is empty',
        'processed' => 0,
      ]);
    }

    $processed = 0;
    $errors = [];

    $worker = \Drupal::service('plugin.manager.queue_worker')
      ->createInstance('avc_email_reply');

    while ($item = $queue->claimItem()) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (\Exception $e) {
        $errors[] = $e->getMessage();
        $queue->releaseItem($item);
      }
    }

    return new JsonResponse([
      'success' => count($errors) === 0,
      'message' => "Processed {$processed} of {$count} items",
      'processed' => $processed,
      'errors' => $errors,
    ]);
  }

  /**
   * Get list of test users.
   */
  protected function getTestUsers(): array {
    $users = [];
    $query = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->range(0, 50)
      ->sort('uid', 'ASC');
    $uids = $query->execute();

    foreach ($this->entityTypeManager->getStorage('user')->loadMultiple($uids) as $user) {
      if ($user->id() > 0) {
        $users[$user->id()] = $user->getDisplayName() . ' (' . $user->getEmail() . ')';
      }
    }

    return $users;
  }

  /**
   * Get list of test groups.
   */
  protected function getTestGroups(): array {
    $groups = [];

    try {
      $query = $this->entityTypeManager
        ->getStorage('group')
        ->getQuery()
        ->accessCheck(FALSE)
        ->range(0, 50)
        ->sort('id', 'ASC');
      $gids = $query->execute();

      foreach ($this->entityTypeManager->getStorage('group')->loadMultiple($gids) as $group) {
        $groups[$group->id()] = $group->label();
      }
    }
    catch (\Exception $e) {
      // Group module may not be installed or no groups exist.
    }

    return $groups;
  }

  /**
   * Get list of test nodes.
   */
  protected function getTestNodes(): array {
    $nodes = [];
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->range(0, 50)
      ->sort('nid', 'DESC');
    $nids = $query->execute();

    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      $nodes[$node->id()] = $node->label() . ' (' . $node->bundle() . ')';
    }

    return $nodes;
  }

}
