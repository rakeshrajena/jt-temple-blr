(function () {
  'use strict';

  var cfg = window.SJT_DONATION || {};
  var form = document.getElementById('donation-form');
  if (!form || !cfg.postUrl) {
    return;
  }

  var errEl = document.getElementById('donation-error');
  var submitBtn = document.getElementById('donation-submit');
  var syncBtn = document.getElementById('donation-sync');
  var amountInput = document.getElementById('donation-amount');
  var amountDisplay = document.getElementById('donation-amount-display');
  var pendingRef = null;

  function busyShow(text) {
    if (window.jtBusy) {
      window.jtBusy(text || 'Please wait');
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
    if (msg) {
      errEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  function formatInr(n) {
    try {
      return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(n);
    } catch (e) {
      return '₹' + n;
    }
  }

  function updateAmountDisplay() {
    var n = parseInt(String(amountInput && amountInput.value ? amountInput.value : ''), 10);
    if (amountDisplay) {
      amountDisplay.textContent = n > 0 ? formatInr(n) : '₹0';
    }
    if (submitBtn) {
      submitBtn.textContent = n > 0 && n <= cfg.maxRupees ? 'Pay ' + formatInr(n) + ' now' : 'Pay now';
    }
  }

  if (amountInput) {
    amountInput.addEventListener('input', updateAmountDisplay);
    updateAmountDisplay();
  }

  function postBody(extra) {
    var fd = new FormData(form);
    var body = new URLSearchParams();
    fd.forEach(function (value, key) {
      body.set(key, value);
    });
    if (extra) {
      Object.keys(extra).forEach(function (k) {
        body.set(k, extra[k]);
      });
    }
    return body;
  }

  function api(action, extra) {
    return fetch(cfg.postUrl, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: postBody(Object.assign({ action: action }, extra || {})),
      credentials: 'same-origin',
    }).then(function (res) {
      return res.json().then(function (data) {
        return { status: res.status, data: data };
      });
    });
  }

  function validateAmount(rupees) {
    var n = parseInt(String(rupees), 10);
    if (!n || n < 1) {
      return 'Please enter a valid donation amount.';
    }
    if (n > cfg.maxRupees) {
      return cfg.offlineMessage || 'Amount exceeds the online limit.';
    }
    return null;
  }

  function startPayment() {
    showError('');
    if (syncBtn) {
      syncBtn.hidden = true;
    }
    pendingRef = null;

    var amountErr = validateAmount(amountInput && amountInput.value);
    if (amountErr) {
      showError(amountErr);
      if (amountInput) {
        amountInput.focus();
      }
      return;
    }

    var emailInput = form.querySelector('[name="email"]');
    var email = emailInput && String(emailInput.value || '').trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showError('Please enter a valid email address for your receipt.');
      if (emailInput) {
        emailInput.focus();
      }
      return;
    }

    if (!form.reportValidity()) {
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
    }
    busyShow('Opening secure payment');

    api('order')
      .then(function (r) {
        if (r.status !== 200) {
          throw new Error(r.data && r.data.error ? r.data.error : 'Could not start payment.');
        }
        pendingRef = r.data.ref_no;
        if (r.data.offline_required) {
          throw new Error(r.data.error);
        }
        busyHide();
        return openRazorpay(r.data);
      })
      .catch(function (e) {
        busyHide();
        showError(e.message || String(e));
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
        }
      });
  }

  function openRazorpay(order) {
    if (typeof Razorpay === 'undefined') {
      throw new Error('Payment script did not load. Please refresh and try again.');
    }
    return new Promise(function (resolve, reject) {
      var options = {
        key: order.key,
        amount: order.amount,
        currency: order.currency || 'INR',
        name: order.name,
        description: order.description,
        order_id: order.order_id,
        prefill: order.prefill || {},
        handler: function (response) {
          if (submitBtn) {
            submitBtn.disabled = true;
          }
          busyShow('Confirming your payment');
          api('verify', {
            ref_no: order.ref_no,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_signature: response.razorpay_signature,
          })
            .then(function (r) {
              busyHide();
              if (r.status !== 200 || !r.data.redirect) {
                throw new Error(r.data && r.data.error ? r.data.error : 'Payment could not be verified.');
              }
              window.location.href = r.data.redirect;
              resolve();
            })
            .catch(function (e) {
              busyHide();
              showError(e.message || String(e));
              if (syncBtn) {
                syncBtn.hidden = false;
              }
              if (submitBtn) {
                submitBtn.disabled = false;
              }
              reject(e);
            });
        },
        modal: {
          ondismiss: function () {
            if (syncBtn && pendingRef) {
              syncBtn.hidden = false;
            }
            reject(new Error('Payment window closed.'));
          },
        },
      };
      var rzp = new Razorpay(options);
      rzp.on('payment.failed', function () {
        showError('Payment failed. Try again or use “I paid — check payment status” if money was deducted.');
        if (syncBtn) {
          syncBtn.hidden = false;
        }
      });
      rzp.open();
    });
  }

  if (submitBtn) {
    submitBtn.addEventListener('click', startPayment);
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    startPayment();
  });

  if (syncBtn) {
    syncBtn.addEventListener('click', function () {
      if (!pendingRef) {
        showError('Start payment first, or contact the temple office with your reference.');
        return;
      }
      showError('');
      syncBtn.disabled = true;
      busyShow('Checking payment status');
      api('sync', { ref_no: pendingRef })
        .then(function (r) {
          busyHide();
          if (r.status !== 200 || !r.data.redirect) {
            throw new Error(r.data && r.data.error ? r.data.error : 'Payment not found yet.');
          }
          window.location.href = r.data.redirect;
        })
        .catch(function (e) {
          busyHide();
          showError(e.message || String(e));
        })
        .finally(function () {
          syncBtn.disabled = false;
        });
    });
  }
})();
