<?php

namespace Drupal\views_bulk_operations\Service;

use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Views;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;
use Drupal\Core\Entity\EntityInterface;
use Drupal\views_bulk_operations\ViewsBulkOperationsBatch;

/**
 * Defines VBO action processor.
 */
class ViewsBulkOperationsActionProcessor {

  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * VBO action manager.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager
   */
  protected $actionManager;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $user;

  /**
   * Definition of the processed action.
   *
   * @var array
   */
  protected $actionDefinition;

  /**
   * The processed action object.
   *
   * @var array
   */
  protected $action;

  /**
   * View data from the bulk form.
   *
   * @var array
   */
  protected $bulkFormData;

  /**
   * View data provider service.
   *
   * @var \Drupal\views_bulk_operations\ViewsbulkOperationsViewData
   */
  protected $viewDataService;

  /**
   * Array of entities that will be processed in the current batch.
   *
   * @var array
   */
  protected $queue;

  /**
   * Constructor.
   */
  public function __construct(ViewsbulkOperationsViewData $viewDataService, EntityTypeManagerInterface $entityTypeManager, ViewsBulkOperationsActionManager $actionManager, AccountProxyInterface $user) {
    $this->viewDataService = $viewDataService;
    $this->entityTypeManager = $entityTypeManager;
    $this->actionManager = $actionManager;
    $this->user = $user;
  }

  /**
   * Set values.
   *
   * @param array $view_data
   *   Data concerning the view that will be processed.
   */
  public function initialize(array $view_data) {
    if (!isset($view_data['configuration'])) {
      $view_data['configuration'] = [];
    }
    if (!empty($view_data['preconfiguration'])) {
      $view_data['configuration'] += $view_data['preconfiguration'];
    }

    // Initialize action object.
    $this->actionDefinition = $this->actionManager->getDefinition($view_data['action_id']);
    $this->action = $this->actionManager->createInstance($view_data['action_id'], $view_data['configuration']);

    // Set action context.
    $this->setActionContext($view_data);

    // Set entire view data as object parameter for future reference.
    $this->bulkFormData = $view_data;
  }

  /**
   * Populate entity queue for processing.
   */
  public function populateQueue($list, $data, &$context = []) {
    $this->queue = [];

    // Get the view if entity list is empty
    // or we have to pass rows to the action.
    if (empty($list) || $this->actionDefinition['pass_view']) {
      $view = Views::getView($data['view_id']);
      $view->setDisplay($data['display_id']);
      if (!empty($data['arguments'])) {
        $view->setArguments($data['arguments']);
      }
      if (!empty($data['exposed_input'])) {
        $view->setExposedInput($data['exposed_input']);
      }
      $view->build();
    }

    // Determine batch size and offset.
    if (!empty($context)) {
      $batch_size = empty($data['batch_size']) ? 10 : $data['batch_size'];
      if (!isset($context['sandbox']['current_batch'])) {
        $context['sandbox']['current_batch'] = 0;
      }
      $current_batch = &$context['sandbox']['current_batch'];
      $offset = $current_batch * $batch_size;
    }
    else {
      $batch_size = 0;
      $current_batch = 0;
      $offset = 0;
    }

    // Get view results if required.
    if (empty($list)) {
      $view->setCurrentPage($current_batch);
      $view->setItemsPerPage($batch_size);

      // If the view doesn't start from the first result,
      // move the offset.
      if ($view_offset = $view->pager->getOffset()) {
        $offset += $view_offset;
      }
      $view->query->setLimit($batch_size);
      $view->query->setOffset($offset);
      $view->query->execute($view);

      // Prepare result getter.
      $this->viewDataService->init($view, $view->getDisplay(), $this->bulkFormData['relationship_id']);
      foreach ($view->result as $row) {
        $this->queue[] = $this->viewDataService->getEntity($row);
      }
    }
    else {
      if ($batch_size) {
        $batch_list = array_slice($list, $offset, $batch_size);
      }
      else {
        $batch_list = $list;
      }
      foreach ($batch_list as $item) {
        $this->queue[] = $this->getEntity($item);
      }

      // Get view rows if required.
      if ($this->actionDefinition['pass_view']) {
        $this->getViewResult($view, $batch_list);
      }
    }

    // Extra processing when executed in a Batch API operation.
    if (!empty($context)) {
      if (!isset($context['sandbox']['total'])) {
        if (empty($list)) {
          $query = $view->query->query();
          if (!empty($query)) {
            $context['sandbox']['total'] = $this->viewDataService->getTotalResults();
          }
          else {
            if (empty($view->result)) {
              $view->query->execute($view);
            }
            $context['sandbox']['total'] = $view->total_rows;
          }
        }
        else {
          $context['sandbox']['total'] = count($list);
        }
      }
      if ($this->actionDefinition['pass_context']) {
        $this->action->setContext($context);
      }
    }

    if ($batch_size) {
      $current_batch++;
    }

    if ($this->actionDefinition['pass_view']) {
      $this->action->setView($view);
    }

    return count($this->queue);
  }

  /**
   * Set action context if action method exists.
   *
   * @param array $context
   *   The context to be set.
   */
  public function setActionContext(array $context) {
    if (isset($this->action) && method_exists($this->action, 'setContext')) {
      $this->action->setContext($context);
    }
  }

  /**
   * Process result.
   */
  public function process() {
    $output = [];

    // Check if all queue items are actually Drupal entities.
    foreach ($this->queue as $delta => $entity) {
      if (!($entity instanceof EntityInterface)) {
        $output[] = $this->t('Skipped');
        unset($this->queue[$delta]);
      }
    }

    // Check entity type for multi-type views like search_api index.
    if (!empty($this->actionDefinition['type'])) {
      foreach ($this->queue as $delta => $entity) {
        if ($entity->getEntityTypeId() !== $this->actionDefinition['type']) {
          $output[] = $this->t('Entity type not supported');
          unset($this->queue[$delta]);
        }
      }
    }

    // Check access.
    foreach ($this->queue as $delta => $entity) {
      if (!$this->action->access($entity, $this->user)) {
        $output[] = $this->t('Access denied');
        unset($this->queue[$delta]);
      }
    }

    // Process queue.
    $results = $this->action->executeMultiple($this->queue);

    // Populate output.
    if (empty($results)) {
      $count = count($this->queue);
      for ($i = 0; $i < $count; $i++) {
        $output[] = $this->bulkFormData['action_label'];
      }
    }
    else {
      foreach ($results as $result) {
        $output[] = $result;
      }
    }
    return $output;
  }

  /**
   * Helper function for processing results from view data.
   *
   * @param array $data
   *   Data concerning the view that will be processed.
   */
  public function executeProcessing(array $data) {
    if ($data['batch']) {
      $batch = ViewsBulkOperationsBatch::getBatch($data);
      batch_set($batch);
    }
    else {
      $list = $data['list'];
      unset($data['list']);

      // Populate and process queue.
      $this->initialize($data);
      if ($this->populateQueue($list, $data)) {
        $batch_results = $this->process();
      }

      $results = [];
      foreach ($batch_results as $result) {
        $results[] = (string) $result;
      }
      ViewsBulkOperationsBatch::finished(TRUE, $results, []);
    }
  }

  /**
   * Get entity for processing.
   */
  public function getEntity($entity_data) {
    if (!isset($entity_data[4])) {
      $entity_data[4] = FALSE;
    }
    list(, $langcode, $entity_type_id, $id, $revision_id) = $entity_data;

    // Load the entity or a specific revision depending on the given key.
    $entityStorage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity = $revision_id ? $entityStorage->loadRevision($revision_id) : $entityStorage->load($id);

    if ($entity instanceof TranslatableInterface) {
      $entity = $entity->getTranslation($langcode);
    }

    return $entity;
  }

  /**
   * Populate view result with selected rows.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view object.
   * @param array $list
   *   User selection data.
   */
  protected function getViewResult(ViewExecutable $view, array $list) {
    $view->query->execute($view);

    // Filter result using the $list array.
    $selected = [];
    foreach ($list as $item) {
      $selected[$item[0]] = $item[0];
    }
    foreach ($view->result as $delta => $row) {
      if (!isset($selected[$delta])) {
        unset($view->result[$delta]);
      }
    }
    $view->result = array_values($view->result);
  }

}
