/**
 * @file
 * Defines Javascript behaviors for the Module Builder module.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Filters hooks as the user types.
   */
  Drupal.behaviors.filterHooks = {
    attach: function (context, settings) {
      var $input = $('input.hooks-filter-text').once('hooks-filter-text');
      var $container = $($input.attr('data-container'));
      var $groups = $container.find('details');
      // Holds the hooks jQuery elements to hide, keyed by hook name.
      var hooks = {};
      // Hash keyed by hook name whose values are group names.
      var hooks_with_group = {};
      // Holds the groups jQuery elements to open/close, keyed by group name.
      var groups = {};

      // Build up arrays of references for easy access later.
      $groups.each(function(index, group) {
        var $group = $(group);

        // Add a class to mark groups that were initially open.
        // TODO: selecting a hook should make its group be open by default
        // from now on. Unselecting all hooks from a group should cause it to
        // be closed by default.
        if ($group.attr("open")) {
          $group.addClass("initially-open");
        }

        var group_name = $group.find('summary').html();

        groups[group_name] = $group;

        // Get the DIV around the each checkbox, so that we can hide the whole
        // form element: checkbox and label.
        // We know the group only contains checkbox items, so we're loose about
        // what we find, as different core themes use a different class for
        // specific element types.
        var $group_hooks = $group.find('.form-item');

        $group_hooks.each(function(index, hook) {
          var $hook = $(hook);
          var hook_name = $hook.find('input').attr('value');

          hooks[hook_name] = $hook;
          hooks_with_group[hook_name] = group_name;
        });
      });

      /**
       * Apply the filter.
       */
      function filterHooks(e) {
        var query = $(e.target).val().toLowerCase();

        var groupVisibility = {};

        if (query == '') {
          resetFilter();
          return;
        }

        // Iterate over all hooks.
        Object.keys(hooks_with_group).forEach(function (hook_name, index) {
          var textMatch = hook_name.indexOf(query) !== -1;

          hooks[hook_name].toggle(textMatch);

          // A group should be made visible if it has a matched hook, and hidden
          // if it has no matched hooks at all.
          if (textMatch) {
            var group_name = hooks_with_group[hook_name];
            groupVisibility[group_name] = true;
          }
        });

        // Show and open all the relevant groups.
        Object.keys(groups).forEach(function (group_name, index) {
          var $group = groups[group_name];

          var showGroup = (group_name in groupVisibility);

          $group.attr('open', showGroup);
          $group.toggle(showGroup);
        });
      }

      /**
       * Reset the page when the filter is cleared.
       */
      function resetFilter(e) {
        $.each(hooks, function(index, $item) {
          $item.toggle(true);
        });
        $.each(groups, function(group_name, $item) {
          $item.toggle(true);

          var originallyOpen = groups[group_name].hasClass('initially-open');
          $item.attr('open', originallyOpen);
        });
      }

      $input.on('keyup', filterHooks);
    }
  };
})(jQuery, Drupal, drupalSettings);
