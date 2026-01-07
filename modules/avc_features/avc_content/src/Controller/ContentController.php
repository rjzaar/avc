<?php

namespace Drupal\avc_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for AVC Content pages.
 */
class ContentController extends ControllerBase {

  /**
   * About page.
   */
  public function about() {
    return [
      '#theme' => 'avc_about',
      '#content' => [
        'intro' => $this->t('AV Commons is a community collaboration site designed to help members work together on projects, documents, and resources.'),
        'mission' => $this->t('Our mission is to provide a structured yet flexible environment where members at all levels can contribute through collaborative work.'),
      ],
      '#attached' => [
        'library' => ['avc_theme/global'],
      ],
    ];
  }

  /**
   * Contact page.
   */
  public function contact() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['contact-page']],
    ];

    $build['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Have questions about AV Commons? We are here to help.'),
      '#attributes' => ['class' => ['lead']],
    ];

    $build['general'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-8']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['card__header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('General Inquiries'),
          '#attributes' => ['class' => ['card__title']],
        ],
      ],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card__body']],
        'content' => [
          '#markup' => '<p>' . $this->t('For general questions about AV Commons:') . '</p>' .
            '<p><strong>' . $this->t('Email:') . '</strong> <a href="mailto:contact@apostoliviae.org">contact@apostoliviae.org</a></p>',
        ],
      ],
    ];

    $build['technical'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-8']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['card__header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Technical Support'),
          '#attributes' => ['class' => ['card__title']],
        ],
      ],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card__body']],
        'content' => [
          '#markup' => '<p>' . $this->t('For technical issues with the platform:') . '</p>' .
            '<p><strong>' . $this->t('Email:') . '</strong> <a href="mailto:rjzaar@gmail.com">rjzaar@gmail.com</a></p>' .
            '<p>' . $this->t('Please include a description of the issue and any error messages you see.') . '</p>',
        ],
      ],
    ];

    return $build;
  }

  /**
   * User guide page.
   */
  public function userGuide() {
    $sections = [
      'overview' => [
        'title' => $this->t('Platform Overview'),
        'content' => $this->getOverviewContent(),
      ],
      'dashboard' => [
        'title' => $this->t('Your Dashboard'),
        'content' => $this->getDashboardContent(),
      ],
      'assets' => [
        'title' => $this->t('Working with Assets'),
        'content' => $this->getAssetsContent(),
      ],
      'workflow' => [
        'title' => $this->t('Workflow System'),
        'content' => $this->getWorkflowContent(),
      ],
      'groups' => [
        'title' => $this->t('Groups & Collaboration'),
        'content' => $this->getGroupsContent(),
      ],
      'guilds' => [
        'title' => $this->t('Guild System'),
        'content' => $this->getGuildsContent(),
      ],
      'notifications' => [
        'title' => $this->t('Notification Settings'),
        'content' => $this->getNotificationsContent(),
      ],
    ];

    return [
      '#theme' => 'avc_user_guide',
      '#sections' => $sections,
      '#attached' => [
        'library' => ['avc_theme/global'],
      ],
    ];
  }

  /**
   * Getting started page.
   */
  public function gettingStarted() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page']],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page__header']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Getting Started with AV Commons'),
        '#attributes' => ['class' => ['help-page__title']],
      ],
      'subtitle' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Welcome! This guide will help you get up and running with AV Commons in just a few minutes.'),
        '#attributes' => ['class' => ['help-page__subtitle']],
      ],
    ];

    $build['steps'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-section']],
      'content' => [
        '#markup' => '
          <div class="steps">
            <div class="steps__item">
              <div class="steps__item-title">' . $this->t('Complete Your Profile') . '</div>
              <div class="steps__item-content">' . $this->t('After registering, visit your profile page to add your information. Include your AV level (Disciple, Aspirant, or Sojourner) and accept the public domain contribution acknowledgment.') . '</div>
            </div>
            <div class="steps__item">
              <div class="steps__item-title">' . $this->t('Find Your Dashboard') . '</div>
              <div class="steps__item-content">' . $this->t('Click on "My Dashboard" in the menu to see your personal workspace. This shows all your current assignments, pending tasks, and activity across groups.') . '</div>
            </div>
            <div class="steps__item">
              <div class="steps__item-title">' . $this->t('Join Groups') . '</div>
              <div class="steps__item-content">' . $this->t('Browse available groups and request to join those relevant to your interests. Groups are where collaborative work happens.') . '</div>
            </div>
            <div class="steps__item">
              <div class="steps__item-title">' . $this->t('Set Notification Preferences') . '</div>
              <div class="steps__item-content">' . $this->t('Configure how you want to receive notifications. Choose between immediate, daily digest, weekly digest, or no notifications for different event types.') . '</div>
            </div>
            <div class="steps__item">
              <div class="steps__item-title">' . $this->t('Start Contributing') . '</div>
              <div class="steps__item-content">' . $this->t('Create your first document or pick up a task from your worklist. Every contribution helps build our shared resources.') . '</div>
            </div>
          </div>

          <div class="info-box info-box--tip">
            <div class="info-box__title">' . $this->t('Tip') . '</div>
            <p>' . $this->t('Not sure where to start? Check your Dashboard for any pending assignments, or browse existing Projects to see what work is in progress.') . '</p>
          </div>
        ',
      ],
    ];

    $build['next'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card__header--primary']],
      'content' => [
        '#markup' => '
          <div class="card__header card__header--primary">
            <h3 class="card__title">' . $this->t('Ready to Learn More?') . '</h3>
          </div>
          <div class="card__body">
            <p>' . $this->t('Explore our detailed guides:') . '</p>
            <ul>
              <li><a href="/help/dashboard">' . $this->t('Understanding Your Dashboard') . '</a></li>
              <li><a href="/help/assets">' . $this->t('Working with Assets (Projects, Documents, Resources)') . '</a></li>
              <li><a href="/help/workflow">' . $this->t('The Workflow System') . '</a></li>
              <li><a href="/help/guilds">' . $this->t('Joining and Participating in Guilds') . '</a></li>
            </ul>
          </div>
        ',
      ],
    ];

    return $build;
  }

  /**
   * FAQ page.
   */
  public function faq() {
    $faqs = [
      [
        'question' => $this->t('What is AV Commons?'),
        'answer' => $this->t('AV Commons is a community collaboration site. It allows members to work together on projects, documents, and resources with proper workflow management and quality control.'),
      ],
      [
        'question' => $this->t('Who can join AV Commons?'),
        'answer' => $this->t('AV Commons is open to all members, including Disciples, Aspirants, and Sojourners. Each level has access to appropriate features and groups.'),
      ],
      [
        'question' => $this->t('What are the different AV Levels?'),
        'answer' => $this->t('<strong>Disciple:</strong> Active members committed to the community.<br><strong>Aspirant:</strong> Those discerning a deeper commitment.<br><strong>Sojourner:</strong> Friends and supporters of the community.'),
      ],
      [
        'question' => $this->t('What is the difference between Groups and Guilds?'),
        'answer' => $this->t('Groups are general collaborative spaces for projects and teams. Guilds are specialized skill-based groups with mentorship, scoring, and endorsement features. Guilds have specific roles (Junior, Endorsed, Mentor, Admin) and track member progress.'),
      ],
      [
        'question' => $this->t('How does the workflow system work?'),
        'answer' => $this->t('Documents and projects go through defined stages (like Draft, Review, Approval, Published). Each stage can have assigned reviewers who must approve the work before it moves forward. This ensures quality control.'),
      ],
      [
        'question' => $this->t('What happens to my contributions?'),
        'answer' => $this->t('All contributions to AV Commons are licensed as public domain. By contributing, you acknowledge that your work can be used by AV Commons without individual attribution.'),
      ],
      [
        'question' => $this->t('How do notifications work?'),
        'answer' => $this->t('You can configure notification preferences for different event types. Options include: Immediate (get notified right away), Daily Digest (bundled once per day), Weekly Digest (bundled once per week), or None.'),
      ],
      [
        'question' => $this->t('What is ratification in Guilds?'),
        'answer' => $this->t('Junior members in Guilds need their work ratified (approved) by a Mentor before it is considered complete. This mentorship system helps maintain quality and train new members.'),
      ],
      [
        'question' => $this->t('How do I earn points in a Guild?'),
        'answer' => $this->t('Points are earned through various activities: completing tasks, having work ratified, endorsing other members, and participating in guild activities. Points are tracked on the guild leaderboard.'),
      ],
      [
        'question' => $this->t('Can I be in multiple Groups or Guilds?'),
        'answer' => $this->t('Yes! You can be a member of multiple groups and guilds simultaneously. Each has its own dashboard and worklist.'),
      ],
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page']],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page__header']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Frequently Asked Questions'),
        '#attributes' => ['class' => ['help-page__title']],
      ],
    ];

    $faq_markup = '<div class="faq">';
    foreach ($faqs as $faq) {
      $faq_markup .= '
        <div class="faq__item">
          <div class="faq__question" tabindex="0">' . $faq['question'] . '</div>
          <div class="faq__answer">' . $faq['answer'] . '</div>
        </div>
      ';
    }
    $faq_markup .= '</div>';

    $build['faqs'] = [
      '#markup' => $faq_markup,
    ];

    return $build;
  }

  /**
   * Dashboard help page.
   */
  public function dashboardHelp() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page']],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page__header']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Understanding Your Dashboard'),
        '#attributes' => ['class' => ['help-page__title']],
      ],
      'subtitle' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Your dashboard is your personal command center in AV Commons.'),
        '#attributes' => ['class' => ['help-page__subtitle']],
      ],
    ];

    $build['content'] = [
      '#markup' => '
        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Accessing Your Dashboard') . '</h2>
          </div>
          <div class="help-section__content">
            <p>' . $this->t('Click on your username in the top right corner, then select "My Dashboard". Alternatively, go directly to <code>/user/[your-id]/dashboard</code>.') . '</p>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Dashboard Sections') . '</h2>
          </div>
          <div class="help-section__content">
            <h3>' . $this->t('Your Worklist') . '</h3>
            <p>' . $this->t('This shows all tasks currently assigned to you across all groups. Items are sorted by priority and due date. Each item shows:') . '</p>
            <ul>
              <li>' . $this->t('The asset name (click to view)') . '</li>
              <li>' . $this->t('Current workflow stage') . '</li>
              <li>' . $this->t('Which group it belongs to') . '</li>
              <li>' . $this->t('When it was assigned') . '</li>
            </ul>

            <h3>' . $this->t('Group Worklists') . '</h3>
            <p>' . $this->t('See pending work for each group you belong to. This helps you understand the overall workload and find items to work on.') . '</p>

            <h3>' . $this->t('Quick Stats') . '</h3>
            <p>' . $this->t('At a glance view of your activity:') . '</p>
            <ul>
              <li>' . $this->t('Pending assignments') . '</li>
              <li>' . $this->t('Completed this week') . '</li>
              <li>' . $this->t('Groups you belong to') . '</li>
            </ul>
          </div>
        </div>

        <div class="info-box info-box--tip">
          <div class="info-box__title">' . $this->t('Pro Tip') . '</div>
          <p>' . $this->t('Check your dashboard daily to stay on top of assignments. Items requiring urgent attention appear at the top.') . '</p>
        </div>
      ',
    ];

    return $build;
  }

  /**
   * Assets help page.
   */
  public function assetsHelp() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page']],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page__header']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Working with Assets'),
        '#attributes' => ['class' => ['help-page__title']],
      ],
      'subtitle' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Assets are the core content items in AV Commons. Learn how to create and manage them.'),
        '#attributes' => ['class' => ['help-page__subtitle']],
      ],
    ];

    $build['content'] = [
      '#markup' => '
        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Asset Types') . '</h2>
          </div>
          <div class="help-section__content">
            <div class="feature-list">
              <div class="feature-list__item">
                <div class="feature-list__text">
                  <strong>' . $this->t('Projects') . '</strong><br>
                  ' . $this->t('Large initiatives that can contain other assets. Use projects to organize related documents and resources.') . '
                </div>
              </div>
              <div class="feature-list__item">
                <div class="feature-list__text">
                  <strong>' . $this->t('Documents') . '</strong><br>
                  ' . $this->t('Text-based content that goes through the workflow system. Documents can be articles, guides, policies, etc.') . '
                </div>
              </div>
              <div class="feature-list__item">
                <div class="feature-list__text">
                  <strong>' . $this->t('Resources') . '</strong><br>
                  ' . $this->t('External links, references, and files. Resources are simpler assets for storing useful materials.') . '
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Creating an Asset') . '</h2>
          </div>
          <div class="help-section__content">
            <ol class="steps">
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Navigate to Your Group') . '</div>
                <div class="steps__item-content">' . $this->t('Assets are created within groups. Go to the group where you want to create the asset.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Click "Create Content"') . '</div>
                <div class="steps__item-content">' . $this->t('Select the type of asset you want to create (Project, Document, or Resource).') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Fill in Details') . '</div>
                <div class="steps__item-content">' . $this->t('Add a title, description, and any other required fields. An asset number is automatically generated.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Select Workflow Template') . '</div>
                <div class="steps__item-content">' . $this->t('Choose the appropriate workflow template. This determines which approval stages the asset will go through.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Save') . '</div>
                <div class="steps__item-content">' . $this->t('The asset is created in Draft status. You can continue editing before submitting for review.') . '</div>
              </li>
            </ol>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Asset Actions') . '</h2>
          </div>
          <div class="help-section__content">
            <table class="role-table">
              <thead>
                <tr>
                  <th>' . $this->t('Action') . '</th>
                  <th>' . $this->t('Description') . '</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>' . $this->t('Check Workflow') . '</strong></td>
                  <td>' . $this->t('View the current workflow status and see all stages.') . '</td>
                </tr>
                <tr>
                  <td><strong>' . $this->t('Process') . '</strong></td>
                  <td>' . $this->t('Move the asset to the next workflow stage (if you have permission).') . '</td>
                </tr>
                <tr>
                  <td><strong>' . $this->t('Resend Notification') . '</strong></td>
                  <td>' . $this->t('Send a reminder to the current assignee.') . '</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="info-box info-box--note">
          <div class="info-box__title">' . $this->t('Note') . '</div>
          <p>' . $this->t('Assets inherit their access permissions from the group they belong to. Make sure you are in the correct group before creating content.') . '</p>
        </div>
      ',
    ];

    return $build;
  }

  /**
   * Workflow help page.
   */
  public function workflowHelp() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page']],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page__header']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Understanding the Workflow System'),
        '#attributes' => ['class' => ['help-page__title']],
      ],
      'subtitle' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('The workflow system ensures quality control and proper approval for all content.'),
        '#attributes' => ['class' => ['help-page__subtitle']],
      ],
    ];

    $build['content'] = [
      '#markup' => '
        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('How Workflow Works') . '</h2>
          </div>
          <div class="help-section__content">
            <p>' . $this->t('Every document and project moves through a series of stages before being published. Each stage has:') . '</p>
            <ul>
              <li>' . $this->t('An assigned reviewer or approver') . '</li>
              <li>' . $this->t('Specific criteria for approval') . '</li>
              <li>' . $this->t('The ability to add comments') . '</li>
            </ul>

            <h3>' . $this->t('Common Workflow Stages') . '</h3>
            <table class="role-table">
              <thead>
                <tr>
                  <th>' . $this->t('Stage') . '</th>
                  <th>' . $this->t('Description') . '</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><span class="workflow-status workflow-status--draft">' . $this->t('Draft') . '</span></td>
                  <td>' . $this->t('Initial creation. Author is working on the content.') . '</td>
                </tr>
                <tr>
                  <td><span class="workflow-status workflow-status--review">' . $this->t('Review') . '</span></td>
                  <td>' . $this->t('Submitted for review. Reviewer checks for quality and accuracy.') . '</td>
                </tr>
                <tr>
                  <td><span class="workflow-status workflow-status--approved">' . $this->t('Approved') . '</span></td>
                  <td>' . $this->t('Content has been approved and is ready for publication.') . '</td>
                </tr>
                <tr>
                  <td><span class="workflow-status workflow-status--published">' . $this->t('Published') . '</span></td>
                  <td>' . $this->t('Content is live and visible to appropriate audiences.') . '</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Processing Workflow Stages') . '</h2>
          </div>
          <div class="help-section__content">
            <h3>' . $this->t('When You Are the Assignee') . '</h3>
            <ol class="steps">
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Review the Content') . '</div>
                <div class="steps__item-content">' . $this->t('Carefully read through the document or project.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Click "Process"') . '</div>
                <div class="steps__item-content">' . $this->t('Open the workflow processing form.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Make Your Decision') . '</div>
                <div class="steps__item-content">' . $this->t('Approve to move forward, or reject to send back for revisions.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Add Comments') . '</div>
                <div class="steps__item-content">' . $this->t('Always add helpful comments, especially when rejecting.') . '</div>
              </li>
            </ol>
          </div>
        </div>

        <div class="info-box info-box--important">
          <div class="info-box__title">' . $this->t('Important') . '</div>
          <p>' . $this->t('When you have pending workflow assignments, they appear in your Dashboard worklist. Please process them promptly to avoid delays.') . '</p>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Workflow Templates') . '</h2>
          </div>
          <div class="help-section__content">
            <p>' . $this->t('Different types of content use different workflow templates. Administrators configure these templates based on the type and importance of the content. Common templates include:') . '</p>
            <ul>
              <li><strong>' . $this->t('Simple Review') . ':</strong> ' . $this->t('Draft to Review to Published') . '</li>
              <li><strong>' . $this->t('Editorial') . ':</strong> ' . $this->t('Draft to Editor Review to Final Approval to Published') . '</li>
              <li><strong>' . $this->t('Committee') . ':</strong> ' . $this->t('Draft to Committee Review to Leadership Approval to Published') . '</li>
            </ul>
          </div>
        </div>
      ',
    ];

    return $build;
  }

  /**
   * Guilds help page.
   */
  public function guildsHelp() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page']],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page__header']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('The Guild System'),
        '#attributes' => ['class' => ['help-page__title']],
      ],
      'subtitle' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Guilds are specialized skill-based groups with mentorship, scoring, and endorsements.'),
        '#attributes' => ['class' => ['help-page__subtitle']],
      ],
    ];

    $build['content'] = [
      '#markup' => '
        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('What Makes Guilds Special') . '</h2>
          </div>
          <div class="help-section__content">
            <p>' . $this->t('Unlike regular groups, Guilds have additional features designed for skill development and quality assurance:') . '</p>
            <ul>
              <li><strong>' . $this->t('Role-based Membership') . ':</strong> ' . $this->t('Members progress from Junior to Endorsed to Mentor') . '</li>
              <li><strong>' . $this->t('Ratification') . ':</strong> ' . $this->t('Junior members\' work is reviewed by Mentors') . '</li>
              <li><strong>' . $this->t('Skill Endorsements') . ':</strong> ' . $this->t('Members can endorse each other for specific skills') . '</li>
              <li><strong>' . $this->t('Scoring & Leaderboard') . ':</strong> ' . $this->t('Track contributions and recognize top contributors') . '</li>
            </ul>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Guild Roles') . '</h2>
          </div>
          <div class="help-section__content">
            <table class="role-table">
              <thead>
                <tr>
                  <th>' . $this->t('Role') . '</th>
                  <th>' . $this->t('Description') . '</th>
                  <th>' . $this->t('Can Ratify') . '</th>
                  <th>' . $this->t('Can Endorse') . '</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><span class="role-badge role-badge--junior">' . $this->t('Junior') . '</span></td>
                  <td>' . $this->t('New members learning the ropes') . '</td>
                  <td class="cross">-</td>
                  <td class="cross">-</td>
                </tr>
                <tr>
                  <td><span class="role-badge role-badge--endorsed">' . $this->t('Endorsed') . '</span></td>
                  <td>' . $this->t('Proven members who work independently') . '</td>
                  <td class="cross">-</td>
                  <td class="check">Yes</td>
                </tr>
                <tr>
                  <td><span class="role-badge role-badge--mentor">' . $this->t('Mentor') . '</span></td>
                  <td>' . $this->t('Experienced members who train others') . '</td>
                  <td class="check">Yes</td>
                  <td class="check">Yes</td>
                </tr>
                <tr>
                  <td><span class="role-badge role-badge--admin">' . $this->t('Admin') . '</span></td>
                  <td>' . $this->t('Full guild administration') . '</td>
                  <td class="check">Yes</td>
                  <td class="check">Yes</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Ratification Process') . '</h2>
          </div>
          <div class="help-section__content">
            <p>' . $this->t('When a Junior member completes work, it must be ratified by a Mentor before being considered complete.') . '</p>

            <h3>' . $this->t('For Juniors') . '</h3>
            <ol class="steps">
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Complete Your Work') . '</div>
                <div class="steps__item-content">' . $this->t('Finish the document, task, or contribution.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Submit for Ratification') . '</div>
                <div class="steps__item-content">' . $this->t('Mark the work as complete. It enters the ratification queue.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Wait for Review') . '</div>
                <div class="steps__item-content">' . $this->t('A Mentor will review your work and either approve or provide feedback.') . '</div>
              </li>
            </ol>

            <h3>' . $this->t('For Mentors') . '</h3>
            <ol class="steps">
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Check the Queue') . '</div>
                <div class="steps__item-content">' . $this->t('Visit the Guild\'s Ratification Queue to see pending items.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Claim an Item') . '</div>
                <div class="steps__item-content">' . $this->t('Click "Claim" to take responsibility for reviewing the work.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Review and Ratify') . '</div>
                <div class="steps__item-content">' . $this->t('Approve if quality meets standards, or reject with constructive feedback.') . '</div>
              </li>
            </ol>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Skill Endorsements') . '</h2>
          </div>
          <div class="help-section__content">
            <p>' . $this->t('Endorsed, Mentor, and Admin members can endorse others for specific skills. Endorsements help identify expertise within the guild.') . '</p>
            <ol class="steps">
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Visit Member Profile') . '</div>
                <div class="steps__item-content">' . $this->t('Go to a guild member\'s profile page.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Click "Endorse Skill"') . '</div>
                <div class="steps__item-content">' . $this->t('Select a skill from the list or add a new one.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Add Notes (Optional)') . '</div>
                <div class="steps__item-content">' . $this->t('Explain why you are endorsing this person for the skill.') . '</div>
              </li>
            </ol>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Scoring & Leaderboard') . '</h2>
          </div>
          <div class="help-section__content">
            <p>' . $this->t('Guilds track member contributions through a scoring system. Points are earned by:') . '</p>
            <ul>
              <li>' . $this->t('Completing tasks') . '</li>
              <li>' . $this->t('Having work ratified') . '</li>
              <li>' . $this->t('Ratifying others\' work (Mentors)') . '</li>
              <li>' . $this->t('Receiving endorsements') . '</li>
              <li>' . $this->t('Active participation') . '</li>
            </ul>
            <p>' . $this->t('The Leaderboard shows the top contributors in the guild. View it by clicking "Leaderboard" on the guild page.') . '</p>
          </div>
        </div>

        <div class="info-box info-box--tip">
          <div class="info-box__title">' . $this->t('Tip for Advancement') . '</div>
          <p>' . $this->t('Focus on quality over quantity. Consistent, high-quality contributions are the best way to progress from Junior to Endorsed status.') . '</p>
        </div>
      ',
    ];

    return $build;
  }

  /**
   * Notifications help page.
   */
  public function notificationsHelp() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page']],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['help-page__header']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Notification Settings'),
        '#attributes' => ['class' => ['help-page__title']],
      ],
      'subtitle' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Control how and when you receive notifications from AV Commons.'),
        '#attributes' => ['class' => ['help-page__subtitle']],
      ],
    ];

    $build['content'] = [
      '#markup' => '
        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Notification Options') . '</h2>
          </div>
          <div class="help-section__content">
            <p>' . $this->t('For each type of notification, you can choose how you want to be notified:') . '</p>

            <table class="role-table">
              <thead>
                <tr>
                  <th>' . $this->t('Option') . '</th>
                  <th>' . $this->t('Code') . '</th>
                  <th>' . $this->t('Description') . '</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>' . $this->t('Immediate') . '</strong></td>
                  <td><code>n</code></td>
                  <td>' . $this->t('Receive an email as soon as the event occurs.') . '</td>
                </tr>
                <tr>
                  <td><strong>' . $this->t('Daily Digest') . '</strong></td>
                  <td><code>d</code></td>
                  <td>' . $this->t('Receive a summary email once per day (if there are notifications).') . '</td>
                </tr>
                <tr>
                  <td><strong>' . $this->t('Weekly Digest') . '</strong></td>
                  <td><code>w</code></td>
                  <td>' . $this->t('Receive a summary email once per week.') . '</td>
                </tr>
                <tr>
                  <td><strong>' . $this->t('None') . '</strong></td>
                  <td><code>x</code></td>
                  <td>' . $this->t('Do not send email notifications for this event type.') . '</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Notification Types') . '</h2>
          </div>
          <div class="help-section__content">
            <p>' . $this->t('You can configure preferences for different types of events:') . '</p>
            <ul>
              <li><strong>' . $this->t('Workflow Assignments') . ':</strong> ' . $this->t('When you are assigned to review or approve content.') . '</li>
              <li><strong>' . $this->t('Workflow Updates') . ':</strong> ' . $this->t('When content you created moves through workflow stages.') . '</li>
              <li><strong>' . $this->t('Group Activity') . ':</strong> ' . $this->t('New content or discussions in your groups.') . '</li>
              <li><strong>' . $this->t('Guild Updates') . ':</strong> ' . $this->t('Ratification results, endorsements, and guild announcements.') . '</li>
              <li><strong>' . $this->t('Mentions') . ':</strong> ' . $this->t('When someone mentions you in a comment or discussion.') . '</li>
            </ul>
          </div>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Configuring Your Preferences') . '</h2>
          </div>
          <div class="help-section__content">
            <ol class="steps">
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Go to Notification Preferences') . '</div>
                <div class="steps__item-content">' . $this->t('Click your username, then "Notification Preferences", or go directly to /user/[your-id]/notification-preferences.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Review Each Category') . '</div>
                <div class="steps__item-content">' . $this->t('Go through each notification type and select your preferred delivery method.') . '</div>
              </li>
              <li class="steps__item">
                <div class="steps__item-title">' . $this->t('Save Changes') . '</div>
                <div class="steps__item-content">' . $this->t('Click "Save" to apply your preferences.') . '</div>
              </li>
            </ol>
          </div>
        </div>

        <div class="info-box info-box--important">
          <div class="info-box__title">' . $this->t('Recommended Settings') . '</div>
          <p>' . $this->t('We recommend keeping "Workflow Assignments" set to Immediate (n) so you are notified promptly when you have work to review. This helps prevent bottlenecks in the approval process.') . '</p>
        </div>

        <div class="help-section">
          <div class="help-section__header">
            <h2 class="help-section__title">' . $this->t('Digest Timing') . '</h2>
          </div>
          <div class="help-section__content">
            <ul>
              <li><strong>' . $this->t('Daily Digest') . ':</strong> ' . $this->t('Sent each morning around 6:00 AM local time.') . '</li>
              <li><strong>' . $this->t('Weekly Digest') . ':</strong> ' . $this->t('Sent Monday morning around 6:00 AM local time.') . '</li>
            </ul>
            <p>' . $this->t('If there are no notifications to include, the digest email is not sent.') . '</p>
          </div>
        </div>
      ',
    ];

    return $build;
  }

  /**
   * Get overview content for user guide.
   */
  protected function getOverviewContent() {
    return $this->t('<p>AV Commons is a community collaboration site. It enables our community to:</p>
      <ul>
        <li><strong>Collaborate on Projects, Documents, and Resources</strong> - Work together on shared content with proper version control and approval workflows.</li>
        <li><strong>Participate in skill-based Guilds</strong> - Join specialized groups with mentorship, scoring, and endorsement features.</li>
        <li><strong>Track workflow assignments</strong> - Know what needs your attention with personalized dashboards and worklists.</li>
        <li><strong>Receive customizable notifications</strong> - Stay informed without inbox overload through digest options.</li>
      </ul>');
  }

  /**
   * Get dashboard content for user guide.
   */
  protected function getDashboardContent() {
    return $this->t('<p>Your personal dashboard shows:</p>
      <ul>
        <li><strong>Your Worklist</strong> - All items currently assigned to you</li>
        <li><strong>Group Worklists</strong> - Pending work in each of your groups</li>
        <li><strong>Quick Stats</strong> - At-a-glance summary of your activity</li>
      </ul>
      <p>Access your dashboard by clicking your username and selecting "My Dashboard".</p>');
  }

  /**
   * Get assets content for user guide.
   */
  protected function getAssetsContent() {
    return $this->t('<p>AV Commons supports three types of assets:</p>
      <ul>
        <li><strong>Projects</strong> - Large initiatives that contain other assets</li>
        <li><strong>Documents</strong> - Text-based content with workflow</li>
        <li><strong>Resources</strong> - External links and references</li>
      </ul>
      <p>Each asset type has its own icon and can be assigned to groups and workflows.</p>');
  }

  /**
   * Get workflow content for user guide.
   */
  protected function getWorkflowContent() {
    return $this->t('<p>The workflow system ensures quality control:</p>
      <ul>
        <li>Documents move through defined stages (Draft, Review, Approval, Published)</li>
        <li>Each stage has assigned reviewers</li>
        <li>Reviewers can approve, reject, or request changes</li>
        <li>Notifications keep everyone informed of status changes</li>
      </ul>');
  }

  /**
   * Get groups content for user guide.
   */
  protected function getGroupsContent() {
    return $this->t('<p>Groups are collaborative spaces where work happens:</p>
      <ul>
        <li>Join groups based on your interests and skills</li>
        <li>Each group has its own content, members, and workflow</li>
        <li>Group managers can assign roles and permissions</li>
      </ul>');
  }

  /**
   * Get guilds content for user guide.
   */
  protected function getGuildsContent() {
    return $this->t('<p>Guilds are specialized groups with additional features:</p>
      <ul>
        <li><strong>Roles:</strong> Junior, Endorsed, Mentor, Admin</li>
        <li><strong>Ratification:</strong> Junior work is reviewed by Mentors</li>
        <li><strong>Endorsements:</strong> Members can endorse each other\'s skills</li>
        <li><strong>Scoring:</strong> Track contributions on the leaderboard</li>
      </ul>');
  }

  /**
   * Get notifications content for user guide.
   */
  protected function getNotificationsContent() {
    return $this->t('<p>Customize how you receive notifications:</p>
      <ul>
        <li><strong>n (Immediate)</strong> - Get notified right away</li>
        <li><strong>d (Daily)</strong> - Bundled into a daily digest</li>
        <li><strong>w (Weekly)</strong> - Bundled into a weekly digest</li>
        <li><strong>x (None)</strong> - No notification</li>
      </ul>
      <p>Configure preferences at User Menu > Notification Preferences.</p>');
  }

}
