<?php

namespace Drupal\views_bulk_operations\Service;

use Drupal\Core\Action\ActionManager;

/**
 * Allow VBO actions to define additional configuration.
 */
class ViewsBulkOperationsActionManager extends ActionManager {

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();
    foreach ($definitions as $id => $definition) {
      $this->extendDefinition($definitions[$id]);
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    $definition = parent::getDefinition($plugin_id, $exception_on_invalid);
    $this->extendDefinition($definition);
    return $definition;
  }

  /**
   * Add additional configuration to action definition.
   *
   * @param array $definition
   *   The plugin definition.
   */
  protected function extendDefinition(array &$definition) {
    if (in_array('Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionInterface', class_implements($definition['class']), TRUE)) {
      $extra = $definition['class']::vboConfiguration();
      // Merge in defaults.
      $extra += [
        'confirm' => FALSE,
        'pass_rows' => FALSE,
      ];
      foreach ($extra as $key => $value) {
        $definition[$key] = $value;
      }
    }
  }

}
