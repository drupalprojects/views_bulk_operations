<?php

namespace Drupal\views_bulk_operations\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\views_bulk_operations\ViewsBulkOperationsEvent;

/**
 * Defines module event subscriber class.
 *
 * Allows getting data of core entity views.
 */
class ViewsBulkOperationsEventSubscriber implements EventSubscriberInterface {

  // Subscribe to the VBO event with high priority
  // to prepopulate the event data.
  const PRIORITY = 999;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ViewsBulkOperationsEvent::NAME][] = ['provideViewData', self::PRIORITY];
    return $events;
  }

  /**
   * Respond to view data request event.
   *
   * @var \Drupal\views_bulk_operations\ViewsBulkOperationsEvent $event
   *   The event to respond to.
   */
  public function provideViewData(ViewsBulkOperationsEvent $event) {
    $view_data = $event->getViewData();
    if ($entity_type = $view_data['table']['entity type']) {
      $event->setEntityTypeIds([$entity_type]);
      $event->setEntityGetter([
        'file' => __DIR__ . '/../Service/ViewsBulkOperationsActionProcessor.php',
        'callable' => '\Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor::getEntityFromRow',
      ]);
    }
  }

}
