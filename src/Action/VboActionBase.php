<?php

namespace Drupal\views_bulk_operations\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Views Bulk Operations action plugin base.
 *
 * Provides a base implementation for a configurable
 * and preconfigurable VBO Action plugin.
 */
abstract class ViewsBulkOperationsActionBase extends ConfigurableActionBase {

  /**
   * Action context.
   *
   * @var array
   *   Contains view data.
   */
  protected $context;

  /**
   * Set context.
   *
   * @param array $context
   *   The context array.
   */
  public function setContext(array $context) {
    foreach ($context as $key => $item) {
      $this->context[$key] = $item;
    }
  }

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
  public function buildPreConfigurationForm(array $element, array $values, FormStateInterface $form_state) {
    return $element;
  }

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
  public function executeMultiple(array $objects) {
    $results = [];
    foreach ($objects as $entity) {
      // Do some processing..
      // ...
      $results[] = 'Some action has been performed';
    }

    return $results;
  }

}
