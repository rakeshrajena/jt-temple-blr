(function () {
  'use strict';

  var form = document.getElementById('donation-resend');
  var msg = document.getElementById('donation-resend-msg');
  var cfg = window.SJT_DONATION_RESEND || {};
  if (!form) {
    return;
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (msg) {
      msg.hidden = true;
      msg.textContent = '';
    }
    var fd = new FormData(form);
    var body = new URLSearchParams();
    fd.forEach(function (v, k) {
      body.set(k, v);
    });
    var url = cfg.postUrl || form.getAttribute('action') || '';
    fetch(url, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: body,
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { status: res.status, data: data };
        });
      })
      .then(function (r) {
        if (r.status !== 200) {
          throw new Error(r.data && r.data.error ? r.data.error : 'Could not send email.');
        }
        if (msg) {
          msg.textContent = r.data.message || 'Receipt sent.';
          msg.classList.remove('donation-error');
          msg.classList.add('donation-note');
          msg.hidden = false;
        }
      })
      .catch(function (e) {
        if (msg) {
          msg.textContent = e.message || String(e);
          msg.classList.add('donation-error');
          msg.hidden = false;
        }
      });
  });
})();
