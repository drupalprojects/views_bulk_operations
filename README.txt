Introduction
------------

Views Bulk Operations augments Views by allowing actions
(provided by Drupal core or contrib modules) to be executed
on the selected view rows.

It does so by showing a checkbox in front of each displayed row, and adding a
select box on top of the View containing operations that can be applied.


Getting started
-----------------

1. Create a View with a page or block display.
2. Add a "Views bulk operations" field (global), available on
   all entity types.
3. Configure the field by selecting at least one operation.
4. Go to the View page. VBO functionality should be present.


Creating custom actions
-----------------------

Example that covers all possibilities available in
modules/views_bulk_operatios_example/.

In a module, create an action plugin (check example module
  or \core\modules\node\src\Plugin\Action\AssignOwnerNode.php).

Available annotation parameters:
  - id: The action ID (required),
  - label: Action label (required),
  - type: Entity type for the action, if left empty, action will be
    applicable to all entity types,
  - confirm: If set to TRUE and the next parameter is empty,
    the module default confirmation form will be used,
  - confirm_form_route_name: Route name of the action confirmation form.
    If left empty and the previous parameter is empty, there will be
    no confirmation step.
  - pass_rows: If set to TRUE, selected view rows will be passed to
    The action object context parameter. Not implemented yet, see
    https://www.drupal.org/node/2884847 for more information.


Additional notes
----------------

Documentation also available at
https://www.drupal.org/docs/8/modules/views-bulk-operations-vbo.
