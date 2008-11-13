<?php

define('TAXONOMY_ACTION_ADD', 0);
define('TAXONOMY_ACTION_REPLACE', 1);
define('TAXONOMY_ACTION_DELETE', 2);

function taxonomy_action(&$node, $context) {
  $terms = array();
  foreach ($context['terms'] as $tid) {
    $terms[$tid] = taxonomy_get_term($tid);
  }
	// check for add or delete
  if ($context['do'] == TAXONOMY_ACTION_ADD || $context['do'] == TAXONOMY_ACTION_DELETE) {
		$existing_terms = taxonomy_node_get_terms($node->nid);
		if ($context['do'] == TAXONOMY_ACTION_DELETE) {
			// delete terms
			while (list($delete_tid) = each($terms)) {
				if (array_key_exists($delete_tid,$existing_terms)) {
					unset($existing_terms[$delete_tid]);
				}
			}
			$terms = $existing_terms;
		} else {
			// add terms
      foreach ($terms as $add_tid => $term) {
        if (array_key_exists($add_tid, $existing_terms)) {
          unset($terms[$add_tid]);
        }
      }
    	$terms = array_merge($terms, $existing_terms);
		}
  }
  $node->taxonomy = $terms;
}

function taxonomy_action_form($context) {
  foreach (taxonomy_get_vocabularies() as $vid => $vocabulary) {
    foreach (taxonomy_get_tree($vid) as $term) {
      $terms[$term->tid] = $vocabulary->name.': '.$term->name;
    }
  }
  $form['terms'] = array(
    '#type' => 'select',
    '#title' => t('Terms'),
    '#options' => $terms,
    '#default_value' => NULL,
    '#multiple' => TRUE,
    '#size' => min(20, count($terms)),
    '#description' => t('Choose the terms you want to associate with or delete from the nodes.'),
		'#weight' => -5,
  );
  $form['do'] = array(
    '#type' => 'radios',
    '#title' => t('Action to take'),
		'#default_value' => TAXONOMY_ACTION_ADD,
		'#options' => array(
			TAXONOMY_ACTION_ADD => t('Add the selected terms'),
			TAXONOMY_ACTION_REPLACE => t('Replace existing terms with selected ones'),
			TAXONOMY_ACTION_DELETE => t('Delete selected terms')
		),
		'#required' => TRUE,
		'#weight' => -2,
  );
	return $form;
}

function taxonomy_action_submit($form_id, $form_values) {
  return array(
    'do' => $form_values['do'], 
    'terms' => $form_values['terms'],
  );
}

function taxonomy_action_validate($form_id, $form_values) {
  if ($form_values['do'] != TAXONOMY_ACTION_REPLACE && empty($form_values['terms'])) {
    form_set_error('terms', t('You did not select any term nor did you choose to replace existing terms. Please select one or more terms or choose to replace the terms.'));
  }
}

