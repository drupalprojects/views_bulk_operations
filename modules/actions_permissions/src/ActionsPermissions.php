<?php

namespace Drupal\actions_permissions;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Create permissions for existing actions.
 */
class ActionsPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Action manager service.
   *
   * We're using the core action manager, because we need
   * actions unprocessed by the Views Bulk Operations action
   * manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Action\ActionManager $actionManager
   *   The action manager.
   */
  public function __construct(ActionManager $actionManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->actionManager = $actionManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.action'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get permissions for Actions.
   *
   * @return array
   *   Permissions array.
   */
  public function permissions() {
    $permissions = [];
    $entity_type_definitions = $this->entityTypeManager->getDefinitions();

    foreach ($this->actionManager->getDefinitions() as $definition) {

      // Skip actions that define their own requirements.
      if (!empty($definition['requirements'])) {
        continue;
      }

      $id = 'execute ' . $definition['id'];
      $entity_type = NULL;
      if (empty($definition['type'])) {
        $entity_type = $this->t('all entity types');
        $id .= ' all';
      }
      elseif (isset($entity_type_definitions[$definition['type']])) {
        $entity_type = $entity_type_definitions[$definition['type']]->getLabel();
        $id .= ' ' . $definition['type'];
      }

      if (isset($entity_type)) {
        $permissions[$id] = [
          'title' => $this->t('Execute the %action action on %type.', [
            '%action' => $definition['label'],
            '%type' => $entity_type,
          ]),
        ];
      }
    }
    return $permissions;
  }

}
