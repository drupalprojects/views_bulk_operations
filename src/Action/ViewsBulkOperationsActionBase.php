<?php

namespace Drupal\views_bulk_operations\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\views\ViewExecutable;

/**
 * Views Bulk Operations action plugin base.
 *
 * Provides a base implementation for a configurable
 * and preconfigurable VBO Action plugin.
 */
abstract class ViewsBulkOperationsActionBase extends ConfigurableActionBase implements ViewsBulkOperationsActionInterface {

  /**
   * Action context.
   *
   * @var array
   *   Contains view data and optionally batch operation context.
   */
  protected $context;

  /**
   * The processed view.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * {@inheritdoc}
   */
  public function setContext(array $context) {
    foreach ($context as $key => $item) {
      $this->context[$key] = $item;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setView(ViewExecutable $view) {
    $this->view = $view;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $element, array $values, FormStateInterface $form_state) {
    return $element;
  }

  /**
   * {@inheritdoc}
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
