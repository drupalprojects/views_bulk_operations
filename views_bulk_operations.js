// $Id$

Drupal.vboSelectAll = function() {
  var table = this;

  thSelectAll = $("th.select-all", table).click(function() {
    if ($("input.form-checkbox", thSelectAll[0])[0].checked) {
      tdSelectAll = $("td.view-field-select-all").css("display", "table-cell");
      $("input#vbo-select-all-pages", tdSelectAll).click(function() {
        $("span#vbo-this-page", tdSelectAll).css("display", "none");
        $("span#vbo-all-pages", tdSelectAll).css("display", "inline");
        $("form input#edit-nodes-select-all").attr("value", 1);
      });
      $("input#vbo-select-this-page", tdSelectAll).click(function() {
        $("span#vbo-this-page", tdSelectAll).css("display", "inline");
        $("span#vbo-all-pages", tdSelectAll).css("display", "none");
        $("form input#edit-nodes-select-all").attr("value", 0);
      });
    }
    else {
      tdSelectAll = $("td.view-field-select-all").css("display", "none");
      $("span#vbo-this-page", tdSelectAll).css("display", "inline");
      $("span#vbo-all-pages", tdSelectAll).css("display", "none");
      $("form input#edit-nodes-select-all").attr("value", 0);
    }
  });
}

if (Drupal.jsEnabled) {
  $(document).ready(function() {
    $('form table th.select-all').parents('table').each(Drupal.vboSelectAll);
  })
}
