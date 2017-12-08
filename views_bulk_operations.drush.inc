<?php

/**
 * @file
 * Contains code providing drush commands functionality.
 */

use Drupal\views\Views;
use Drupal\views_bulk_operations\ViewsBulkOperationsBatch;

/**
 * Implements hook_drush_command().
 */
function views_bulk_operations_drush_command() {
  return [
    'views-bulk-operations-execute' => [
      'description' => 'Execute an action on all results of the given view.',
      'aliases' => ['vbo-execute'],
      'arguments' => [
        'view_id' => 'The ID of the view to use',
        'action_id' => 'The ID of the action to execute',
      ],
      'options' => [
        'display-id' => 'ID of the display to use (default: default)',
        'args' => 'View arguments (slash is a delimeter, default: none)',
        'exposed' => 'Exposed filters (query string format)',
        'batch-size' => 'Processing batch size (default: 100)',
        'config' => 'Action configuration (query string format)',
      ],
      'examples' => [
        'drush vbo-execute some_view some_action --user=1' => 'Execute some action on some view as the superuser.',
        'drush vbo-execute some_view some_action --args=arg1/arg2 --batch-size=50' => 'Execute some action on some view with arg1 and arg2 as view arguments and 50 entities processed per batch.',
        'drush vbo-execute some_view some_action --config="key1=value1&key2=value2"' => 'Execute some action on some view with action configuration set.',
      ],
    ],
  ];
}

/**
 * The vbo-exec command executtion function.
 *
 * @param string $view_id
 *   The ID of the view to use.
 * @param string $action_id
 *   The ID of the action to execute.
 */
function drush_views_bulk_operations_execute($view_id, $action_id) {

  // Prepare parameters.
  $arguments = drush_get_option('args', FALSE);
  if ($arguments) {
    $arguments = explode('/', $arguments);
  }

  $qs_config = [
    'config' => [],
    'exposed' => [],
  ];
  foreach ($qs_config as $name => $value) {
    $config_data = drush_get_option($name, []);
    if (!empty($config_data)) {
      parse_str($config_data, $qs_config[$name]);
    }
  }

  $vbo_data = [
    'list' => [],
    'view_id' => $view_id,
    'display_id' => drush_get_option('display-id', 'default'),
    'action_id' => $action_id,
    'preconfiguration' => $qs_config['config'],
    'batch' => TRUE,
    'arguments' => $arguments,
    'exposed_input' => $qs_config['exposed'],
    'batch_size' => drush_get_option('batch-size', 100),
    'relationship_id' => 'none',
  ];

  // Initialize the view to check if parameters are correct.
  if (!$view = Views::getView($vbo_data['view_id'])) {
    drush_set_error('Incorrect view ID provided.');
    return;
  }
  if (!$view->setDisplay($vbo_data['display_id'])) {
    drush_set_error('Incorrect view display ID provided.');
    return;
  }
  if (!empty($vbo_data['arguments'])) {
    $view->setArguments($vbo_data['arguments']);
  }
  if (!empty($vbo_data['exposed_input'])) {
    $view->setExposedInput($vbo_data['exposed_input']);
  }

  // Get action definition and check if action ID is correct.
  try {
    $action_definition = \Drupal::service('plugin.manager.views_bulk_operations_action')->getDefinition($action_id);
  }
  catch (\Exception $e) {
    drush_set_error($e->getMessage());
    return;
  }
  $vbo_data['action_label'] = $action_definition['label'];

  // Populate entity list.
  $context = [];
  do {
    ViewsBulkOperationsBatch::getList($vbo_data, $context);
    if (!isset($context['finished'])) {
      $context['finished'] = 1;
    }
    drush_log($context['message'], 'ok');
  } while ($context['finished'] < 1);
  $vbo_data = $context['results'];

  // Execute the selected action.
  $context = [];
  do {
    ViewsBulkOperationsBatch::operation($vbo_data, $context);
    if (!isset($context['finished'])) {
      $context['finished'] = 1;
    }
    drush_log($context['message'], 'ok');
  } while ($context['finished'] < 1);

  // Output a summary message.
  $operations = array_count_values($context['results']['operations']);
  $details = [];
  foreach ($operations as $op => $count) {
    $details[] = $op . ' (' . $count . ')';
  }
  drush_log(dt('Action processing results: @results.', ['@results' => implode(', ', $details)]), 'ok');
}
