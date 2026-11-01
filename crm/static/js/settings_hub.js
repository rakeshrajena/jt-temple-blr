(function () {
  'use strict';
  var hub = document.querySelector('[data-settings-hub]');
  if (!hub) return;

  var tabs = hub.querySelectorAll('.settings-jumbo-card[data-settings-panel]');
  var panels = hub.querySelectorAll('.settings-panel[data-settings-panel]');

  function panelForHash(hash) {
    if (!hash || hash === '#') return 'identity';
    var id = hash.replace(/^#/, '');
    if (id.indexOf('choice-') === 0) return 'choices';
    if (id.indexOf('ob-') === 0 || id === 'online-booking') return 'online-booking';
    var known = ['identity', 'approval', 'messages', 'choices', 'online-booking'];
    return known.indexOf(id) >= 0 ? id : 'identity';
  }

  function panelExists(panelId) {
    for (var i = 0; i < panels.length; i++) {
      if (panels[i].getAttribute('data-settings-panel') === panelId) return true;
    }
    return false;
  }

  function syncTabs(panelId) {
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-settings-panel') === panelId;
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
  }

  function showPanel(panelId, hashOption) {
    if (!panelExists(panelId)) {
      panelId = 'identity';
    }
    panels.forEach(function (p) {
      var on = p.getAttribute('data-settings-panel') === panelId;
      p.classList.toggle('is-active', on);
      p.hidden = !on;
    });
    syncTabs(panelId);
    if (hashOption === 'preserve') {
      return;
    }
    if (typeof hashOption === 'string' && hashOption.charAt(0) === '#') {
      history.replaceState(null, '', hashOption);
    } else if (hashOption !== false) {
      history.replaceState(null, '', '#' + panelId);
    }
  }

  function scrollToHash(hash) {
    if (!hash || hash === '#') return;
    var el = document.querySelector(hash);
    if (!el) return;
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        el.scrollIntoView({ block: 'start', behavior: 'smooth' });
      });
    });
  }

  function activateFromHash(hash, preserveHash) {
    var panelId = panelForHash(hash);
    showPanel(panelId, preserveHash ? 'preserve' : false);
    scrollToHash(hash);
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      showPanel(tab.getAttribute('data-settings-panel'), true);
    });
  });

  function wireSubnavJump(anchor) {
    anchor.addEventListener('click', function (e) {
      var href = anchor.getAttribute('href');
      if (!href || href.charAt(0) !== '#') return;
      e.preventDefault();
      var panelId = panelForHash(href);
      showPanel(panelId, href);
      scrollToHash(href);
    });
  }

  hub.querySelectorAll('[data-choice-jump]').forEach(wireSubnavJump);
  hub.querySelectorAll('.settings-booking-jump a[href^="#"], .settings-link-list a[href^="#ob"], .settings-ob-nav-link[href^="#"]').forEach(wireSubnavJump);

  var initialHash = location.hash || '';
  activateFromHash(initialHash, initialHash !== '');

  window.addEventListener('hashchange', function () {
    activateFromHash(location.hash, true);
  });
})();
