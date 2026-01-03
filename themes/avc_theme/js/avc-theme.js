/**
 * @file
 * AV Commons Theme - JavaScript behaviors
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Mobile navigation toggle
   */
  Drupal.behaviors.avcMobileNav = {
    attach: function (context) {
      once('avc-mobile-nav', '.header-toggle', context).forEach(function (toggle) {
        toggle.addEventListener('click', function () {
          var headerRight = document.querySelector('.header-right');
          if (headerRight) {
            headerRight.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded',
              headerRight.classList.contains('is-open') ? 'true' : 'false'
            );
          }
        });
      });
    }
  };

  /**
   * FAQ accordion
   */
  Drupal.behaviors.avcFaq = {
    attach: function (context) {
      once('avc-faq', '.faq__question', context).forEach(function (question) {
        question.addEventListener('click', function () {
          var item = question.closest('.faq__item');
          if (item) {
            item.classList.toggle('is-open');
          }
        });

        // Keyboard accessibility
        question.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            question.click();
          }
        });
      });
    }
  };

  /**
   * Smooth scroll for anchor links
   */
  Drupal.behaviors.avcSmoothScroll = {
    attach: function (context) {
      once('avc-smooth-scroll', 'a[href^="#"]', context).forEach(function (link) {
        link.addEventListener('click', function (e) {
          var targetId = this.getAttribute('href');
          if (targetId === '#') return;

          var target = document.querySelector(targetId);
          if (target) {
            e.preventDefault();
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });

            // Update URL without jumping
            history.pushState(null, null, targetId);
          }
        });
      });
    }
  };

  /**
   * Table responsive wrapper
   */
  Drupal.behaviors.avcResponsiveTables = {
    attach: function (context) {
      once('avc-responsive-tables', 'table:not(.table-responsive table)', context).forEach(function (table) {
        // Add data-label attributes for mobile view
        var headers = table.querySelectorAll('th');
        var headerTexts = [];

        headers.forEach(function (header) {
          headerTexts.push(header.textContent.trim());
        });

        table.querySelectorAll('tbody tr').forEach(function (row) {
          row.querySelectorAll('td').forEach(function (cell, index) {
            if (headerTexts[index]) {
              cell.setAttribute('data-label', headerTexts[index]);
            }
          });
        });
      });
    }
  };

  /**
   * Notification preferences form enhancement
   */
  Drupal.behaviors.avcNotificationPrefs = {
    attach: function (context) {
      once('avc-notification-prefs', '.notification-preferences-form', context).forEach(function (form) {
        // Add "Select All" functionality for each column
        form.querySelectorAll('thead th').forEach(function (th, index) {
          if (index > 0) { // Skip first column (labels)
            var selectAll = document.createElement('button');
            selectAll.type = 'button';
            selectAll.className = 'button--small button--tertiary';
            selectAll.textContent = 'All';
            selectAll.style.marginTop = '4px';
            selectAll.addEventListener('click', function () {
              form.querySelectorAll('tbody tr').forEach(function (row) {
                var inputs = row.querySelectorAll('input[type="radio"]');
                if (inputs[index - 1]) {
                  inputs[index - 1].checked = true;
                }
              });
            });
            th.appendChild(document.createElement('br'));
            th.appendChild(selectAll);
          }
        });
      });
    }
  };

  /**
   * Workflow status tooltips
   */
  Drupal.behaviors.avcWorkflowTooltips = {
    attach: function (context) {
      once('avc-workflow-tooltips', '.workflow-status', context).forEach(function (status) {
        var statusText = status.textContent.trim().toLowerCase();
        var descriptions = {
          'draft': 'This item is being drafted and is not yet ready for review.',
          'in progress': 'Work is actively being done on this item.',
          'review': 'This item is awaiting review or approval.',
          'approved': 'This item has been approved.',
          'rejected': 'This item was rejected and needs revision.',
          'published': 'This item is published and visible to the public.'
        };

        if (descriptions[statusText]) {
          status.setAttribute('title', descriptions[statusText]);
        }
      });
    }
  };

  /**
   * Leaderboard animation
   */
  Drupal.behaviors.avcLeaderboard = {
    attach: function (context) {
      once('avc-leaderboard', '.leaderboard__entry', context).forEach(function (entry, index) {
        entry.style.animationDelay = (index * 0.1) + 's';
        entry.classList.add('animate-fade-in');
      });
    }
  };

  /**
   * Card hover effects
   */
  Drupal.behaviors.avcCardEffects = {
    attach: function (context) {
      once('avc-card-effects', '.card--clickable', context).forEach(function (card) {
        var link = card.querySelector('a');
        if (link) {
          card.style.cursor = 'pointer';
          card.addEventListener('click', function (e) {
            if (e.target.tagName !== 'A') {
              link.click();
            }
          });
        }
      });
    }
  };

  /**
   * Print page handler
   */
  Drupal.behaviors.avcPrint = {
    attach: function (context) {
      once('avc-print', '.print-page', context).forEach(function (button) {
        button.addEventListener('click', function (e) {
          e.preventDefault();
          window.print();
        });
      });
    }
  };

})(Drupal, once);
