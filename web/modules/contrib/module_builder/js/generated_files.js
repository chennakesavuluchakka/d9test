/**
 * @file
 * Provides JS behaviour for the generate code form.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.moduleBuilder = {
    attach: function (context, settings) {

      $('.generated-files .generated-code:not(:first)').each(function () {
        $(this).hide();
      });

      $('.generated-files li :checkbox').change(function() {
        if (this.checked) {
          $('.generated-files .generated-code').hide();

          // Show the corresponding code textarea.
          var filename = $(this).attr('data-generated-file');
          $('.generated-files textarea[data-generated-file="' + filename + '"]').parents('.generated-code').show();
        }
    });


    }
  };
})(jQuery, Drupal, drupalSettings);
