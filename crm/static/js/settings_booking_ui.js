(function () {
  'use strict';
  var shell = document.querySelector('[data-settings-ob]');
  if (!shell) return;

  var links = shell.querySelectorAll('.settings-ob-nav-link[href^="#"]');
  var sections = shell.querySelectorAll('.settings-ob-main [id^="ob-"]');

  function setActive(hash) {
    if (!hash || hash === '#') return;
    links.forEach(function (a) {
      a.classList.toggle('is-active', a.getAttribute('href') === hash);
    });
  }

  links.forEach(function (a) {
    a.addEventListener('click', function (e) {
      var href = a.getAttribute('href');
      if (!href || href.charAt(0) !== '#') return;
      var el = document.querySelector(href);
      if (el) {
        e.preventDefault();
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', href);
        setActive(href);
      }
    });
  });

  setActive(location.hash);

  window.addEventListener('hashchange', function () {
    setActive(location.hash);
  });

  if ('IntersectionObserver' in window) {
    var visible = new Map();
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          visible.set(entry.target.id, entry.isIntersecting ? entry.intersectionRatio : 0);
        });
        var best = '';
        var bestRatio = 0;
        visible.forEach(function (ratio, id) {
          if (ratio > bestRatio) {
            bestRatio = ratio;
            best = id;
          }
        });
        if (best && bestRatio > 0.15) setActive('#' + best);
      },
      { root: null, rootMargin: '-25% 0px -60% 0px', threshold: [0, 0.12, 0.3, 0.5] }
    );
    sections.forEach(function (el) {
      if (el.id) observer.observe(el);
    });
  }
})();
