<?php

namespace Drupal\views_bulk_operations\Service;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;

/**
 * Allow VBO actions to define additional configuration.
 */
class ViewsBulkOperationsActionManager extends ActionManager {

  const EVENT_NAME = 'views_bulk_operations.action_definitions';

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Service constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EventDispatcherInterface $eventDispatcher) {
    parent::__construct($namespaces, $cache_backend, $module_handler);
    $this->eventDispatcher = $eventDispatcher;
    $this->setCacheBackend($cache_backend, 'views_bulk_operations_action_info');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = $this->getCachedDefinitions();
    if (!isset($definitions)) {
      $definitions = $this->findDefinitions();

      foreach ($definitions as $plugin_id => &$definition) {
        if (empty($definition)) {
          unset($definitions[$plugin_id]);
        }
      }

      $this->setCachedDefinitions($definitions);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    // Loading all definitions here will not hurt much, as they're cached.
    $definitions = $this->getDefinitions();
    if (isset($definitions[$plugin_id])) {
      return $definitions[$plugin_id];
    }
    elseif (!$exception_on_invalid) {
      return NULL;
    }

    throw new PluginNotFoundException($plugin_id, sprintf('The "%s" plugin does not exist.', $plugin_id));
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    // Only arrays can be operated on.
    if (!is_array($definition)) {
      return;
    }

    if (!empty($this->defaults) && is_array($this->defaults)) {
      $definition = NestedArray::mergeDeep($this->defaults, $definition);
    }

    // Remove incompatible actions.
    $incompatible = ['node_delete_action'];
    if (!isset($definition['id']) || in_array($definition['id'], $incompatible)) {
      $definition = NULL;
      return;
    }

    // Merge in defaults.
    $definition += [
      'confirm' => FALSE,
      'pass_context' => FALSE,
      'pass_view' => FALSE,
    ];

    // Add default confirmation form if confirm set to TRUE
    // and not explicitly set.
    if ($definition['confirm'] && empty($definition['confirm_form_route_name'])) {
      $definition['confirm_form_route_name'] = 'views_bulk_operations.confirm';
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions) {
    // Let other modules change definitions.
    // Main purpose: Action permissions bridge.
    $event = new Event();
    $event->definitions = &$definitions;
    $this->eventDispatcher->dispatch(static::EVENT_NAME, $event);
  }

}
