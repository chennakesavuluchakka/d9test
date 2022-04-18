<?php

namespace Drupal\module_builder\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Defines a custom form element for a list of generated files to write.
 *
 * @RenderElement("module_builder_generated_files")
 */
class GeneratedFiles extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processFiles'],
      ],
      '#theme' => 'generated_files',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Element process callback.
   */
  public static function processFiles(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['filename_list'] = [
      '#tree' => TRUE,
    ];

    foreach ($element['#files'] as $filename => $code) {
      $element['filename_list'][$filename] = [
        '#type' => 'checkbox',
        '#title' => $filename . ' ' . ($element['#statuses'][$filename] ?? ''),
        '#attributes' => [
          'data-generated-file' => $filename,
        ],
      ];

      $element['code'][$filename] = [
        '#type' => 'textarea',
        '#title' => t("@filename code", [
          '@filename' => $filename,
          ])
          . ' ' . ($element['#statuses'][$filename] ?? ''),
        '#rows' => count(explode("\n", $code)),
        '#default_value' => $code,
        // This creates an item in form values that just contains the code, so
        // we don't need to filter out the values from button labels when
        // writing code.
        '#parents' => ['file_code', $filename],
        '#attributes' => [
          'data-generated-file' => $filename,
        ],
      ];
    }

    $element['#attached'] = [
      'library' => ['module_builder/generated_files'],
    ];

    return $element;
  }

}
