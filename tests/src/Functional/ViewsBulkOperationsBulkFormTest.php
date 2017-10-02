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
   * Tests the VBO bulk form.
   */
  public function testViewsBulkOperationsBulkForm() {

    $this->drupalCreateContentType(['type' => 'page']);
    $nodes = [];
    $created = [];
    for ($i = 0; $i < 10; $i++) {
      // Ensure nodes are sorted in the same order they are inserted in the
      // array.
      $created[$i] = REQUEST_TIME - $i;
      $nodes[] = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Title ' . $i,
        'sticky' => FALSE,
        'created' => $created[$i],
        'changed' => $created[$i],
      ]);
    }

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

    // Execute the module test action.
    $edit = [
      'action' => 'views_bulk_operations_test_action',
    ];
    $selected = [3, 5, 7];
    foreach ($selected as $index) {
      $edit["views_bulk_operations_bulk_form[$index]"] = TRUE;
    }
    $this->drupalPostForm('views-bulk-operations-test', $edit, t('Apply to selected items'));

    // Initialize configuration manager to get/update
    // config settings for the test view.
    $viewConfig = \Drupal::service('config.factory')->getEditable('views.view.views_bulk_operations_test');
    $configData = $viewConfig->getRawData();

    $preconfig_setting = $configData['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form']['preconfiguration']['views_bulk_operations_test_action']['preconfig'];

    foreach ($selected as $index) {
      $this->assertText(sprintf('Test action (preconfig: %s, label: %s)',
        $preconfig_setting,
        $nodes[$index]->label()
      ));
    }

  }

}
