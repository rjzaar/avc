# AVC Error Report Module

A simple error reporting module that allows users to submit bug reports directly to GitLab.

## Overview

When users encounter errors, they can:
1. Copy the error page content (Ctrl+A, Ctrl+C)
2. Click the browser Back button
3. Click "Report an Error" in the footer
4. Paste the error and describe what happened
5. Submit - a GitLab issue is created automatically

## Installation

```bash
drush en avc_error_report -y
drush role:perm:add authenticated 'submit error reports'
```

## Configuration

### Admin UI

Navigate to **Admin > Configuration > System > Error Report Settings** (`/admin/config/avc/error-report`)

Configure:
- **GitLab URL**: Your GitLab instance (e.g., `https://git.nwpcode.org`)
- **GitLab Project**: The project path (e.g., `avc/avc`)
- **GitLab API Token**: A personal or project access token with `api` scope
- **Issue Labels**: Comma-separated labels for created issues
- **Rate Limiting**: Max reports and time window

### GitLab API Token

1. Go to your GitLab instance
2. Navigate to **Settings > Access Tokens**
3. Create a token with `api` scope
4. Copy the token to the Error Report Settings form

### Rate Limiting

Default: 5 reports per hour per user. Configurable in admin settings.

## User Workflow

### Reporting an Error

1. **Error occurs**: User is on a page and an action causes an error
2. **Copy error**: User selects all (Ctrl+A) and copies (Ctrl+C) the error page
3. **Go back**: User clicks browser Back button to return to the working page
4. **Click report**: User clicks "Report an Error" link in the page footer
5. **Fill form**:
   - Page URL is auto-filled from the referer
   - User pastes error content
   - User describes what action caused the error
6. **Submit**: A GitLab issue is created with all the context
7. **Confirmation**: User sees success message with link to the issue

### Form Fields

| Field | Required | Description |
|-------|----------|-------------|
| Page URL | Yes | Auto-filled, editable |
| What did you do? | Yes | Action that caused the error |
| Error page content | No | Pasted error page |
| Can you reproduce? | No | Always/Sometimes/Once/Haven't tried |
| Additional notes | No | Any extra context |

### Auto-Captured Data

The form automatically captures:
- Username and user ID
- User roles
- Drupal version
- PHP version
- Browser and OS (simplified from User-Agent)

## Permissions

| Permission | Description |
|------------|-------------|
| `submit error reports` | Allows users to submit error reports |
| `administer error reporting` | Access to settings form |

## GitLab Issue Format

Issues are created with this format:

```markdown
## Error Report

**Reported by:** @username
**Page:** /path/to/page
**Date:** 2026-01-16 14:30:00
**Reproducible:** Sometimes

### What the user did

User's description of the action

### Error page content

```
Pasted error content
```

### Additional notes

User's additional context

### Environment

| | |
|---|---|
| User | username |
| Drupal | 10.2.0 |
| PHP | 8.2.0 |
| Browser | Chrome 120 / macOS |
| Roles | authenticated, editor |

---
*Submitted via AVC Error Report*
```

## Files

```
avc_error_report/
├── avc_error_report.info.yml      # Module definition
├── avc_error_report.module        # Hook implementations
├── avc_error_report.routing.yml   # Route definitions
├── avc_error_report.permissions.yml
├── avc_error_report.services.yml  # Service definitions
├── avc_error_report.libraries.yml
├── avc_error_report.links.menu.yml
├── README.md
├── config/
│   ├── install/
│   │   └── avc_error_report.settings.yml
│   └── schema/
│       └── avc_error_report.schema.yml
├── css/
│   └── error-report.css
└── src/
    ├── Form/
    │   ├── ErrorReportForm.php
    │   └── ErrorReportSettingsForm.php
    └── Service/
        ├── GitLabService.php
        └── RateLimitService.php
```

## Troubleshooting

### "Report an Error" link not visible

- Ensure the user has the `submit error reports` permission
- Clear caches: `drush cr`

### GitLab API errors

- Check the API token has `api` scope
- Verify the project path is correct (e.g., `group/project`)
- Use the "Test Connection" button in settings

### Rate limit errors

- Users are limited to 5 reports per hour by default
- Admins can adjust in settings
- Wait for the time window to expire

## Development

### Services

- `avc_error_report.gitlab`: GitLab API communication
- `avc_error_report.rate_limit`: Rate limiting logic

### Extending

To customize the issue format, extend `GitLabService` and override `formatDescription()`.

## Changelog

### 1.0.0

- Initial release
- Footer link for error reporting
- GitLab issue creation
- Rate limiting
- Admin configuration form
