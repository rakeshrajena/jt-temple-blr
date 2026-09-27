/*
 * Adds a "Filter this table" box above long tables that have no filter of their own.
 * Skipped for tables with selection checkboxes, so a bulk action never includes rows the user cannot see.
 */
(function () {
  'use strict';

  var MIN_ROWS = 6;
  var body = document.body;
  var label = body.getAttribute('data-table-filter-label') || 'Filter this table';
  var countTemplate = body.getAttribute('data-table-filter-count') || '{shown} of {total} rows';

  function dataRows(table) {
    return Array.prototype.filter.call(table.rows, function (row) {
      if (row.closest('thead') || row.classList.contains('reveal-panel')) {
        return false;
      }
      var cells = row.cells;
      if (cells.length === 0 || row.querySelector('th') && !row.querySelector('td')) {
        return false;
      }
      return !(cells.length === 1 && cells[0].colSpan > 1);
    });
  }

  function wanted(table) {
    if (table.hasAttribute('data-no-filter')) {
      return false;
    }
    if (table.hasAttribute('data-filter')) {
      return true;
    }
    if (table.querySelector('td input[type="checkbox"]')) {
      return false;
    }
    var panel = table.closest('.panel');
    if (panel && panel.querySelector('form.filters')) {
      return false;
    }
    return dataRows(table).length >= MIN_ROWS;
  }

  function attachedPanels(row) {
    var panels = [];
    var next = row.nextElementSibling;
    while (next && next.classList.contains('reveal-panel')) {
      panels.push(next);
      next = next.nextElementSibling;
    }
    return panels;
  }

  function setup(table, index) {
    var rows = dataRows(table);
    var wrap = document.createElement('div');
    wrap.className = 'table-filter no-print';
    var id = 'table-filter-' + index;
    var input = document.createElement('input');
    input.type = 'search';
    input.id = id;
    input.placeholder = label;
    input.setAttribute('aria-label', label);
    input.autocomplete = 'off';
    var count = document.createElement('span');
    count.className = 'table-filter-count';
    count.setAttribute('aria-live', 'polite');
    wrap.appendChild(input);
    wrap.appendChild(count);
    table.parentNode.insertBefore(wrap, table);

    var texts = rows.map(function (row) {
      return row.textContent.replace(/\s+/g, ' ').toLowerCase();
    });

    function apply() {
      var words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
      var shown = 0;
      rows.forEach(function (row, i) {
        var match = words.every(function (word) { return texts[i].indexOf(word) !== -1; });
        row.classList.toggle('table-filter-hide', !match);
        attachedPanels(row).forEach(function (panel) {
          panel.classList.toggle('table-filter-hide', !match);
        });
        if (match) {
          shown += 1;
        }
      });
      count.textContent = words.length === 0
        ? ''
        : countTemplate.replace('{shown}', String(shown)).replace('{total}', String(rows.length));
    }

    input.addEventListener('input', apply);
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && input.value !== '') {
        input.value = '';
        apply();
      }
    });
  }

  Array.prototype.filter.call(document.querySelectorAll('table.data-table'), wanted).forEach(setup);
})();
