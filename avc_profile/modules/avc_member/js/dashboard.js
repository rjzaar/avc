/**
 * @file
 * JavaScript for AVC Member Dashboard.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Dashboard behaviors.
   */
  Drupal.behaviors.avcMemberDashboard = {
    attach: function (context, settings) {
      // Highlight current tasks.
      $('.worklist-item.status-current', context).once('avc-highlight').each(function () {
        $(this).addClass('highlighted');
      });

      // Add click handler for row expansion (future enhancement).
      $('.avc-worklist-table tbody tr', context).once('avc-row-click').on('click', function (e) {
        // Don't trigger if clicking a link.
        if ($(e.target).is('a')) {
          return;
        }
        // Find the view link and navigate.
        var link = $(this).find('a[href*="workflow"]').first();
        if (link.length) {
          window.location.href = link.attr('href');
        }
      });
    }
  };

})(jQuery, Drupal);
