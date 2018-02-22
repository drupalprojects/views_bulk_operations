<?php

namespace Drupal\Tests\views_bulk_operations\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simpletest\NodeCreationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\views\Views;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\views_bulk_operations\ViewsBulkOperationsBatch;

/**
 * Base class for Views Bulk Operations kernel tests.
 */
abstract class ViewsBulkOperationsKernelTestBase extends KernelTestBase {

  use NodeCreationTrait {
    getNodeByTitle as drupalGetNodeByTitle;
    createNode as drupalCreateNode;
  }

  const TEST_NODES_COUNT = 10;

  const VBO_DEFAULTS = [
    'list' => [],
    'display_id' => 'default',
    'preconfiguration' => [],
    'batch' => TRUE,
    'arguments' => [],
    'exposed_input' => [],
    'batch_size' => 10,
    'relationship_id' => 'none',
  ];

  /**
   * Test nodes data including titles and languages.
   *
   * @var array
   */
  protected $testNodesData;

  /**
   * VBO views data service.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsViewDataInterface
   */
  protected $vboDataService;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'node',
    'field',
    'content_translation',
    'views_bulk_operations',
    'views_bulk_operations_test',
    'views',
    'filter',
    'language',
    'text',
    'action',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');
    $this->installSchema('system', 'sequences');
    $this->installSchema('system', 'key_value_expire');

    $user = User::create();
    $user->setPassword('password');
    $user->enforceIsNew();
    $user->setEmail('email');
    $user->setUsername('user_name');
    $user->save();
    user_login_finalize($user);

    $this->installConfig([
      'system',
      'filter',
      'views_bulk_operations_test',
      'language',
    ]);

    $languages = ['pl', 'es', 'it', 'fr', 'de'];
    $count_languages = count($languages);
    for ($i = 0; $i < $count_languages; $i++) {
      $language = ConfigurableLanguage::createFromLangcode($languages[$i]);
      $language->save();
    }

    $type = NodeType::create([
      'type' => 'page',
      'name' => 'page',
    ]);
    $type->save();

    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();

    // Create some test nodes with translations.
    $this->testNodesData = [];
    $time = REQUEST_TIME;
    for ($i = 0; $i < static::TEST_NODES_COUNT; $i++) {
      $time -= $i;
      $title = 'Title ' . $i;
      $node = $this->drupalCreateNode([
        'type' => 'page',
        'title' => $title,
        'sticky' => FALSE,
        'created' => $time,
        'changed' => $time,
      ]);
      $this->testNodesData[$node->id()]['en'] = $title;
      $this->resultsCount++;

      $langcode = $languages[rand(0, $count_languages - 1)];
      $title = 'Translated title ' . $langcode . ' ' . $i;
      $translation = $node->addTranslation($langcode, [
        'title' => $title,
      ]);
      $translation->save();
      $this->testNodesData[$node->id()][$langcode] = $title;
      $this->resultsCount++;
    }

    // Get VBO view data service.
    $this->vboDataService = $this->container->get('views_bulk_operations.data');

  }

  /**
   * Initialize and return the view described by $vbo_data.
   *
   * @param array $vbo_data
   *   An array of data passed to VBO Processor service.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view object.
   */
  protected function initializeView(array $vbo_data) {
    if (!$view = Views::getView($vbo_data['view_id'])) {
      throw new \Exception('Incorrect view ID provided.');
    }
    if (!$view->setDisplay($vbo_data['display_id'])) {
      throw new \Exception('Incorrect view display ID provided.');
    }
    $view->built = FALSE;
    $view->executed = FALSE;

    // We will need total rows count for most cases.
    $view->get_total_rows = TRUE;

    return $view;
  }

  /**
   * Get a random list of results bulk keys.
   *
   * @param array $vbo_data
   *   An array of data passed to VBO Processor service.
   * @param int $limit
   *   Number of list items to return.
   *
   * @return array
   *   List of results to process.
   */
  protected function getRandomList(array $vbo_data, $limit) {
    // Merge in defaults.
    $vbo_data += static::VBO_DEFAULTS;

    $view = $this->initializeView($vbo_data);
    if (!empty($vbo_data['arguments'])) {
      $view->setArguments($vbo_data['arguments']);
    }
    if (!empty($vbo_data['exposed_input'])) {
      $view->setExposedInput($vbo_data['exposed_input']);
    }

    $view->setItemsPerPage(0);
    $view->setCurrentPage(0);
    $view->execute();

    $this->vboDataService->init($view, $view->getDisplay(), $vbo_data['relationship_id']);
    $total_results = $this->vboDataService->getTotalResults();
    $numbers = range(0, $total_results - 1);
    shuffle($numbers);

    $list = [];
    $base_field = $view->storage->get('base_field');
    for ($i = 0; $i < $limit; $i++) {
      $entity = $this->vboDataService->getEntity($view->result[$numbers[$i]]);

      $list[] = [
        $view->result[$numbers[$i]]->{$base_field},
        $entity->language()->getId(),
        $entity->getEntityTypeId(),
        $entity->id(),
      ];
    }

    $view->destroy();

    return $list;
  }

  /**
   * Execute an action on a specific view results.
   *
   * @param array $vbo_data
   *   An array of data passed to VBO Processor service.
   */
  protected function executeAction(array $vbo_data) {

    // Merge in defaults.
    $vbo_data += static::VBO_DEFAULTS;

    $view = $this->initializeView($vbo_data);

    $view->execute();

    // Get total rows count.
    $this->vboDataService->init($view, $view->getDisplay(), $vbo_data['relationship_id']);
    $vbo_data['total_results'] = $this->vboDataService->getTotalResults();

    // Get action definition and check if action ID is correct.
    $action_definition = $this->container->get('plugin.manager.views_bulk_operations_action')->getDefinition($vbo_data['action_id']);
    if (!isset($vbo_data['action_label'])) {
      $vbo_data['action_label'] = (string) $action_definition['label'];
    }

    // Populate entity list if empty.
    if (empty($vbo_data['list'])) {
      $context = [];
      do {
        $context['finished'] = 1;
        $context['message'] = '';
        ViewsBulkOperationsBatch::getList($vbo_data, $context);
      } while ($context['finished'] < 1);
      $vbo_data = $context['results'];
    }

    $summary = [
      'messages' => [],
    ];

    // Execute the selected action.
    $context = [];
    do {
      $context['finished'] = 1;
      $context['message'] = '';
      ViewsBulkOperationsBatch::operation($vbo_data, $context);
      if (!empty($context['message'])) {
        $summary['messages'][] = (string) $context['message'];
      }
    } while ($context['finished'] < 1);

    // Add information to the summary array.
    $summary += [
      'operations' => array_count_values($context['results']['operations']),
    ];

    return $summary;
  }

}
