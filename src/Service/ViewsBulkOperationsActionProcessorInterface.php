<?php

namespace Drupal\views_bulk_operations\Service;

/**
 * Defines Views Bulk Operations action processor.
 */
interface ViewsBulkOperationsActionProcessorInterface {

  /**
   * Set values.
   *
   * @param array $view_data
   *   Data concerning the view that will be processed.
   * @param mixed $view
   *   The current view object or NULL.
   */
  public function initialize(array $view_data, $view = NULL);

  /**
   * Populate entity queue for processing.
   *
   * @param array $list
   *   Array of selected view results.
   * @param array $context
   *   Batch API context.
   */
  public function populateQueue(array $list, array &$context = []);

  /**
   * Set action context if action method exists.
   *
   * @param array $context
   *   The context to be set.
   */
  public function setActionContext(array $context);

  /**
   * Process results.
   */
  public function process();

  /**
   * Helper function for processing results from view data.
   *
   * @param array $data
   *   Data concerning the view that will be processed.
   * @param mixed $view
   *   The current view object or NULL.
   */
  public function executeProcessing(array &$data, $view = NULL);

  /**
   * Get entity for processing.
   *
   * @param array $entity_data
   *   Entity data array.
   */
  public function getEntity(array $entity_data);

  /**
   * Get the current entity queue.
   *
   * @return array
   *   Array of entities from the current queue.
   */
  public function getQueue();

}
