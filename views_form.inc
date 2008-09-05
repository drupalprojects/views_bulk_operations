<?php
// $Id$

/**
 * @file
 * Helper functions for the views_node_selector form element type. 
 */

/**
 * Theme function for the views_node_selector form element
 *
 * Display the nodes of a view as a form in a table with radios or checkboxes.
 */
function theme_views_node_selector($element) {
  drupal_add_js(drupal_get_path('module', 'views_bulk_operations').'/views_bulk_operations.js');

  $fields = _views_get_fields();
  $view = $element['#view'];
  $multiple = $element['#multiple'];
  $output = '';

  // Make sure we have the table header.
  if (!isset($view->table_header)) {
    $view->table_header = _views_construct_header($view, $fields);
  }

  // Add CSS classes for the whole form.
  $class = 'views-form '. ($element['#multiple'] ? 'views-form-multiple' : 'views-form-single');
  if (isset($element['#attributes']['class'])) {
    $class .= ' '. $element['#attributes']['class'];
  }

  $output .=  '<div class = "'. $class .'">';
  if (!empty($element['#view_nodes']) && is_array($element['#view_nodes'])) {
    $rows = array();
    foreach ($element['#view_nodes'] as $node) {
      $arow = array();
      $arow[] = array('data' => ($multiple ? theme('checkbox', $element[$node->nid]) : theme('radio', $element[$node->nid])));
      
      foreach ($view->field as $field) {
        if ($fields[$field['id']]['visible'] !== FALSE) {
          $cell['data'] = views_theme_field('views_handle_field', $field['queryname'],
            $fields, $field, $node, $view);
          $cell['class'] = "view-field ". views_css_safe('view-field-'. $field['queryname']);
          $arow[] = $cell;
        }
      }
      $rows[] = $arow;
    }
  }
  else  {
    $rows[] = array(array('data' => t('No posts available.'), 'colspan' => (count($view->table_header) + 1)));
  }
  // Add a column to the header to accomodate our selection column, and include
  // the 'select all' checkbox if we are using checkboxes.
  $extra = $multiple ? theme('table_select_header_cell') : '&nbsp;';

  if (is_array($view->table_header) && $view->table_header[0] != $extra) {
    array_unshift($view->table_header, $extra);
  }

  // Add the first row as option to select all records across all pages.
  if ($view->use_pager) {
    $arow = array(
      array(
        'data' => '<span id="vbo-this-page">'. t('All <strong>!nodes</strong> nodes on this page are selected.', array('!nodes' => count($element['#view_nodes'])))
          .'&nbsp;<input type="button" id="vbo-select-all-pages" value="'. t('Select all !nodes nodes in this view.', array('!nodes' => $view->total_rows)) .'" /></span>'
          .'<span id="vbo-all-pages" style="display: none">'. t('All <strong>!nodes</strong> nodes in this view are selected.', array('!nodes' => $view->total_rows)) 
          .'&nbsp;<input type="button" id="vbo-select-this-page" value="'. t('Select only !nodes nodes on this page.', array('!nodes' => count($element['#view_nodes']))) .'" /></span>',
        'class' => 'view-field view-field-select-all',
        'colspan' => count($view->table_header) + 1,
      ),
    );
    array_unshift($rows, $arow);
  }

  $output .= theme('views_form_table', $view->table_header, $rows);
  $output .= theme('hidden', $element['select_all']);
  $output .= '</div>';

  return theme('form_element', $element, $output);
}


/**
 * Default views_node_selector form table theme function.
 */
function theme_views_form_table($header, $rows) {
  return theme('table', $header, $rows);
}

/**
 * True views_node_selector form element expansion function. 
 *
 * @see views_bulk_operations_elements() in views_bulk_operations.module
 * @see expand_views_node_selector()  in views_bulk_operations.module
 * @see form_builder() in Drupal core includes/form.inc
 */
function _expand_views_node_selector($element){
  $options = array();

  foreach ($element['#view_nodes'] as $node) {
    $options[$node->nid] = '';
  }

  $element['#options'] = $options;
  if ($element['#multiple']) {
    $element = expand_checkboxes($element);
  }
  else {
    // Handle the case of an array of default values for radios.
    if (is_array($element['#default_value'])) {
      $element['#default_value'] = current($element['#default_value']);
    }
    $element = expand_radios($element);
  }

  // Check for disabled items
  foreach ($options as $nid => $v) {
    if (in_array($nid, $element['#disabled_nodes'])) {
      $element[$nid]['#disabled'] = TRUE;
    }
    // Disable the theme function for the radio/checkbox elements so that
    // the output can be built in theme_views_node_selector.
    $element[$nid]['#theme'] = '';
    // Add CSS class.
    $element[$nid]['#attributes']['class'] = 'view-field';
  }

  // Ummm unset the #options so that FAPI doesn't give me the "Illegal choice %choice in !name element" error.
  unset($element['#options']);
  $element['select_all'] = array('#type' => 'hidden', '#value' => 0);

  return $element;
}

/**
 * Pre-process default values when using checkboxes.
 *
 * @see form_builder() in Drupal core includes/form.inc
 */
function views_node_selector_value(&$form) {
  if ($form['#multiple']) {
    checkboxes_value($form);
  }
}

