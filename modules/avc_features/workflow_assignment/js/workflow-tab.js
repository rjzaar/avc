/**
 * @file
 * JavaScript behaviors for the Workflow Assignment module.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Behavior for expandable table cells with AJAX save.
   */
  Drupal.behaviors.workflowExpandableCells = {
    attach: function (context, settings) {
      // Handle expandable cells
      $('.expandable-cell', context).once('expandable-cell').each(function () {
        var $cell = $(this);
        var fullText = $cell.data('full-text');
        var workflowId = $cell.closest('tr').data('workflow-id');
        var fieldName = $cell.hasClass('description-cell') ? 'description' :
                       ($cell.hasClass('comments-cell') ? 'comments' : null);
        var $content = $cell.find('.cell-content');
        var isExpanded = false;
        var isEditing = false;

        // Click to expand/collapse
        $cell.on('click', function (e) {
          if (isEditing) {
            return;
          }

          if (!isExpanded) {
            $content.text(fullText);
            $cell.addClass('expanded');
            isExpanded = true;
          }
          else {
            var truncated = truncateText(fullText, 50);
            $content.text(truncated);
            $cell.removeClass('expanded');
            isExpanded = false;
          }
        });

        // Double-click to edit (only for description and comments)
        if (fieldName && drupalSettings.workflowAssignment && drupalSettings.workflowAssignment.canEdit) {
          $cell.on('dblclick', function (e) {
            e.stopPropagation();

            if (isEditing) {
              return;
            }

            isEditing = true;
            var originalText = fullText === '-' ? '' : fullText;

            // Create textarea
            var $textarea = $('<textarea>').val(originalText);
            var $saveBtn = $('<button class="save-btn" type="button">Save</button>');
            var $cancelBtn = $('<button class="cancel-btn" type="button">Cancel</button>');
            var $actions = $('<div class="edit-actions">').append($saveBtn, $cancelBtn);

            $cell.addClass('editing');
            $cell.empty().append($textarea, $actions);
            $textarea.focus().select();

            // Save handler with AJAX
            $saveBtn.on('click', function () {
              var newText = $textarea.val() || '-';
              $saveBtn.prop('disabled', true).text('Saving...');

              // Make AJAX call to save
              $.ajax({
                url: Drupal.url('api/workflow-assignment/save'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                  workflow_id: workflowId,
                  field: fieldName === 'description-cell' ? 'description' :
                         (fieldName === 'comments-cell' ? 'comments' : fieldName),
                  value: newText === '-' ? '' : newText
                }),
                success: function (response) {
                  if (response.success) {
                    fullText = newText;
                    $cell.data('full-text', fullText);
                    showMessage(Drupal.t('Changes saved successfully.'), 'status');
                  }
                  else {
                    showMessage(response.message || Drupal.t('Error saving changes.'), 'error');
                  }
                  finishEditing();
                },
                error: function (xhr) {
                  var message = Drupal.t('Error saving changes.');
                  if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                  }
                  showMessage(message, 'error');
                  finishEditing();
                }
              });

              function finishEditing() {
                $cell.removeClass('editing expanded');
                $cell.empty().append($('<span class="cell-content">').text(truncateText(fullText, 50)));
                isEditing = false;
                isExpanded = false;
              }
            });

            // Cancel handler
            $cancelBtn.on('click', function () {
              $cell.removeClass('editing');
              $cell.empty().append($('<span class="cell-content">').text(isExpanded ? fullText : truncateText(fullText, 50)));
              isEditing = false;
            });

            // Handle escape key
            $textarea.on('keydown', function (e) {
              if (e.key === 'Escape') {
                $cancelBtn.click();
              }
              else if (e.key === 'Enter' && e.ctrlKey) {
                $saveBtn.click();
              }
            });
          });
        }
      });
    }
  };

  /**
   * Behavior for drag-and-drop reordering.
   */
  Drupal.behaviors.workflowDragDrop = {
    attach: function (context, settings) {
      var $table = $('#workflow-assignments-table tbody', context);

      if (!$table.length || !drupalSettings.workflowAssignment || !drupalSettings.workflowAssignment.canEdit) {
        return;
      }

      $table.once('workflow-sortable').each(function () {
        var $tbody = $(this);
        var nodeId = drupalSettings.workflowAssignment.nodeId;

        // Add drag handle to each row
        $tbody.find('tr').each(function () {
          var $row = $(this);
          if (!$row.find('.drag-handle').length) {
            $row.find('td:first').prepend('<span class="drag-handle" title="' + Drupal.t('Drag to reorder') + '">&#9776;</span> ');
          }
        });

        // Initialize sortable
        if (typeof Sortable !== 'undefined') {
          Sortable.create($tbody[0], {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function (evt) {
              saveOrder($tbody, nodeId);
            }
          });
        }
        else {
          // Fallback to jQuery UI sortable if available
          if ($.fn.sortable) {
            $tbody.sortable({
              handle: '.drag-handle',
              axis: 'y',
              update: function (event, ui) {
                saveOrder($tbody, nodeId);
              }
            });
          }
        }
      });

      function saveOrder($tbody, nodeId) {
        var order = [];
        $tbody.find('tr').each(function () {
          var workflowId = $(this).data('workflow-id');
          if (workflowId) {
            order.push(workflowId);
          }
        });

        if (order.length === 0) {
          return;
        }

        $.ajax({
          url: Drupal.url('api/workflow-assignment/reorder/' + nodeId),
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify({ order: order }),
          success: function (response) {
            if (response.success) {
              showMessage(Drupal.t('Order saved.'), 'status');
            }
            else {
              showMessage(response.message || Drupal.t('Error saving order.'), 'error');
            }
          },
          error: function () {
            showMessage(Drupal.t('Error saving order.'), 'error');
          }
        });
      }
    }
  };

  /**
   * Helper function to truncate text.
   */
  function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) {
      return text || '-';
    }
    return text.substring(0, maxLength) + '...';
  }

  /**
   * Helper function to show messages.
   */
  function showMessage(message, type) {
    type = type || 'status';
    var $message = $('<div class="messages messages--' + type + '">').text(message);
    var $container = $('.workflow-tab-content');

    if ($container.length) {
      $container.prepend($message);
    }
    else {
      $('#workflow-assignments-table').before($message);
    }

    setTimeout(function () {
      $message.fadeOut(function () {
        $(this).remove();
      });
    }, 3000);
  }

})(jQuery, Drupal, drupalSettings);
