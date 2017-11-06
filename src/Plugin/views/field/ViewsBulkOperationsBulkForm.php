<?php

namespace Drupal\views_bulk_operations\Plugin\views\field;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\field\UncacheableFieldHandlerTrait;
use Drupal\views\Plugin\views\style\Table;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views_bulk_operations\Service\ViewsbulkOperationsViewData;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Defines a actions-based bulk operation form element.
 *
 * @ViewsField("views_bulk_operations_bulk_form")
 * @class
 */
class ViewsBulkOperationsBulkForm extends FieldPluginBase implements CacheableDependencyInterface {

  use RedirectDestinationTrait;
  use UncacheableFieldHandlerTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Object that gets the current view data.
   *
   * @var \Drupal\views_bulk_operations\ViewsbulkOperationsViewData
   */
  protected $viewData;

  /**
   * Views Bulk Operations action manager.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager
   */
  protected $actionManager;

  /**
   * Views Bulk Operations action processor.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor
   */
  protected $actionProcessor;

  /**
   * User private temporary storage factory.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * An array of actions that can be executed.
   *
   * @var array
   */
  protected $actions = [];

  /**
   * An array of bulk form options.
   *
   * @var array
   */
  protected $bulkOptions;

  /**
   * The current user temporary storage.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $userTempStore;

  /**
   * Constructs a new BulkForm object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\views_bulk_operations\Service\ViewsbulkOperationsViewData $viewData
   *   The VBO View Data provider service.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager $actionManager
   *   Extended action manager object.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor $actionProcessor
   *   Views Bulk Operations action processor.
   * @param \Drupal\user\PrivateTempStoreFactory $tempStoreFactory
   *   User private temporary storage factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager, ViewsbulkOperationsViewData $viewData, ViewsBulkOperationsActionManager $actionManager, ViewsBulkOperationsActionProcessor $actionProcessor, PrivateTempStoreFactory $tempStoreFactory, AccountInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->languageManager = $language_manager;
    $this->viewData = $viewData;
    $this->actionManager = $actionManager;
    $this->actionProcessor = $actionProcessor;
    $this->tempStoreFactory = $tempStoreFactory;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('views_bulk_operations.data'),
      $container->get('plugin.manager.views_bulk_operations_action'),
      $container->get('views_bulk_operations.processor'),
      $container->get('user.private_tempstore'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    // Initialize VBO View Data object.
    $this->viewData->init($view, $display, $this->options['relationship']);

    // Fetch actions.
    $this->actions = [];
    $entity_types = $this->viewData->getEntityTypeIds();

    // Get actions only if there are any entity types set for the view.
    if (!empty($entity_types)) {
      foreach ($this->actionManager->getDefinitions() as $id => $definition) {
        if (empty($definition['type']) || in_array($definition['type'], $entity_types, TRUE)) {
          $this->actions[$id] = $definition;
        }
      }
    }

    // Initialize tempstore object.
    $tempstore_name = 'views_bulk_operations_' . $view->id() . '_' . $view->current_display;
    $this->userTempStore = $this->tempStoreFactory->get($tempstore_name);

    // Force form_step setting to TRUE due to #2879310.
    $this->options['form_step'] = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // @todo Consider making the bulk operation form cacheable. See
    //   https://www.drupal.org/node/2503009.
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLanguageManager() {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getView() {
    return $this->view;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(ResultRow $row) {
    return $this->viewData->getEntity($row);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['batch'] = ['default' => TRUE];
    $options['batch_size'] = ['default' => 10];
    $options['form_step'] = ['default' => TRUE];
    $options['action_title'] = ['default' => $this->t('Action')];
    $options['selected_actions'] = ['default' => []];
    $options['preconfiguration'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    // If the view type is not supported, suppress form display.
    // Also display information note to the user.
    if (empty($this->actions)) {
      $form = [
        '#type' => 'item',
        '#title' => $this->t('NOTE'),
        '#markup' => $this->t('Views Bulk Operations will work only with normal entity views and contrib module views that are integrated. See /Drupal\views_bulk_operations\EventSubscriber\ViewsBulkOperationsEventSubscriber class for integration best practice.'),
        '#prefix' => '<div class="scroll">',
        '#suffix' => '</div>',
      ];
      return;
    }

    $form['#attributes']['class'][] = 'views-bulk-operations-ui';
    $form['#attached']['library'][] = 'views_bulk_operations/adminUi';

    $form['batch'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Process in a batch operation'),
      '#default_value' => $this->options['batch'],
    ];

    $form['batch_size'] = [
      '#title' => $this->t('Batch size'),
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#description' => $this->t('Only applicable if results are processed in a batch operation.'),
      '#default_value' => $this->options['batch_size'],
    ];

    $form['form_step'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Configuration form on new page (configurable actions)'),
      '#default_value' => $this->options['form_step'],
      // Due to #2879310 this setting must always be at TRUE.
      '#access' => FALSE,
    ];

    $form['action_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Action title'),
      '#default_value' => $this->options['action_title'],
      '#description' => $this->t('The title shown above the actions dropdown.'),
    ];

    $form['selected_actions'] = [
      '#tree' => TRUE,
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Selected actions'),
    ];

    // Load values for AJAX functionality.
    $form_values = $form_state->getValue(['options', 'selected_actions']);
    if (is_null($form_values)) {
      $selected_actions = $this->options['selected_actions'];
      $preconfiguration = $this->options['preconfiguration'];
    }
    else {
      $selected_actions = [];
      $preconfiguration = [];
      foreach ($form_values as $id => $value) {
        $selected_actions[$id] = $value['state'] ? $id : 0;
        $preconfiguration[$id] = isset($value['preconfiguration']) ? $value['preconfiguration'] : [];
      }
    }

    foreach ($this->actions as $id => $action) {
      $form['selected_actions'][$id]['state'] = [
        '#type' => 'checkbox',
        '#title' => $action['label'],
        '#default_value' => empty($selected_actions[$id]) ? 0 : 1,
        '#attributes' => ['class' => ['action-state']],
      ];

      // There are problems with AJAX on this form when adding
      // new elements (Views issue), a workaround is to render
      // all elements and show/hide them when needed.
      $form['selected_actions'][$id]['preconfiguration'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Preconfiguration for "@action"', [
          '@action' => $action['label'],
        ]),
        '#attributes' => [
          'data-for' => $id,
          'style' => empty($selected_actions[$id]) ? 'display: none' : NULL,
        ],
      ];

      // Default label_override element.
      $form['selected_actions'][$id]['preconfiguration']['label_override'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Override label'),
        '#description' => $this->t('Leave empty for the default label.'),
        '#default_value' => isset($preconfiguration[$id]['label_override']) ? $preconfiguration[$id]['label_override'] : '',
      ];

      // Load preconfiguration form if available.
      if (method_exists($action['class'], 'buildPreConfigurationForm')) {
        if (!isset($preconfiguration[$id])) {
          $preconfiguration[$id] = [];
        }
        $actionObject = $this->actionManager->createInstance($id);
        $form['selected_actions'][$id]['preconfiguration'] = $actionObject->buildPreConfigurationForm($form['selected_actions'][$id]['preconfiguration'], $preconfiguration[$id], $form_state);
      }
    }

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $options = &$form_state->getValue('options');
    foreach ($options['selected_actions'] as $id => $action) {
      if (!empty($action['state'])) {
        if (isset($action['preconfiguration'])) {
          $options['preconfiguration'][$id] = $action['preconfiguration'];
          unset($options['selected_actions'][$id]['preconfiguration']);
        }
        $options['selected_actions'][$id] = $id;
      }
      else {
        unset($options['preconfiguration'][$id]);
        $options['selected_actions'][$id] = 0;
      }
    }
    parent::submitOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    parent::preRender($values);

    // Add empty classes if there are no actions available.
    if (empty($this->getBulkOptions())) {
      $this->options['element_label_class'] .= 'empty';
      $this->options['element_class'] .= 'empty';
      $this->options['element_wrapper_class'] .= 'empty';
      $this->options['label'] = '';
    }
    // If the view is using a table style, provide a placeholder for a
    // "select all" checkbox.
    elseif (!empty($this->view->style_plugin) && $this->view->style_plugin instanceof Table) {
      // Add the tableselect css classes.
      $this->options['element_label_class'] .= 'select-all';
      // Hide the actual label of the field on the table header.
      $this->options['label'] = '';
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $row, $field = NULL) {
    return '<!--form-item-' . $this->options['id'] . '--' . $row->index . '-->';
  }

  /**
   * Form constructor for the bulk form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    // Make sure we do not accidentally cache this form.
    // @todo Evaluate this again in https://www.drupal.org/node/2503009.
    $form['#cache']['max-age'] = 0;

    $use_revision = array_key_exists('revision', $this->view->getQuery()->getEntityTableInfo());

    // Add select all and tableselect libraries for table display style.
    if ($this->view->style_plugin instanceof Table) {
      $form['#attached']['library'][] = 'core/drupal.tableselect';
      $form['#attached']['library'][] = 'views_bulk_operations/selectAll';
    }

    // Only add the bulk form options and buttons if
    // there are results and any actions are available.
    $action_options = $this->getBulkOptions();
    if (!empty($this->view->result) && !empty($action_options)) {

      // Render checkboxes for all rows.
      $form[$this->options['id']]['#tree'] = TRUE;
      foreach ($this->view->result as $row_index => $row) {
        $entity = $this->getEntity($row);

        $form[$this->options['id']][$row_index] = [
          '#type' => 'checkbox',
          // We are not able to determine a main "title" for each row, so we can
          // only output a generic label.
          '#title' => $this->t('Update this item'),
          '#title_display' => 'invisible',
          '#default_value' => !empty($form_state->getValue($this->options['id'])[$row_index]) ? 1 : NULL,
          '#return_value' => self::calculateEntityBulkFormKey($entity, $use_revision, $row_index),
        ];
      }

      // Replace the form submit button label.
      $form['actions']['submit']['#value'] = $this->t('Apply to selected items');

      // Ensure a consistent container for filters/operations
      // in the view header.
      $form['header'] = [
        '#type' => 'container',
        '#weight' => -100,
      ];

      // Build the bulk operations action widget for the header.
      // Allow themes to apply .container-inline on this separate container.
      $form['header'][$this->options['id']] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'vbo-action-form-wrapper',
        ],
      ];
      $form['header'][$this->options['id']]['action'] = [
        '#type' => 'select',
        '#title' => $this->options['action_title'],
        '#options' => ['' => $this->t('-- Select action --')] + $action_options,
      ];

      // Add AJAX functionality if actions are configurable through this form.
      if (empty($this->options['form_step'])) {
        $form['header'][$this->options['id']]['action']['#ajax'] = [
          'callback' => [__CLASS__, 'viewsFormAjax'],
          'wrapper' => 'vbo-action-configuration-wrapper',
        ];
        $form['header'][$this->options['id']]['configuration'] = [
          '#type' => 'container',
          '#attributes' => ['id' => 'vbo-action-configuration-wrapper'],
        ];

        $action_id = $form_state->getValue('action');
        if (!empty($action_id)) {
          $action = $this->actions[$action_id];
          if ($this->isConfigurable($action)) {
            $actionObject = $this->actionManager->createInstance($action_id);
            $form['header'][$this->options['id']]['configuration'] += $actionObject->buildConfigurationForm($form['header'][$this->options['id']]['configuration'], $form_state);
            $form['header'][$this->options['id']]['configuration']['#config_included'] = TRUE;
          }
        }
      }

      $total_results = $this->viewData->getTotalResults();
      $items_per_page = $this->view->getItemsPerPage();

      // Select all results checkbox.
      if (!empty($items_per_page) && $total_results > $items_per_page) {
        $form['header'][$this->options['id']]['select_all'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Select all @count results in this view', [
            '@count' => $total_results,
          ]),
          '#attributes' => ['class' => ['vbo-select-all']],
        ];
      }

      // Duplicate the form actions into the action container in the header.
      $form['header'][$this->options['id']]['actions'] = $form['actions'];
    }
    else {
      // Remove the default actions build array.
      unset($form['actions']);
    }
  }

  /**
   * AJAX callback for the views form.
   *
   * Currently not used due to #2879310.
   */
  public static function viewsFormAjax(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $plugin_id = $trigger['#array_parents'][1];
    return $form['header'][$plugin_id]['configuration'];
  }

  /**
   * Returns the available operations for this form.
   *
   * @return array
   *   An associative array of operations, suitable for a select element.
   */
  protected function getBulkOptions() {
    if (!isset($this->bulkOptions)) {
      $this->bulkOptions = [];
      foreach ($this->actions as $id => $definition) {
        // Filter out actions that weren't selected.
        if (!in_array($id, $this->options['selected_actions'], TRUE)) {
          continue;
        }

        // Check access permission, if defined.
        if (!empty($definition['requirements']['_permission']) && !$this->currentUser->hasPermission($definition['requirements']['_permission'])) {
          continue;
        }

        // Override label if applicable.
        if (!empty($this->options['preconfiguration'][$id]['label_override'])) {
          $this->bulkOptions[$id] = $this->options['preconfiguration'][$id]['label_override'];
        }
        else {
          $this->bulkOptions[$id] = $definition['label'];
        }
      }
    }

    return $this->bulkOptions;
  }

  /**
   * Submit handler for the bulk form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user tried to access an action without access to it.
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->get('step') == 'views_form_views_form') {

      $action_id = $form_state->getValue('action');

      $action = $this->actions[$action_id];

      $data = [
        'action_id' => $action_id,
        'action_label' => empty($this->options['preconfiguration'][$action_id]['label_override']) ? $action['label'] : $this->options['preconfiguration'][$action_id]['label_override'],
        'relationship_id' => $this->options['relationship'],
        'preconfiguration' => isset($this->options['preconfiguration'][$action_id]) ? $this->options['preconfiguration'][$action_id] : [],
        'list' => [],
        'view_id' => $this->view->id(),
        'display_id' => $this->view->current_display,
        'batch' => $this->options['batch'],
      ];

      if (!$form_state->getValue('select_all')) {
        // We get selected items from user input as
        // in \Drupal\system\Plugin\views\field\BulkForm.
        // TODO: Check if this is necessary.
        $user_input = $form_state->getUserInput();
        $selected = array_values(array_filter($user_input[$this->options['id']]));
        foreach ($selected as $bulk_form_key) {
          $data['list'][] = json_decode(base64_decode($bulk_form_key));
        }
      }

      $configurable = $this->isConfigurable($action);

      // Get configuration if using AJAX.
      if ($configurable && empty($this->options['form_step'])) {
        $actionObject = $this->actionManager->createInstance($action_id);
        if (method_exists($actionObject, 'submitConfigurationForm')) {
          $actionObject->submitConfigurationForm($form, $form_state);
          $data['configuration'] = $actionObject->getConfiguration();
        }
        else {
          $form_state->cleanValues();
          $data['configuration'] = $form_state->getValues();
        }
      }

      // Routing - determine redirect route.
      if ($this->options['form_step'] && $configurable) {
        $redirect_route = 'views_bulk_operations.execute_configurable';
      }
      elseif ($this->options['batch']) {
        if (!empty($action['confirm_form_route_name'])) {
          $redirect_route = $action['confirm_form_route_name'];
        }
        else {
          $redirect_route = 'views_bulk_operations.execute_batch';
        }
      }
      elseif (!empty($action['confirm_form_route_name'])) {
        $redirect_route = $action['confirm_form_route_name'];
      }

      // Redirect if needed.
      if (!empty($redirect_route)) {
        if ($form_state->getValue('select_all')) {
          $data['arguments'] = $this->view->args;
          $data['exposed_input'] = $this->view->getExposedInput();
        }
        $data['batch_size'] = $this->options['batch_size'];
        $data['redirect_url'] = Url::createFromRequest(\Drupal::request());

        $this->userTempStore->set($this->currentUser->id(), $data);

        $form_state->setRedirect($redirect_route, [
          'view_id' => $this->view->id(),
          'display_id' => $this->view->current_display,
        ]);
      }
      // Or process rows here.
      else {
        $this->actionProcessor->executeProcessing($data);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormValidate(&$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('action'))) {
      $form_state->setErrorByName('action', $this->t('Please select an action to perform.'));
    }

    // This happened once, can't reproduce but here's a safety switch.
    if (!isset($this->actions[$form_state->getValue('action')])) {
      $form_state->setErrorByName('action', $this->t('Form error occurred, please try again.'));
    }

    if (!$form_state->getValue('select_all')) {
      $selected = array_filter($form_state->getValue($this->options['id']));
      if (empty($selected)) {
        $form_state->setErrorByName('', $this->t('No items selected.'));
      }
    }

    // Action config validation (if implemented).
    if (empty($this->options['form_step']) && !empty($form['header'][$this->options['id']]['configuration']['#config_included'])) {
      $action_id = $form_state->getValue('action');
      $action = $this->actions[$action_id];
      if (method_exists($action['class'], 'validateConfigurationForm')) {
        $actionObject = $this->actionManager->createInstance($action_id);
        $actionObject->validateConfigurationForm($form['header'][$this->options['id']]['configuration'], $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * Wraps drupal_set_message().
   */
  protected function drupalSetMessage($message = NULL, $type = 'status', $repeat = FALSE) {
    drupal_set_message($message, $type, $repeat);
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
      $row_index,
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

  /**
   * Check if an action is configurable.
   */
  protected function isConfigurable($action) {
    return (in_array('Drupal\Core\Plugin\PluginFormInterface', class_implements($action['class']), TRUE) || method_exists($action['class'], 'buildConfigurationForm'));
  }

}
