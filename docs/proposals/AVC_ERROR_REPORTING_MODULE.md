# Proposal: AVC Error Reporting Module

**Status:** PROPOSED
**Author:** Claude Code
**Date:** 2026-01-13
**Target Version:** v0.14

## Executive Summary

Create a new Drupal custom module `avc_error_report` that adds a prominent "Report Error" button to the site navigation, allowing authenticated users to submit error reports directly to the AVC GitLab repository. The module will capture context automatically, provide a user-friendly form for additional details, and optionally record user interactions and subsequent errors for comprehensive debugging information.

## Problem Statement

Currently, when AVC users encounter errors, they must:
- Screenshot or manually copy error messages
- Find contact information for reporting
- Compose an email with context
- Wait for follow-up questions about reproduction steps

This creates friction and results in:
- Under-reporting of bugs
- Incomplete error reports lacking context
- Developer time spent gathering information
- Delayed fixes for issues

## Proposed Solution

Add an always-visible "Report Error" button that:
1. Auto-captures previous page URL
2. Provides simple form for user explanation
3. Optionally records next-page actions and errors
4. Automatically creates GitLab issues with formatted data
5. Returns confirmation with issue link

## Requirements

### Functional Requirements

1. **Navigation Button**
   - Red, prominent button in main navigation
   - Visible to all authenticated users
   - Accessible via keyboard navigation
   - Responsive on mobile devices

2. **Error Report Form**
   - Previous page address (auto-populated from HTTP_REFERER)
   - User explanation field (required, textarea)
   - Optional error URL field (if different from previous page)
   - Error message paste area (for stack traces, console output)
   - Optional "Record next actions" checkbox
   - Submit button with clear feedback

3. **Error Capture System**
   - JavaScript-based action recording
   - Console error capturing
   - Click and form submission tracking
   - 30-second recording window
   - SessionStorage for cross-page persistence

4. **GitLab Integration**
   - Create issues in AVC repo at git.nwpcode.org
   - Format issue with structured data
   - Auto-label as "bug,user-reported,automated"
   - Return issue URL to user

5. **Security & Abuse Prevention**
   - Rate limiting (5 reports per hour per user)
   - CSRF protection
   - Input validation and sanitization
   - API token protection

### Non-Functional Requirements

1. **Performance**
   - Form load time < 100ms
   - GitLab API response < 2s
   - Recording has no noticeable impact

2. **Security**
   - All inputs sanitized
   - API tokens never exposed to client
   - Rate limiting prevents abuse
   - Logging for audit trail

3. **Usability**
   - WCAG 2.1 AA accessible
   - Clear, helpful error messages
   - Success confirmation with next steps
   - Mobile-responsive design

4. **Maintainability**
   - Follows Drupal coding standards
   - Service-oriented architecture
   - Comprehensive test coverage
   - Documented code and API

## Technical Design

### Module Structure

```
modules/custom/avc_error_report/
├── avc_error_report.info.yml
├── avc_error_report.routing.yml
├── avc_error_report.links.menu.yml
├── avc_error_report.module
├── avc_error_report.permissions.yml
├── avc_error_report.libraries.yml
├── README.md
├── config/
│   └── install/
│       └── avc_error_report.settings.yml
├── src/
│   ├── Form/
│   │   ├── ErrorReportForm.php
│   │   └── ErrorReportSettingsForm.php
│   └── Service/
│       ├── GitLabApiService.php
│       ├── ErrorCaptureService.php
│       └── RateLimitService.php
├── js/
│   └── error-capture.js
├── css/
│   └── error-report-button.css
└── tests/
    └── src/
        ├── Unit/
        ├── Kernel/
        └── Functional/
```

### Core Components

#### 1. ErrorReportForm (FormBase)

**Purpose:** Main user-facing form for error reporting

**Key Methods:**
- `buildForm()` - Constructs form with auto-populated fields
- `validateForm()` - Rate limit check, input validation
- `submitForm()` - Collects data, calls GitLab API, shows confirmation

**Dependencies:**
- GitLabApiService (for issue creation)
- RateLimitService (for abuse prevention)
- Logger (for error tracking)

**Form Fields:**
- `previous_page` (textfield, disabled) - Auto from HTTP_REFERER
- `explanation` (textarea, required) - User description
- `error_url` (url, optional) - Specific error location
- `error_message` (textarea, optional) - Stack traces, console output
- `captured_data` (hidden) - JSON from JavaScript recorder
- `enable_recording` (checkbox) - Opt-in for next-page capture

#### 2. GitLabApiService

**Purpose:** Handle all GitLab API interactions

**Key Methods:**
- `createIssue(array $reportData): string` - Creates issue, returns URL
- `formatIssueTitle(array $data): string` - Generates title
- `formatIssueDescription(array $data): string` - Formats Markdown description
- `getApiToken(): string` - Retrieves token from config/secrets

**Configuration:**
- GitLab URL (default: https://git.nwpcode.org)
- Project ID (default: avc/avc)
- API token (from .secrets.yml)

**Issue Format:**
```markdown
## User-Reported Error

**Reported by:** username (ID: 123)
**Timestamp:** 2026-01-13 15:30:00

### What Happened

[User's explanation]

### Error Location

URL: https://avc.ddev.site/path/to/error

### Previous Page

https://avc.ddev.site/previous/path

### Error Message

```
[Pasted error or stack trace]
```

### Captured User Actions

- click on button#submit at 2026-01-13T15:29:45Z
- submit on form.user-form at 2026-01-13T15:29:46Z

### Console Errors

```
TypeError: Cannot read property 'foo' of undefined at script.js:123
```

### Technical Details

- **User Agent:** Mozilla/5.0...
```

#### 3. RateLimitService

**Purpose:** Prevent spam and abuse

**Implementation:**
- Uses KeyValueFactory for persistent storage
- Key format: `user_{user_id}`
- Stores array of submission timestamps
- Configurable: max submissions and time window
- Auto-cleanup of expired timestamps

**Default Limits:**
- 5 reports per user
- Within 1 hour window
- Configurable via admin UI

**Methods:**
- `checkLimit(int $userId): bool` - Returns true if under limit
- `recordSubmission(int $userId): void` - Records new submission

#### 4. JavaScript Error Capture (error-capture.js)

**Purpose:** Record user actions and errors on next page

**Workflow:**
1. User checks "Record next actions" on form
2. Sets flag in sessionStorage
3. Navigates to another page
4. JavaScript initializes capture if flag present
5. Records clicks, form submissions, console errors
6. Saves to sessionStorage after 30s or on navigation
7. Returns to error form
8. Auto-populates captured data

**Captured Data:**
```javascript
{
  url: "https://avc.ddev.site/path",
  timestamp: "2026-01-13T15:30:00Z",
  actions: [
    {
      type: "click",
      target: "button#submit.primary",
      timestamp: "2026-01-13T15:30:05Z"
    }
  ],
  console_errors: [
    "TypeError: Cannot read property 'foo' of undefined"
  ]
}
```

**Security:**
- No eval() or innerHTML
- Uses safe DOM methods (textContent, classList)
- Time-limited recording (30s max)
- Opt-in only

### Security Architecture

#### Authentication & Authorization

```yaml
# avc_error_report.routing.yml
avc_error_report.form:
  path: '/report-error'
  defaults:
    _form: '\Drupal\avc_error_report\Form\ErrorReportForm'
    _title: 'Report Error'
  requirements:
    _user_is_logged_in: 'TRUE'
```

**Optional:** Add custom permission for fine-grained control

```yaml
# avc_error_report.permissions.yml
submit error report:
  title: 'Submit error reports'
  description: 'Allows users to submit error reports to GitLab'
```

#### Rate Limiting

**Strategy:** User-based limits with sliding window

**Storage:** KeyValueFactory (persistent, database-backed)

**Configuration:**
- `rate_limit_max`: Default 5
- `rate_limit_window`: Default 3600 (1 hour)

**Bypass:** Admin role can override (optional)

#### Input Sanitization

**Form API:** Automatic sanitization via Drupal Form API

**Additional Validation:**
- URL fields: `#type => 'url'` validation
- Explanation: Minimum 10 characters
- Error message: Maximum 10,000 characters (prevent DOS)
- All text: Xss::filter() before GitLab submission

#### CSRF Protection

**Drupal Form API:** Automatic token generation and validation

**AJAX Endpoints:** Must include form token in headers

#### Token Protection

**Storage:** .secrets.yml (never committed to git)

**Access:** Only server-side via GitLabApiService

**Scope:** Project-level token with `api` permission only

**Fallback:** Admin can override in module config

### Configuration

#### Default Config (config/install/avc_error_report.settings.yml)

```yaml
gitlab_url: 'https://git.nwpcode.org'
project_id: 'avc/avc'
gitlab_token: ''
rate_limit_max: 5
rate_limit_window: 3600
enable_recording: true
```

#### Admin Configuration Form

**Path:** `/admin/config/system/avc-error-report`

**Fields:**
- GitLab URL (default: git.nwpcode.org)
- Project ID (default: avc/avc)
- API Token (optional override)
- Rate limit max (default: 5)
- Rate limit window (default: 3600 seconds)
- Enable/disable recording feature

#### .secrets.yml Integration

```yaml
# .secrets.yml (not committed)
gitlab:
  url: "https://git.nwpcode.org"
  api_token: "glpat-xxxxxxxxxxxxxxxxxxxx"
  project_id: "avc/avc"
```

### User Experience Flow

#### Happy Path

1. User encounters error while browsing
2. Clicks red "Report Error" button in navigation
3. Form opens with previous page auto-filled
4. User types explanation: "I clicked save and got a white screen"
5. User optionally checks "Record next actions"
6. User pastes error message from browser console
7. User clicks "Submit Error Report"
8. Form validates, submits to GitLab
9. Success message: "Thank you! Your report: [Issue #123]"
10. User can click link to view issue on GitLab
11. Developers see formatted issue with all context

#### Recording Flow

1. User checks "Record next actions" box
2. Submits form (or navigates away)
3. Goes to problematic page
4. JavaScript starts recording in background
5. User clicks buttons, fills forms (captured)
6. Error occurs (captured in console)
7. After 30 seconds or navigation, recording stops
8. Data saved to sessionStorage
9. User returns to error form
10. Captured data auto-populates
11. User submits report with rich debugging data

#### Error Paths

**Rate Limit Exceeded:**
- Message: "You've submitted 5 reports in the last hour. Please wait before submitting another."
- No form submission
- User can view but not submit

**GitLab API Failure:**
- Message: "Sorry, we couldn't submit your report. Please try again or email support@..."
- Error logged for admin review
- User prompted to screenshot/copy report

**Validation Errors:**
- Inline field errors (e.g., "Please provide more detail")
- Form not submitted
- User corrects and resubmits

---

## Numbered Implementation Plan

This section provides a detailed, numbered implementation plan organized into phases. Each task is numbered for easy reference and tracking.

### Phase 1: Core Module Foundation (MVP)

**Goal:** Working error reporting with GitLab integration

#### 1.1 Module Scaffolding
- **1.1.1** Create module directory structure at `modules/custom/avc_error_report/`
- **1.1.2** Create `avc_error_report.info.yml` with dependencies (core:config, core:user)
- **1.1.3** Create `avc_error_report.module` with hook implementations
- **1.1.4** Create `avc_error_report.permissions.yml` with 'submit error report' permission
- **1.1.5** Create `avc_error_report.services.yml` for dependency injection

#### 1.2 Configuration Setup
- **1.2.1** Create `config/install/avc_error_report.settings.yml` with default values
- **1.2.2** Create `config/schema/avc_error_report.schema.yml` for config validation
- **1.2.3** Define GitLab URL, project ID, rate limits as configurable values

#### 1.3 Error Report Form
- **1.3.1** Create `src/Form/ErrorReportForm.php` extending FormBase
- **1.3.2** Implement `buildForm()` with all required fields:
  - Previous page URL (auto-populated, disabled)
  - User explanation (textarea, required, min 10 chars)
  - Error URL (optional URL field)
  - Error message (textarea, max 10,000 chars)
- **1.3.3** Implement `validateForm()` with input validation
- **1.3.4** Implement `submitForm()` stub (prepare for GitLab integration)
- **1.3.5** Add form-level CSRF protection verification

#### 1.4 Routing and Menu
- **1.4.1** Create `avc_error_report.routing.yml` with `/report-error` path
- **1.4.2** Create `avc_error_report.links.menu.yml` for navigation link
- **1.4.3** Configure route to require authenticated user
- **1.4.4** Create `css/error-report-button.css` with red button styling
- **1.4.5** Create `avc_error_report.libraries.yml` to attach CSS

#### 1.5 GitLab API Service
- **1.5.1** Create `src/Service/GitLabApiService.php`
- **1.5.2** Implement `createIssue(array $data): string` method
- **1.5.3** Implement `formatIssueTitle(array $data): string` method
- **1.5.4** Implement `formatIssueDescription(array $data): string` method
- **1.5.5** Implement `getApiToken(): string` with .secrets.yml fallback
- **1.5.6** Add Guzzle HTTP client dependency injection
- **1.5.7** Implement error handling for API failures

#### 1.6 Rate Limiting (Basic)
- **1.6.1** Create `src/Service/RateLimitService.php`
- **1.6.2** Implement `checkLimit(int $userId): bool` method
- **1.6.3** Implement `recordSubmission(int $userId): void` method
- **1.6.4** Use KeyValueFactory for persistent storage
- **1.6.5** Integrate rate limiting into form validation

#### 1.7 Integration and Testing
- **1.7.1** Wire up GitLabApiService in form submission
- **1.7.2** Display success message with GitLab issue URL
- **1.7.3** Display error message on API failure
- **1.7.4** Manual testing with test GitLab repository
- **1.7.5** Verify button appears in navigation
- **1.7.6** Test rate limiting functionality

### Phase 2: Error Capture Enhancement

**Goal:** JavaScript-based action and error recording

#### 2.1 JavaScript Infrastructure
- **2.1.1** Create `js/error-capture.js` base structure
- **2.1.2** Implement initialization with Drupal behaviors
- **2.1.3** Implement sessionStorage flag detection
- **2.1.4** Add recording state management

#### 2.2 Action Recording
- **2.2.1** Implement click event listener with target identification
- **2.2.2** Implement form submission listener
- **2.2.3** Implement keyboard event listener (optional, configurable)
- **2.2.4** Build action array with timestamps
- **2.2.5** Implement 30-second timeout for auto-stop

#### 2.3 Console Error Capture
- **2.3.1** Override `console.error` to intercept errors
- **2.3.2** Hook into `window.onerror` for uncaught exceptions
- **2.3.3** Store captured errors in array
- **2.3.4** Preserve original console functionality

#### 2.4 Data Persistence
- **2.4.1** Implement sessionStorage write on navigation/timeout
- **2.4.2** Implement sessionStorage read on form load
- **2.4.3** Clear sessionStorage after successful form submission
- **2.4.4** Handle storage quota exceeded gracefully

#### 2.5 Form Integration
- **2.5.1** Add "Record next actions" checkbox to form
- **2.5.2** Add hidden `captured_data` field for JSON
- **2.5.3** Auto-populate captured data on form load
- **2.5.4** Display captured data summary to user
- **2.5.5** Include captured data in GitLab issue body

#### 2.6 Cross-Browser Testing
- **2.6.1** Test in Chrome/Chromium
- **2.6.2** Test in Firefox
- **2.6.3** Test in Safari
- **2.6.4** Test in Edge
- **2.6.5** Test in mobile browsers (iOS Safari, Chrome Android)
- **2.6.6** Fix any compatibility issues

### Phase 3: Administration and Configuration

**Goal:** Admin UI for module configuration

#### 3.1 Settings Form
- **3.1.1** Create `src/Form/ErrorReportSettingsForm.php` extending ConfigFormBase
- **3.1.2** Add GitLab URL field
- **3.1.3** Add Project ID field
- **3.1.4** Add API Token field (password type)
- **3.1.5** Add Rate limit max field
- **3.1.6** Add Rate limit window field
- **3.1.7** Add Enable recording toggle
- **3.1.8** Implement form validation
- **3.1.9** Implement form submission to save config

#### 3.2 Admin Routing
- **3.2.1** Add admin route `/admin/config/system/avc-error-report`
- **3.2.2** Create `avc_error_report.links.menu.yml` admin menu entry
- **3.2.3** Configure permission requirement for admin access

#### 3.3 Configuration Validation
- **3.3.1** Add GitLab API connection test button
- **3.3.2** Validate API token has required permissions
- **3.3.3** Display connection status message

### Phase 4: Security Hardening

**Goal:** Production-ready security measures

#### 4.1 Input Validation
- **4.1.1** Add Xss::filter() to all text fields before GitLab submission
- **4.1.2** Implement URL whitelist validation (optional)
- **4.1.3** Add maximum length enforcement server-side
- **4.1.4** Sanitize captured JavaScript data

#### 4.2 Rate Limiting Enhancement
- **4.2.1** Implement sliding window algorithm
- **4.2.2** Add timestamp cleanup for expired entries
- **4.2.3** Add admin bypass capability (optional)
- **4.2.4** Add IP-based rate limiting (optional, for anonymous)

#### 4.3 Logging and Audit
- **4.3.1** Log all successful submissions with user ID
- **4.3.2** Log all failed submissions with reason
- **4.3.3** Log rate limit violations
- **4.3.4** Log GitLab API errors

#### 4.4 Token Security
- **4.4.1** Verify token never appears in client-side code
- **4.4.2** Verify token never logged in plaintext
- **4.4.3** Document token rotation procedure

### Phase 5: Testing and Quality Assurance

**Goal:** Comprehensive test coverage

#### 5.1 Unit Tests
- **5.1.1** Create `tests/src/Unit/GitLabApiServiceTest.php`
- **5.1.2** Test `formatIssueTitle()` with various inputs
- **5.1.3** Test `formatIssueDescription()` with various inputs
- **5.1.4** Create `tests/src/Unit/RateLimitServiceTest.php`
- **5.1.5** Test limit calculation at boundaries
- **5.1.6** Test timestamp cleanup functionality

#### 5.2 Kernel Tests
- **5.2.1** Create `tests/src/Kernel/ServiceRegistrationTest.php`
- **5.2.2** Test service instantiation via container
- **5.2.3** Test configuration CRUD operations
- **5.2.4** Test permission registration

#### 5.3 Functional Tests
- **5.3.1** Create `tests/src/Functional/ErrorReportFormTest.php`
- **5.3.2** Test anonymous user cannot access form
- **5.3.3** Test authenticated user can access form
- **5.3.4** Test form validation error messages
- **5.3.5** Test successful form submission flow
- **5.3.6** Test rate limit enforcement

#### 5.4 JavaScript Tests
- **5.4.1** Create Jest test configuration
- **5.4.2** Test action recording functionality
- **5.4.3** Test console error capture
- **5.4.4** Test sessionStorage operations
- **5.4.5** Test timeout behavior

#### 5.5 Manual Testing Checklist
- **5.5.1** Execute full happy path test
- **5.5.2** Execute recording flow test
- **5.5.3** Test all error paths
- **5.5.4** Test accessibility with screen reader
- **5.5.5** Test keyboard-only navigation
- **5.5.6** Test mobile responsiveness

### Phase 6: Documentation and Deployment

**Goal:** Complete documentation and deployment readiness

#### 6.1 Developer Documentation
- **6.1.1** Create `README.md` with overview and installation
- **6.1.2** Document all configuration options
- **6.1.3** Document service API and extension points
- **6.1.4** Add PHPDoc blocks to all classes and methods

#### 6.2 User Documentation
- **6.2.1** Create `USER_GUIDE.md` with how-to instructions
- **6.2.2** Add screenshots of form and button
- **6.2.3** Document recording feature usage
- **6.2.4** Add FAQ section

#### 6.3 Admin Documentation
- **6.3.1** Create `ADMIN_GUIDE.md` with configuration instructions
- **6.3.2** Document GitLab token creation process
- **6.3.3** Document troubleshooting procedures
- **6.3.4** Document rate limiting configuration

#### 6.4 Code Quality
- **6.4.1** Run PHP CodeSniffer and fix violations
- **6.4.2** Run PHPStan and fix issues
- **6.4.3** Peer code review
- **6.4.4** Address review feedback

#### 6.5 Deployment
- **6.5.1** Create installation instructions
- **6.5.2** Document required GitLab configuration
- **6.5.3** Create deployment checklist
- **6.5.4** Enable module on staging environment
- **6.5.5** Verify functionality on staging
- **6.5.6** Enable module on production

---

## Implementation Summary

| Phase | Tasks | Dependencies | Deliverables |
|-------|-------|--------------|--------------|
| **Phase 1** | 1.1-1.7 (28 tasks) | None | Working MVP with GitLab integration |
| **Phase 2** | 2.1-2.6 (24 tasks) | Phase 1 | JavaScript error capture |
| **Phase 3** | 3.1-3.3 (12 tasks) | Phase 1 | Admin configuration UI |
| **Phase 4** | 4.1-4.4 (13 tasks) | Phases 1-3 | Security hardening |
| **Phase 5** | 5.1-5.5 (21 tasks) | Phases 1-4 | Test coverage |
| **Phase 6** | 6.1-6.5 (18 tasks) | Phases 1-5 | Documentation and deployment |

**Total Tasks:** 116 numbered implementation tasks

### Critical Path

1. Phase 1 (MVP) must be completed first
2. Phases 2 and 3 can proceed in parallel after Phase 1
3. Phase 4 depends on Phases 1-3
4. Phase 5 depends on Phases 1-4
5. Phase 6 depends on Phase 5

### Success Criteria by Phase

#### Phase 1 Success Criteria
- [ ] Button visible in navigation bar
- [ ] Form pre-populates previous page
- [ ] Submission creates GitLab issue
- [ ] Issue contains all form data
- [ ] Rate limit prevents spam

#### Phase 2 Success Criteria
- [ ] Recording checkbox works
- [ ] Next page actions captured
- [ ] Console errors captured
- [ ] Data persists across pages
- [ ] Works in all major browsers

#### Phase 3 Success Criteria
- [ ] Admin can access settings
- [ ] All settings save correctly
- [ ] Connection test works

#### Phase 4 Success Criteria
- [ ] All inputs sanitized
- [ ] Rate limiting persists
- [ ] All actions logged
- [ ] Token protected

#### Phase 5 Success Criteria
- [ ] Unit test coverage >80%
- [ ] Functional tests pass
- [ ] Manual testing complete
- [ ] Accessibility verified

#### Phase 6 Success Criteria
- [ ] All documentation complete
- [ ] Code review approved
- [ ] Staging verified
- [ ] Production deployed

---

## Testing Strategy

### Unit Tests

**Target:** Service classes

**Tests:**
- `GitLabApiServiceTest`
  - Test issue title formatting
  - Test description formatting
  - Test API error handling
  - Mock HTTP client responses
- `RateLimitServiceTest`
  - Test limit calculation
  - Test timestamp cleanup
  - Test edge cases (exactly at limit)
- Form validation logic

### Kernel Tests

**Target:** Drupal integration

**Tests:**
- Service registration and dependency injection
- Configuration management (CRUD)
- Key-value storage operations
- Permission checks

### Functional Tests

**Target:** End-to-end user flows

**Tests:**
- `ErrorReportFormTest`
  - Anonymous users cannot access
  - Authenticated users see form
  - Form submission creates GitLab issue
  - Rate limiting prevents spam
  - Validation errors shown correctly
  - Success message with issue link
- Navigation button visibility
- Recording feature cross-page flow

### Manual Testing Checklist

**User Interface:**
- [ ] "Report Error" button appears in navigation
- [ ] Button is red and visually prominent
- [ ] Button is keyboard accessible (Tab + Enter)
- [ ] Mobile responsive (button and form)

**Form Functionality:**
- [ ] Previous page auto-populates correctly
- [ ] All fields accept valid input
- [ ] Required fields enforce validation
- [ ] URL field rejects invalid URLs
- [ ] Explanation field requires 10+ characters
- [ ] Error message field accepts long text (stack traces)

**GitLab Integration:**
- [ ] Issue created successfully
- [ ] Issue title format correct
- [ ] Issue description includes all data
- [ ] Issue has correct labels
- [ ] Issue URL returned to user
- [ ] User can access issue (permissions)

**Rate Limiting:**
- [ ] First 5 submissions succeed
- [ ] 6th submission blocked with message
- [ ] After 1 hour, can submit again
- [ ] Rate limit persists across sessions

**Error Recording:**
- [ ] Checkbox enables recording
- [ ] Next page actions captured
- [ ] Console errors captured
- [ ] Data persists via sessionStorage
- [ ] Recording stops after 30 seconds
- [ ] Captured data appears in form
- [ ] No performance impact

**Security:**
- [ ] CSRF tokens prevent forgery
- [ ] Input sanitization prevents XSS
- [ ] API token never exposed in HTML/JS
- [ ] Rate limiting prevents abuse
- [ ] Validation prevents oversized inputs

**Accessibility:**
- [ ] Screen reader announces all fields
- [ ] Keyboard navigation works throughout
- [ ] Focus indicators visible
- [ ] Error messages programmatically associated
- [ ] ARIA labels present and correct

**Browser Compatibility:**
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari
- [ ] Edge
- [ ] Mobile browsers (iOS Safari, Chrome Android)

## Security Considerations

### Threat Model

**Threat:** Spam/Abuse
- **Attack:** User submits hundreds of fake reports
- **Mitigation:** Rate limiting (5/hour), user accountability
- **Impact:** Medium (wastes developer time, clutters issues)

**Threat:** XSS via User Input
- **Attack:** User includes malicious script in explanation
- **Mitigation:** Drupal Form API sanitization, Xss::filter()
- **Impact:** High (could compromise other users/admins)

**Threat:** API Token Compromise
- **Attack:** Attacker gains access to .secrets.yml or config
- **Mitigation:** File permissions, .gitignore, token rotation
- **Impact:** High (could create/modify any issues)

**Threat:** Information Disclosure
- **Attack:** User includes sensitive data in report
- **Mitigation:** User education, admin review, optional confidential flag
- **Impact:** Medium (PII exposure)

**Threat:** CSRF Attack
- **Attack:** Malicious site tricks user into submitting report
- **Mitigation:** Drupal Form API CSRF tokens
- **Impact:** Low (requires authenticated user)

**Threat:** DOS via Large Inputs
- **Attack:** User submits massive error messages
- **Mitigation:** Field length limits, rate limiting
- **Impact:** Low (limited by rate limits)

### Security Best Practices Applied

1. **Principle of Least Privilege**
   - API token has only `api` scope
   - Only authenticated users can submit
   - Optional custom permission for further restriction

2. **Defense in Depth**
   - Client-side validation (UX)
   - Server-side validation (security)
   - Rate limiting (abuse prevention)
   - Logging (audit trail)

3. **Secure Defaults**
   - Rate limiting enabled by default
   - Recording opt-in only
   - Conservative field length limits

4. **Input Validation**
   - Whitelist validation (URL fields)
   - Length limits (DOS prevention)
   - Type checking (form API)
   - Sanitization (XSS prevention)

5. **Error Handling**
   - Never expose internal errors to users
   - Log all failures for admin review
   - Graceful degradation (show generic message)

6. **Secrets Management**
   - API token in .secrets.yml (not committed)
   - Never exposed to client
   - Rotatable without code changes

## Success Metrics

### Adoption Metrics

**Target:** Measure how many users use the feature

- **Metric 1:** % of active users who submit ≥1 report
  - **Target:** 25% within 3 months
  - **Measurement:** Drupal logs, GitLab issue counts

- **Metric 2:** Time to first report for new users
  - **Target:** <1 week average
  - **Measurement:** User registration date vs first report

### Quality Metrics

**Target:** Measure usefulness of reports

- **Metric 1:** % of reports marked "actionable"
  - **Target:** 80%+
  - **Measurement:** GitLab labels (valid/invalid/duplicate)

- **Metric 2:** Average developer response time
  - **Target:** <24 hours for critical, <1 week for normal
  - **Measurement:** Issue creation to first comment

- **Metric 3:** % of reports leading to bug fixes
  - **Target:** 50%+
  - **Measurement:** Issues with fix commits linked

### Performance Metrics

**Target:** Ensure good performance

- **Metric 1:** Form load time (p95)
  - **Target:** <200ms
  - **Measurement:** Browser timing API, logs

- **Metric 2:** GitLab API response time (p95)
  - **Target:** <3s
  - **Measurement:** Service timing logs

- **Metric 3:** JavaScript recording overhead
  - **Target:** <5% CPU, <1MB memory
  - **Measurement:** Browser profiling

### User Satisfaction

**Target:** Users find it helpful

- **Metric 1:** User feedback rating
  - **Target:** 4.0+ / 5.0
  - **Measurement:** Optional survey after 3rd report

- **Metric 2:** Feature usage retention
  - **Target:** 60%+ of users who report once report again
  - **Measurement:** User IDs in reports over time

## Dependencies

### External Dependencies

- **GitLab API v4:** For issue creation
  - Version: v4 (stable)
  - Documentation: https://docs.gitlab.com/ee/api/issues.html
  - Authentication: Personal/Project access token

- **Drupal Core:** 9.5+ or 10.x
  - Form API
  - HTTP client (Guzzle)
  - KeyValue storage
  - Config API

### Internal Dependencies

- **No AVC module dependencies:** Standalone module
- **.secrets.yml:** For GitLab token (optional, can use admin config)

### Development Dependencies

- **PHPUnit:** For unit/kernel/functional tests
- **Drupal Test Traits:** For test helpers
- **PHP CodeSniffer:** For coding standards

## Risks & Mitigation

### Technical Risks

**Risk:** GitLab API changes break integration
- **Probability:** Low
- **Impact:** High
- **Mitigation:** Version lock API endpoints, monitor GitLab changelog, add integration tests
- **Contingency:** Fallback to email-based reporting

**Risk:** JavaScript recorder causes performance issues
- **Probability:** Medium
- **Impact:** Medium
- **Mitigation:** Time limits (30s), opt-in only, performance testing
- **Contingency:** Make recording admin-disabled, optimize recording logic

**Risk:** Rate limiting is bypassed by sophisticated attacker
- **Probability:** Low
- **Impact:** Medium
- **Mitigation:** Additional IP-based limits, honeypot fields, admin monitoring
- **Contingency:** Temporarily disable module, tighten limits

### Operational Risks

**Risk:** API token compromise
- **Probability:** Low
- **Impact:** High
- **Mitigation:** File permissions, .gitignore, regular rotation, audit logging
- **Contingency:** Immediately revoke token, create new one, review all issues

**Risk:** Spam/abuse of reporting system
- **Probability:** Medium
- **Impact:** Low
- **Mitigation:** Rate limiting, user accountability, admin review dashboard
- **Contingency:** Ban abusive users, tighten rate limits, add CAPTCHA

**Risk:** Users include sensitive data in reports
- **Probability:** Medium
- **Impact:** Medium
- **Mitigation:** User education, admin review, optional confidential flag
- **Contingency:** Delete sensitive issues, educate user, update UI warnings

### Adoption Risks

**Risk:** Users don't discover or use feature
- **Probability:** Medium
- **Impact:** Medium
- **Mitigation:** Prominent button, user education, in-app prompts
- **Contingency:** Add onboarding tour, email announcement, documentation

**Risk:** Reports lack useful information
- **Probability:** Medium
- **Impact:** Medium
- **Mitigation:** Required fields, helpful placeholders, recording feature
- **Contingency:** Add more guidance, templates, examples

## Maintenance Plan

### Ongoing Maintenance

**Tasks:**
- Monitor GitLab API for changes (monthly review)
- Review error logs for failures (weekly)
- Update dependencies (quarterly)
- Rotate API tokens (annually or on compromise)
- Review and triage reported issues (daily)

### Future Enhancements (Phase 7+)

**Potential Improvements:**
- Duplicate detection algorithm
- Screenshot capture integration
- Advanced analytics dashboard
- Machine learning for auto-categorization
- Integration with other error tracking services

**Prioritization:** Based on user feedback and adoption metrics

## Documentation Deliverables

### Developer Documentation

1. **README.md** (Module root)
   - Overview and purpose
   - Installation instructions
   - Configuration guide
   - Development setup
   - Testing instructions
   - Contributing guidelines

2. **API.md** (Module root)
   - Service documentation
   - Public methods and parameters
   - Example usage
   - Extension points

3. **Inline Code Comments**
   - PHPDoc blocks for all classes and methods
   - Complex logic explanations
   - Security notes where relevant

### User Documentation

1. **USER_GUIDE.md** (Module root or AVC docs)
   - How to report an error
   - What information to include
   - How to use recording feature
   - What happens after submission
   - FAQ

2. **ADMIN_GUIDE.md** (Module root or AVC docs)
   - Configuration instructions
   - GitLab setup and token creation
   - Rate limiting configuration
   - Monitoring and troubleshooting
   - Security best practices

### Integration Documentation

1. **docs/decisions/ADR-XXX-error-reporting.md**
   - Architecture Decision Record
   - Why this approach was chosen
   - Alternatives considered
   - Trade-offs and implications

## Alternatives Considered

### Alternative 1: Third-Party Error Tracking (e.g., Sentry)

**Pros:**
- More features (automatic error capture, source maps, releases)
- Less development effort
- Professional support

**Cons:**
- External dependency and cost
- Less control over data
- Requires ongoing subscription
- Not user-initiated reports

**Decision:** Rejected for initial implementation, but could integrate later

### Alternative 2: Email-Based Reporting

**Pros:**
- Simpler implementation
- No GitLab API dependency
- Users familiar with email

**Cons:**
- Manual triage required
- No automatic formatting
- Harder to track and organize
- No direct link to codebase

**Decision:** Rejected as primary approach, but useful as fallback

### Alternative 3: Built-in Drupal Logging

**Pros:**
- No external dependencies
- Already integrated with Drupal
- Fast and reliable

**Cons:**
- Not visible to external developers
- No user-friendly interface
- Requires server access to view
- Not collaborative

**Decision:** Complement with this module, not replace

### Alternative 4: Forum/Support Tickets

**Pros:**
- User-friendly
- Encourages community support
- Searchable by other users

**Cons:**
- Not directly linked to code
- Requires manual developer follow-up
- Less structured data
- Slower for developers

**Decision:** Use for general support, not bug reporting

## Conclusion

The AVC Error Reporting Module will significantly improve the bug reporting workflow by:

1. **Reducing Friction:** One-click access to reporting form
2. **Improving Quality:** Auto-captured context and optional recording
3. **Accelerating Development:** Direct GitLab integration with formatted issues
4. **Preventing Abuse:** Rate limiting and security measures
5. **Empowering Users:** Transparency via issue links and follow-up

The phased implementation allows for iterative development:
- **Phase 1 (MVP):** Immediate value with basic reporting
- **Phase 2 (Enhanced):** Advanced debugging with error capture
- **Phase 3 (Admin):** Configuration and customization
- **Phase 4 (Security):** Production hardening
- **Phase 5 (Testing):** Quality assurance
- **Phase 6 (Documentation):** Deployment readiness

**Next Steps:**
1. Review and approve proposal
2. Set up GitLab project board with milestones
3. Configure test environment and test GitLab repo
4. Begin Phase 1 implementation (tasks 1.1.1 - 1.7.6)

## Appendix: Configuration Examples

### GitLab API Token Setup

```bash
# On GitLab (git.nwpcode.org):
# 1. Navigate to AVC project
# 2. Settings > Access Tokens
# 3. Create token with:
#    - Name: "AVC Error Reporting Module"
#    - Role: Developer
#    - Scopes: api
#    - Expiration: 1 year from now

# In .secrets.yml:
gitlab:
  url: "https://git.nwpcode.org"
  api_token: "glpat-xxxxxxxxxxxxxxxxxxxx"
  project_id: "avc/avc"
```

### Module Configuration

```bash
# Enable module
drush en avc_error_report -y

# Configure via drush
drush config:set avc_error_report.settings gitlab_url 'https://git.nwpcode.org' -y
drush config:set avc_error_report.settings project_id 'avc/avc' -y
drush config:set avc_error_report.settings rate_limit_max 5 -y

# Or via admin UI: /admin/config/system/avc-error-report
```

### Testing Configuration

```bash
# Set up test GitLab repo for development
drush config:set avc_error_report.settings project_id 'avc/avc-test' -y

# Disable rate limiting for testing
drush config:set avc_error_report.settings rate_limit_max 9999 -y

# Run tests
drush test avc_error_report
```

---

**Document Version:** 2.0
**Last Updated:** 2026-01-13
**Status:** PROPOSED - Awaiting approval
