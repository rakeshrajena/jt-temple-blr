(function () {
  'use strict';

  /** Apply visual theme class; re-apply when React remounts #root. */
  function applyTheme() {
    document.body.classList.add('sjt-home-renovated');
  }

  applyTheme();

  var root = document.getElementById('root');
  if (root) {
    var obs = new MutationObserver(applyTheme);
    obs.observe(root, { childList: true });
  }
})();