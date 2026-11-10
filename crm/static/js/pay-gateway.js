(function () {
  'use strict';

  var cfg = window.SJT_SUBSCRIPTION_PAY || {};
  var payBtn = document.getElementById('sub-pay-btn');
  if (!payBtn || !cfg.token) {
    return;
  }

  var errEl = document.getElementById('sub-pay-error');
  var syncBtn = document.getElementById('sub-pay-sync');

  function post(url, extra) {
    var body = new URLSearchParams();
    body.set('csrf', cfg.csrf || '');
    if (extra) {
      Object.keys(extra).forEach(function (k) {
        body.set(k, extra[k]);
      });
    }
    return fetch(url, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: body,
      credentials: 'same-origin',
    }).then(function (res) {
      return res.json().then(function (data) {
        return { status: res.status, data: data };
      });
    });
  }

  function busyShow(text) {
    if (window.jtBusy) {
      window.jtBusy(text || 'Please wait…');
    }
  }

  function busyHide() {
    if (window.jtBusyDone) {
      window.jtBusyDone();
    }
  }

  function showError(msg) {
    if (!errEl) {
      return;
    }
    errEl.textContent = msg || '';
    errEl.hidden = !msg;
    if (syncBtn && msg) {
      syncBtn.hidden = false;
    }
  }

  function redirect(url) {
    window.location.href = url;
  }

  function openCheckout(order) {
    if (!window.Razorpay) {
      showError('Payment could not load. Refresh and try again.');
      return;
    }
    var options = {
      key: order.key,
      amount: order.amount,
      currency: order.currency || 'INR',
      name: order.name,
      description: order.description,
      order_id: order.order_id,
      prefill: order.prefill || {},
      theme: { color: '#7A1626' },
      handler: function (response) {
        busyShow('Confirming payment…');
        post(cfg.verifyUrl, {
          razorpay_payment_id: response.razorpay_payment_id,
          razorpay_order_id: response.razorpay_order_id,
          razorpay_signature: response.razorpay_signature,
        })
          .then(function (result) {
            busyHide();
            if (result.data && result.data.redirect) {
              redirect(result.data.redirect);
              return;
            }
            showError((result.data && result.data.error) || 'Payment could not be confirmed.');
          })
          .catch(function () {
            busyHide();
            showError('Network error while confirming payment.');
          });
      },
      modal: {
        ondismiss: function () {
          busyHide();
        },
      },
    };
    var rzp = new window.Razorpay(options);
    rzp.on('payment.failed', function () {
      busyHide();
      showError('Payment did not complete. You can try again or use payment sync.');
    });
    rzp.open();
  }

  function startPay() {
    showError('');
    if (syncBtn) {
      syncBtn.hidden = true;
    }
    busyShow('Starting payment…');
    post(cfg.orderUrl, {})
      .then(function (result) {
        if (result.data && result.data.redirect) {
          busyHide();
          redirect(result.data.redirect);
          return;
        }
        if (result.status !== 200 || !result.data || !result.data.order_id) {
          busyHide();
          showError((result.data && result.data.error) || 'Could not start online payment.');
          return;
        }
        busyHide();
        openCheckout(result.data);
      })
      .catch(function () {
        busyHide();
        showError('Network error. Check your connection and try again.');
      });
  }

  function syncPayment() {
    showError('');
    busyShow('Checking payment status…');
    post(cfg.syncUrl, {})
      .then(function (result) {
        busyHide();
        if (result.data && result.data.redirect) {
          redirect(result.data.redirect);
          return;
        }
        showError((result.data && result.data.error) || 'Payment not found yet.');
      })
      .catch(function () {
        busyHide();
        showError('Network error while syncing payment.');
      });
  }

  payBtn.addEventListener('click', startPay);
  if (syncBtn) {
    syncBtn.addEventListener('click', syncPayment);
  }
})();
