(function () {
  function norm(value) {
    return String(value || '').toLowerCase();
  }

  function esc(value) {
    return String(value).replace(/[&<>"]/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch];
    });
  }

  function subtitle(row, kind) {
    if (kind === 'donor') {
      return [row.phone, row.email].filter(Boolean).join(' · ');
    }
    if (kind === 'food') {
      return [row.stock, row.unit].filter(Boolean).join(' ');
    }
    if (kind === 'vastra') {
      return row.color || (Array.isArray(row.colors) ? row.colors.join(', ') : '');
    }
    if (kind === 'inventory') {
      return [row.unit, row.location].filter(Boolean).join(' · ');
    }
    if (kind === 'color') {
      return row.item || '';
    }
    if (kind === 'puja') {
      return row.amount ? '₹' + row.amount : '';
    }
    return '';
  }

  function setField(form, name, value) {
    if (value === undefined || value === null || String(value) === '') {
      return;
    }
    var field = form.elements[name];
    if (!field) {
      return;
    }
    var text = String(value);
    if (field.tagName === 'SELECT') {
      var found = Array.prototype.some.call(field.options, function (option) {
        return option.value === text || option.text === text;
      });
      if (!found) {
        return;
      }
    }
    field.value = text;
  }

  function setup(input) {
    var form = input.form;
    var box = input.closest('.suggest');
    if (!form || !box) {
      return;
    }
    var menu = box.querySelector('.suggest-menu');
    if (!menu) {
      return;
    }
    var kind = input.getAttribute('data-kind') || '';
    var remote = input.getAttribute('data-url') || '';
    var source = [];
    var sourceId = input.getAttribute('data-source');
    if (sourceId) {
      var node = document.getElementById(sourceId);
      if (node) {
        try {
          source = JSON.parse(node.textContent || '[]');
        } catch (error) {
          source = [];
        }
      }
    }
    var matchFields = (input.getAttribute('data-match') || 'name').split(',');
    var fills = {};
    (input.getAttribute('data-fill') || '').split(',').forEach(function (pair) {
      var parts = pair.split(':');
      if (parts.length === 2 && parts[0] && parts[1]) {
        fills[parts[0]] = parts[1];
      }
    });
    var filterSpec = input.getAttribute('data-filter') || '';
    var filterName = filterSpec.split(':')[0];
    var filterKey = filterSpec.split(':')[1] || filterName;
    var filterField = filterName ? form.elements[filterName] : null;
    var idName = input.getAttribute('data-id') || '';
    var idField = idName ? form.elements[idName] : null;
    var active = -1;
    var rows = [];
    var timer = 0;
    var request = 0;
    var pricedName = '';

    function exactRow(query) {
      if (remote) {
        return null;
      }
      var needle = norm(String(query || '').trim());
      if (needle === '') {
        return null;
      }
      var found = null;
      source.some(function (row) {
        if (norm(row.name) === needle) {
          found = row;
          return true;
        }
        return false;
      });
      return found;
    }

    function applyKnownPrice() {
      var row = exactRow(input.value);
      if (!row) {
        pricedName = '';
        return;
      }
      var key = norm(row.name);
      if (pricedName === key) {
        return;
      }
      pricedName = key;
      Object.keys(fills).forEach(function (field) {
        setField(form, field, row[fills[field]]);
      });
    }

    function allowed(row) {
      if (!filterField || filterField.value === '') {
        return true;
      }
      var value = row[filterKey] == null ? '' : String(row[filterKey]);
      return value !== '' && value === filterField.value;
    }

    function close() {
      menu.hidden = true;
      menu.innerHTML = '';
      input.setAttribute('aria-expanded', 'false');
      active = -1;
      rows = [];
    }

    function paint(list) {
      var seen = {};
      rows = [];
      list.forEach(function (row) {
        var key = String(row.name || '') + '\n' + String(row.id || '') + '\n' + String(row.item || '');
        if (!row.name || seen[key]) {
          return;
        }
        seen[key] = true;
        rows.push(row);
      });
      var limit = parseInt(input.getAttribute('data-limit') || '12', 10);
      if (!isFinite(limit) || limit < 1) {
        limit = 12;
      }
      rows = rows.slice(0, limit);
      if (rows.length === 0) {
        close();
        return;
      }
      menu.innerHTML = rows.map(function (row, index) {
        var extra = subtitle(row, kind);
        return '<button type="button" role="option" data-index="' + index + '">' +
          esc(row.name) +
          (extra ? '<small>' + esc(extra) + '</small>' : '') +
          '</button>';
      }).join('');
      menu.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      active = -1;
    }

    function localRows(query) {
      var needle = norm(query);
      return source.filter(function (row) {
        if (!allowed(row)) {
          return false;
        }
        return matchFields.some(function (field) {
          return norm(row[field]).indexOf(needle) !== -1;
        });
      });
    }

    function show(query) {
      var needle = query.trim();
      if (needle.length < 1) {
        close();
        return;
      }
      if (!remote) {
        paint(localRows(needle));
        return;
      }
      var token = ++request;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        var join = remote.indexOf('?') === -1 ? '?' : '&';
        fetch(remote + join + 'q=' + encodeURIComponent(needle), {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        }).then(function (response) {
          return response.ok ? response.json() : [];
        }).then(function (list) {
          if (token !== request || input.value.trim() !== needle) {
            return;
          }
          paint(Array.isArray(list) ? list : []);
        }).catch(function () {
          if (token === request) {
            close();
          }
        });
      }, 160);
    }

    function choose(index) {
      var row = rows[index];
      if (!row) {
        return;
      }
      input.value = row.name;
      Object.keys(fills).forEach(function (field) {
        setField(form, field, row[fills[field]]);
      });
      if (idField && row.id) {
        idField.value = String(row.id);
      }
      pricedName = norm(row.name);
      close();
    }

    input.setAttribute('autocomplete', 'off');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    menu.setAttribute('role', 'listbox');

    input.addEventListener('input', function () {
      if (idField) {
        idField.value = '0';
      }
      applyKnownPrice();
      show(input.value);
    });
    input.addEventListener('focus', function () {
      if (input.getAttribute('data-open') === 'focus' && !remote) {
        paint(localRows(input.value.trim()));
        return;
      }
      if (input.value.trim() !== '') {
        show(input.value);
      }
    });
    input.addEventListener('keydown', function (event) {
      if (menu.hidden) {
        return;
      }
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        active = Math.min(rows.length - 1, active + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active = Math.max(0, active - 1);
      } else if (event.key === 'Enter' && active >= 0) {
        event.preventDefault();
        choose(active);
        return;
      } else if (event.key === 'Escape') {
        close();
        return;
      } else {
        return;
      }
      Array.prototype.forEach.call(menu.querySelectorAll('button'), function (button, index) {
        button.classList.toggle('active', index === active);
      });
    });
    menu.addEventListener('mousedown', function (event) {
      var button = event.target.closest('button');
      if (!button) {
        return;
      }
      event.preventDefault();
      choose(Number(button.getAttribute('data-index')));
    });
    document.addEventListener('click', function (event) {
      if (!box.contains(event.target)) {
        close();
      }
    });
    if (filterField) {
      filterField.addEventListener('change', function () {
        if (idField) {
          idField.value = '0';
        }
        if (input.value.trim() !== '') {
          show(input.value);
        }
      });
    }
  }

  document.querySelectorAll('input[data-suggest]').forEach(setup);
})();
