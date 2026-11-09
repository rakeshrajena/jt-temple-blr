/* Shree Jagannatha Temple Bengaluru — booking app.
 * Progressive enhancement only: every page works without JavaScript and the
 * server re-validates and re-prices everything. */
(function () {
  'use strict';
  var doc = document.documentElement;
  doc.classList.add('js');
  if (/Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)) doc.classList.add('is-mobile');

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function rupees(paise) {
    var r = Math.floor(Math.abs(paise) / 100), p = Math.abs(paise) % 100, s = String(r);
    if (s.length > 3) s = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + s.slice(-3);
    return '₹' + s + (p ? '.' + String(p).padStart(2, '0') : '');
  }

  // ---------------------------------------------------------------- common
  $$('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy'), label = btn.textContent;
      var done = function () { btn.textContent = 'Copied ✓'; setTimeout(function () { btn.textContent = label; }, 2000); };
      if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () {});
      else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (e) {} t.remove(); }
    });
  });
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  $$('form[data-submit-once]').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = f.querySelector('button[type=submit]');
      if (b) { setTimeout(function () { b.disabled = true; if (b.dataset.busyText) b.textContent = b.dataset.busyText; }, 0); }
    });
  });
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });

  function fillAvailDialogBody(body, problems) {
    body.innerHTML = '';
    if (problems.length === 1) {
      var p = document.createElement('p'); p.textContent = problems[0]; body.appendChild(p);
    } else {
      var ul = document.createElement('ul');
      problems.forEach(function (t) { var li = document.createElement('li'); li.textContent = t; ul.appendChild(li); });
      body.appendChild(ul);
    }
  }
  function closeAvailDialog() {
    var dialog = $('#avail-dialog');
    if (dialog && dialog.open) dialog.close();
    if (window.jtBusyDone) window.jtBusyDone();
  }

  $$('[data-avail-dialog-ok]').forEach(function (btn) {
    btn.addEventListener('click', function () { closeAvailDialog(); });
  });

  function showAvailabilityPopup(problems, hint) {
    if (!problems || !problems.length) return;
    if (window.jtBusyDone) window.jtBusyDone();
    var dialog = $('#avail-dialog');
    var title = $('#avail-dialog-title');
    if (title) {
      title.textContent = hint
        ? 'Please choose another date or service'
        : (problems.length > 1 ? 'Some services are not available' : 'Date not available');
    }
    var body = $('#avail-dialog-body');
    if (body) {
      body.innerHTML = '';
      if (hint) {
        var lead = document.createElement('p');
        lead.className = 'avail-dialog-lead';
        lead.textContent = hint;
        body.appendChild(lead);
      }
      fillAvailDialogBody(body, problems);
    }
    if (dialog && dialog.showModal) {
      if (dialog.open) dialog.close();
      dialog.showModal();
      var ok = $('[data-avail-dialog-ok]', dialog);
      if (ok) setTimeout(function () { ok.focus(); }, 0);
    } else if (window.alert) {
      window.alert((hint ? hint + '\n\n' : '') + problems.join('\n\n'));
    }
  }

  function parseCheckResponse(res) {
    return res.json().catch(function () { return { ok: false, problems: ['Could not verify availability. Please try again.'] }; })
      .then(function (data) { return { status: res.status, data: data || {} }; });
  }

  function runServerBookingCheck(checkUrl, body, onOk, onFail) {
    var opts = { method: body ? 'POST' : 'GET', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
    if (body) opts.body = body;
    fetch(checkUrl, opts)
      .then(parseCheckResponse)
      .then(function (res) {
        if (res.status >= 200 && res.status < 300 && res.data.ok) {
          onOk();
          return;
        }
        var problems = (res.data.problems && res.data.problems.length) ? res.data.problems : ['Something is no longer available on the date you chose.'];
        showAvailabilityPopup(problems, res.data.hint || null);
        if (onFail) onFail(res.data);
      })
      .catch(function () {
        showAvailabilityPopup(['Could not verify availability. Please check your connection and try again.']);
        if (onFail) onFail(null);
      });
  }

  var rescheduleForm = $('#reschedule-form');
  if (rescheduleForm) {
    var checkUrl = rescheduleForm.getAttribute('data-reschedule-check');
    try {
      var initial = JSON.parse(rescheduleForm.getAttribute('data-reschedule-problems') || '[]');
      if (initial.length) showAvailabilityPopup(initial);
    } catch (ignore) {}
    rescheduleForm.addEventListener('submit', function (e) {
      if (rescheduleForm.getAttribute('data-reschedule-ok') === '1') {
        rescheduleForm.removeAttribute('data-reschedule-ok');
        return;
      }
      if (!checkUrl || !window.fetch) return;
      e.preventDefault();
      var fd = new FormData(rescheduleForm);
      var params = new URLSearchParams();
      if (fd.get('date')) params.set('date', String(fd.get('date')));
      if (fd.get('override')) params.set('override', '1');
      fd.forEach(function (value, key) {
        if (key.indexOf('slots[') === 0) params.append(key, String(value));
      });
      fetch(checkUrl + '?' + params.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) {
            rescheduleForm.setAttribute('data-reschedule-ok', '1');
            if (rescheduleForm.requestSubmit) rescheduleForm.requestSubmit();
            else rescheduleForm.submit();
          } else if (data.problems && data.problems.length) {
            showAvailabilityPopup(data.problems);
          }
        })
        .catch(function () {
          rescheduleForm.setAttribute('data-reschedule-ok', '1');
          if (rescheduleForm.requestSubmit) rescheduleForm.requestSubmit();
          else rescheduleForm.submit();
        });
    });
  }
  var summary = $('[data-error-summary]');
  if (summary) summary.focus();

  // ---------------------------------------------------------------- booking wizard
  var form = $('#booking-form');
  if (form) initWizard(form);

  function initWizard(form) {
    var pricing = {};
    try { pricing = JSON.parse(form.getAttribute('data-pricing') || '{}'); } catch (e) {}
    var steps = $$('.step', form);
    var current = 0;
    var svcBoxes = $$('input[name="service_id"]', form);
    var addFood = $('#add_food', form);
    var couponsBox = $('[data-coupons]', form);
    var dateInput = $('#service_date', form);
    var availDialog = $('#avail-dialog');
    var lastAvailProblems = [];
    var lastAvailDate = '';
    var lastAvailServices = null;
    var lastPrasadam = null;
    var prasadamRefreshTimer = null;
    var availFetchId = 0;
    var svcById = {};
    (pricing.services || []).forEach(function (s) { svcById[s.id] = s; });

    function show(i, focus) {
      current = Math.max(0, Math.min(steps.length - 1, i));
      steps.forEach(function (s, n) { s.classList.toggle('is-current', n === current); });
      $$('.progress li').forEach(function (li, n) {
        li.classList.toggle('current', n === current);
        li.classList.toggle('done', n < current);
        if (n === current) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
        var dot = li.querySelector('.dot'); if (dot) dot.textContent = n < current ? '✓' : String(n + 1);
      });
      var pt = $('[data-progress-text]');
      if (pt) { var lbl = $$('.progress .lbl')[current]; pt.textContent = 'Step ' + (current + 1) + ' of 6: ' + (lbl ? lbl.textContent : ''); }
      if (focus) { var h = steps[current].querySelector('legend h1, legend h2'); if (h) { h.setAttribute('tabindex', '-1'); h.focus(); } window.scrollTo({ top: form.offsetTop - 10, behavior: 'smooth' }); }
      if (current === 2) schedulePrasadamRefresh();
      if (current === 0) applyServiceAvailability(lastAvailServices);
    }

    function personsCount(n) {
      n = Math.abs(parseInt(n, 10) || 0);
      return n === 1 ? '1 person' : n + ' persons';
    }
    function bookingsCount(n) {
      n = Math.abs(parseInt(n, 10) || 0);
      return n === 1 ? '1 booking' : n + ' bookings';
    }
    function slotOpenSummary(slots) {
      if (!slots || !slots.length) return null;
      var open = 0, total = slots.length, places = 0;
      slots.forEach(function (sl) {
        if (sl.remaining === null || sl.remaining > 0) {
          open++;
          if (sl.remaining !== null && sl.remaining > 0) places += sl.remaining;
        }
      });
      return { open: open, total: total, places: places };
    }
    function setServiceStatus(sid, text, className, hidden) {
      $$('[data-service-status="' + sid + '"]', form).forEach(function (el) {
        el.hidden = hidden;
        if (!hidden) { el.textContent = text; el.className = className; }
      });
    }

    function selected() { return svcBoxes.filter(function (b) { return b.checked; }).map(function (b) { return svcById[b.value] || { id: +b.value }; }); }

    function clearMsg(scope) { $$('.js-error', scope).forEach(function (n) { n.remove(); }); $$('[data-js-invalid]', scope).forEach(function (n) { n.removeAttribute('aria-invalid'); n.removeAttribute('data-js-invalid'); }); }
    function addMsg(el, text) {
      var p = document.createElement('p'); p.className = 'field-error js-error'; p.setAttribute('role', 'alert'); p.textContent = text;
      if (el.matches && el.matches('input,select,textarea')) { el.setAttribute('aria-invalid', 'true'); el.setAttribute('data-js-invalid', '1'); }
      var holder = el.closest('.field, .coupon, fieldset') || el;
      holder.appendChild(p);
      return el;
    }

    // Mark every invalid field (not just the first) and remember the first one for focus.
    function flag(first, el, text) { var e = addMsg(el, text); return first || e; }

    function validateStep(i) {
      var step = steps[i]; clearMsg(step);
      var first = null, sel = selected();
      if (i === 0) {
        if (!sel.length) first = flag(first, $('.service-options', step), 'Please choose a service.');
        else if (dateInput && dateInput.value && lastAvailDate === dateInput.value && lastAvailServices) {
          var svcProblems = [];
          sel.forEach(function (s) {
            var a = lastAvailServices[s.id] || lastAvailServices[String(s.id)];
            if (a && !a.available && a.message) svcProblems.push(a.message);
          });
          if (svcProblems.length) {
            showAvailPopup(svcProblems);
            first = first || $('.service-options', step);
          }
        }
      }
      if (i === 1) {
        if ($('#name').value.trim().length < 2) first = flag(first, $('#name'), 'Please enter your full name.');
        var m = $('#mobile').value.replace(/[^\d+]/g, '');
        if (!/^(\+?91|0)?[6-9]\d{9}$/.test(m) && !/^\+\d{8,15}$/.test(m)) first = flag(first, $('#mobile'), 'Please enter a valid 10-digit mobile number.');
        var em = $('#email').value.trim();
        if (em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) first = flag(first, $('#email'), 'Please enter a valid email address, or leave it empty.');
        if (!dateInput.value) first = flag(first, dateInput, 'Please choose a date.');
        else if ((dateInput.min && dateInput.value < dateInput.min) || (dateInput.max && dateInput.value > dateInput.max)) first = flag(first, dateInput, 'Please choose a date within the booking window shown below.');
        else if (step2AvailabilityBlocked()) {
          showAvailPopup(availabilityBlockMessages());
          first = first || dateInput;
        } else {
          sel.forEach(function (s) {
            if (servicePanelBlocked(String(s.id))) return;
            var panel = $('[data-panel="' + s.id + '"]', step); if (!panel) return;
            var slot = $('[data-slot-select]', panel);
            if (slot && !slot.disabled && !slot.value) first = flag(first, slot, 'Please choose a time for ' + s.name + '.');
            $$('[data-required]', panel).forEach(function (f) { if (!f.disabled && !f.value.trim()) first = flag(first, f, 'This field is required.'); });
            var p = $('input[name^="participants"]', panel);
            if (p && !p.disabled && (!(+p.value >= 1) || +p.value > +p.max)) first = flag(first, p, 'Enter a number between 1 and ' + p.max + '.');
          });
        }
      }
      if (i === 2) {
        if (step2AvailabilityBlocked()) {
          showAvailPopup(availabilityBlockMessages());
          return false;
        }
        var foodOnly = sel.some(function (s) { return s.foodOnly; });
        if (foodOnly || (addFood && addFood.checked)) {
          var any = false;
          $$('[data-coupon]', step).forEach(function (c) {
            var chk = $('[data-coupon-check]', c), q = $('[data-qty]', c);
            if (chk.checked) { if (!(+q.value >= 1)) first = flag(first, q, 'Enter the number of persons.'); else any = true; }
          });
          if (!any && !first) first = flag(first, couponsBox, 'Please choose a coupon category and enter the number of persons' + (foodOnly ? '.' : ', or untick "Add Food".'));
        }
      }
      if (first) { var f = first.matches && first.matches('input,select,textarea') ? first : first.querySelector && first.querySelector('input,select,textarea'); (f || first).focus && (f || first).focus(); return false; }
      return true;
    }

    // Remove a field's error as soon as the devotee changes it.
    function clearField(e) {
      var el = e.target, holder = el.closest && el.closest('.field, .coupon, fieldset');
      if (!holder) return;
      $$('.js-error', holder).forEach(function (n) { if (holder.matches('fieldset') ? n.parentNode === holder || el.closest('.service-options') : true) n.remove(); });
      if (el.getAttribute('data-js-invalid')) { el.removeAttribute('aria-invalid'); el.removeAttribute('data-js-invalid'); }
    }
    form.addEventListener('input', clearField);
    form.addEventListener('change', clearField);

    $$('[data-next]', form).forEach(function (b) { b.addEventListener('click', function () { if (validateStep(current)) show(current + 1, true); }); });
    $$('[data-prev]', form).forEach(function (b) { b.addEventListener('click', function () { show(current - 1, true); }); });
    form.addEventListener('submit', function (e) {
      for (var i = 0; i < steps.length; i++) { if (!validateStep(i)) { e.preventDefault(); show(i, false); return; } }
      if (form.getAttribute('data-book-submit-ok') === '1') {
        form.removeAttribute('data-book-submit-ok');
        return;
      }
      var checkUrl = form.getAttribute('data-book-check');
      if (!checkUrl || !window.fetch) return;
      e.preventDefault();
      var btn = form.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.textContent = 'Checking availability…'; }
      runServerBookingCheck(checkUrl, new FormData(form), function () {
        form.setAttribute('data-book-submit-ok', '1');
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
      }, function () {
        if (window.jtBusyDone) window.jtBusyDone();
        if (btn) { btn.disabled = false; btn.textContent = 'Review booking'; }
      });
    });

    svcBoxes.forEach(function (box) {
      box.addEventListener('change', function () { sync(); });
    });

    var prasadEntry = form.getAttribute('data-entry-mode') === 'prasad';

    function sync() {
      var sel = selected();
      var foodOnly = sel.some(function (s) { return s.foodOnly; }) || prasadEntry;
      var foodAllowed = foodOnly || sel.some(function (s) { return !s.foodOnly && s.allowFood; });
      $$('[data-panel]', form).forEach(function (p) {
        var on = sel.some(function (s) { return String(s.id) === p.getAttribute('data-panel'); });
        if (on) p.removeAttribute('data-inactive'); else p.setAttribute('data-inactive', '');
      });
      var row = $('[data-add-food-row]', form), note = $('[data-food-only-note]', form);
      if (row) row.hidden = prasadEntry || foodOnly || (!foodAllowed && sel.length > 0);
      if (note) note.hidden = prasadEntry || !foodOnly;
      if (addFood && !foodAllowed && !foodOnly && sel.length > 0) {
        addFood.checked = false;
      }
      var showCoupons = prasadEntry || foodOnly || (addFood && addFood.checked);
      if (couponsBox) { if (showCoupons) couponsBox.removeAttribute('data-collapsed'); else couponsBox.setAttribute('data-collapsed', ''); }
      estimate();
      applyAvailabilityFields(lastAvailServices);
      applyServiceAvailability(lastAvailServices);
    }
    if (addFood) addFood.addEventListener('change', function () {
      if (addFood.checked) { var firstCheck = $('[data-coupon-check]', form); if (firstCheck && !$$('[data-coupon-check]:checked', form).length) { /* leave choice to devotee */ } }
      sync();
    });

    // Coupon quantity steppers
    $$('[data-coupon]', form).forEach(function (c) {
      var id = c.getAttribute('data-coupon'), q = $('[data-qty]', c), chk = $('[data-coupon-check]', c);
      function clamp(v) { v = parseInt(v, 10); if (isNaN(v) || v < 0) v = 0; var mx = parseInt(q.max, 10) || 999; return Math.min(v, mx); }
      $('[data-inc="' + id + '"]', c).addEventListener('click', function () { q.value = clamp((+q.value || 0) + 1); chk.checked = +q.value > 0; estimate(); schedulePrasadamRefresh(); });
      $('[data-dec="' + id + '"]', c).addEventListener('click', function () { q.value = clamp((+q.value || 0) - 1); if (+q.value === 0) { q.value = ''; chk.checked = false; } estimate(); schedulePrasadamRefresh(); });
      q.addEventListener('input', function () { if (q.value !== '') { q.value = clamp(q.value) || ''; } chk.checked = +q.value > 0; estimate(); schedulePrasadamRefresh(); });
      chk.addEventListener('change', function () { if (chk.checked && !(+q.value > 0)) q.value = 1; if (!chk.checked) q.value = ''; estimate(); schedulePrasadamRefresh(); });
    });

    function estimate() {
      var box = $('[data-estimate]', form); if (!box) return;
      var sel = selected(), lines = [], total = 0;
      sel.forEach(function (s) { if (!s.foodOnly && s.price > 0) { lines.push([s.name, s.price]); total += s.price; } else if (!s.foodOnly) lines.push([s.name, null]); });
      var foodOn = sel.some(function (s) { return s.foodOnly; }) || (addFood && addFood.checked);
      (pricing.coupons || []).forEach(function (c) {
        var q = $('[data-qty="' + c.id + '"]', form), chk = $('[data-coupon-check="' + c.id + '"]', form), out = $('[data-line-total="' + c.id + '"]', form);
        var n = q ? (+q.value || 0) : 0, on = foodOn && chk && chk.checked && n > 0;
        if (out) out.textContent = on ? rupees(c.price) + ' × ' + n + ' = ' + rupees(c.price * n) : '';
        if (on) { lines.push([c.name + ' × ' + n, c.price * n]); total += c.price * n; }
      });
      if (pricing.extra && pricing.extra.amount > 0) { lines.push([pricing.extra.label, pricing.extra.amount]); total += pricing.extra.amount; }
      var dl = $('[data-estimate-lines]', box); dl.innerHTML = '';
      lines.forEach(function (l) {
        var dt = document.createElement('dt'), dd = document.createElement('dd');
        dt.textContent = l[0]; dd.textContent = l[1] === null ? 'to be confirmed' : rupees(l[1]);
        dl.appendChild(dt); dl.appendChild(dd);
      });
      $('[data-estimate-total]', box).textContent = rupees(total);
      box.hidden = lines.length === 0;
    }

    // Availability for the chosen date: slot places left, blocked dates.
    var slotLabels = {};
    $$('[data-slot-select]', form).forEach(function (sel) { $$('option', sel).forEach(function (o) { slotLabels[o.value] = o.textContent; }); });
    function fillAvailDialogBody(body, problems) {
      body.innerHTML = '';
      if (problems.length === 1) {
        var p = document.createElement('p'); p.textContent = problems[0]; body.appendChild(p);
      } else {
        var ul = document.createElement('ul');
        problems.forEach(function (t) { var li = document.createElement('li'); li.textContent = t; ul.appendChild(li); });
        body.appendChild(ul);
      }
    }
    function showAvailPopup(problems) {
      if (!problems.length) return;
      if (window.jtBusyDone) window.jtBusyDone();
      var title = $('#avail-dialog-title');
      if (title) title.textContent = problems.length > 1 ? 'Some services are not available' : 'Date not available';
      var body = $('#avail-dialog-body');
      if (body) fillAvailDialogBody(body, problems);
      if (availDialog && availDialog.showModal) {
        if (availDialog.open) availDialog.close();
        availDialog.showModal();
        var ok = $('[data-avail-dialog-ok]', availDialog);
        if (ok) setTimeout(function () { ok.focus(); }, 0);
      } else if (window.alert) {
        window.alert(problems.join('\n\n'));
      }
    }
    if (availDialog) {
      availDialog.addEventListener('close', function () {
        if (window.jtBusyDone) window.jtBusyDone();
        if (dateInput) dateInput.focus();
      });
    }
    function setAvailDisabled(el, off) {
      if (!el || el === dateInput || el.matches('[data-prev]')) return;
      if (off) { el.disabled = true; el.setAttribute('data-avail-disabled', '1'); }
      else if (el.getAttribute('data-avail-disabled')) { el.disabled = false; el.removeAttribute('data-avail-disabled'); }
    }
    function clearAvailabilityBlockUI() {
      lastAvailProblems = [];
      lastAvailServices = null;
      lastPrasadam = null;
      lastAvailDate = '';
      var dm = $('[data-date-message]', form);
      if (dm) { dm.textContent = ''; dm.hidden = true; }
      setPrasadamLiveLoading(false);
      applyAvailabilityFields(null);
      applyServiceAvailability(null);
    }
    function availabilityBlockMessages() {
      if (!dateInput || !dateInput.value) return [];
      if (lastAvailDate === dateInput.value && lastAvailProblems.length) return lastAvailProblems;
      // New date picked — wait for fetch; do not keep blocking from the previous date's message.
      if (lastAvailDate !== dateInput.value) return [];
      var dm = $('[data-date-message]', form);
      if (dm && !dm.hidden && dm.textContent.trim()) return [dm.textContent.trim()];
      return [];
    }
    function step2AvailabilityBlocked() {
      return availabilityBlockMessages().length > 0;
    }
    function servicePanelBlocked(serviceId) {
      if (step2AvailabilityBlocked()) return true;
      if (!lastAvailServices || lastAvailDate !== dateInput.value) return false;
      var a = lastAvailServices[serviceId];
      if (!a || !a.available) return true;
      var panel = $('[data-panel="' + serviceId + '"]', form);
      var slotSel = panel && $('[data-slot-select]', panel);
      if (slotSel) {
        var open = false;
        $$('option', slotSel).forEach(function (o) { if (o.value && !o.disabled) open = true; });
        if (!open) return true;
      }
      return false;
    }
    function applyAvailabilityFields(services) {
      var step2 = steps[1];
      var step3 = steps[2];
      var globalOff = step2AvailabilityBlocked();
      ['#name', '#mobile', '#email', '#instructions'].forEach(function (id) { setAvailDisabled($(id, form), globalOff); });
      var waIn = $('.wa-opt input', form);
      if (waIn) setAvailDisabled(waIn, globalOff);
      $$('[data-next]', step2).forEach(function (btn) { btn.disabled = globalOff; });
      if (step3) {
        var foodOff = globalOff;
        $$('input, select, textarea, button', step3).forEach(function (el) {
          setAvailDisabled(el, foodOff);
        });
      }
      $$('[data-panel]', form).forEach(function (panel) {
        var sid = panel.getAttribute('data-panel');
        var hidden = panel.hasAttribute('data-inactive');
        var block = !hidden && servicePanelBlocked(sid);
        $$('input, select, textarea, button', panel).forEach(function (el) { setAvailDisabled(el, block); });
        panel.classList.toggle('panel-unavailable', block);
      });
      if (step2) step2.classList.toggle('step-availability-blocked', globalOff);
      if (step3) step3.classList.toggle('step-availability-blocked', globalOff);
      if (!services && !globalOff) {
        $$('[data-avail-disabled]', form).forEach(function (el) { setAvailDisabled(el, false); });
      }
    }
    function setPrasadamLiveLoading(on) {
      $$('[data-stat-live-row]', form).forEach(function (row) {
        row.classList.toggle('is-loading', on);
        var val = $('[data-stat-live-value]', row);
        if (!val) return;
        if (on) {
          if (!val.getAttribute('data-prev-live')) val.setAttribute('data-prev-live', val.textContent);
          val.textContent = 'Checking temple records…';
        } else if (val.getAttribute('data-prev-live')) {
          val.removeAttribute('data-prev-live');
        }
      });
    }

    function applyPrasadCaps(prasadam) {
      if (prasadam) lastPrasadam = prasadam;
      var data = lastPrasadam;
      var hasDate = dateInput && dateInput.value && lastAvailDate === dateInput.value;
      (pricing.coupons || []).forEach(function (c) {
        var couponEl = $('[data-coupon="' + c.id + '"]', form);
        var q = $('[data-qty="' + c.id + '"]', form);
        if (!q) return;
        var panel = $('[data-coupon-availability="' + c.id + '"]', form);
        var liveRow = panel && $('[data-stat-live-row]', panel);
        var liveVal = panel && $('[data-stat-live-value]', panel);
        var statusEl = panel && $('[data-coupon-status]', panel);
        var incBtn = couponEl && $('[data-inc="' + c.id + '"]', couponEl);
        var perBooking = parseInt(c.max, 10) || parseInt(q.getAttribute('data-per-booking'), 10) || 999;
        var cap = perBooking;
        var qty = +q.value || 0;
        var row = data && (data[c.id] || data[String(c.id)]);
        if (liveRow && liveVal && c.dailyMax) {
          if (!hasDate) {
            liveVal.textContent = 'Choose your date in step 2 to see live counts';
          } else if (!row || row.remaining === null || row.remaining === undefined) {
            liveVal.textContent = 'No daily cap on this item';
          } else {
            var used = parseInt(row.used, 10);
            var left = parseInt(row.remaining, 10);
            if (!isNaN(used) && !isNaN(left)) {
              cap = Math.min(cap, Math.max(0, left));
              liveVal.innerHTML = '<strong>' + used + '</strong> already booked · <strong>' + left + '</strong> still available';
            }
          }
        }
        if (statusEl) {
          if (!c.dailyMax || !hasDate || !row || row.remaining === null || row.remaining === undefined) {
            statusEl.hidden = true;
          } else {
            var remaining = parseInt(row.remaining, 10);
            statusEl.hidden = false;
            if (remaining <= 0) {
              statusEl.textContent = 'Fully booked on this date. Choose another date or item';
              statusEl.className = 'coupon-status badge badge-bad';
            } else if (qty > 0) {
              var after = remaining - qty;
              statusEl.textContent = 'Your selection: ' + personsCount(qty)
                + (after <= 0 ? ' (uses the last places available today)' : ' · ' + personsCount(after) + ' would remain for others');
              statusEl.className = 'coupon-status badge ' + (after <= 2 ? 'badge-warn' : 'badge-ok');
            } else if (remaining <= 3) {
              statusEl.textContent = 'Only ' + personsCount(remaining) + ' left on this date';
              statusEl.className = 'coupon-status badge badge-warn';
            } else {
              statusEl.textContent = personsCount(remaining) + ' available on this date';
              statusEl.className = 'coupon-status badge badge-ok';
            }
          }
        }
        q.max = String(cap);
        if (incBtn) incBtn.disabled = cap <= 0 || qty >= cap;
        if (q.value !== '' && qty > cap) {
          q.value = cap > 0 ? String(cap) : '';
          var chk = $('[data-coupon-check="' + c.id + '"]', form);
          if (chk) chk.checked = +q.value > 0;
        }
      });
    }

    function applyServiceAvailability(services) {
      var hasDate = dateInput && dateInput.value && lastAvailDate === dateInput.value;
      (pricing.services || []).forEach(function (s) {
        if (s.foodOnly) return;
        var box = $('input[data-service="' + s.id + '"]', form);
        var card = box && box.closest('.option-card');
        var a = services && (services[s.id] || services[String(s.id)]);
        var cap = s.dailyCapacity;
        $$('[data-service-availability="' + s.id + '"]', form).forEach(function (panel) {
          var liveVal = $('[data-stat-live-value]', panel);
          if (!liveVal) return;
          if (!hasDate) {
            liveVal.textContent = cap != null
              ? 'Choose your date in step 2 to see live counts'
              : 'Choose your date in step 2 to see open times';
          } else if (!a) {
            liveVal.textContent = 'Checking temple records…';
          } else if (cap != null && a.capacity) {
            var used = parseInt(a.capacity.used, 10);
            var left = a.capacity.remaining;
            if (left === null || left === undefined) {
              liveVal.textContent = 'No daily cap on this puja';
            } else {
              liveVal.innerHTML = '<strong>' + used + '</strong> already booked · <strong>' + left + '</strong> still available';
            }
          } else if (s.requiresSlot) {
            var sum = slotOpenSummary(a.slots || []);
            if (!sum || sum.open === 0) liveVal.textContent = 'No open time slots on this date';
            else if (sum.places > 0 && sum.places <= 8) {
              liveVal.textContent = sum.open + ' slot' + (sum.open === 1 ? '' : 's') + ' open · '
                + sum.places + ' place' + (sum.places === 1 ? '' : 's') + ' left';
            } else {
              liveVal.textContent = sum.open + ' of ' + sum.total + ' time slots open on this date';
            }
          }
        });
        var unavailable = !!(hasDate && a && !a.available);
        if (card) card.classList.toggle('option-unavailable', unavailable);
        if (box) box.disabled = unavailable;
        if (!hasDate || !a) {
          setServiceStatus(s.id, '', 'coupon-status badge badge-info', true);
          return;
        }
        if (!a.available) {
          setServiceStatus(s.id, a.message || (s.name + ' is not available on this date.'), 'coupon-status badge badge-bad', false);
        } else if (cap != null && a.capacity && a.capacity.remaining === 0) {
          setServiceStatus(s.id, 'Fully booked on this date. Choose another date or service', 'coupon-status badge badge-bad', false);
        } else if (cap != null && a.capacity && a.capacity.remaining !== null && a.capacity.remaining <= 2) {
          setServiceStatus(s.id, 'Only ' + bookingsCount(a.capacity.remaining) + ' left on this date', 'coupon-status badge badge-warn', false);
        } else if (cap != null && a.capacity && a.capacity.remaining !== null) {
          setServiceStatus(s.id, bookingsCount(a.capacity.remaining) + ' still available on this date', 'coupon-status badge badge-ok', false);
        } else if (s.requiresSlot) {
          var sum2 = slotOpenSummary(a.slots || []);
          if (sum2 && sum2.open > 0 && sum2.places > 0 && sum2.places <= 5) {
            setServiceStatus(s.id, 'Limited places in open time slots. Choose your time below', 'coupon-status badge badge-warn', false);
          } else {
            setServiceStatus(s.id, '', 'coupon-status badge badge-info', true);
          }
        } else {
          setServiceStatus(s.id, '', 'coupon-status badge badge-info', true);
        }
      });
    }

    function schedulePrasadamRefresh() {
      applyPrasadCaps(null);
      clearTimeout(prasadamRefreshTimer);
      prasadamRefreshTimer = setTimeout(function () {
        if (dateInput && dateInput.value && selected().length) loadAvailability({ silent: true, prasadamFocus: true });
        else applyPrasadCaps(null);
      }, 220);
    }

    function loadAvailability(opts) {
      opts = opts || {};
      var msg = $('[data-date-message]', form);
      if (!dateInput.value) {
        lastAvailDate = '';
        lastAvailProblems = [];
        lastAvailServices = null;
        lastPrasadam = null;
        if (msg) { msg.textContent = ''; msg.hidden = true; }
        applyPrasadCaps(null);
        applyAvailabilityFields(null);
        applyServiceAvailability(null);
        return;
      }
      if (!pricing.availabilityUrl || !window.fetch) return;
      var fetchId = ++availFetchId;
      clearAvailabilityBlockUI();
      if (opts.prasadamFocus) setPrasadamLiveLoading(true);
      else applyServiceAvailability(null);
      fetch(pricing.availabilityUrl + '?date=' + encodeURIComponent(dateInput.value), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (fetchId !== availFetchId) return;
          setPrasadamLiveLoading(false);
          if (!data || !data.services) return;
          var problems = [];
          selected().forEach(function (s) { var a = data.services[s.id]; if (a && !a.available && a.message) problems.push(a.message); });
          lastAvailDate = dateInput.value;
          lastAvailProblems = problems;
          lastAvailServices = data.services;
          lastPrasadam = data.prasadam || null;
          if (msg) { msg.textContent = problems.join(' '); msg.hidden = !problems.length; }
          if (problems.length && !opts.silent) showAvailPopup(problems);
          if (!opts.prasadamFocus) {
            $$('[data-slot-select]', form).forEach(function (sel) {
              var sid = sel.getAttribute('data-slot-select');
              var a = data.services[sid]; if (!a) return;
              if (!a.available) {
                setAvailDisabled(sel, true);
                sel.value = '';
                return;
              }
              setAvailDisabled(sel, false);
              var open = {}; (a.slots || []).forEach(function (sl) { open[sl.id] = sl; });
              $$('option', sel).forEach(function (o) {
                if (!o.value) return;
                var sl = open[o.value];
                if (!sl) { o.disabled = true; o.textContent = slotLabels[o.value] + ' (not available this day)'; }
                else if (sl.remaining === 0) { o.disabled = true; o.textContent = slotLabels[o.value] + ' - full'; }
                else { o.disabled = false; o.textContent = slotLabels[o.value] + (sl.remaining !== null && sl.remaining <= 5 ? ' (' + sl.remaining + ' place' + (sl.remaining === 1 ? '' : 's') + ' left)' : ''); }
                if (o.disabled && o.selected) sel.value = '';
              });
            });
            applyAvailabilityFields(data.services);
          }
          applyServiceAvailability(data.services);
          applyPrasadCaps(data.prasadam || null);
          estimate();
        }).catch(function () {
          if (fetchId !== availFetchId) return;
          setPrasadamLiveLoading(false);
        });
    }
    function onServiceDateChange() {
      if (availDialog && availDialog.open) availDialog.close();
      if (window.jtBusyDone) window.jtBusyDone();
      loadAvailability();
    }
    if (dateInput) {
      var dateBeforePick = '';
      dateInput.addEventListener('focus', function () { dateBeforePick = dateInput.value; });
      dateInput.addEventListener('change', onServiceDateChange);
      dateInput.addEventListener('input', onServiceDateChange);
      dateInput.addEventListener('blur', function () {
        if (!dateInput.value || dateInput.value !== dateBeforePick) return;
        if (lastAvailDate === dateInput.value && lastAvailProblems.length) showAvailPopup(lastAvailProblems);
      });
    }
    svcBoxes.forEach(function (b) { b.addEventListener('change', loadAvailability); });

    // Initial state: after a server error, open the first step containing an error.
    sync();
    var start = 0;
    var errKey = $('[data-error-key]');
    if (errKey) {
      var k = errKey.getAttribute('data-error-key');
      start = k === 'services' ? 0 : (/^food/.test(k) ? 2 : 1);
    } else if (form.getAttribute('data-entry-mode') === 'prasad') {
      var prasadSel = selected();
      if (prasadSel.length === 1 && prasadSel[0].foodOnly) start = 1;
    }
    show(start, false);
    if (dateInput && dateInput.value) loadAvailability();
  }

  // ---------------------------------------------------------------- optional online gateway (Razorpay)
  function busyShow(text) {
    if (window.jtBusy) window.jtBusy(text || 'Please wait');
  }
  function busyHide() {
    if (window.jtBusyDone) window.jtBusyDone();
  }
  function gatewaySyncFromEl(el, onOk, onFail) {
    var syncUrl = el.getAttribute('data-gateway-sync') || el.getAttribute('data-gateway-sync-only');
    if (!syncUrl) return onFail('Could not check payment status.');
    busyShow('Confirming your payment');
    var body = new URLSearchParams({ _csrf: el.getAttribute('data-csrf') });
    fetch(syncUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { error: 'Server error while checking payment.' }; }); })
      .then(function (d) { if (d.redirect) onOk(d.redirect); else onFail(d.error || 'Payment not found yet.'); })
      .catch(function () { onFail('Network error while checking payment. Please try again.'); });
  }
  function gatewayVerifyFromEl(gw, resp, fail) {
    busyShow('Confirming your payment');
    var v = new URLSearchParams(resp);
    v.append('_csrf', gw.getAttribute('data-csrf'));
    fetch(gw.getAttribute('data-gateway-verify'), { method: 'POST', body: v, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { error: 'Server error after payment.' }; }); })
      .then(function (d) {
        if (d.redirect) { window.location = d.redirect; return; }
        gatewaySyncFromEl(gw, function (url) { window.location = url; }, function (m) {
          fail((d.error || 'Payment could not be verified.') + ' ' + m);
        });
      })
      .catch(function () {
        gatewaySyncFromEl(gw, function (url) { window.location = url; }, fail);
      });
  }
  var gw = $('[data-gateway-pay]');
  if (gw) {
    gw.addEventListener('click', function () {
      var err = $('[data-gateway-error]');
      function fail(m) {
        busyHide();
        if (err) { err.textContent = m; err.hidden = false; }
        gw.disabled = false;
      }
      gw.disabled = true;
      if (err) err.hidden = true;
      busyShow(gw.getAttribute('data-busy') || 'Opening secure payment');
      var body = new URLSearchParams({ _csrf: gw.getAttribute('data-csrf') });
      fetch(gw.getAttribute('data-gateway-pay'), { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (o) {
          if (!o || o.error || !window.Razorpay) return fail((o && o.error) || 'Online payment is unavailable. Please try again or contact the temple office.');
          busyHide();
          var rz = new window.Razorpay({
            key: o.key, order_id: o.order_id, amount: o.amount, currency: o.currency, name: o.name, description: o.description, prefill: o.prefill,
            handler: function (resp) { gatewayVerifyFromEl(gw, resp, fail); },
            modal: { ondismiss: function () { busyHide(); gw.disabled = false; } }
          });
          rz.open();
        }).catch(function () { fail('Online payment is unavailable. Please try again or contact the temple office.'); });
    });
  }
  var gwSync = $('[data-gateway-sync-only]');
  if (gwSync) {
    gwSync.addEventListener('click', function () {
      var err = $('[data-gateway-error]');
      gwSync.disabled = true;
      busyShow('Checking payment status');
      gatewaySyncFromEl(gwSync, function (url) { window.location = url; }, function (m) {
        busyHide();
        if (err) { err.textContent = m; err.hidden = false; }
        gwSync.disabled = false;
      });
    });
  }

  var reviewForm = $('#review-confirm-form');
  if (reviewForm) {
    var reviewCheck = reviewForm.getAttribute('data-review-check');
    var reviewWarn = $('#review-avail-warn');
    function reviewAvailabilityFailed(data) {
      var problems = (data && data.problems && data.problems.length)
        ? data.problems
        : ['Something is no longer available on the date you chose.'];
      showAvailabilityPopup(problems, (data && data.hint) ? data.hint : null);
      if (reviewWarn) {
        reviewWarn.textContent = ((data && data.hint) ? data.hint : 'Some services or prasadam are no longer available on this date.')
          + ' Use Edit booking to choose another date, service, or quantity.';
        reviewWarn.hidden = false;
      }
    }
    reviewForm.addEventListener('submit', function (e) {
      if (reviewForm.getAttribute('data-review-submit-ok') === '1') {
        reviewForm.removeAttribute('data-review-submit-ok');
        return;
      }
      if (!reviewCheck || !window.fetch) return;
      e.preventDefault();
      var btn = reviewForm.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.textContent = 'Checking availability…'; }
      runServerBookingCheck(reviewCheck, null, function () {
        if (reviewWarn) reviewWarn.hidden = true;
        reviewForm.setAttribute('data-review-submit-ok', '1');
        if (btn) btn.disabled = false;
        if (reviewForm.requestSubmit) reviewForm.requestSubmit();
        else reviewForm.submit();
      }, function () {
        if (btn) { btn.disabled = false; btn.textContent = 'Submit booking'; }
      });
    });
    if (reviewCheck && window.fetch) {
      fetch(reviewCheck, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(parseCheckResponse)
        .then(function (res) {
          if (!res.data.ok) reviewAvailabilityFailed(res.data);
          else if (reviewWarn) reviewWarn.hidden = true;
        });
      setInterval(function () {
        fetch(reviewCheck, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
          .then(parseCheckResponse)
          .then(function (res) {
            if (res.data.ok) {
              if (reviewWarn) reviewWarn.hidden = true;
            } else {
              if (reviewWarn) {
                reviewWarn.textContent = ((res.data.hint) ? res.data.hint : 'Availability changed while you were on this page.')
                  + ' Use Edit booking or try submit again.';
                reviewWarn.hidden = false;
              }
            }
          });
      }, 90000);
    }
  }
})();
