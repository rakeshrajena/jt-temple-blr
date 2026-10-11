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
    var svcBoxes = $$('input[name="services[]"]', form);
    var addFood = $('#add_food', form);
    var couponsBox = $('[data-coupons]', form);
    var dateInput = $('#service_date', form);
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
      if (i === 0 && !sel.length) first = flag(first, $('.service-options', step), 'Please choose at least one service.');
      if (i === 1) {
        if ($('#name').value.trim().length < 2) first = flag(first, $('#name'), 'Please enter your full name.');
        var m = $('#mobile').value.replace(/[^\d+]/g, '');
        if (!/^(\+?91|0)?[6-9]\d{9}$/.test(m) && !/^\+\d{8,15}$/.test(m)) first = flag(first, $('#mobile'), 'Please enter a valid 10-digit mobile number.');
        var em = $('#email').value.trim();
        if (em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) first = flag(first, $('#email'), 'Please enter a valid email address, or leave it empty.');
        if (!dateInput.value) first = flag(first, dateInput, 'Please choose a date.');
        else if ((dateInput.min && dateInput.value < dateInput.min) || (dateInput.max && dateInput.value > dateInput.max)) first = flag(first, dateInput, 'Please choose a date within the booking window shown below.');
        sel.forEach(function (s) {
          var panel = $('[data-panel="' + s.id + '"]', step); if (!panel) return;
          var slot = $('[data-slot-select]', panel);
          if (slot && !slot.value) first = flag(first, slot, 'Please choose a time for ' + s.name + '.');
          $$('[data-required]', panel).forEach(function (f) { if (!f.value.trim()) first = flag(first, f, 'This field is required.'); });
          var p = $('input[name^="participants"]', panel);
          if (p && (!(+p.value >= 1) || +p.value > +p.max)) first = flag(first, p, 'Enter a number between 1 and ' + p.max + '.');
        });
        var unavailable = $('[data-date-message]:not([hidden])', step);
        if (unavailable && unavailable.textContent.trim() && !first) first = dateInput;
      }
      if (i === 2) {
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
      var btn = form.querySelector('button[type=submit]'); if (btn) setTimeout(function () { btn.disabled = true; btn.textContent = 'Checking…'; }, 0);
    });

    // Service selection rules: Food-Only and "book alone" services are exclusive.
    svcBoxes.forEach(function (box) {
      box.addEventListener('change', function () {
        if (box.checked) {
          var me = svcById[box.value] || {};
          svcBoxes.forEach(function (o) {
            if (o === box || !o.checked) return;
            var other = svcById[o.value] || {};
            if (me.foodOnly || other.foodOnly || !me.combinable || !other.combinable) o.checked = false;
          });
        }
        sync();
      });
    });

    function sync() {
      var sel = selected();
      var foodOnly = sel.some(function (s) { return s.foodOnly; });
      var foodAllowed = sel.some(function (s) { return s.allowFood; });
      $$('[data-panel]', form).forEach(function (p) {
        var on = sel.some(function (s) { return String(s.id) === p.getAttribute('data-panel'); });
        if (on) p.removeAttribute('data-inactive'); else p.setAttribute('data-inactive', '');
      });
      var row = $('[data-add-food-row]', form), note = $('[data-food-only-note]', form);
      if (row) row.hidden = foodOnly || (!foodAllowed && sel.length > 0);
      if (note) note.hidden = !foodOnly;
      var showCoupons = foodOnly || (addFood && addFood.checked);
      if (couponsBox) { if (showCoupons) couponsBox.removeAttribute('data-collapsed'); else couponsBox.setAttribute('data-collapsed', ''); }
      estimate();
    }
    if (addFood) addFood.addEventListener('change', function () {
      if (addFood.checked) { var firstCheck = $('[data-coupon-check]', form); if (firstCheck && !$$('[data-coupon-check]:checked', form).length) { /* leave choice to devotee */ } }
      sync();
    });

    // Coupon quantity steppers
    $$('[data-coupon]', form).forEach(function (c) {
      var id = c.getAttribute('data-coupon'), q = $('[data-qty]', c), chk = $('[data-coupon-check]', c);
      function clamp(v) { v = parseInt(v, 10); if (isNaN(v) || v < 0) v = 0; var mx = parseInt(q.max, 10) || 999; return Math.min(v, mx); }
      $('[data-inc="' + id + '"]', c).addEventListener('click', function () { q.value = clamp((+q.value || 0) + 1); chk.checked = +q.value > 0; estimate(); });
      $('[data-dec="' + id + '"]', c).addEventListener('click', function () { q.value = clamp((+q.value || 0) - 1); if (+q.value === 0) { q.value = ''; chk.checked = false; } estimate(); });
      q.addEventListener('input', function () { if (q.value !== '') { q.value = clamp(q.value) || ''; } chk.checked = +q.value > 0; estimate(); });
      chk.addEventListener('change', function () { if (chk.checked && !(+q.value > 0)) q.value = 1; if (!chk.checked) q.value = ''; estimate(); });
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
    function loadAvailability() {
      var msg = $('[data-date-message]', form);
      if (!dateInput.value || !pricing.availabilityUrl || !window.fetch) return;
      fetch(pricing.availabilityUrl + '?date=' + encodeURIComponent(dateInput.value), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data || !data.services) return;
          var problems = [];
          selected().forEach(function (s) { var a = data.services[s.id]; if (a && !a.available && a.message) problems.push(a.message); });
          if (msg) { msg.textContent = problems.join(' '); msg.hidden = !problems.length; }
          $$('[data-slot-select]', form).forEach(function (sel) {
            var a = data.services[sel.getAttribute('data-slot-select')]; if (!a) return;
            var open = {}; (a.slots || []).forEach(function (sl) { open[sl.id] = sl; });
            $$('option', sel).forEach(function (o) {
              if (!o.value) return;
              var sl = open[o.value];
              if (!sl) { o.disabled = true; o.textContent = slotLabels[o.value] + ' — not available this day'; }
              else if (sl.remaining === 0) { o.disabled = true; o.textContent = slotLabels[o.value] + ' — full'; }
              else { o.disabled = false; o.textContent = slotLabels[o.value] + (sl.remaining !== null && sl.remaining <= 5 ? ' — ' + sl.remaining + ' place' + (sl.remaining === 1 ? '' : 's') + ' left' : ''); }
              if (o.disabled && o.selected) sel.value = '';
            });
          });
        }).catch(function () {});
    }
    if (dateInput) dateInput.addEventListener('change', loadAvailability);
    svcBoxes.forEach(function (b) { b.addEventListener('change', loadAvailability); });

    // Initial state: after a server error, open the first step containing an error.
    sync();
    var start = 0;
    var errKey = $('[data-error-key]');
    if (errKey) {
      var k = errKey.getAttribute('data-error-key');
      start = k === 'services' ? 0 : (/^food/.test(k) ? 2 : 1);
    }
    show(start, false);
    if (dateInput && dateInput.value) loadAvailability();
  }

  // ---------------------------------------------------------------- optional online gateway (Razorpay)
  var gw = $('[data-gateway-pay]');
  if (gw) {
    gw.addEventListener('click', function () {
      var err = $('[data-gateway-error]');
      function fail(m) { if (err) { err.textContent = m; err.hidden = false; } gw.disabled = false; }
      gw.disabled = true;
      var body = new URLSearchParams({ _csrf: gw.getAttribute('data-csrf') });
      fetch(gw.getAttribute('data-gateway-pay'), { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (o) {
          if (!o || o.error || !window.Razorpay) return fail((o && o.error) || 'Online payment is unavailable. Please use the UPI QR code.');
          var rz = new window.Razorpay({
            key: o.key, order_id: o.order_id, amount: o.amount, currency: o.currency, name: o.name, description: o.description, prefill: o.prefill,
            handler: function (resp) {
              var v = new URLSearchParams(resp); v.append('_csrf', gw.getAttribute('data-csrf'));
              fetch(gw.getAttribute('data-gateway-verify'), { method: 'POST', body: v, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d.redirect) window.location = d.redirect; else fail(d.error || 'Payment could not be verified.'); });
            },
            modal: { ondismiss: function () { gw.disabled = false; } }
          });
          rz.open();
        }).catch(function () { fail('Online payment is unavailable. Please use the UPI QR code.'); });
    });
  }
})();
