<?php

namespace Drupal\views_bulk_operations\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * Defines common methods for Views Bulk Operations forms.
 */
trait ViewsBulkOperationsFormTrait {

  /**
   * The tempstore object associated with the current view.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $viewTempstore;

  /**
   * The tempstore name.
   *
   * @var string
   */
  protected $tempStoreName;

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
    $form_data = $this->getTempstoreData($view_id, $display_id);

    // Get data needed for selected entities list.
    if (!empty($form_data['list'])) {
      $form_data['entity_labels'] = [];
      $form_data['selected_count'] = 0;
      foreach ($form_data['list'] as $item) {
        $form_data['selected_count']++;
        $form_data['entity_labels'][] = $item[0];
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
   * @param mixed $base_field_value
   *   The value of the base field for this result.
   *
   * @return string
   *   The bulk form key representing the entity's id, language and revision (if
   *   applicable) as one string.
   *
   * @see self::loadEntityFromBulkFormKey()
   */
  public static function calculateEntityBulkFormKey(EntityInterface $entity, $use_revision, $base_field_value) {
    $key_parts = [
      $base_field_value,
      $entity->language()->getId(),
      $entity->getEntityTypeId(),
      $entity->id(),
      0,
    ];

    if ($entity instanceof RevisionableInterface && $use_revision) {
      $key_parts[4] = $entity->getRevisionId();
    }

    // An entity ID could be an arbitrary string (although they are typically
    // numeric). JSON then Base64 encoding ensures the bulk_form_key is
    // safe to use in HTML, and that the key parts can be retrieved.
    $key = json_encode($key_parts);
    return base64_encode($key);
  }

  /**
   * Initialize the current view tempstore object.
   */
  protected function getTempstore($view_id = NULL, $display_id = NULL) {
    if (!isset($this->viewTempstore)) {
      $this->tempStoreName = 'views_bulk_operations_' . $view_id . '_' . $display_id;
      $this->viewTempstore = $this->tempStoreFactory->get($this->tempStoreName);
    }
    return $this->viewTempstore;
  }

  /**
   * Gets the current view user tempstore data.
   *
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The display ID of the current view.
   */
  protected function getTempstoreData($view_id = NULL, $display_id = NULL) {
    $data = $this->getTempstore($view_id, $display_id)->get($this->currentUser()->id());
    if (!empty($data)) {
      $data['tempstore_name'] = $this->tempStoreName;
    }

    return $data;
  }

  /**
   * Sets the current view user tempstore data.
   *
   * @param array $data
   *   The data to set.
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The display ID of the current view.
   */
  protected function setTempstoreData(array $data, $view_id = NULL, $display_id = NULL) {
    return $this->getTempstore($view_id, $display_id)->set($this->currentUser()->id(), $data);
  }

  /**
   * Deletes the current view user tempstore data.
   *
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The display ID of the current view.
   */
  protected function deleteTempstoreData($view_id = NULL, $display_id = NULL) {
    return $this->getTempstore($view_id, $display_id)->delete($this->currentUser()->id());
  }

}
