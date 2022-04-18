<?php

namespace Drupal\module_builder_test_component_type\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\module_builder\Form\ModuleMiscForm;
use Drupal\module_builder_test_component_type\TestGenerateTask;
use DrupalCodeBuilder\Task\Generate;

/**
 * Uses a custom Generate task for a standardized set of form elements.
 */
class TestComponentMiscForm extends ModuleMiscForm {

  /**
   * {@inheritdoc}
   */
  public function setGenerateTask(Generate $generate_task) {
    $dcb_container =  \DrupalCodeBuilder\Factory::getContainer();

    // Can't use the container directly, as it won't know about the class we
    // want to use instead.
    $generate_task = new TestGenerateTask(
      $dcb_container->get('environment'),
      'module',
      $dcb_container->get('Generate\ComponentClassHandler'),
      $dcb_container->get('Generate\ComponentCollector'),
      $dcb_container->get('Generate\FileAssembler'),
    );

    $this->codeBuilderTaskHandlerGenerate = $generate_task;
  }

}
