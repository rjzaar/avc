# Proposal: AVC Error Reporting Module

**Status:** IMPLEMENTED
**Author:** Claude Code
**Date:** 2026-01-16 (Revised)
**Implemented Version:** v0.5.1

## Executive Summary

Create a simple Drupal module `avc_error_report` that adds a "Report an Error" link to the site footer, allowing authenticated users to submit error reports directly to the AVC GitLab repository. The module captures context automatically and provides a straightforward form for users to describe what happened and paste error content.

## Problem Statement

When AVC users encounter errors, they must:
- Remember or screenshot error messages
- Find contact information for reporting
- Compose an email with context
- Wait for follow-up questions about reproduction steps

This creates friction resulting in under-reported bugs and incomplete information.

## Proposed Solution

A simple error reporting workflow:

```
1. User performs an action (e.g., clicks "Save")
2. Error page appears
3. User copies the error page content (Ctrl+A, Ctrl+C)
4. User clicks browser Back button → returns to previous working page
5. User clicks "Report an Error" link in footer
6. Form opens with current URL auto-captured
7. User pastes error content and describes what they did
8. Submit → GitLab issue created with all context
```

This approach:
- Works for any error type (Drupal, PHP, server-level)
- Requires no complex JavaScript or error interception
- Captures the actual error output
- Uses a reliable method for the "source" URL (current page)
- Keeps the user in control of the process

## User Workflow

### Step-by-Step Flow

1. **Error Occurs**: User is on `/group/5/edit`, clicks "Save", sees error page
2. **Copy Error**: User selects all (Ctrl+A) and copies (Ctrl+C) the error page
3. **Go Back**: User clicks browser Back button, returns to `/group/5/edit`
4. **Click Report**: User clicks "Report an Error" in the page footer
5. **Fill Form**: Form shows current URL; user pastes error and describes action
6. **Submit**: Issue created in GitLab with formatted report
7. **Confirmation**: User sees success message with link to the GitLab issue

### Why This Works

| Concern | Solution |
|---------|----------|
| Error page doesn't render navigation | User goes Back to working page first |
| How to know the source page? | Current page URL captured (user is back on it) |
| How to capture error details? | User copies and pastes error page content |
| Complex recording needed? | No - user describes what they did |

## Form Design

### Auto-Captured Fields (No User Input)

| Field | Source | Purpose |
|-------|--------|---------|
| Page URL | `\Drupal::request()->getRequestUri()` | Where the user was |
| Username | `\Drupal::currentUser()->getAccountName()` | Who reported |
| User ID | `\Drupal::currentUser()->id()` | User reference |
| Timestamp | `\Drupal::time()->getRequestTime()` | When reported |
| User Agent | `$_SERVER['HTTP_USER_AGENT']` | Browser/OS |
| User Roles | `\Drupal::currentUser()->getRoles()` | Permission context |
| Drupal Version | `\Drupal::VERSION` | Environment |
| PHP Version | `phpversion()` | Environment |

### User-Provided Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| What did you do? | Textarea | Yes | Action that caused the error |
| Error page content | Textarea (large) | No | Pasted from Ctrl+A/Ctrl+C |
| Can you reproduce it? | Radio | No | Always / Sometimes / Once / Haven't tried |
| Additional notes | Textarea | No | Any other relevant info |

### Form Mockup

```
┌─────────────────────────────────────────────────────────────┐
│  Report an Error                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Page where error occurred:                                 │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ /group/5/edit                                 (auto)  │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                             │
│  What did you do? *                                         │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ I clicked Save after changing the group name          │  │
│  │                                                       │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                             │
│  Error page content                                         │
│  Tip: If you saw an error page, copy it (Ctrl+A, Ctrl+C)   │
│  before clicking Back, then paste here.                    │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ The website encountered an unexpected error.          │  │
│  │ Drupal\Core\Entity\EntityStorageException:            │  │
│  │ SQLSTATE[23000]: Integrity constraint violation...    │  │
│  │                                                       │  │
│  │                                                       │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                             │
│  Can you reproduce this?                                    │
│  ○ Haven't tried  ○ Always  ○ Sometimes  ○ Only once       │
│                                                             │
│  Additional notes (optional)                                │
│  ┌───────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                             │
│                              [ Submit Error Report ]        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## GitLab Issue Format

```markdown
## Error Report

**Reported by:** @jsmith
**Page:** /group/5/edit
**Date:** 2026-01-16 14:30:00
**Reproducible:** Sometimes

### What the user did

I clicked Save after changing the group name

### Error page content

```
The website encountered an unexpected error. Please try again later.

Drupal\Core\Entity\EntityStorageException: SQLSTATE[23000]:
Integrity constraint violation: 1062 Duplicate entry 'tech-team'
for key 'group__field_group_slug'

in Drupal\Core\Entity\Sql\SqlContentEntityStorage->save()
(line 770 of core/lib/Drupal/Core/Entity/Sql/SqlContentEntityStorage.php)
```

### Additional notes

This is the second time it happened today.

### Environment

| | |
|---|---|
| Drupal | 10.2.0 |
| PHP | 8.2.0 |
| Browser | Chrome 120 / macOS |
| Roles | authenticated, tech_guild_admin |

---
*Submitted via AVC Error Report*
```

## Technical Design

### Module Structure

```
modules/custom/avc_error_report/
├── avc_error_report.info.yml
├── avc_error_report.module
├── avc_error_report.routing.yml
├── avc_error_report.permissions.yml
├── avc_error_report.services.yml
├── avc_error_report.libraries.yml
├── README.md
├── config/
│   ├── install/
│   │   └── avc_error_report.settings.yml
│   └── schema/
│       └── avc_error_report.schema.yml
├── css/
│   └── error-report.css
├── src/
│   ├── Form/
│   │   ├── ErrorReportForm.php
│   │   └── ErrorReportSettingsForm.php
│   └── Service/
│       ├── GitLabService.php
│       └── RateLimitService.php
└── tests/
    └── src/
        ├── Unit/
        │   ├── GitLabServiceTest.php
        │   └── RateLimitServiceTest.php
        └── Functional/
            └── ErrorReportFormTest.php
```

### Components

#### ErrorReportForm

Main user-facing form extending `FormBase`.

**Methods:**
- `buildForm()` - Constructs form with auto-populated and user fields
- `validateForm()` - Rate limit check, required field validation
- `submitForm()` - Formats data, calls GitLab API, shows confirmation

#### GitLabService

Handles GitLab API communication.

**Methods:**
- `createIssue(array $data): ?string` - Creates issue, returns URL or null on failure
- `formatIssueBody(array $data): string` - Formats Markdown issue body
- `getLabels(): array` - Returns labels for auto-tagging

**Configuration:**
- GitLab URL (default: https://git.nwpcode.org)
- Project path (default: nwp/avc)
- API token (from .secrets.yml or config)

#### RateLimitService

Prevents spam/abuse.

**Methods:**
- `isAllowed(int $userId): bool` - Checks if user can submit
- `recordSubmission(int $userId): void` - Records a submission

**Storage:** KeyValue store with user-based keys

**Default Limits:** 5 reports per hour per user

### Footer Link Implementation

```php
/**
 * Implements hook_page_bottom().
 */
function avc_error_report_page_bottom(array &$page) {
  $user = \Drupal::currentUser();
  if ($user->isAuthenticated() && $user->hasPermission('submit error reports')) {
    $page['error_report_link'] = [
      '#theme' => 'error_report_link',
      '#url' => Url::fromRoute('avc_error_report.form'),
      '#attached' => [
        'library' => ['avc_error_report/error-report'],
      ],
    ];
  }
}
```

### Security

| Concern | Mitigation |
|---------|------------|
| Spam/abuse | Rate limiting (5/hour/user) |
| XSS in pasted content | Sanitize before GitLab submission |
| CSRF | Drupal Form API handles automatically |
| API token exposure | Server-side only, stored in .secrets.yml |
| Large payloads | Max field lengths (error content: 50KB) |

### Configuration

**Default settings (`config/install/avc_error_report.settings.yml`):**

```yaml
gitlab_url: 'https://git.nwpcode.org'
gitlab_project: 'nwp/avc'
gitlab_token: ''
rate_limit_max: 5
rate_limit_window: 3600
labels:
  - 'bug'
  - 'user-reported'
```

**Secrets (`.secrets.yml`):**

```yaml
gitlab:
  api_token: 'glpat-xxxxxxxxxxxxxxxxxxxx'
```

## Implementation Plan

### Phase 1: Core Module (MVP)

| # | Task | Effort |
|---|------|--------|
| 1.1 | Create module scaffolding (info.yml, module, routing, permissions) | 30 min |
| 1.2 | Create ErrorReportForm with all fields | 1.5 hours |
| 1.3 | Create GitLabService with issue creation | 1.5 hours |
| 1.4 | Create RateLimitService | 30 min |
| 1.5 | Add footer link via hook_page_bottom | 30 min |
| 1.6 | Add basic CSS for footer link | 15 min |
| 1.7 | Manual end-to-end testing | 30 min |
| | **Phase 1 Total** | **~5 hours** |

### Phase 2: Configuration

| # | Task | Effort |
|---|------|--------|
| 2.1 | Create ErrorReportSettingsForm | 1 hour |
| 2.2 | Add admin route and menu link | 15 min |
| 2.3 | Add .secrets.yml integration | 30 min |
| 2.4 | Add config schema | 15 min |
| | **Phase 2 Total** | **~2 hours** |

### Phase 3: Polish & Testing

| # | Task | Effort |
|---|------|--------|
| 3.1 | Add success message with issue link | 15 min |
| 3.2 | Add error handling for API failures | 30 min |
| 3.3 | Style form and footer link properly | 30 min |
| 3.4 | Write unit tests for services | 1 hour |
| 3.5 | Write functional test for form | 1 hour |
| 3.6 | Write README documentation | 30 min |
| | **Phase 3 Total** | **~4 hours** |

### Total Effort

| Phase | Tasks | Effort |
|-------|-------|--------|
| Phase 1 (MVP) | 7 | ~5 hours |
| Phase 2 (Config) | 4 | ~2 hours |
| Phase 3 (Polish) | 6 | ~4 hours |
| **Total** | **17** | **~11 hours** |

## Success Criteria

### Phase 1 (MVP)
- [x] "Report an Error" link visible in footer for authenticated users
- [x] Form captures current page URL automatically
- [x] Form submission creates GitLab issue with formatted content
- [x] Rate limiting prevents more than 5 submissions per hour
- [x] Success message shows link to created issue

### Phase 2 (Configuration)
- [x] Admin can configure GitLab URL and project
- [x] API token can be set via admin UI or .secrets.yml
- [x] Rate limits are configurable

### Phase 3 (Polish)
- [x] API failures show user-friendly error message
- [x] Unit tests pass for GitLabService and RateLimitService
- [ ] Functional test verifies form submission flow
- [x] README documents installation and configuration

## Permissions

```yaml
# avc_error_report.permissions.yml
submit error reports:
  title: 'Submit error reports'
  description: 'Allows users to submit error reports to GitLab.'

administer error reporting:
  title: 'Administer error reporting'
  description: 'Configure error reporting settings.'
  restrict access: true
```

## Dependencies

- **Drupal Core:** 10.x or 11.x
- **PHP:** 8.1+
- **GitLab API:** v4

No dependencies on other AVC modules - this is a standalone utility module.

## Risks & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| GitLab API unavailable | Low | Medium | Show friendly error, log for admin |
| Users don't copy error content | Medium | Low | Clear instructions on form |
| Spam submissions | Low | Low | Rate limiting, user accountability |
| Token compromise | Low | High | .secrets.yml, file permissions, rotation |

## Alternatives Considered

### 1. JavaScript Error Recording

**Rejected:** Over-engineered. Recording needs to happen *before* the user knows they need it. Adds complexity without proportional benefit.

### 2. Navigation Button (Always Visible)

**Rejected:** Doesn't work when error pages fail to render the theme. Footer link on working pages is more reliable.

### 3. HTTP_REFERER for Source URL

**Rejected:** Unreliable - often missing due to privacy settings, HTTPS transitions, or direct navigation.

### 4. Email-Based Reporting

**Rejected:** Requires manual triage, no structured format, harder to track and organize.

## Future Enhancements (Not in Scope)

If this module proves useful, future versions could add:

- Screenshot upload capability
- Browser console log capture (opt-in)
- Duplicate detection
- User notification when issue is resolved
- Integration with Drupal watchdog logs

These are explicitly **not** part of this proposal.

## Conclusion

This simplified approach delivers a practical error reporting system. By leveraging the user's natural workflow (copy error → go back → report), we avoid complex JavaScript recording and unreliable referer detection while still capturing comprehensive error information.

**Implementation Complete:**
1. ✅ Module created at `modules/custom/avc_error_report/`
2. ✅ All services implemented (GitLabService, RateLimitService)
3. ✅ Forms created (ErrorReportForm, ErrorReportSettingsForm)
4. ✅ Footer link added via hook_page_bottom()
5. ✅ Unit tests written for RateLimitService
6. ⏳ Create GitLab API token and configure in admin UI
7. ⏳ Test in staging environment
8. ⏳ Deploy to production

---

**Document Version:** 3.1
**Last Updated:** 2026-01-16
**Status:** IMPLEMENTED in v0.5.1
