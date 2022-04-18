<?php

namespace Drupal\module_builder_devel;

use Drupal\module_builder\DrupalCodeBuilder;

/**
 * Alternative library wrapper service, to use the test samples environment.
 */
class DrupalCodeBuilderTestSamples extends DrupalCodeBuilder {

  /**
   * {@inheritdoc}
   */
  protected function doLoadLibrary() {
    \DrupalCodeBuilder\Factory::setEnvironmentLocalClass('WriteTestsSampleLocation')
      ->setCoreVersionNumber(\Drupal::VERSION);
  }

}
