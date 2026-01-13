# Proposal: Group/Guild Email Reply-to-Comment System

**Status**: DRAFT
**Author**: Claude Code Research
**Date**: 2026-01-13

## Executive Summary

This proposal outlines a system allowing AVC group/guild members to reply to notification emails, with those replies automatically posted as comments on the original content and distributed to all group members. The system prioritizes security, leverages existing AVC infrastructure, and follows Drupal best practices.

---

## Current State Analysis

### Existing AVC Infrastructure

**Groups/Guilds System** (`avc_guild`, `avc_group` modules):
- Group module foundation with flexible groups and specialized guilds
- Role hierarchy: admin, mentor, endorsed, junior, member, outsider
- Membership managed via Open Social group integration

**Notification System** (`avc_notification` module):
- NotificationQueue entity with event types: workflow_advance, assignment, ratification_needed, ratification_complete, endorsement, guild_promotion
- NotificationService for queuing notifications
- NotificationSender for email dispatch via Drupal's mail system
- User preferences: immediate (n), daily digest (d), weekly digest (w), none (x)
- NotificationProcessor and NotificationAggregator for batch processing

**Comment Systems Currently In Use**:
- WorkflowTask comments (text_long field for workflow notes)
- Skill endorsement comments
- Ratification feedback
- Open Social comment system (Drupal core comments via social_comment)

---

## Proposed Architecture

### Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         EMAIL REPLY FLOW                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  1. User Action (new post, comment, etc.)                               │
│         │                                                               │
│         ▼                                                               │
│  2. NotificationService queues notification                             │
│         │                                                               │
│         ▼                                                               │
│  3. Enhanced NotificationSender adds:                                   │
│     - Reply-To: group+{reply_token}@avc.example.com                     │
│     - Message-ID header with token                                      │
│     - References header chain                                           │
│         │                                                               │
│         ▼                                                               │
│  4. Email delivered to member                                           │
│         │                                                               │
│         ▼                                                               │
│  5. Member replies to email                                             │
│         │                                                               │
│         ▼                                                               │
│  6. Email provider webhook (SendGrid/Mailgun)                           │
│         │                                                               │
│         ▼                                                               │
│  7. InboundEmailController receives POST                                │
│     - Validates HMAC signature                                          │
│     - Extracts reply token                                              │
│     - Verifies token authenticity                                       │
│         │                                                               │
│         ▼                                                               │
│  8. EmailReplyProcessor                                                 │
│     - Validates sender membership                                       │
│     - Creates comment on original content                               │
│     - Triggers group notification                                       │
│         │                                                               │
│         ▼                                                               │
│  9. NotificationService queues to all group members                     │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Detailed Component Design

### 1. Reply Token System

**Purpose**: Securely link email replies to original content without exposing database IDs.

**Token Structure**:
```
{random_id}.{content_type}.{entity_id}.{user_id}.{expiry}.{signature}
```

**Example**:
```
a7b3c9d2.node.142.25.1737500000.f8e2a1b3c4d5
```

**Implementation**:

```php
// modules/avc_features/avc_email_reply/src/Service/ReplyTokenService.php

class ReplyTokenService {

  private const TOKEN_EXPIRY_DAYS = 30;

  /**
   * Generate a secure reply token.
   */
  public function generateToken(
    string $content_type,
    int $entity_id,
    int $user_id,
    ?int $group_id = NULL
  ): string {
    $random = bin2hex(random_bytes(8));
    $expiry = time() + (self::TOKEN_EXPIRY_DAYS * 86400);

    $payload = implode('.', [
      $random,
      $content_type,
      $entity_id,
      $user_id,
      $group_id ?? 0,
      $expiry,
    ]);

    $signature = $this->sign($payload);

    return base64_encode($payload . '.' . $signature);
  }

  /**
   * Validate and decode a reply token.
   */
  public function validateToken(string $token): ?array {
    $decoded = base64_decode($token);
    if (!$decoded) {
      return NULL;
    }

    $parts = explode('.', $decoded);
    if (count($parts) !== 7) {
      return NULL;
    }

    [$random, $content_type, $entity_id, $user_id, $group_id, $expiry, $signature] = $parts;

    // Verify signature
    $payload = implode('.', [$random, $content_type, $entity_id, $user_id, $group_id, $expiry]);
    if (!$this->verifySignature($payload, $signature)) {
      return NULL;
    }

    // Check expiry
    if (time() > (int) $expiry) {
      return NULL;
    }

    return [
      'content_type' => $content_type,
      'entity_id' => (int) $entity_id,
      'user_id' => (int) $user_id,
      'group_id' => (int) $group_id ?: NULL,
      'expiry' => (int) $expiry,
    ];
  }

  private function sign(string $payload): string {
    $key = Settings::get('hash_salt');
    return substr(hash_hmac('sha256', $payload, $key), 0, 16);
  }

  private function verifySignature(string $payload, string $signature): bool {
    return hash_equals($this->sign($payload), $signature);
  }
}
```

### 2. Enhanced Notification Sender

**Modified email headers for reply support**:

```php
// Modified sendMail() in NotificationSender.php

protected function sendMail(
  AccountInterface $user,
  string $key,
  array $params,
  NotificationQueue $notification
) {
  // Generate reply token if content supports replies
  $reply_to = NULL;
  $message_id = NULL;

  if ($this->supportsReply($notification)) {
    $token = $this->replyTokenService->generateToken(
      $notification->getContentType(),
      $notification->getContentId(),
      $user->id(),
      $notification->getTargetGroup()?->id()
    );

    $domain = $this->config->get('avc_email_reply.settings', 'reply_domain');
    $reply_to = "reply+{$token}@{$domain}";

    // RFC 5322 compliant Message-ID
    $message_id = "<{$token}@{$domain}>";
  }

  $params['headers'] = [
    'Reply-To' => $reply_to,
    'Message-ID' => $message_id,
  ];

  // Add reply instructions to email body
  if ($reply_to) {
    $params['reply_instructions'] = t(
      'Reply to this email to post a comment. Your reply will be shared with all group members.'
    );
  }

  // ... existing mail send logic
}
```

### 3. Inbound Email Processing

**Option A: SendGrid Inbound Parse (Recommended)**

```php
// modules/avc_features/avc_email_reply/src/Controller/InboundEmailController.php

class InboundEmailController extends ControllerBase {

  /**
   * Handle SendGrid inbound parse webhook.
   *
   * @Route("/api/email/inbound", methods={"POST"})
   */
  public function receive(Request $request): Response {
    // 1. Validate webhook signature
    if (!$this->validateWebhookSignature($request)) {
      $this->logger->warning('Invalid webhook signature');
      return new Response('Unauthorized', 401);
    }

    // 2. Parse email data
    $email_data = [
      'from' => $request->request->get('from'),
      'to' => $request->request->get('to'),
      'subject' => $request->request->get('subject'),
      'text' => $request->request->get('text'),
      'html' => $request->request->get('html'),
      'headers' => $request->request->get('headers'),
      'envelope' => json_decode($request->request->get('envelope'), TRUE),
      'spam_score' => $request->request->get('spam_score'),
      'spam_report' => $request->request->get('spam_report'),
      'dkim' => $request->request->get('dkim'),
      'spf' => $request->request->get('SPF'),
    ];

    // 3. Extract reply token from address
    $token = $this->extractToken($email_data['to']);
    if (!$token) {
      $this->logger->notice('No reply token found in email to: @to', [
        '@to' => $email_data['to'],
      ]);
      return new Response('OK', 200);
    }

    // 4. Queue for processing (async to avoid webhook timeout)
    $this->emailQueue->addItem([
      'token' => $token,
      'email_data' => $email_data,
      'received_at' => time(),
    ]);

    return new Response('OK', 200);
  }

  private function validateWebhookSignature(Request $request): bool {
    $signature = $request->headers->get('X-Twilio-Email-Event-Webhook-Signature');
    $timestamp = $request->headers->get('X-Twilio-Email-Event-Webhook-Timestamp');

    if (!$signature || !$timestamp) {
      return FALSE;
    }

    $verification_key = Settings::get('sendgrid_webhook_verification_key');
    $payload = $timestamp . $request->getContent();

    return hash_equals(
      base64_encode(hash_hmac('sha256', $payload, $verification_key, TRUE)),
      $signature
    );
  }

  private function extractToken(string $to_address): ?string {
    if (preg_match('/reply\+([a-zA-Z0-9+\/=]+)@/', $to_address, $matches)) {
      return $matches[1];
    }
    return NULL;
  }
}
```

**Option B: Mailgun Routes (Alternative)**

```php
// Similar structure, different signature validation
private function validateMailgunSignature(Request $request): bool {
  $timestamp = $request->request->get('timestamp');
  $token = $request->request->get('token');
  $signature = $request->request->get('signature');

  $api_key = Settings::get('mailgun_api_key');
  $expected = hash_hmac('sha256', $timestamp . $token, $api_key);

  return hash_equals($expected, $signature);
}
```

### 4. Email Reply Processor

```php
// modules/avc_features/avc_email_reply/src/Service/EmailReplyProcessor.php

class EmailReplyProcessor {

  /**
   * Process a queued email reply.
   */
  public function process(array $item): ProcessResult {
    $token_data = $this->replyTokenService->validateToken($item['token']);

    if (!$token_data) {
      return ProcessResult::error('Invalid or expired token');
    }

    $email_data = $item['email_data'];

    // Security checks
    $security_result = $this->performSecurityChecks($email_data, $token_data);
    if (!$security_result->passed) {
      return ProcessResult::error($security_result->reason);
    }

    // Load the sender
    $sender = $this->loadSenderFromEmail($email_data['from'], $token_data['user_id']);
    if (!$sender) {
      return ProcessResult::error('Sender not found or email mismatch');
    }

    // Load the target content
    $entity = $this->loadEntity($token_data['content_type'], $token_data['entity_id']);
    if (!$entity) {
      return ProcessResult::error('Target content not found');
    }

    // Verify group membership
    if ($token_data['group_id']) {
      $group = Group::load($token_data['group_id']);
      if (!$group || !$group->getMember($sender)) {
        return ProcessResult::error('User not a member of this group');
      }
    }

    // Extract reply content (strip quoted text)
    $reply_text = $this->extractReplyContent($email_data['text']);

    if (empty(trim($reply_text))) {
      return ProcessResult::error('Empty reply content');
    }

    // Sanitize content
    $sanitized_content = $this->sanitizeContent($reply_text);

    // Create comment
    $comment = $this->createComment($entity, $sender, $sanitized_content, $token_data['group_id']);

    // Notify group members
    $this->notifyGroupMembers($comment, $entity, $sender, $token_data['group_id']);

    return ProcessResult::success($comment->id());
  }

  private function performSecurityChecks(array $email_data, array $token_data): SecurityCheckResult {
    // Check spam score
    if (isset($email_data['spam_score']) && $email_data['spam_score'] > 5.0) {
      return SecurityCheckResult::fail('High spam score: ' . $email_data['spam_score']);
    }

    // Check SPF
    if (isset($email_data['spf']) && !in_array($email_data['spf'], ['pass', 'softfail'])) {
      $this->logger->warning('SPF check failed for email from @from', [
        '@from' => $email_data['from'],
      ]);
      // Don't reject, but log for monitoring
    }

    // Check DKIM
    if (isset($email_data['dkim']) && strpos($email_data['dkim'], 'pass') === FALSE) {
      $this->logger->warning('DKIM check failed for email from @from', [
        '@from' => $email_data['from'],
      ]);
      // Don't reject, but log for monitoring
    }

    // Rate limiting
    if ($this->rateLimiter->isLimited($token_data['user_id'])) {
      return SecurityCheckResult::fail('Rate limit exceeded');
    }

    return SecurityCheckResult::pass();
  }

  private function loadSenderFromEmail(string $from_address, int $expected_user_id): ?AccountInterface {
    // Extract email from "Name <email@example.com>" format
    if (preg_match('/<([^>]+)>/', $from_address, $matches)) {
      $email = $matches[1];
    } else {
      $email = $from_address;
    }

    // Load user by ID
    $user = User::load($expected_user_id);
    if (!$user) {
      return NULL;
    }

    // Verify email matches (case-insensitive)
    if (strtolower($user->getEmail()) !== strtolower($email)) {
      $this->logger->warning('Email mismatch: expected @expected, got @actual', [
        '@expected' => $user->getEmail(),
        '@actual' => $email,
      ]);
      return NULL;
    }

    return $user;
  }

  private function extractReplyContent(string $text): string {
    // Common reply markers to strip quoted content
    $markers = [
      '/^>.*$/m',                           // Lines starting with >
      '/^On .* wrote:$/m',                  // "On date, person wrote:"
      '/^-{2,} Original Message -{2,}/m',   // Outlook style
      '/^_{2,}$/m',                         // Underscore dividers
      '/Reply ABOVE this LINE/i',           // Common marker
      '/From:.*\nSent:.*\nTo:.*\nSubject:/s', // Full header block
    ];

    foreach ($markers as $pattern) {
      $parts = preg_split($pattern, $text, 2);
      if (count($parts) > 1) {
        $text = $parts[0];
      }
    }

    // Remove signature (common patterns)
    $signature_patterns = [
      '/^--\s*$/m',            // Standard signature delimiter
      '/^Sent from my /m',     // Mobile signatures
      '/^Get Outlook for /m',  // Outlook mobile
    ];

    foreach ($signature_patterns as $pattern) {
      $parts = preg_split($pattern, $text, 2);
      if (count($parts) > 1) {
        $text = $parts[0];
      }
    }

    return trim($text);
  }

  private function sanitizeContent(string $content): string {
    // Use Drupal's XSS filter
    $content = Xss::filter($content);

    // Convert newlines to <br> for display
    $content = nl2br($content);

    // Limit length
    if (mb_strlen($content) > 10000) {
      $content = mb_substr($content, 0, 10000) . '...';
    }

    return $content;
  }

  private function createComment(
    EntityInterface $entity,
    AccountInterface $author,
    string $content,
    ?int $group_id
  ): CommentInterface {
    $comment = Comment::create([
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'field_name' => 'comments', // Or appropriate comment field
      'uid' => $author->id(),
      'comment_body' => [
        'value' => $content,
        'format' => 'basic_html',
      ],
      'status' => CommentInterface::PUBLISHED,
    ]);

    // Add metadata about email origin
    if ($comment->hasField('field_submitted_via')) {
      $comment->set('field_submitted_via', 'email');
    }

    $comment->save();

    return $comment;
  }

  private function notifyGroupMembers(
    CommentInterface $comment,
    EntityInterface $entity,
    AccountInterface $author,
    ?int $group_id
  ): void {
    if (!$group_id) {
      return;
    }

    $group = Group::load($group_id);
    if (!$group) {
      return;
    }

    // Get all members except the author
    $members = $group->getMembers();

    foreach ($members as $membership) {
      $member = $membership->getUser();
      if (!$member || $member->id() === $author->id()) {
        continue;
      }

      $this->notificationService->queueGroupComment(
        $member,
        $entity,
        $author,
        $comment,
        $group
      );
    }
  }
}
```

### 5. New Notification Type for Group Comments

```php
// Addition to NotificationService.php

/**
 * Queue a group comment notification.
 */
public function queueGroupComment(
  AccountInterface $target_user,
  EntityInterface $content,
  AccountInterface $commenter,
  CommentInterface $comment,
  GroupInterface $group
): ?NotificationQueue {
  $preference = $this->preferences->getUserPreference($target_user, $group);
  if ($preference === 'x') {
    return NULL;
  }

  $data = [
    'commenter_id' => $commenter->id(),
    'commenter_name' => $commenter->getDisplayName(),
    'comment_id' => $comment->id(),
    'comment_excerpt' => mb_substr(strip_tags($comment->get('comment_body')->value), 0, 200),
  ];

  return $this->createNotification(
    NotificationQueue::EVENT_GROUP_COMMENT,
    $target_user,
    $content instanceof NodeInterface ? $content : NULL,
    t('@commenter commented on "@content" in @group.', [
      '@commenter' => $commenter->getDisplayName(),
      '@content' => $content->label(),
      '@group' => $group->label(),
    ]),
    $data,
    $group
  );
}
```

---

## Security Considerations

### 1. Token Security

| Threat | Mitigation |
|--------|------------|
| Token guessing | 128-bit random component + HMAC signature |
| Token reuse | User-specific tokens, single content association |
| Expired tokens | 30-day expiry, configurable |
| Token leakage | Tokens only valid for original recipient |

### 2. Email Authentication

| Check | Action |
|-------|--------|
| SPF fail | Log warning, allow processing (forwarded emails) |
| SPF softfail | Allow with logging |
| DKIM fail | Log warning, allow processing |
| High spam score (>5.0) | Reject |
| Webhook signature | Required, reject if invalid |

### 3. Sender Verification

```
1. Token contains expected user ID
2. Email "From" address must match user's registered email
3. User must still be a member of the group
4. User must have permission to comment on content type
```

### 4. Content Security

| Threat | Mitigation |
|--------|------------|
| XSS attacks | Drupal Xss::filter() on all content |
| SQL injection | Entity API, parameterized queries |
| Large payloads | 10KB content limit |
| Spam/abuse | Rate limiting (10 replies/hour default) |
| Script injection | basic_html format with limited tags |

### 5. Rate Limiting

```php
// Default limits
'email_reply_rate_limit' => [
  'per_user_per_hour' => 10,
  'per_user_per_day' => 50,
  'per_group_per_hour' => 100,
  'global_per_minute' => 60,
]
```

---

## Module Structure

```
modules/avc_features/avc_email_reply/
├── avc_email_reply.info.yml
├── avc_email_reply.module
├── avc_email_reply.services.yml
├── avc_email_reply.routing.yml
├── avc_email_reply.permissions.yml
├── config/
│   ├── install/
│   │   └── avc_email_reply.settings.yml
│   └── schema/
│       └── avc_email_reply.schema.yml
├── src/
│   ├── Controller/
│   │   └── InboundEmailController.php
│   ├── Service/
│   │   ├── ReplyTokenService.php
│   │   ├── EmailReplyProcessor.php
│   │   ├── ReplyContentExtractor.php
│   │   └── EmailRateLimiter.php
│   ├── Plugin/
│   │   └── QueueWorker/
│   │       └── EmailReplyWorker.php
│   ├── Form/
│   │   └── EmailReplySettingsForm.php
│   └── Event/
│       ├── EmailReplyEvent.php
│       └── EmailReplySubscriber.php
└── tests/
    ├── src/
    │   ├── Unit/
    │   │   ├── ReplyTokenServiceTest.php
    │   │   └── ReplyContentExtractorTest.php
    │   └── Kernel/
    │       └── EmailReplyProcessorTest.php
    └── fixtures/
        └── sample_emails/
```

---

## Configuration

### Settings Form

```
/admin/config/avc/email-reply

- Reply domain: reply.avc.example.com
- Email service provider: [SendGrid / Mailgun / Custom]
- Webhook secret key: ********
- Token expiry (days): 30
- Enable rate limiting: [x]
- Rate limit (per user/hour): 10
- Spam score threshold: 5.0
- Allowed content types: [x] avc_document, [x] avc_project, [ ] avc_resource
- Enable for group types: [x] flexible_group, [x] guild
```

### Email Service Setup

**SendGrid**:
1. Configure MX record: `reply.avc.example.com` → `mx.sendgrid.net`
2. Add Inbound Parse settings: `reply.avc.example.com` → `https://avc.example.com/api/email/inbound`
3. Enable spam checking
4. Store webhook verification key in settings

**Mailgun**:
1. Add domain: `reply.avc.example.com`
2. Configure route: `match_recipient("reply+.*@reply.avc.example.com") → forward("https://avc.example.com/api/email/inbound")`
3. Store API key in settings

---

## Email Template Updates

### Notification Email Format

```html
Subject: [AVC - {Group Name}] {Commenter} commented on "{Content Title}"

---

{Commenter Name} posted in {Group Name}:

{Comment Content}

---

View the full discussion: {Link to content}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Reply to this email to add your comment.
Your reply will be shared with all group members.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Notification preferences: {Link to preferences}
```

---

## Integration with Existing Systems

### Open Social Compatibility

The system integrates with Open Social's existing features:
- Uses Open Social's `social_comment` for comment entities
- Respects group permissions from the Group module
- Extends rather than replaces existing notification preferences

### avc_notification Integration

- New event type: `EVENT_GROUP_COMMENT`
- Uses existing `NotificationQueue`, `NotificationSender`, and preference system
- Compatible with digest aggregation

---

## Drupal Module Options Analysis

### Evaluated Modules

| Module | Status | Drupal 10+ | Notes |
|--------|--------|------------|-------|
| [Mail Comment](https://www.drupal.org/project/mailcomment) | Maintenance only | No | D7 only, requires Mailhandler |
| [Message Group Notify](https://www.drupal.org/project/message_group_notify) | Active | Yes | Good for outbound, no inbound |
| [Inmail](https://www.drupal.org/project/inmail) | Seeking maintainer | D8 only | Security not covered |
| [Comment Notify](https://www.drupal.org/project/comment_notify) | Active | Yes | Outbound only |
| [Message Stack](https://www.drupal.org/project/message) | Active | Yes | Foundation, needs custom code |

### Recommendation

**Build custom module** based on:
- Message Stack for notification infrastructure (already partially implemented in `avc_notification`)
- External email service (SendGrid/Mailgun) for reliable inbound processing
- Custom reply token system for security

This approach provides:
- Full control over security implementation
- No dependency on unmaintained modules
- Integration with existing AVC infrastructure
- Modern webhook-based email processing

---

## Implementation Phases

### Phase 1: Foundation
- [ ] Create `avc_email_reply` module structure
- [ ] Implement `ReplyTokenService` with unit tests
- [ ] Add token generation to `NotificationSender`
- [ ] Set up email service (SendGrid recommended)

### Phase 2: Inbound Processing
- [ ] Create `InboundEmailController` with webhook validation
- [ ] Implement queue worker for async processing
- [ ] Create `EmailReplyProcessor` with security checks
- [ ] Add reply content extraction logic

### Phase 3: Comment Creation
- [ ] Implement comment creation with proper sanitization
- [ ] Add `queueGroupComment()` to `NotificationService`
- [ ] Create email template for group comments
- [ ] Test reply → comment → notify flow

### Phase 4: Security & Polish
- [ ] Implement rate limiting
- [ ] Add comprehensive logging
- [ ] Create admin settings form
- [ ] Add user preference for email replies

### Phase 5: Testing & Documentation
- [ ] Unit tests for all services
- [ ] Kernel tests for integration
- [ ] Functional tests for full flow
- [ ] User documentation
- [ ] Admin documentation

---

## Alternatives Considered

### 1. IMAP Polling

**Approach**: Drupal cron job polls IMAP mailbox for replies.

**Pros**:
- No external service dependency
- Simpler email setup

**Cons**:
- Latency (depends on cron frequency)
- IMAP connection issues
- Mailbox management complexity
- Poor scalability

**Decision**: Rejected - webhook-based approach is more reliable and real-time.

### 2. Mail Comment Module Fork

**Approach**: Fork and modernize the abandoned Mail Comment module.

**Pros**:
- Existing proven logic
- Community-tested patterns

**Cons**:
- Requires maintaining forked code
- Legacy architecture (Feeds/Mailhandler)
- Security not covered
- D7 patterns don't translate cleanly

**Decision**: Rejected - building fresh with modern patterns is cleaner.

### 3. ECA + Message Stack

**Approach**: Use ECA (Event-Condition-Action) module for workflow.

**Pros**:
- Visual workflow builder
- Flexible event handling

**Cons**:
- Adds module dependency
- Complex configuration
- Overkill for this use case
- No inbound email support

**Decision**: Rejected - custom code is simpler for this specific use case.

---

## Success Criteria

1. **Security**: Zero successful spoofed comments after 30 days of production
2. **Reliability**: 99%+ of legitimate replies successfully create comments
3. **Performance**: Inbound processing < 5 seconds end-to-end
4. **Usability**: 80%+ of users who receive reply-enabled emails successfully reply at least once
5. **Maintainability**: Module passes Drupal coding standards, 80%+ test coverage

---

## Sources

- [Message Group Notify](https://www.drupal.org/project/message_group_notify)
- [Drupal 10 Notification System with Message and ECA](https://www.hashbangcode.com/article/drupal-10-creating-notification-system-using-message-and-eca-modules)
- [Mail Comment Module](https://www.drupal.org/project/mailcomment)
- [Comment Notify](https://www.drupal.org/project/comment_notify)
- [SendGrid Inbound Parse](https://www.twilio.com/docs/sendgrid/for-developers/parsing-email/setting-up-the-inbound-parse-webhook)
- [Mailgun Inbound Routing](https://www.mailgun.com/features/inbound-email-routing/)
- [GitLab Reply by Email](https://docs.gitlab.com/ee/administration/reply_by_email.html)
- [DKIM, SPF, DMARC Explained](https://www.cloudflare.com/learning/email-security/dmarc-dkim-spf/)
- [Email Authentication Best Practices](https://www.emailonacid.com/blog/article/email-deliverability/email-authentication-protocols/)
