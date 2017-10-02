/**
 * @file
 * Views admin UI functionality.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.views_bulk_operations = {
    attach: function (context, settings) {
      $('.views-bulk-operations-ui').once('views-bulk-operations-ui').each(Drupal.viewsBulkOperationsUi);
    }
  };

  /**
   * Callback used in {@link Drupal.behaviors.views_bulk_operations}.
   */
  Drupal.viewsBulkOperationsUi = function () {
    var uiElement = $(this);
    uiElement.find('.action-state').each(function () {
      var matches = $(this).attr('name').match(/.*\[.*?\]\[(.*?)\]\[.*?\]/);
      if (typeof (matches[1]) != 'undefined') {
        var preconfigurationElement = uiElement.find('*[data-for="' + matches[1] + '"]');
        $(this).change(function (event) {
          if ($(this).is(':checked')) {
            preconfigurationElement.show('fast');
          }
          else {
            preconfigurationElement.hide('fast');
          }
        });
      }
    });
  };
})(jQuery, Drupal);
