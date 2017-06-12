<?php

namespace Drupal\views_bulk_operations\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines Views Bulk Operations action interface.
 */
interface ViewsBulkOperationsActionInterface {

  /**
   * Set context.
   *
   * @param array $context
   *   The context array.
   */
  public function setContext(array $context);

  /**
   * Add additional action configuration.
   *
   * To avoid reinventing core action plugin and
   * defining more annotations, we'll use a static
   * method to add additional VBO-specific
   * configuration to an action.
   */
  public static function vboConfiguration();

  /**
   * Build preconfigure action form elements.
   *
   * @param array $element
   *   Element of the views API form where configuration resides.
   * @param array $values
   *   Current values of the plugin pre-configuration.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface object.
   *
   * @return array
   *   The action configuration form element.
   */
  public function buildPreConfigurationForm(array $element, array $values, FormStateInterface $form_state);

  /**
   * Execute action on multiple entities.
   *
   * Can return an array of results of processing, if no return value
   * is provided, action label will be used for each result.
   *
   * @param array $objects
   *   An array of entities.
   *
   * @return array
   *   An array of translatable markup objects or strings (optional)
   */
  public function executeMultiple(array $objects);

}
