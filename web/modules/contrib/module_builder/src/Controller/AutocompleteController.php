<?php

namespace Drupal\module_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\NestedArray;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a route controller for properties with extra options.
 */
class AutocompleteController extends ControllerBase {

  /**
   * Handler for autocomplete request for properties with extra options.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $property_address
   *   The address of the property this autocomplete request is for, as a string
   *   imploded with ':'.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The matching options.
   */
  public function handleAutocomplete(Request $request, $property_address) {
    $results = [];

    if ($input = $request->query->get('q')) {
      try {
        // TODO: inject.
        $generate_task = \Drupal::service('module_builder.drupal_code_builder')->getTask('Generate', 'module');
      }
      catch (\Exception $e) {
        // If we get here we should be ok.
      }

      // Get the definition that the autocomplete is for.
      $component_data = $generate_task->getRootComponentData();
      $root_definition = $component_data->getDefinition();
      $autocomplete_property = $root_definition->getNestedProperty($property_address);

      $options = array_keys($autocomplete_property->getOptions());

      $matched_keys = preg_grep("@{$input}@", $options);

      foreach ($matched_keys as $key) {
        $results[] = [
          'value' => $key,
          'label' => $key,
        ];
      }
    }

    return new JsonResponse($results);
  }

}
