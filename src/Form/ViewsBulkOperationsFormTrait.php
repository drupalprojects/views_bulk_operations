<?php

namespace Drupal\views_bulk_operations\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;

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
    if (!empty($form_data['list'])) {
      $form_data['entity_labels'] = [];
      $form_data['selected_count'] = 0;
      foreach ($form_data['list'] as $page => $items) {
        foreach ($items as $item) {
          $form_data['selected_count']++;
          $form_data['entity_labels'][] = $item[0];
        }
      }
    }
    elseif ($form_data['total_results']) {
      $form_data['selected_count'] = $form_data['total_results'];
    }
    else {
      $form_data['selected_count'] = (string) $this->t('all');
    }

    return $form_data;
  }

  /**
   * Calculates a bulk form key.
   *
   * This generates a key that is used as the checkbox return value when
   * submitting a bulk form. This key allows the entity for the row to be loaded
   * totally independently of the executed view row.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to calculate a bulk form key for.
   * @param bool $use_revision
   *   Whether the revision id should be added to the bulk form key. This should
   *   be set to TRUE only if the view is listing entity revisions.
   * @param int $row_index
   *   Index of the views row that contains the entity.
   *
   * @return string
   *   The bulk form key representing the entity's id, language and revision (if
   *   applicable) as one string.
   *
   * @see self::loadEntityFromBulkFormKey()
   */
  public static function calculateEntityBulkFormKey(EntityInterface $entity, $use_revision, $row_index) {
    $key_parts = [
      $entity->language()->getId(),
      $entity->getEntityTypeId(),
      $entity->id(),
    ];

    if ($entity instanceof RevisionableInterface && $use_revision) {
      $key_parts[] = $entity->getRevisionId();
    }

    // An entity ID could be an arbitrary string (although they are typically
    // numeric). JSON then Base64 encoding ensures the bulk_form_key is
    // safe to use in HTML, and that the key parts can be retrieved.
    $key = json_encode($key_parts);
    return base64_encode($key);
  }

}
