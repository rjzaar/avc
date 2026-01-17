# AVC Email Reply Module

Allows group members to reply to notification emails to create comments on content.

## Overview

When users receive notification emails about group content, they can reply directly to the email to post a comment. The reply is processed through a secure webhook and creates a comment on the original content.

## Architecture

1. **Outbound Flow**: Notifications are sent with `Reply-To: reply+{token}@domain` headers
2. **Inbound Flow**: Email provider (SendGrid/Mailgun) webhooks to `/api/email/inbound`
3. **Processing**: Queued and processed asynchronously via cron
4. **Result**: Comments are created on the target entity

## Configuration

### Via Admin UI
Navigate to `/admin/config/avc/email-reply` to configure:
- Enable/disable email reply
- Set reply domain
- Configure rate limits
- Set spam score threshold

### Via Drush
```bash
# Check status
drush email-reply:status

# Enable with domain
drush email-reply:enable --domain=reply.example.com

# Configure settings
drush email-reply:configure --domain=reply.example.com --provider=sendgrid
```

### Via Recipe (cnwp.yml)
Add to your recipe in `example.cnwp.yml`:
```yaml
email_reply:
  enabled: true
  reply_domain: "reply.example.com"
  email_provider: sendgrid
  webhook_secret: "your-webhook-secret"
```

## Testing

### DDEV Testing (Dev/Stage)

The module includes built-in testing tools for DDEV environments.

#### Quick Start
```bash
# Set up test infrastructure
ddev email-reply-test setup

# Run automated end-to-end test
ddev email-reply-test test
```

#### Manual Testing
```bash
# Generate a token and simulate a reply
ddev email-reply-test simulate <node_id> <user_id> "My reply text"

# Check system status
ddev email-reply-test status
```

#### Web UI Testing
Visit `/admin/config/avc/email-reply/test` to:
1. Generate tokens for any user/entity combination
2. Simulate email replies without actual emails
3. Process the email queue manually

#### Webhook Testing
```bash
# Simulate a webhook request
ddev email-reply-test webhook <token> <from_email> "Reply text"
```

### Production Testing

1. Configure SendGrid/Mailgun inbound parse
2. Set up the reply domain DNS (MX records)
3. Configure the webhook secret
4. Send a test email to verify the flow

## Drush Commands

| Command | Description |
|---------|-------------|
| `email-reply:status` | Check system status |
| `email-reply:enable` | Enable email reply |
| `email-reply:disable` | Disable email reply |
| `email-reply:configure` | Configure settings |
| `email-reply:generate-token` | Generate a reply token |
| `email-reply:simulate` | Simulate an email reply |
| `email-reply:process-queue` | Process the queue |
| `email-reply:setup-test` | Set up test data |
| `email-reply:test` | Run end-to-end test |

## Security Features

- **Token Authentication**: HMAC-SHA256 signed tokens with expiration
- **Email Verification**: Sender email must match user in token
- **Group Membership**: Verifies user is still a group member
- **Spam Filtering**: Rejects high spam score emails
- **Rate Limiting**: Per-user and per-group limits
- **Content Sanitization**: HTML filtering before comment creation

## Email Provider Setup

### SendGrid
1. Create an Inbound Parse webhook at SendGrid
2. Point your MX records to `mx.sendgrid.net`
3. Configure webhook URL: `https://yoursite.com/api/email/inbound`
4. Copy the webhook verification key to settings

### Mailgun
1. Create a route in Mailgun
2. Point MX records to Mailgun servers
3. Configure webhook URL: `https://yoursite.com/api/email/inbound`
4. Configure webhook signing key

## Troubleshooting

### Emails not being processed
1. Check if the module is enabled: `drush email-reply:status`
2. Check the queue: `drush queue:list`
3. Process queue manually: `drush queue:run avc_email_reply`
4. Check logs: `drush watchdog:show --type=avc_email_reply`

### Invalid token errors
- Token may have expired (default: 30 days)
- User email may not match
- Token may have been tampered with

### Rate limiting
- Check remaining quota: `drush email-reply:status`
- Adjust limits in settings if needed

## Files

```
avc_email_reply/
├── src/
│   ├── Controller/
│   │   ├── InboundEmailController.php   # Webhook endpoint
│   │   └── EmailReplyTestController.php # Test UI
│   ├── Commands/
│   │   └── EmailReplyCommands.php       # Drush commands
│   ├── Form/
│   │   └── EmailReplySettingsForm.php   # Admin settings
│   ├── Plugin/QueueWorker/
│   │   └── EmailReplyWorker.php         # Queue processor
│   └── Service/
│       ├── ReplyTokenService.php        # Token generation/validation
│       ├── EmailReplyProcessor.php      # Email processing logic
│       ├── ReplyContentExtractor.php    # Extract reply from email
│       └── EmailRateLimiter.php         # Rate limiting
├── scripts/
│   └── configure_email_reply.php        # Post-install configuration
├── config/
│   ├── install/
│   │   └── avc_email_reply.settings.yml # Default settings
│   └── schema/
│       └── avc_email_reply.schema.yml   # Config schema
└── tests/
    └── src/Unit/Service/                # Unit tests
```

## License

GPL-2.0-or-later
