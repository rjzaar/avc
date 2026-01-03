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

      // Add click handler for worklist rows.
      $('.avc-worklist-table tbody tr.worklist-item', context).once('avc-row-click').on('click', function (e) {
        // Don't trigger if clicking a link.
        if ($(e.target).is('a')) {
          return;
        }

        // Navigate to workflow task page if task ID is available.
        var taskId = $(this).data('task-id');
        var nodeId = $(this).data('node-id');

        if (taskId) {
          window.location.href = '/workflow-task/' + taskId;
        } else if (nodeId) {
          window.location.href = '/node/' + nodeId;
        }
      });
    }
  };

})(jQuery, Drupal);
