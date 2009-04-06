<?php

define('TAXONOMY_ACTION_ADD', 0);
define('TAXONOMY_ACTION_REPLACE', 1);
define('TAXONOMY_ACTION_DELETE', 2);
define('TAXONOMY_ACTION_REPLACE_VOCABULARY', 3);

function taxonomy_action_info() {
  if (!module_exists('taxonomy')) return array();
  return array('taxonomy_action' => array(
    'type' => 'node',
    'description' => t('Modify node\'s taxonomy terms'),
    'configurable' => TRUE,
    'behavior' => array('changes_node_property'),
  ));
}

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
  else if ($context['do'] == TAXONOMY_ACTION_REPLACE_VOCABULARY) {
    $existing_terms = taxonomy_node_get_terms($node->nid);
    foreach ($existing_terms as $existing_term) {
      foreach ($terms as $term) {
        if ($term->vid == $existing_term->vid) {
          unset($existing_terms[$existing_term->tid]);
        }
      }
    }
    $terms = array_merge($terms, $existing_terms);
  }

  $node->taxonomy = $terms;
}

function taxonomy_action_form($context) {
	$vocabs = taxonomy_get_vocabularies();
	$form['taxonomy'] = array(
		'#type' => 'fieldset',
		'#title' => t('Vocabularies'),
		'#tree' => true,
	);	
	while (list(,$vocab) = each($vocabs)) {
		$form['taxonomy'][$vocab->vid] = taxonomy_form($vocab->vid);
    $form['taxonomy'][$vocab->vid]['#weight'] = $vocab->weight;
  }

  $form['do'] = array(
    '#type' => 'radios',
    '#title' => t('Action to take'),
		'#default_value' => TAXONOMY_ACTION_ADD,
		'#options' => array(
			TAXONOMY_ACTION_ADD => t('Add the selected terms'),
			TAXONOMY_ACTION_REPLACE => t('Replace existing terms with selected ones'),
      TAXONOMY_ACTION_REPLACE_VOCABULARY => t('Replace terms within same vocabulary'),
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
    'terms' => taxonomy_action_parse_terms($form_values['taxonomy']),
  );
}

/**
 * Take a multi-dimensional array of vocabularies -> selected terms and
 * return an array of only the terms selected
 */
function taxonomy_action_parse_terms($taxonomy) {
	$ret = array();
	while (list(,$vocab) = each($taxonomy)) {
		if (!is_array($vocab)) {
		 	if (!empty($vocab)) $ret[] = $vocab;
		} else {
			while (list(,$tid) = each($vocab)) {
				if (!empty($tid)) $ret[] = $tid;
			}
		}
	}
	return $ret;
}

function taxonomy_action_validate($form_id, $form_values) {
	if ($form_values['do'] != TAXONOMY_ACTION_REPLACE) {
		$terms = taxonomy_action_parse_terms($form_values['taxonomy']);
		if(empty($terms))
    	form_set_error('terms', t('You did not select any term nor did you choose to replace existing terms. Please select one or more terms or choose to replace the terms.'));
  }
}

