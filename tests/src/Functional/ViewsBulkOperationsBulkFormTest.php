<?php

namespace Drupal\Tests\views_bulk_operations\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @coversDefaultClass \Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm
 * @group views_bulk_operations
 */
class ViewsBulkOperationsBulkFormTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'views',
    'views_bulk_operations',
    'views_bulk_operations_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create some nodes for testing.
    $this->drupalCreateContentType(['type' => 'page']);

    $this->testNodes = [];
    $time = REQUEST_TIME;
    for ($i = 0; $i < 10; $i++) {
      // Ensure nodes are sorted in the same order they are inserted in the
      // array.
      $time -= $i;
      $this->testNodes[] = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Title ' . $i,
        'sticky' => FALSE,
        'created' => $time,
        'changed' => $time,
      ]);
    }

  }

  /**
   * Helper function to test a batch process.
   *
   * After checking if we're on a Batch API page,
   * the iterations are executed, the finished page is opened
   * and browser redirects to the final destination.
   *
   * NOTE: As of Drupal 8.4 Functional test automatically redirects user through
   * all Batch API pages, so this function is not longer needed.
   */
  protected function assertBatchProcess() {
    // Get the current batch ID.
    $current_url = $this->getUrl();
    $q = substr($current_url, strrpos($current_url, '/') + 1);
    $this->assertEquals('batch?', substr($q, 0, 6), 'We are on a Batch API page.');

    preg_match('#id=([0-9]+)#', $q, $matches);
    $batch_id = $matches[1];

    // Proceed with the operations.
    // Assumption: all operations will be completed within a single request.
    // TODO: modify code to include an option when the assumption is false.
    do {
      $this->drupalGet('batch', [
        'query' => [
          'id' => $batch_id,
          'op' => 'do_nojs',
        ],
      ]);
    } while (FALSE);

    // Get the finished page.
    $this->drupalGet('batch', [
      'query' => [
        'id' => $batch_id,
        'op' => 'finished',
      ],
    ]);
  }

  /**
   * Tests the VBO bulk form with simple test action.
   */
  public function testViewsBulkOperationsBulkFormSimple() {

    $this->drupalGet('views-bulk-operations-test');

    // Test that the views edit header appears first.
    $first_form_element = $this->xpath('//form/div[1][@id = :id]', [':id' => 'edit-header']);
    $this->assertTrue($first_form_element, 'The views form edit header appears first.');

    $this->assertFieldById('edit-action', NULL, 'The action select field appears.');

    // Make sure a checkbox appears on all rows.
    $edit = [];
    for ($i = 0; $i < 10; $i++) {
      $this->assertFieldById('edit-views-bulk-operations-bulk-form-' . $i, NULL, format_string('The checkbox on row @row appears.', ['@row' => $i]));
    }

    // Log in as a user with 'edit any page content' permission
    // to have access to perform the test operation.
    $admin_user = $this->drupalCreateUser(['edit any page content']);
    $this->drupalLogin($admin_user);

    // Execute the simple test action.
    $edit = [
      'action' => 'views_bulk_operations_simple_test_action',
    ];
    $selected = [3, 5, 7];
    foreach ($selected as $index) {
      $edit["views_bulk_operations_bulk_form[$index]"] = TRUE;
    }
    $this->drupalPostForm('views-bulk-operations-test', $edit, t('Apply to selected items'));

    $testViewConfig = \Drupal::service('config.factory')->get('views.view.views_bulk_operations_test');
    $configData = $testViewConfig->getRawData();
    $preconfig_setting = $configData['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form']['preconfiguration']['views_bulk_operations_simple_test_action']['preconfig'];

    foreach ($selected as $index) {
      $this->assertText(sprintf('Test action (preconfig: %s, label: %s)',
        $preconfig_setting,
        $this->testNodes[$index]->label()
      ));
    }

  }

  /**
   * More advanced test.
   *
   * Uses the ViewsBulkOperationsAdvancedTestAction.
   */
  public function testViewsBulkOperationsBulkFormAdvanced() {
    // Log in as a user with 'edit any page content' permission
    // to have access to perform the test operation.
    $admin_user = $this->drupalCreateUser(['edit any page content']);
    $this->drupalLogin($admin_user);

    // Execute the advanced test action.
    $edit = [
      'action' => 'views_bulk_operations_advanced_test_action',
    ];
    $selected = [0, 1, 3];
    foreach ($selected as $index) {
      $edit["views_bulk_operations_bulk_form[$index]"] = TRUE;
    }
    $this->drupalPostForm('views-bulk-operations-test-advanced', $edit, t('Apply to selected items'));

    // Check if configuration form is open and contains the
    // test_config field.
    $this->assertFieldById('edit-test-config', NULL, 'The action select field appears.');

    $config_value = 'test value';
    $edit = [
      'test_config' => $config_value,
    ];
    $this->drupalPostForm(NULL, $edit, t('Apply'));

    // Execute action by posting the confirmation form
    // (also tests if the submit button exists on the page).
    $confirm_button_value = t('Execute action');
    $this->drupalPostForm(NULL, [], t('Execute action'));

    // If all went well and Batch API did its job,
    // the next page should display results.
    $testViewConfig = \Drupal::service('config.factory')->get('views.view.views_bulk_operations_test_advanced');
    $configData = $testViewConfig->getRawData();
    $preconfig_setting = $configData['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form']['preconfiguration']['views_bulk_operations_advanced_test_action']['test_preconfig'];

    // NOTE: The view pager has an offset set on this view, so checkbox
    // indexes are not equal to test nodes array keys. Hence the $index + 1.
    foreach ($selected as $index) {
      $this->assertText(sprintf('Test action (preconfig: %s, config: %s, label: %s)',
        $preconfig_setting,
        $config_value,
        $this->testNodes[$index + 1]->label()
      ));
    }

  }

}
