<?php

namespace Drupal\views_bulk_operations;

use Drupal\views\Views;

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
   * Batch operation callback.
   */
  public static function operation($list, $data, &$context) {
    // Initialize batch.
    if (empty($context['sandbox'])) {
      $context['sandbox']['processed'] = 0;
      $context['results'] = [];
    }

    // Get entities to process.
    $batch_size = empty($data['batch_size']) ? 10 : $data['batch_size'];
    $actionProcessor = \Drupal::service('views_bulk_operations.processor');
    $actionProcessor->initialize($data);
    $entities = [];

    if (empty($list)) {

      // Load results from view if list is not provided.
      if (!isset($context['sandbox']['offset'])) {
        $context['sandbox']['offset'] = 0;
      }

      $view = Views::getView($data['view_id']);
      $view->setDisplay($data['display_id']);
      if (!empty($data['arguments'])) {
        $view->setArguments($data['arguments']);
      }
      if (!empty($data['exposed_input'])) {
        $view->setExposedInput($data['exposed_input']);
      }
      $view->setItemsPerPage($batch_size);
      $view->setOffset($context['sandbox']['offset']);
      $view->usePager();
      $view->execute();

      foreach ($view->result as $delta => $row) {
        $entities[] = $actionProcessor->getEntityTranslation($row);
      }
      $context['sandbox']['offset'] += $batch_size;

      if (!isset($context['sandbox']['total'])) {
        $context['sandbox']['total'] = $view->query->query()->countQuery()->execute()->fetchField();
      }
    }
    else {
      $list = array_slice($list, $context['sandbox']['processed'], $batch_size);
      foreach ($list as $item) {
        $entities[] = $actionProcessor->getEntity($item);
      }
      if (!isset($context['sandbox']['total'])) {
        $context['sandbox']['total'] = count($list);
      }
    }

    // Do the processing.
    if (!empty($entities)) {
      $batch_results = $actionProcessor->process($entities);
      if (!empty($batch_results)) {
        // Convert translatable markup to strings in order to allow
        // correct operation of array_count_values function.
        foreach ($batch_results as $result) {
          $context['results'][] = (string) $result;
        }
      }
      $context['sandbox']['processed'] += count($entities);
      $context['finished'] = $context['sandbox']['processed'] / $context['sandbox']['total'];
      $context['message'] = static::t('Processed @count of @total entities.', [
        '@count' => $context['sandbox']['processed'],
        '@total' => $context['sandbox']['total'],
      ]);
    }
  }

  /**
   * Batch finished callback.
   */
  public static function finished($success, $results, $operations) {
    if ($success) {
      $nres = count($results);
      $operations = array_count_values($results);
      $details = [];
      foreach ($operations as $op => $count) {
        $details[] = $op . ': ' . $count;
      }
      if (count($details) === 1) {
        $keys = array_keys($operations);
        $message = static::t('@operation operation performed on @count results.', [
          '@operation' => $keys[0],
          '@count' => $nres,
        ]);
      }
      else {
        $message = static::t('Operations performed: @operations.', [
          '@operations' => implode(', ', $details),
        ]);
      }
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
    $results = $view_data['list'];
    unset($view_data['list']);

    return [
      'title' => static::t('Performing @operation on selected entities.', ['@operation' => $view_data['action_label']]),
      'operations' => [
        [
          ['\Drupal\views_bulk_operations\ViewsBulkOperationsBatch', 'operation'],
          [
            $results,
            $view_data,
          ],
        ],
      ],
      'finished' => ['\Drupal\views_bulk_operations\ViewsBulkOperationsBatch', 'finished'],
    ];
  }

}
