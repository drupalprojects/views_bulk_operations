<?php

namespace Drupal\views_bulk_operations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines VBO controller class.
 */
class ViewsBulkOperationsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * User private temporary storage factory.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Views Bulk Operations action processor.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor
   */
  protected $actionProcessor;

  /**
   * Constructs a new controller object.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $tempStoreFactory
   *   User private temporary storage factory.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor $actionProcessor
   *   Views Bulk Operations action processor.
   */
  public function __construct(PrivateTempStoreFactory $tempStoreFactory, ViewsBulkOperationsActionProcessor $actionProcessor) {
    $this->tempStoreFactory = $tempStoreFactory;
    $this->actionProcessor = $actionProcessor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('views_bulk_operations.processor')
    );
  }

  /**
   * The actual page callback.
   *
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The display ID of the current view.
   */
  public function execute($view_id, $display_id) {
    $tempstore_name = 'views_bulk_operations_' . $view_id . '_' . $display_id;

    $tempstore = $this->tempStoreFactory->get($tempstore_name);
    $view_data = $tempstore->get($this->currentUser()->id());
    $tempstore->delete($this->currentUser()->id());

    $this->actionProcessor->executeProcessing($view_data);
    return batch_process($view_data['redirect_url']);
  }

}
