<?php

namespace Drupal\views_bulk_operations\Service;

use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Views;
use Drupal\views\ResultRow;
use Drupal\Core\TypedData\TranslatableInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\views_bulk_operations\ViewsBulkOperationsEvent;

/**
 * Gets Views data needed by VBO.
 */
class ViewsbulkOperationsViewData {

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The current view.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * The realtionship ID.
   *
   * @var string
   */
  protected $relationship;

  /**
   * Views data concerning the current view.
   *
   * @var array
   */
  protected $data;

  /**
   * Entity type ids returned by this view.
   *
   * @var array
   */
  protected $entityTypeIds;

  /**
   * Entity getter data.
   *
   * @var array
   */
  protected $entityGetter;

  /**
   * Object constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   */
  public function __construct(EventDispatcherInterface $eventDispatcher) {
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Initialize additional variables.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view object.
   * @param \Drupal\views\Plugin\views\display\DisplayPluginBase $display
   *   The current display plugin.
   * @param string $relationship
   *   Relationship ID.
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, $relationship) {
    $this->view = $view;
    $this->displayHandler = $display;
    $this->relationship = $relationship;

    // Get view entity types and results fetcher callable.
    $event = new ViewsBulkOperationsEvent($this->getViewProvider(), $this->getData(), $view);
    $this->eventDispatcher->dispatch(ViewsBulkOperationsEvent::NAME, $event);
    $this->entityTypeIds = $event->getEntityTypeIds();
    $this->entityGetter = $event->getEntityGetter();
  }

  /**
   * Get entity type IDs.
   *
   * @return array
   *   Array of entity type IDs.
   */
  public function getEntityTypeIds() {
    return $this->entityTypeIds;
  }

  /**
   * Helper function to get data of the current view.
   *
   * @return array
   *   Part of views data that refers to the current view.
   */
  protected function getData() {
    if (!$this->data) {
      $viewsData = Views::viewsData();
      if (!empty($this->relationship) && $this->relationship != 'none') {
        $relationship = $this->displayHandler->getOption('relationships')[$this->relationship];
        $table_data = $viewsData->get($relationship['table']);
        $this->data = $viewsData->get($table_data[$relationship['field']]['relationship']['base']);
      }
      else {
        $this->data = $viewsData->get($this->view->storage->get('base_table'));
      }
    }
    return $this->data;
  }

  /**
   * Get ID of the entity type associated with the view.
   *
   * @return string
   *   Entity type ID.
   */
  public function getEntityTypeId() {
    $views_data = $this->getData();
    if (isset($views_data['table']['entity type'])) {
      return $views_data['table']['entity type'];
    }
    return FALSE;
  }

  /**
   * Get view provider.
   *
   * @return string
   *   View provider ID.
   */
  public function getViewProvider() {
    $views_data = $this->getData();
    if (isset($views_data['table']['provider'])) {
      return $views_data['table']['provider'];
    }
    return FALSE;
  }

  /**
   * Get entity from views row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Views row object.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An entity object.
   */
  public function getEntity(ResultRow $row) {
    if (!empty($this->entityGetter['file'])) {
      require_once $this->entityGetter['file'];
    }
    if (is_callable($this->entityGetter['callable'])) {
      return call_user_func($this->entityGetter['callable'], $row, $this->relationship, $this->view);
    }
    else {
      if (is_array($this->entityGetter['callable'])) {
        if (is_object($this->entityGetter['callable'][0])) {
          $info = get_class($this->entityGetter['callable'][0]);
        }
        else {
          $info = $this->entityGetter['callable'][0];
        }
        $info .= '::' . $this->entityGetter['callable'][1];
      }
      else {
        $info = $this->entityGetter['callable'];
      }
      throw new \Exception(sprintf("Entity getter method %s doesn't exist.", $info));
    }
  }

  /**
   * Get the total count of results on all pages.
   *
   * @return int
   *   The total number of results this view displays.
   */
  public function getTotalResults() {
    // This number is not correct in $this->view->total_rows for
    // standard entity views, so we build a custom query in such a case.
    if (isset($this->view->query)) {
      $query = $this->view->query->query();
    }
    if (!empty($query)) {
      $total_results = $query->countQuery()->execute()->fetchField();
    }
    else {
      $total_results = $this->view->total_rows;
    }

    if ($offset = $this->view->pager->getOffset()) {
      $total_results -= $offset;
    }

    return $total_results;
  }

  /**
   * The default entity getter function.
   *
   * Works well with standard core entity views.
   *
   * @param \Drupal\views\ResultRow $row
   *   Views result row.
   * @param string $relationship_id
   *   Id of the view relationship.
   * @param \Drupal\views\ViewExecutable $view
   *   The current view object.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The translated entity.
   */
  public function getEntityDefault(ResultRow $row, $relationship_id, ViewExecutable $view) {
    if ($relationship_id == 'none') {
      if (!empty($row->_entity)) {
        $entity = $row->_entity;
      }
    }
    elseif (isset($row->_relationship_entities[$relationship_id])) {
      $entity = $row->_relationship_entities[$relationship_id];
    }
    else {
      throw new \Exception('Unexpected view result row structure.');
    }

    if ($entity->isTranslatable()) {

      // Try to find a field alias for the langcode.
      // Assumption: translatable entities always
      // have a langcode key.
      $language_field = '';
      $langcode_key = $entity->getEntityType()->getKey('langcode');
      $base_table = $view->storage->get('base_table');
      foreach ($view->query->fields as $field) {
        if (
          $field['field'] === $langcode_key && (
            empty($field['base_table']) ||
            $field['base_table'] === $base_table
          )
        ) {
          $language_field = $field['alias'];
          break;
        }
      }
      if (!$language_field) {
        $language_field = $langcode_key;
      }

      if ($entity instanceof TranslatableInterface && isset($row->{$language_field})) {
        return $entity->getTranslation($row->{$language_field});
      }
    }

    return $entity;
  }

}
