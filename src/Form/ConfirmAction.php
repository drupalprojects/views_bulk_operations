<?php

namespace Drupal\views_bulk_operations\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor;
use Drupal\views\Views;

/**
 * Action configuration form.
 */
class ConfirmAction extends FormBase {

  /**
   * User private temporary storage factory.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Views Bulk Operations action manager.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager
   */
  protected $actionManager;

  /**
   * Views Bulk Operations action processor.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor
   */
  protected $actionProcessor;

  /**
   * Constructor.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $tempStoreFactory
   *   User private temporary storage factory.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager $actionManager
   *   Extended action manager object.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor $actionProcessor
   *   Views Bulk Operations action processor.
   */
  public function __construct(PrivateTempStoreFactory $tempStoreFactory, ViewsBulkOperationsActionManager $actionManager, ViewsBulkOperationsActionProcessor $actionProcessor) {
    $this->tempStoreFactory = $tempStoreFactory;
    $this->actionManager = $actionManager;
    $this->actionProcessor = $actionProcessor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('plugin.manager.views_bulk_operations_action'),
      $container->get('views_bulk_operations.processor')
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

    $form_state->setStorage($view_data);

    // Get count of entities to be processed.
    if (!empty($view_data['list'])) {
      $count = count($view_data['list']);
    }
    else {
      $view = Views::getView($view_data['view_id']);
      $view->setDisplay($view_data['display_id']);
      $view->get_total_rows = TRUE;
      if (!empty($view_data['arguments'])) {
        $view->setArguments($view_data['arguments']);
      }
      if (!empty($view_data['exposed_input'])) {
        $view->setExposedInput($view_data['exposed_input']);
      }
      $view->build();
      $query = $view->query->query();
      if (!empty($query)) {
        $count = $query->countQuery()->execute()->fetchField();
      }
      else {
        $view->execute();
        $count = $view->total_rows;
      }
    }

    $form['#title'] = $this->formatPlural(
      $count,
      'Are you sure you wish to perform "%action" action on 1 entity?',
      'Are you sure you wish to perform "%action" action on %count entities?',
      [
        '%action' => $view_data['action_label'],
        '%count' => $count,
      ]
    );

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute action'),
      '#submit' => [
        [$this, 'submitForm'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $view_data = $form_state->getStorage();
    $form_state->setRedirectUrl($view_data['redirect_url']);
    $this->actionProcessor->executeProcessing($view_data);
    $this->tempStoreFactory->get($view_data['tempstore_name'])->delete($this->currentUser()->id());
  }

}
