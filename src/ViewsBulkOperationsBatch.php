<?php

namespace Drupal\views_bulk_operations;

/**
 * Defines module Batch API methods.
 */
class ViewsBulkOperationsBatch {

  /**
   * Translation function wrapper.
   */
  public static function t($string, array $args = [], array $options = []) {
    return \Drupal::translation()->translate($string, $args, $options);
  }

  /**
   * Set message function wrapper.
   */
  public static function message($message = NULL, $type = 'status', $repeat = TRUE) {
    drupal_set_message($message, $type, $repeat);
  }

  /**
   * Gets the list of entities to process.
   *
   * Used in "all results" batch operation.
   *
   * @param array $data
   *   Processed view data.
   * @param mixed $context
   *   Batch context.
   */
  public static function getList(array $data, &$context) {
    // Initialize batch.
    if (empty($context['sandbox'])) {
      $context['sandbox']['processed'] = 0;
      $context['results']['list'] = [];
    }

    $actionProcessor = \Drupal::service('views_bulk_operations.processor');
    $actionProcessor->initialize($data);

    // Populate queue.
    $count = $actionProcessor->populateQueue([], $context);
    if ($count) {
      foreach ($actionProcessor->getQueue() as $index => $entity) {
        $item = [
          $index,
          $entity->language()->getId(),
          $entity->getEntityTypeId(),
          $entity->id(),
        ];
        $context['results']['list'][] = $item;
      }

      $context['finished'] = 0;
      // There may be cases where we don't know the total number of
      // results (e.g. mini pager with a search_api view)
      if ($context['sandbox']['total']) {
        $context['finished'] = $context['sandbox']['processed'] / $context['sandbox']['total'];
        $context['message'] = static::t('Prepared @count of @total entities for processing.', [
          '@count' => $context['sandbox']['processed'],
          '@total' => $context['sandbox']['total'],
        ]);
      }
      else {
        $context['message'] = static::t('Prepared @count entities for processing.', [
          '@count' => $context['sandbox']['processed'],
        ]);
      }
    }

  }

  /**
   * Batch operation callback.
   */
  public static function operation($data, &$context) {
    // Initialize batch.
    if (empty($context['sandbox'])) {
      $context['sandbox']['processed'] = 0;
      $context['results']['operations'] = [];
    }

    // Get list of entities to process.
    if (isset($context['results']['list'])) {
      $list = $context['results']['list'];
    }
    else {
      $list = $data['list'];
    }

    // Get entities to process.
    $actionProcessor = \Drupal::service('views_bulk_operations.processor');
    $actionProcessor->initialize($data);

    // Do the processing.
    $count = $actionProcessor->populateQueue($list, $context);
    if ($count) {
      $batch_results = $actionProcessor->process();
      if (!empty($batch_results)) {
        // Convert translatable markup to strings in order to allow
        // correct operation of array_count_values function.
        foreach ($batch_results as $result) {
          $context['results']['operations'][] = (string) $result;
        }
      }
      $context['sandbox']['processed'] += $count;

      $context['finished'] = 0;
      // There may be cases where we don't know the total number of
      // results (e.g. mini pager with a search_api view)
      if ($context['sandbox']['total']) {
        $context['finished'] = $context['sandbox']['processed'] / $context['sandbox']['total'];
        $context['message'] = static::t('Processed @count of @total entities.', [
          '@count' => $context['sandbox']['processed'],
          '@total' => $context['sandbox']['total'],
        ]);
      }
      else {
        $context['message'] = static::t('Processed @count entities.', [
          '@count' => $context['sandbox']['processed'],
        ]);
      }
    }
  }

  /**
   * Batch finished callback.
   */
  public static function finished($success, $results, $operations) {
    if ($success) {
      $operations = array_count_values($results['operations']);
      $details = [];
      foreach ($operations as $op => $count) {
        $details[] = $op . ' (' . $count . ')';
      }
      $message = static::t('Action processing results: @operations.', [
        '@operations' => implode(', ', $details),
      ]);
      static::message($message);
    }
    else {
      $message = static::t('Finished with an error.');
      static::message($message, 'error');
    }
  }

  /**
   * Batch builder function.
   */
  public static function getBatch($view_data) {
    $current_class = get_called_class();

    $batch = [
      'title' => static::t('Performing @operation on selected entities.', ['@operation' => $view_data['action_label']]),
      'operations' => [],
      'finished' => [$current_class, 'finished'],
      'progress_message' => static::t('Processing, estimated time left: @estimate, elapsed: @elapsed.'),
    ];

    if (empty($view_data['list'])) {
      $batch['operations'][] = [
        [$current_class, 'getList'],
        [$view_data],
      ];
    }

    $batch['operations'][] = [
      [$current_class, 'operation'],
      [$view_data],
    ];

    return $batch;
  }

}
