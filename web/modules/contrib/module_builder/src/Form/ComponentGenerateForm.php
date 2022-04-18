<?php

namespace Drupal\module_builder\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Element;
use DrupalCodeBuilder\Exception\InvalidInputException;

/**
 * Form showing generated component code.
 */
class ComponentGenerateForm extends ComponentFormBase {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $component_data = $this->getComponentDataObject();

    // Get the generation config.
    $config = \Drupal::config('module_builder.settings');
    $module_config = $config->get('generator_settings.module');
    $config_data = \Drupal::service('module_builder.drupal_code_builder')->getTask('Configuration')->getConfigurationData('module');
    $config_data->import($module_config ?? []);

    // Get the files.
    try {
      $files = $this->codeBuilderTaskHandlerGenerate->generateComponent($component_data, [], $config_data);
    }
    catch (InvalidInputException $e) {
      $this->messenger()->addError(t("Invalid input for code generator: @message", [
        '@message' => $e->getMessage(),
      ]));

      return $form;
    }

    // Get the path to the module if it's previously been written.
    $existing_module_path = $this->getExistingModule();
    if ($existing_module_path) {
      $this->messenger()->addWarning(t("This module already exists at @path. It is recommended you use version control to prevent losing any existing code.", [
        '@path' => $existing_module_path,
      ]));
    }

    $module_name = $this->entity->id();
    if (\Drupal::moduleHandler()->moduleExists($module_name)) {
      $this->messenger()->addWarning(t("This module is currently ENABLED on this site. Writing files MAY CAUSE YOUR SITE TO CRASH."));
    }

    ksort($files);
    $statuses = [];
    foreach ($files as $filename => $code) {
      // We don't actually use the value from the form, as the POST process
      // seems to be turning unix line endings into Windows line endings! Store
      // it in the form state instead.
      $form_state->set(['files', $filename], $code);

      // Warn the user if the file already exists, and is not committed to git.
      $filepath = $existing_module_path . '/' . $filename;
      if (file_exists($filepath)) {
        // TODO: if the file is clean in git, we can maybe skip this, or say
        // it's ok to overwrite?

        // Perform the 'git status' command in the module folder, to allow for
        // the case where the module has its own git repository.
        $git_status = shell_exec("cd {$existing_module_path} && git status {$filename} --porcelain");
        if (!empty($git_status)) {
          $git_status_code = substr($git_status, 0, 2);

          // TODO: These don't take into account that changes might be staged.
          switch ($git_status_code) {
            case '??':
              $statuses[$filename] = t("WARNING: file already exists and is not under git version control! Writing this will lose the existing version.");
              break;

            case ' M':
              $statuses[$filename] = t("WARNING: file already exists and has uncommitted changes! Writing this will lose these.");
              break;
          }
        }
        else {
          $statuses[$filename] = t("(File already exists)");

        }
      }
      else {
        $statuses[$filename] = '';
      }
    }

    $form['files'] = [
      '#type' => 'module_builder_generated_files',
      '#files' => $files,
      '#statuses' => $statuses,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // The 'write selected' button needs at least one file to be selected.
    if ($form_state->getTriggeringElement()['#name'] == 'write_selected') {
      $values = $form_state->getValue('filename_list');
      $files_to_write = array_filter($values);

      if (empty($files_to_write)) {
        $form_state->setError($form['files']['filename_list'], $this->t("At least one file must be selected to write."));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = [];

    $actions['write_selected'] = [
      '#type' => 'submit',
      '#name' => 'write_selected',
      '#value' => $this->t('Write selected files'),
      '#submit' => array('::writeSelected'),
      '#dropbutton' => 'generate',
    ];

    $actions['write_new'] = [
      '#type' => 'submit',
      '#name' => 'write_new',
      '#value' => $this->t('Write new files'),
      '#submit' => array('::writeNew'),
      '#dropbutton' => 'generate',
    ];

    $actions['write_all'] = [
      '#type' => 'submit',
      '#name' => 'write_all',
      '#value' => $this->t('Write all files'),
      '#submit' => array('::writeAll'),
      '#dropbutton' => 'generate',
    ];

    return $actions;
  }

  /**
   * Returns the path to the module if it has previously been written.
   *
   * @return
   *  A Drupal-relative path to the module folder, or NULL if the module
   *  does not already exist.
   */
  protected function getExistingModule() {
    $module_name = $this->entity->id();

    $exists = \Drupal::service('extension.list.module')->exists($module_name);
    if ($exists) {
      $module = \Drupal::service('extension.list.module')->get($module_name);

      return $module->getPath();
    }
  }

  /**
   * Submit callback to write all the module files.
   */
  public function writeAll(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $drupal_relative_module_dir = \Drupal::service('module_builder.module_file_writer')->getRelativeModuleFolder($this->entity->id());

    \Drupal::service('file_system')->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);

    $count_written = 0;
    foreach (array_keys($values['file_code']) as $module_relative_filepath) {
      $file_contents = $form_state->get(['files', $module_relative_filepath]);

      $result = \Drupal::service('module_builder.module_file_writer')->writeSingleFile($drupal_relative_module_dir, $module_relative_filepath, $file_contents);

      if ($result) {
        $count_written++;
      }
      else {
        $this->messenger()->addError(t("Problem writing file @file", [
          '@file' => $module_relative_filepath
        ]));
      }
    }

    if ($count_written) {
      $this->messenger()->addStatus(t("Written @count files to folder @folder.", [
        '@count'  => $count_written,
        '@folder' => $drupal_relative_module_dir,
      ]));
    }
  }

  /**
   * Submit callback to write only the selected files.
   */
  public function writeSelected(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValue('filename_list');
    $files_to_write = array_filter($values);

    $drupal_relative_module_dir = \Drupal::service('module_builder.module_file_writer')->getRelativeModuleFolder($this->entity->id());

    \Drupal::service('file_system')->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);

    $count_written = 0;
    foreach (array_keys($files_to_write) as $module_relative_filepath) {
      $file_contents = $form_state->get(['files', $module_relative_filepath]);

      $result = \Drupal::service('module_builder.module_file_writer')->writeSingleFile($drupal_relative_module_dir, $module_relative_filepath, $file_contents);
      if ($result) {
        $count_written++;
      }
      else {
        $this->messenger()->addError(t("Problem writing file @file", [
          '@file' => $module_relative_filepath
        ]));
      }
    }

    if ($count_written) {
      $this->messenger()->addStatus(t("Written @count files to folder @folder.", [
        '@count'  => $count_written,
        '@folder' => $drupal_relative_module_dir,
      ]));
    }
  }

  /**
   * Submit callback to write only new module files.
   */
  public function writeNew(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $drupal_relative_module_dir = \Drupal::service('module_builder.module_file_writer')->getRelativeModuleFolder($this->entity->id());

    \Drupal::service('file_system')->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);

    $count_written = 0;
    foreach (array_keys($values['file_code']) as $module_relative_filepath) {
      if (file_exists($drupal_relative_module_dir . '/' . $module_relative_filepath)) {
        continue;
      }

      $file_contents = $form_state->get(['files', $module_relative_filepath]);

      $result = \Drupal::service('module_builder.module_file_writer')->writeSingleFile($drupal_relative_module_dir, $module_relative_filepath, $file_contents);

      if ($result) {
        $count_written++;
      }
      else {
        $this->messenger()->addError(t("Problem writing file @file", [
          '@file' => $module_relative_filepath
        ]));
      }
    }

    if ($count_written) {
      $this->messenger()->addStatus(t("Written @count files to folder @folder.", [
        '@count'  => $count_written,
        '@folder' => $drupal_relative_module_dir,
      ]));
    }
  }

  /**
   * Submit handler to write a single file.
   */
  public function submitWriteSingle(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $button_array_parents = $button['#array_parents'];

    $file_key = $button_array_parents[1];

    $file_contents = $form_state->get(['files', $file_key]);

    $drupal_relative_module_dir = \Drupal::service('module_builder.module_file_writer')->getRelativeModuleFolder($this->entity->id());

    $result = \Drupal::service('module_builder.module_file_writer')->writeSingleFile($drupal_relative_module_dir, $file_key, $file_contents);

    if ($result) {
      $this->messenger()->addStatus(t("Written file @file to folder @folder.", [
        '@file'  => $file_key,
        '@folder' => $drupal_relative_module_dir,
      ]));
    }
    else {
      $this->messenger()->addError(t("Problem writing file @file", [
        '@file' => $file_key,
      ]));
    }
  }

}
