<?php

namespace Drupal\views_bulk_operations\Form;

/**
 * Defines common methods for Views Bulk Operations forms.
 */
trait ViewsBulkOperationsFormTrait {

  /**
   * Helper function to prepare data needed for proper form display.
   *
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The current view display ID.
   *
   * @return array
   *   Array containing data for the form builder.
   */
  protected function getFormData($view_id, $display_id) {

    // Get tempstore data.
    $tempstore_name = 'views_bulk_operations_' . $view_id . '_' . $display_id;
    $tempstore = $this->tempStoreFactory->get($tempstore_name);
    $form_data = $tempstore->get($this->currentUser()->id());
    $form_data['tempstore_name'] = $tempstore_name;

    // Get data needed for selected entities list.
    if (!empty($form_data['entity_labels'])) {
      $form_data['selected_count'] = count($form_data['entity_labels']);
    }
    elseif ($form_data['total_results']) {
      $form_data['selected_count'] = $form_data['total_results'];
    }
    else {
      $form_data['selected_count'] = (string) $this->t('all');
    }

    return $form_data;
  }

}
