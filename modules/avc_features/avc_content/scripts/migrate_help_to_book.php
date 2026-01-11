<?php

/**
 * @file
 * Migration script for help content from ContentController to Book nodes.
 *
 * Run with:
 *   ddev drush php:script profiles/custom/avc/modules/avc_features/avc_content/scripts/migrate_help_to_book.php
 *
 * This script:
 * - Enables 'book' content type for workflow assignment
 * - Creates Editorial Documentation workflow template
 * - Creates book pages with content from ContentController
 * - Sets up path aliases to preserve URLs
 */

use Drupal\node\Entity\Node;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$entity_type_manager = \Drupal::entityTypeManager();

echo "=== Help Content Migration to Book ===\n\n";

// ============================================================================
// Step 1: Enable 'book' content type for workflow assignment
// ============================================================================
echo "Step 1: Enabling 'book' for workflow assignment...\n";

$config = \Drupal::configFactory()->getEditable('workflow_assignment.settings');
$enabled_types = $config->get('enabled_content_types') ?: [];

if (!in_array('book', $enabled_types)) {
  $enabled_types[] = 'book';
  $config->set('enabled_content_types', $enabled_types)->save();
  echo "  Added 'book' to enabled content types.\n";

  // Add the workflow field to book content type.
  $field_storage = FieldStorageConfig::loadByName('node', 'field_workflow_list');
  if (!$field_storage) {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_workflow_list',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'workflow_assignment'],
      'cardinality' => -1,
    ]);
    $field_storage->save();
    echo "  Created field storage: field_workflow_list\n";
  }

  $field_config = FieldConfig::loadByName('node', 'book', 'field_workflow_list');
  if (!$field_config) {
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'book',
      'label' => 'Workflow Assignments',
      'description' => 'Workflow assignments for this content.',
      'settings' => ['handler' => 'default', 'handler_settings' => []],
    ]);
    $field_config->save();
    echo "  Added field_workflow_list to book content type.\n";
  }
}
else {
  echo "  'book' already enabled for workflow.\n";
}

// ============================================================================
// Step 2: Create Editorial Documentation workflow lists and template
// ============================================================================
echo "\nStep 2: Creating Editorial Documentation workflow...\n";

$workflow_list_storage = $entity_type_manager->getStorage('workflow_list');

// Create workflow lists for Editorial workflow.
$editorial_lists = [
  [
    'id' => 'doc_draft_review',
    'label' => 'Documentation Draft Review',
    'description' => 'Initial review of documentation for completeness and accuracy.',
    'assigned_type' => 'user',
    'assigned_id' => 1,
    'comments' => 'Check content structure, clarity, and completeness.',
  ],
  [
    'id' => 'doc_editor_review',
    'label' => 'Editor Review',
    'description' => 'Editorial review for style, grammar, and consistency.',
    'assigned_type' => 'user',
    'assigned_id' => 1,
    'comments' => 'Focus on readability, formatting, and AVC style guide compliance.',
  ],
  [
    'id' => 'doc_final_approval',
    'label' => 'Final Approval',
    'description' => 'Final sign-off before publication.',
    'assigned_type' => 'user',
    'assigned_id' => 1,
    'comments' => 'Executive approval for public-facing documentation.',
  ],
  [
    'id' => 'doc_publish',
    'label' => 'Publish Documentation',
    'description' => 'Documentation ready for publication.',
    'assigned_type' => 'destination',
    'assigned_id' => 1, // Will be updated if destination vocab exists.
    'comments' => 'Publish to public help section.',
  ],
];

// Check for destination vocabulary.
$dest_storage = $entity_type_manager->getStorage('taxonomy_term');
$public_dest = $dest_storage->loadByProperties([
  'vid' => 'destination_locations',
  'name' => 'Public Website',
]);
if (!empty($public_dest)) {
  $editorial_lists[3]['assigned_id'] = reset($public_dest)->id();
}

$created_lists = [];
foreach ($editorial_lists as $list_data) {
  $existing = $workflow_list_storage->load($list_data['id']);
  if ($existing) {
    echo "  Workflow list exists: {$list_data['label']}\n";
    $created_lists[$list_data['id']] = $existing;
    continue;
  }

  $workflow_list = $workflow_list_storage->create($list_data);
  $workflow_list->save();
  $created_lists[$list_data['id']] = $workflow_list;
  echo "  Created: {$list_data['label']} ({$list_data['id']})\n";
}

// Create the Editorial Documentation workflow template.
$template_storage = $entity_type_manager->getStorage('workflow_template');
$existing_template = $template_storage->loadByProperties(['name' => 'Editorial Documentation Workflow']);

if (empty($existing_template)) {
  $template = $template_storage->create([
    'name' => 'Editorial Documentation Workflow',
    'description' => 'Full editorial workflow for help documentation: Draft Review, Editor Review, Final Approval, then Publish.',
    'uid' => 1,
  ]);
  $workflow_refs = [];
  foreach (['doc_draft_review', 'doc_editor_review', 'doc_final_approval', 'doc_publish'] as $list_id) {
    if (isset($created_lists[$list_id])) {
      $workflow_refs[] = ['target_id' => $list_id];
    }
  }
  $template->set('template_workflows', $workflow_refs);
  $template->save();
  echo "  Created template: Editorial Documentation Workflow\n";
}
else {
  echo "  Template exists: Editorial Documentation Workflow\n";
}

// ============================================================================
// Step 3: Define help content (extracted from ContentController.php)
// ============================================================================
echo "\nStep 3: Preparing help content...\n";

$help_pages = [
  // Root book page.
  [
    'title' => 'AV Commons Help',
    'alias' => '/help',
    'parent' => NULL,
    'weight' => 0,
    'body' => '<div class="help-page">
<div class="help-page__header">
  <p class="help-page__subtitle">Welcome to the AV Commons Help Center. Find guides, tutorials, and answers to common questions about using the platform.</p>
</div>

<div class="help-section">
  <h2>Quick Links</h2>
  <ul>
    <li><a href="/help/getting-started">Getting Started</a> - New to AV Commons? Start here.</li>
    <li><a href="/help/user-guide">User Guide</a> - Comprehensive platform documentation.</li>
    <li><a href="/help/faq">FAQ</a> - Frequently asked questions.</li>
  </ul>
</div>

<div class="help-section">
  <h2>Popular Topics</h2>
  <div class="feature-list">
    <div class="feature-list__item">
      <strong><a href="/help/dashboard">Dashboard</a></strong><br>
      Understanding your personal workspace and worklist.
    </div>
    <div class="feature-list__item">
      <strong><a href="/help/assets">Assets</a></strong><br>
      Working with Projects, Documents, and Resources.
    </div>
    <div class="feature-list__item">
      <strong><a href="/help/workflow">Workflow</a></strong><br>
      Understanding the content approval process.
    </div>
    <div class="feature-list__item">
      <strong><a href="/help/guilds">Guilds</a></strong><br>
      Skill-based groups with mentorship and scoring.
    </div>
  </div>
</div>

<div class="info-box info-box--tip">
  <div class="info-box__title">Need Help?</div>
  <p>Can\'t find what you\'re looking for? <a href="/contact">Contact us</a> for assistance.</p>
</div>
</div>',
  ],

  // Getting Started.
  [
    'title' => 'Getting Started with AV Commons',
    'alias' => '/help/getting-started',
    'parent' => 'AV Commons Help',
    'weight' => 0,
    'body' => '<div class="help-page">
<div class="help-page__header">
  <p class="help-page__subtitle">Welcome! This guide will help you get up and running with AV Commons in just a few minutes.</p>
</div>

<div class="help-section">
  <div class="steps">
    <div class="steps__item">
      <div class="steps__item-title">Complete Your Profile</div>
      <div class="steps__item-content">After registering, visit your profile page to add your information. Include your AV level (Disciple, Aspirant, or Sojourner) and accept the public domain contribution acknowledgment.</div>
    </div>
    <div class="steps__item">
      <div class="steps__item-title">Find Your Dashboard</div>
      <div class="steps__item-content">Click on "My Dashboard" in the menu to see your personal workspace. This shows all your current assignments, pending tasks, and activity across groups.</div>
    </div>
    <div class="steps__item">
      <div class="steps__item-title">Join Groups</div>
      <div class="steps__item-content">Browse available groups and request to join those relevant to your interests. Groups are where collaborative work happens.</div>
    </div>
    <div class="steps__item">
      <div class="steps__item-title">Set Notification Preferences</div>
      <div class="steps__item-content">Configure how you want to receive notifications. Choose between immediate, daily digest, weekly digest, or no notifications for different event types.</div>
    </div>
    <div class="steps__item">
      <div class="steps__item-title">Start Contributing</div>
      <div class="steps__item-content">Create your first document or pick up a task from your worklist. Every contribution helps build our shared resources.</div>
    </div>
  </div>

  <div class="info-box info-box--tip">
    <div class="info-box__title">Tip</div>
    <p>Not sure where to start? Check your Dashboard for any pending assignments, or browse existing Projects to see what work is in progress.</p>
  </div>
</div>

<div class="card card__header--primary">
  <div class="card__header card__header--primary">
    <h3 class="card__title">Ready to Learn More?</h3>
  </div>
  <div class="card__body">
    <p>Explore our detailed guides:</p>
    <ul>
      <li><a href="/help/dashboard">Understanding Your Dashboard</a></li>
      <li><a href="/help/assets">Working with Assets (Projects, Documents, Resources)</a></li>
      <li><a href="/help/workflow">The Workflow System</a></li>
      <li><a href="/help/guilds">Joining and Participating in Guilds</a></li>
    </ul>
  </div>
</div>
</div>',
  ],

  // User Guide (parent for detailed sections).
  [
    'title' => 'User Guide',
    'alias' => '/help/user-guide',
    'parent' => 'AV Commons Help',
    'weight' => 1,
    'body' => '<div class="help-page">
<div class="help-page__header">
  <p class="help-page__subtitle">Comprehensive documentation for using AV Commons effectively.</p>
</div>

<div class="help-section">
  <h2>Platform Overview</h2>
  <p>AV Commons is a community collaboration site. It enables our community to:</p>
  <ul>
    <li><strong>Collaborate on Projects, Documents, and Resources</strong> - Work together on shared content with proper version control and approval workflows.</li>
    <li><strong>Participate in skill-based Guilds</strong> - Join specialized groups with mentorship, scoring, and endorsement features.</li>
    <li><strong>Track workflow assignments</strong> - Know what needs your attention with personalized dashboards and worklists.</li>
    <li><strong>Receive customizable notifications</strong> - Stay informed without inbox overload through digest options.</li>
  </ul>
</div>

<div class="help-section">
  <h2>Detailed Guides</h2>
  <p>Explore each topic in depth:</p>
  <ul>
    <li><a href="/help/dashboard">Your Dashboard</a> - Personal workspace and worklist</li>
    <li><a href="/help/assets">Working with Assets</a> - Projects, Documents, Resources</li>
    <li><a href="/help/workflow">Workflow System</a> - Content approval process</li>
    <li><a href="/help/guilds">Guild System</a> - Skill-based groups with mentorship</li>
    <li><a href="/help/notifications">Notification Settings</a> - Customize your alerts</li>
  </ul>
</div>
</div>',
  ],

  // Dashboard Help.
  [
    'title' => 'Understanding Your Dashboard',
    'alias' => '/help/dashboard',
    'parent' => 'User Guide',
    'weight' => 0,
    'body' => '<div class="help-page">
<div class="help-page__header">
  <p class="help-page__subtitle">Your dashboard is your personal command center in AV Commons.</p>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Accessing Your Dashboard</h2>
  </div>
  <div class="help-section__content">
    <p>Click on your username in the top right corner, then select "My Dashboard". Alternatively, go directly to <code>/user/[your-id]/dashboard</code>.</p>
  </div>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Dashboard Sections</h2>
  </div>
  <div class="help-section__content">
    <h3>Your Worklist</h3>
    <p>This shows all tasks currently assigned to you across all groups. Items are sorted by priority and due date. Each item shows:</p>
    <ul>
      <li>The asset name (click to view)</li>
      <li>Current workflow stage</li>
      <li>Which group it belongs to</li>
      <li>When it was assigned</li>
    </ul>

    <h3>Group Worklists</h3>
    <p>See pending work for each group you belong to. This helps you understand the overall workload and find items to work on.</p>

    <h3>Quick Stats</h3>
    <p>At a glance view of your activity:</p>
    <ul>
      <li>Pending assignments</li>
      <li>Completed this week</li>
      <li>Groups you belong to</li>
    </ul>
  </div>
</div>

<div class="info-box info-box--tip">
  <div class="info-box__title">Pro Tip</div>
  <p>Check your dashboard daily to stay on top of assignments. Items requiring urgent attention appear at the top.</p>
</div>
</div>',
  ],

  // Assets Help.
  [
    'title' => 'Working with Assets',
    'alias' => '/help/assets',
    'parent' => 'User Guide',
    'weight' => 1,
    'body' => '<div class="help-page">
<div class="help-page__header">
  <p class="help-page__subtitle">Assets are the core content items in AV Commons. Learn how to create and manage them.</p>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Asset Types</h2>
  </div>
  <div class="help-section__content">
    <div class="feature-list">
      <div class="feature-list__item">
        <div class="feature-list__text">
          <strong>Projects</strong><br>
          Large initiatives that can contain other assets. Use projects to organize related documents and resources.
        </div>
      </div>
      <div class="feature-list__item">
        <div class="feature-list__text">
          <strong>Documents</strong><br>
          Text-based content that goes through the workflow system. Documents can be articles, guides, policies, etc.
        </div>
      </div>
      <div class="feature-list__item">
        <div class="feature-list__text">
          <strong>Resources</strong><br>
          External links, references, and files. Resources are simpler assets for storing useful materials.
        </div>
      </div>
    </div>
  </div>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Creating an Asset</h2>
  </div>
  <div class="help-section__content">
    <ol class="steps">
      <li class="steps__item">
        <div class="steps__item-title">Navigate to Your Group</div>
        <div class="steps__item-content">Assets are created within groups. Go to the group where you want to create the asset.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Click "Create Content"</div>
        <div class="steps__item-content">Select the type of asset you want to create (Project, Document, or Resource).</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Fill in Details</div>
        <div class="steps__item-content">Add a title, description, and any other required fields. An asset number is automatically generated.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Select Workflow Template</div>
        <div class="steps__item-content">Choose the appropriate workflow template. This determines which approval stages the asset will go through.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Save</div>
        <div class="steps__item-content">The asset is created in Draft status. You can continue editing before submitting for review.</div>
      </li>
    </ol>
  </div>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Asset Actions</h2>
  </div>
  <div class="help-section__content">
    <table class="role-table">
      <thead>
        <tr>
          <th>Action</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>Check Workflow</strong></td>
          <td>View the current workflow status and see all stages.</td>
        </tr>
        <tr>
          <td><strong>Process</strong></td>
          <td>Move the asset to the next workflow stage (if you have permission).</td>
        </tr>
        <tr>
          <td><strong>Resend Notification</strong></td>
          <td>Send a reminder to the current assignee.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="info-box info-box--note">
  <div class="info-box__title">Note</div>
  <p>Assets inherit their access permissions from the group they belong to. Make sure you are in the correct group before creating content.</p>
</div>
</div>',
  ],

  // Workflow Help.
  [
    'title' => 'Understanding the Workflow System',
    'alias' => '/help/workflow',
    'parent' => 'User Guide',
    'weight' => 2,
    'body' => '<div class="help-page">
<div class="help-page__header">
  <p class="help-page__subtitle">The workflow system ensures quality control and proper approval for all content.</p>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">How Workflow Works</h2>
  </div>
  <div class="help-section__content">
    <p>Every document and project moves through a series of stages before being published. Each stage has:</p>
    <ul>
      <li>An assigned reviewer or approver</li>
      <li>Specific criteria for approval</li>
      <li>The ability to add comments</li>
    </ul>

    <h3>Common Workflow Stages</h3>
    <table class="role-table">
      <thead>
        <tr>
          <th>Stage</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="workflow-status workflow-status--draft">Draft</span></td>
          <td>Initial creation. Author is working on the content.</td>
        </tr>
        <tr>
          <td><span class="workflow-status workflow-status--review">Review</span></td>
          <td>Submitted for review. Reviewer checks for quality and accuracy.</td>
        </tr>
        <tr>
          <td><span class="workflow-status workflow-status--approved">Approved</span></td>
          <td>Content has been approved and is ready for publication.</td>
        </tr>
        <tr>
          <td><span class="workflow-status workflow-status--published">Published</span></td>
          <td>Content is live and visible to appropriate audiences.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Processing Workflow Stages</h2>
  </div>
  <div class="help-section__content">
    <h3>When You Are the Assignee</h3>
    <ol class="steps">
      <li class="steps__item">
        <div class="steps__item-title">Review the Content</div>
        <div class="steps__item-content">Carefully read through the document or project.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Click "Process"</div>
        <div class="steps__item-content">Open the workflow processing form.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Make Your Decision</div>
        <div class="steps__item-content">Approve to move forward, or reject to send back for revisions.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Add Comments</div>
        <div class="steps__item-content">Always add helpful comments, especially when rejecting.</div>
      </li>
    </ol>
  </div>
</div>

<div class="info-box info-box--important">
  <div class="info-box__title">Important</div>
  <p>When you have pending workflow assignments, they appear in your Dashboard worklist. Please process them promptly to avoid delays.</p>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Workflow Templates</h2>
  </div>
  <div class="help-section__content">
    <p>Different types of content use different workflow templates. Administrators configure these templates based on the type and importance of the content. Common templates include:</p>
    <ul>
      <li><strong>Simple Review:</strong> Draft to Review to Published</li>
      <li><strong>Editorial:</strong> Draft to Editor Review to Final Approval to Published</li>
      <li><strong>Committee:</strong> Draft to Committee Review to Leadership Approval to Published</li>
    </ul>
  </div>
</div>
</div>',
  ],

  // Guilds Help.
  [
    'title' => 'The Guild System',
    'alias' => '/help/guilds',
    'parent' => 'User Guide',
    'weight' => 3,
    'body' => '<div class="help-page">
<div class="help-page__header">
  <p class="help-page__subtitle">Guilds are specialized skill-based groups with mentorship, scoring, and endorsements.</p>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">What Makes Guilds Special</h2>
  </div>
  <div class="help-section__content">
    <p>Unlike regular groups, Guilds have additional features designed for skill development and quality assurance:</p>
    <ul>
      <li><strong>Role-based Membership:</strong> Members progress from Junior to Endorsed to Mentor</li>
      <li><strong>Ratification:</strong> Junior members\' work is reviewed by Mentors</li>
      <li><strong>Skill Endorsements:</strong> Members can endorse each other for specific skills</li>
      <li><strong>Scoring &amp; Leaderboard:</strong> Track contributions and recognize top contributors</li>
    </ul>
  </div>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Guild Roles</h2>
  </div>
  <div class="help-section__content">
    <table class="role-table">
      <thead>
        <tr>
          <th>Role</th>
          <th>Description</th>
          <th>Can Ratify</th>
          <th>Can Endorse</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="role-badge role-badge--junior">Junior</span></td>
          <td>New members learning the ropes</td>
          <td class="cross">-</td>
          <td class="cross">-</td>
        </tr>
        <tr>
          <td><span class="role-badge role-badge--endorsed">Endorsed</span></td>
          <td>Proven members who work independently</td>
          <td class="cross">-</td>
          <td class="check">Yes</td>
        </tr>
        <tr>
          <td><span class="role-badge role-badge--mentor">Mentor</span></td>
          <td>Experienced members who train others</td>
          <td class="check">Yes</td>
          <td class="check">Yes</td>
        </tr>
        <tr>
          <td><span class="role-badge role-badge--admin">Admin</span></td>
          <td>Full guild administration</td>
          <td class="check">Yes</td>
          <td class="check">Yes</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Ratification Process</h2>
  </div>
  <div class="help-section__content">
    <p>When a Junior member completes work, it must be ratified by a Mentor before being considered complete.</p>

    <h3>For Juniors</h3>
    <ol class="steps">
      <li class="steps__item">
        <div class="steps__item-title">Complete Your Work</div>
        <div class="steps__item-content">Finish the document, task, or contribution.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Submit for Ratification</div>
        <div class="steps__item-content">Mark the work as complete. It enters the ratification queue.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Wait for Review</div>
        <div class="steps__item-content">A Mentor will review your work and either approve or provide feedback.</div>
      </li>
    </ol>

    <h3>For Mentors</h3>
    <ol class="steps">
      <li class="steps__item">
        <div class="steps__item-title">Check the Queue</div>
        <div class="steps__item-content">Visit the Guild\'s Ratification Queue to see pending items.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Claim an Item</div>
        <div class="steps__item-content">Click "Claim" to take responsibility for reviewing the work.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Review and Ratify</div>
        <div class="steps__item-content">Approve if quality meets standards, or reject with constructive feedback.</div>
      </li>
    </ol>
  </div>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Scoring &amp; Leaderboard</h2>
  </div>
  <div class="help-section__content">
    <p>Guilds track member contributions through a scoring system. Points are earned by:</p>
    <ul>
      <li>Completing tasks</li>
      <li>Having work ratified</li>
      <li>Ratifying others\' work (Mentors)</li>
      <li>Receiving endorsements</li>
      <li>Active participation</li>
    </ul>
    <p>The Leaderboard shows the top contributors in the guild. View it by clicking "Leaderboard" on the guild page.</p>
  </div>
</div>

<div class="info-box info-box--tip">
  <div class="info-box__title">Tip for Advancement</div>
  <p>Focus on quality over quantity. Consistent, high-quality contributions are the best way to progress from Junior to Endorsed status.</p>
</div>
</div>',
  ],

  // Notifications Help.
  [
    'title' => 'Notification Settings',
    'alias' => '/help/notifications',
    'parent' => 'User Guide',
    'weight' => 4,
    'body' => '<div class="help-page">
<div class="help-page__header">
  <p class="help-page__subtitle">Control how and when you receive notifications from AV Commons.</p>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Notification Options</h2>
  </div>
  <div class="help-section__content">
    <p>For each type of notification, you can choose how you want to be notified:</p>

    <table class="role-table">
      <thead>
        <tr>
          <th>Option</th>
          <th>Code</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>Immediate</strong></td>
          <td><code>n</code></td>
          <td>Receive an email as soon as the event occurs.</td>
        </tr>
        <tr>
          <td><strong>Daily Digest</strong></td>
          <td><code>d</code></td>
          <td>Receive a summary email once per day (if there are notifications).</td>
        </tr>
        <tr>
          <td><strong>Weekly Digest</strong></td>
          <td><code>w</code></td>
          <td>Receive a summary email once per week.</td>
        </tr>
        <tr>
          <td><strong>None</strong></td>
          <td><code>x</code></td>
          <td>Do not send email notifications for this event type.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Notification Types</h2>
  </div>
  <div class="help-section__content">
    <p>You can configure preferences for different types of events:</p>
    <ul>
      <li><strong>Workflow Assignments:</strong> When you are assigned to review or approve content.</li>
      <li><strong>Workflow Updates:</strong> When content you created moves through workflow stages.</li>
      <li><strong>Group Activity:</strong> New content or discussions in your groups.</li>
      <li><strong>Guild Updates:</strong> Ratification results, endorsements, and guild announcements.</li>
      <li><strong>Mentions:</strong> When someone mentions you in a comment or discussion.</li>
    </ul>
  </div>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Configuring Your Preferences</h2>
  </div>
  <div class="help-section__content">
    <ol class="steps">
      <li class="steps__item">
        <div class="steps__item-title">Go to Notification Preferences</div>
        <div class="steps__item-content">Click your username, then "Notification Preferences", or go directly to /user/[your-id]/notification-preferences.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Review Each Category</div>
        <div class="steps__item-content">Go through each notification type and select your preferred delivery method.</div>
      </li>
      <li class="steps__item">
        <div class="steps__item-title">Save Changes</div>
        <div class="steps__item-content">Click "Save" to apply your preferences.</div>
      </li>
    </ol>
  </div>
</div>

<div class="info-box info-box--important">
  <div class="info-box__title">Recommended Settings</div>
  <p>We recommend keeping "Workflow Assignments" set to Immediate (n) so you are notified promptly when you have work to review. This helps prevent bottlenecks in the approval process.</p>
</div>

<div class="help-section">
  <div class="help-section__header">
    <h2 class="help-section__title">Digest Timing</h2>
  </div>
  <div class="help-section__content">
    <ul>
      <li><strong>Daily Digest:</strong> Sent each morning around 6:00 AM local time.</li>
      <li><strong>Weekly Digest:</strong> Sent Monday morning around 6:00 AM local time.</li>
    </ul>
    <p>If there are no notifications to include, the digest email is not sent.</p>
  </div>
</div>
</div>',
  ],

  // FAQ.
  [
    'title' => 'Frequently Asked Questions',
    'alias' => '/help/faq',
    'parent' => 'AV Commons Help',
    'weight' => 2,
    'body' => '<div class="help-page">
<div class="faq">
  <div class="faq__item">
    <div class="faq__question" tabindex="0">What is AV Commons?</div>
    <div class="faq__answer">AV Commons is a community collaboration site. It allows members to work together on projects, documents, and resources with proper workflow management and quality control.</div>
  </div>

  <div class="faq__item">
    <div class="faq__question" tabindex="0">Who can join AV Commons?</div>
    <div class="faq__answer">AV Commons is open to all members, including Disciples, Aspirants, and Sojourners. Each level has access to appropriate features and groups.</div>
  </div>

  <div class="faq__item">
    <div class="faq__question" tabindex="0">What are the different AV Levels?</div>
    <div class="faq__answer"><strong>Disciple:</strong> Active members committed to the community.<br><strong>Aspirant:</strong> Those discerning a deeper commitment.<br><strong>Sojourner:</strong> Friends and supporters of the community.</div>
  </div>

  <div class="faq__item">
    <div class="faq__question" tabindex="0">What is the difference between Groups and Guilds?</div>
    <div class="faq__answer">Groups are general collaborative spaces for projects and teams. Guilds are specialized skill-based groups with mentorship, scoring, and endorsement features. Guilds have specific roles (Junior, Endorsed, Mentor, Admin) and track member progress.</div>
  </div>

  <div class="faq__item">
    <div class="faq__question" tabindex="0">How does the workflow system work?</div>
    <div class="faq__answer">Documents and projects go through defined stages (like Draft, Review, Approval, Published). Each stage can have assigned reviewers who must approve the work before it moves forward. This ensures quality control.</div>
  </div>

  <div class="faq__item">
    <div class="faq__question" tabindex="0">What happens to my contributions?</div>
    <div class="faq__answer">All contributions to AV Commons are licensed as public domain. By contributing, you acknowledge that your work can be used by AV Commons without individual attribution.</div>
  </div>

  <div class="faq__item">
    <div class="faq__question" tabindex="0">How do notifications work?</div>
    <div class="faq__answer">You can configure notification preferences for different event types. Options include: Immediate (get notified right away), Daily Digest (bundled once per day), Weekly Digest (bundled once per week), or None.</div>
  </div>

  <div class="faq__item">
    <div class="faq__question" tabindex="0">What is ratification in Guilds?</div>
    <div class="faq__answer">Junior members in Guilds need their work ratified (approved) by a Mentor before it is considered complete. This mentorship system helps maintain quality and train new members.</div>
  </div>

  <div class="faq__item">
    <div class="faq__question" tabindex="0">How do I earn points in a Guild?</div>
    <div class="faq__answer">Points are earned through various activities: completing tasks, having work ratified, endorsing other members, and participating in guild activities. Points are tracked on the guild leaderboard.</div>
  </div>

  <div class="faq__item">
    <div class="faq__question" tabindex="0">Can I be in multiple Groups or Guilds?</div>
    <div class="faq__answer">Yes! You can be a member of multiple groups and guilds simultaneously. Each has its own dashboard and worklist.</div>
  </div>
</div>
</div>',
  ],

  // About.
  [
    'title' => 'About AV Commons',
    'alias' => '/about',
    'parent' => 'AV Commons Help',
    'weight' => 3,
    'body' => '<div class="help-page">
<div class="help-section">
  <p class="lead">AV Commons is a community collaboration site designed to help members work together on projects, documents, and resources.</p>

  <h2>Our Mission</h2>
  <p>Our mission is to provide a structured yet flexible environment where members at all levels can contribute through collaborative work.</p>

  <h2>Key Features</h2>
  <ul>
    <li><strong>Projects, Documents, and Resources</strong> - Organize and collaborate on shared content</li>
    <li><strong>Workflow System</strong> - Ensure quality through structured approval processes</li>
    <li><strong>Groups</strong> - Collaborate with teams on specific initiatives</li>
    <li><strong>Guilds</strong> - Develop skills through mentorship and structured progression</li>
    <li><strong>Notifications</strong> - Stay informed with customizable alerts</li>
  </ul>

  <h2>AV Levels</h2>
  <p>Members participate at different levels:</p>
  <ul>
    <li><strong>Disciple</strong> - Active members committed to the community</li>
    <li><strong>Aspirant</strong> - Those discerning a deeper commitment</li>
    <li><strong>Sojourner</strong> - Friends and supporters of the community</li>
  </ul>

  <h2>Public Domain Contributions</h2>
  <p>All contributions to AV Commons are licensed as public domain. By contributing, you acknowledge that your work can be used freely by the community.</p>
</div>
</div>',
  ],

  // Contact.
  [
    'title' => 'Contact Us',
    'alias' => '/contact',
    'parent' => 'AV Commons Help',
    'weight' => 4,
    'body' => '<div class="contact-page">
<p class="lead">Have questions about AV Commons? We are here to help.</p>

<div class="card mb-8">
  <div class="card__header">
    <h3 class="card__title">General Inquiries</h3>
  </div>
  <div class="card__body">
    <p>For general questions about AV Commons:</p>
    <p><strong>Email:</strong> <a href="mailto:contact@apostoliviae.org">contact@apostoliviae.org</a></p>
  </div>
</div>

<div class="card mb-8">
  <div class="card__header">
    <h3 class="card__title">Technical Support</h3>
  </div>
  <div class="card__body">
    <p>For technical issues with the platform:</p>
    <p><strong>Email:</strong> <a href="mailto:rjzaar@gmail.com">rjzaar@gmail.com</a></p>
    <p>Please include a description of the issue and any error messages you see.</p>
  </div>
</div>
</div>',
  ],
];

echo "  Prepared " . count($help_pages) . " help pages.\n";

// ============================================================================
// Step 4: Create book nodes with hierarchy
// ============================================================================
echo "\nStep 4: Creating book pages...\n";

$node_storage = $entity_type_manager->getStorage('node');
$book_manager = \Drupal::service('book.manager');

// Map to track created pages by title.
$page_map = [];
$book_id = NULL;

foreach ($help_pages as $page) {
  // Check if page already exists by alias.
  $path_alias_manager = \Drupal::service('path_alias.manager');
  try {
    $existing_path = $path_alias_manager->getPathByAlias($page['alias']);
    if (strpos($existing_path, '/node/') === 0) {
      $nid = str_replace('/node/', '', $existing_path);
      $existing_node = $node_storage->load($nid);
      if ($existing_node) {
        echo "  Exists: {$page['title']} ({$page['alias']})\n";
        $page_map[$page['title']] = $existing_node->id();
        if ($page['parent'] === NULL) {
          $book_id = $existing_node->id();
        }
        continue;
      }
    }
  }
  catch (\Exception $e) {
    // Alias doesn't exist, continue to create.
  }

  // Create the node.
  $node_data = [
    'type' => 'book',
    'title' => $page['title'],
    'body' => [
      'value' => $page['body'],
      'format' => 'full_html',
    ],
    'status' => 1,
    'uid' => 1,
    'path' => [
      'alias' => $page['alias'],
      'pathauto' => FALSE,
    ],
  ];

  // Set book hierarchy.
  if ($page['parent'] === NULL) {
    // Root book page.
    $node_data['book'] = [
      'bid' => 'new',
      'weight' => $page['weight'],
    ];
  }
  else {
    // Child page.
    $parent_nid = $page_map[$page['parent']] ?? NULL;
    if ($parent_nid) {
      $node_data['book'] = [
        'bid' => $book_id,
        'pid' => $parent_nid,
        'weight' => $page['weight'],
      ];
    }
  }

  $node = $node_storage->create($node_data);
  $node->save();

  // Track the node.
  $page_map[$page['title']] = $node->id();

  // If this is the root, save the book ID.
  if ($page['parent'] === NULL) {
    $book_id = $node->id();
    // Update the book bid to point to itself.
    $node->book['bid'] = $node->id();
    $node->save();
  }

  echo "  Created: {$page['title']} (nid: {$node->id()}, alias: {$page['alias']})\n";
}

// ============================================================================
// Summary
// ============================================================================
echo "\n=== Migration Complete ===\n";
echo "Book pages created: " . count($page_map) . "\n";
echo "Book root ID: $book_id\n";
echo "\nURLs available:\n";
foreach ($help_pages as $page) {
  echo "  {$page['alias']}\n";
}
echo "\nNext steps:\n";
echo "  1. Verify pages at the URLs above\n";
echo "  2. Test book navigation (prev/next links)\n";
echo "  3. Check workflow tab on book pages\n";
echo "  4. Update menu links in avc_content.links.menu.yml\n";
echo "  5. Remove old routes from avc_content.routing.yml\n";
echo "  6. Clear caches: ddev drush cr\n";
