<?php

namespace Drupal\actions_permissions\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager;

/**
 * Defines module event subscriber class.
 *
 * Allows getting data of core entity views.
 */
class ActionsPermissionsEventSubscriber implements EventSubscriberInterface {

  // Subscribe to the VBO event with high priority
  // to prepopulate the event data.
  const PRIORITY = 999;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ViewsBulkOperationsActionManager::EVENT_NAME][] = ['alterActions', static::PRIORITY];
    return $events;
  }

  /**
   * Respond to view data request event.
   *
   * @var \Symfony\Component\EventDispatcher\Event $event
   *   The event to respond to.
   */
  public function alterActions(Event $event) {
    foreach ($event->definitions as $action_id => $definition) {

      // Only process actions that don't define their own requirements.
      if (empty($definition['requirements'])) {
        $permission_id = 'execute ' . $definition['id'];
        if (empty($definition['type'])) {
          $permission_id .= ' all';
        }
        else {
          $permission_id .= ' ' . $definition['type'];
        }
        $definition['requirements']['_permission'] = $permission_id;
        $event->definitions[$action_id] = $definition;
      }
    }
  }

}
