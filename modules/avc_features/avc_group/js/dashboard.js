/**
 * @file
 * JavaScript for AVC Group Workflow Dashboard.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Group workflow dashboard behaviors.
   */
  Drupal.behaviors.avcGroupDashboard = {
    attach: function (context, settings) {
      // Highlight active assignments.
      $(once('avc-highlight', '.worklist-item.status-current', context)).each(function () {
        $(this).addClass('highlighted');
      });

      // Add click handler for row navigation.
      $(once('avc-row-click', '.avc-worklist-table tbody tr', context)).on('click', function (e) {
        // Don't trigger if clicking a link.
        if ($(e.target).is('a')) {
          return;
        }
        // Find the view link and navigate.
        var link = $(this).find('.item-actions a').first();
        if (link.length) {
          window.location.href = link.attr('href');
        }
      });

      // Add data-label attributes for mobile view.
      $(once('avc-mobile-labels', '.avc-worklist-table', context)).each(function () {
        var $table = $(this);
        var headers = [];

        // Get header labels.
        $table.find('thead th').each(function () {
          headers.push($(this).text());
        });

        // Apply to body cells.
        $table.find('tbody tr').each(function () {
          $(this).find('td').each(function (index) {
            if (headers[index]) {
              $(this).attr('data-label', headers[index]);
            }
          });
        });
      });

      // Collapsible completed section.
      $(once('avc-collapse', '.completed-assignments h3', context)).on('click', function () {
        var $section = $(this).parent();
        $section.toggleClass('collapsed');
        $section.find('table, .empty-message').slideToggle(200);
      });
    }
  };

})(jQuery, Drupal, once);
