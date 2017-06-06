<?php

namespace Drupal\views_bulk_operations\Service;

use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Action\ActionManager;

/**
 * Defines VBO action processor.
 */
class ViewsBulkOperationsActionProcessor {

  protected $entityTypeManager;

  protected $actionManager;

  protected $actionLabel;

  protected $action;

  protected $entityType;

  protected $entityStorage;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ActionManager $actionManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->actionManager = $actionManager;
  }

  /**
   * Set values.
   */
  public function initialize(array $view_data) {
    if (!isset($view_data['configuration'])) {
      $view_data['configuration'] = [];
    }
    if (!empty($view_data['preconfiguration'])) {
      $view_data['configuration'] += $view_data['preconfiguration'];
    }

    // Initialize action object.
    $definition = $this->actionManager->getDefinition($view_data['action_id']);
    $this->actionLabel = $definition['label'];
    $this->action = $this->actionManager->createInstance($view_data['action_id'], $view_data['configuration']);

    // Set action context.
    $this->setActionContext($view_data);
    $this->action->context = $view_data;

    // Set-up action processor.
    $this->entityType = $view_data['entity_type'];
    $this->entityStorage = $this->entityTypeManager->getStorage($this->entityType);
  }

  /**
   * Set action context if action method exists.
   *
   * @param array $context
   *   The context to be set.
   */
  public function setActionContext(array $context) {
    if (isset($this->action) && method_exists($this->action, 'setContext')) {
      $this->action->setContext($context);
    }
  }

  /**
   * Process result.
   */
  public function process($entities) {
    $result = $this->action->executeMultiple($entities);
    if (empty($result)) {
      $count = count($entities);
      for ($i = 0; $i < $count; $i++) {
        $output[] = $this->actionLabel;
      }
    }
    else {
      $output = $result;
    }
    return $output;
  }

  /**
   * Get entity for processing.
   */
  public function getEntity($entity_data) {
    $revision_id = NULL;

    // If there are 3 items, vid will be last.
    if (count($entity_data) === 3) {
      $revision_id = array_pop($entity_data);
    }

    // The first two items will always be langcode and ID.
    $id = array_pop($entity_data);
    $langcode = array_pop($entity_data);

    // Load the entity or a specific revision depending on the given key.
    $entity = $revision_id ? $this->entityStorage->loadRevision($revision_id) : $this->entityStorage->load($id);

    if ($entity instanceof TranslatableInterface) {
      $entity = $entity->getTranslation($langcode);
    }

    return $entity;
  }

  /**
   * Get entity translation from views row.
   */
  public function getEntityTranslation($row) {
    if ($row->_entity->isTranslatable()) {
      $language_field = $this->entityType . '_field_data_langcode';
      if ($row->_entity instanceof TranslatableInterface && isset($row->{$language_field})) {
        return $row->_entity->getTranslation($row->{$language_field});
      }
    }
    return $row->_entity;
  }

}
