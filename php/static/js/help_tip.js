(function () {
  var GAP = 8;
  var EDGE = 12;
  var pinned = null;

  function parts(tip) {
    return { button: tip.querySelector('.help-tip-btn'), body: tip.querySelector('.help-tip-body') };
  }

  function place(tip) {
    var p = parts(tip);
    if (!p.button || !p.body) {
      return;
    }
    p.body.hidden = false;
    var anchor = p.button.getBoundingClientRect();
    var width = p.body.offsetWidth;
    var height = p.body.offsetHeight;
    var left = Math.min(Math.max(EDGE, anchor.left + anchor.width / 2 - width / 2), window.innerWidth - width - EDGE);
    var top = anchor.bottom + GAP;
    if (top + height > window.innerHeight - EDGE && anchor.top - GAP - height > EDGE) {
      top = anchor.top - GAP - height;
    }
    p.body.style.left = Math.max(EDGE, left) + 'px';
    p.body.style.top = top + 'px';
    p.button.setAttribute('aria-expanded', 'true');
  }

  function hide(tip) {
    var p = parts(tip);
    if (!p.button || !p.body) {
      return;
    }
    p.body.hidden = true;
    p.button.setAttribute('aria-expanded', 'false');
    if (pinned === tip) {
      pinned = null;
    }
  }

  document.querySelectorAll('.help-tip').forEach(function (tip) {
    var p = parts(tip);
    if (!p.button || !p.body) {
      return;
    }
    tip.addEventListener('mouseenter', function () { place(tip); });
    tip.addEventListener('mouseleave', function () { if (pinned !== tip) { hide(tip); } });
    p.button.addEventListener('focus', function () { place(tip); });
    p.button.addEventListener('blur', function () { if (pinned !== tip) { hide(tip); } });
    p.button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      if (pinned === tip) {
        hide(tip);
        return;
      }
      if (pinned) {
        hide(pinned);
      }
      pinned = tip;
      place(tip);
    });
  });

  document.addEventListener('click', function (event) {
    if (pinned && !pinned.contains(event.target)) {
      hide(pinned);
    }
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && pinned) {
      var button = parts(pinned).button;
      hide(pinned);
      if (button) {
        button.focus();
      }
    }
  });
  window.addEventListener('scroll', function () {
    document.querySelectorAll('.help-tip-body:not([hidden])').forEach(function (body) {
      hide(body.closest('.help-tip'));
    });
  }, true);
})();
