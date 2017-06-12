<?php

namespace Drupal\views_bulk_operations_example\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Unpublishes a node containing certain keywords.
 *
 * @Action(
 *   id = "views_bulk_operations_example",
 *   label = @Translation("VBO example action"),
 *   type = ""
 * )
 */
class ViewsBulkOperationExampleAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  protected $context;

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $objects) {
    $results = [];
    foreach ($objects as $entity) {
      $results[] = $this->execute($entity);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // All config resides in $this->configuration.
    // Passed view rows will be available in $this->context.
    // Do some processing..
    // ...
    return 'Example action';
  }

  /**
   * {@inheritdoc}
   */
  public static function vboConfiguration() {
    return [
      // Use default confirmation form.
      'confirm' => TRUE,
      // Pass result rows to the $context property.
      'pass_rows' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state) {
    $form['example_preconfig_setting'] = [
      '#title' => $this->t('Example setting'),
      '#type' => 'textfield',
      '#default_value' => $values['example_preconfig_setting'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['example_config_setting'] = [
      '#title' => t('Example setting pre-execute'),
      '#type' => 'textfield',
      '#default_value' => $form_state->getValue('example_config_setting'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // This is not required here, when thi method doesn't exist,
    // Form values are assigned to the action configuration by default.
    // This function is a must when result processing is needed.
    $this->configuration['example_config_setting'] = $form_state->getValue('example_config_setting');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $object->access('update', $account, TRUE)
      ->andIf($object->status->access('edit', $account, TRUE));

    return $return_as_object ? $access : $access->isAllowed();
  }

}
