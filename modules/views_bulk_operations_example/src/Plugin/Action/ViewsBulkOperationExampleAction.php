<?php

namespace Drupal\views_bulk_operations_example\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Unpublishes a node containing certain keywords.
 *
 * There are 2 additional parameters in annotation:
 *   - confirm: should a confirm route be used?
 *   (if confirm_form_route_name parameter is not provided or empty,
 *   the VBO default confirm form will be used), actions with
 *   the default confirm form route are always processed using batching,
 *   - pass_rows: should view rows be passed to the action context?
 *
 * If type is left empty, action will be possible for all
 * entity types.
 *
 * @Action(
 *   id = "views_bulk_operations_example",
 *   label = @Translation("VBO example action"),
 *   type = "",
 *   confirm = TRUE,
 *   confirm_form_route_name = "",
 *   pass_rows = TRUE
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
