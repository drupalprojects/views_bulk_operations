<?php

namespace Drupal\views_bulk_operations\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager;
use Drupal\views_bulk_operations\ViewsBulkOperationsBatch;
use Drupal\Core\Url;

/**
 * Action configuration form.
 */
class ConfigureAction extends FormBase {

  /**
   * Constructor.
   */
  public function __construct(PrivateTempStoreFactory $tempStoreFactory, ViewsBulkOperationsActionManager $actionManager) {
    $this->tempStoreFactory = $tempStoreFactory;
    $this->actionManager = $actionManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('plugin.manager.views_bulk_operations_action')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return __CLASS__;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $view_id = NULL, $display_id = NULL) {

    $tempstore_name = 'views_bulk_operations_' . $view_id . '_' . $display_id;
    $tempstore = $this->tempStoreFactory->get($tempstore_name);
    $view_data = $tempstore->get($this->currentUser()->id());
    $view_data['tempstore_name'] = $tempstore_name;

    // TODO: display an error msg, redirect back.
    if (!isset($view_data['action_id'])) {
      return;
    }

    $action = $this->actionManager->createInstance($view_data['action_id']);
    $definition = $this->actionManager->getDefinition($view_data['action_id']);

    $form['#title'] = $this->t('Configure "%action" action applied to the selection', ['%action' => $view_data['action_label']]);

    // :D Make sure the submit button is at the bottom of the form
    // and is editale from the action buildConfigurationForm method.
    $form['actions']['#weight'] = 666;
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#submit' => [
        [$this, 'submitForm'],
      ],
    ];

    if (method_exists($action, 'setContext')) {
      $action->setContext($view_data);
    }

    $form = $action->buildConfigurationForm($form, $form_state);

    $storage = $form_state->getStorage();
    $storage['views_bulk_operations'] = $view_data;
    $form_state->setStorage($storage);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $view_data = $storage['views_bulk_operations'];

    $action = $this->actionManager->createInstance($view_data['action_id']);
    if (method_exists($action, 'validateConfigurationForm')) {
      $action->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $view_data = $storage['views_bulk_operations'];

    $action = $this->actionManager->createInstance($view_data['action_id']);
    if (method_exists($action, 'submitConfigurationForm')) {
      $action->submitConfigurationForm($form, $form_state);
      $view_data['configuration'] = $action->getConfiguration();
    }
    else {
      $form_state->cleanValues();
      $view_data['configuration'] = $form_state->getValues();
    }

    $definition = $this->actionManager->getDefinition($view_data['action_id']);
    if (!empty($definition['confirm_form_route_name'])) {
      // Go to the confirm route.
      $this->tempStoreFactory->get($view_data['tempstore_name'])->set($this->currentUser()->id(), $view_data);
      $form_state->setRedirect($definition['confirm_form_route_name'], [
        'view_id' => $view_data['view_id'],
        'display_id' => $view_data['display_id'],
      ], [
        'query' => $view_data['redirect_uri'],
      ]);
    }
    else {
      // Execute batch process.
      $batch = ViewsBulkOperationsBatch::getBatch($view_data);
      $form_state->setRedirectUrl(Url::fromUserInput($view_data['redirect_uri']['destination']));

      $this->tempStoreFactory->get($view_data['tempstore_name'])->delete($this->currentUser()->id());

      batch_set($batch);
    }
  }

}
