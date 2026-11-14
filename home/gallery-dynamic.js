(function () {
  'use strict';

  var base = (window.SJT_PUBLIC_BASE || '/').replace(/\/?$/, '/');
  var origin = window.location.origin || '';
  var apiUrl =
    window.SJT_GALLERY_API ||
    origin + base + 'home/api/gallery-images.php';
  var HIGHLIGHT_COUNT = 4;
  var cached = null;
  var fetchStarted = false;

  function isGalleryRoute() {
    var p = window.location.pathname || '';
    return /\/gallery\/?$/.test(p);
  }

  function ensureFetch() {
    if (fetchStarted) {
      return;
    }
    fetchStarted = true;
    fetch(apiUrl, { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('gallery api ' + res.status);
        }
        return res.json();
      })
      .then(function (data) {
        cached = Array.isArray(data.images) ? data.images : [];
        run();
      })
      .catch(function () {
        cached = [];
      });
  }

  function makeHighlightCell(img) {
    var wrap = document.createElement('div');
    wrap.className =
      'overflow-hidden rounded-lg h-48 md:h-64 shadow-md transition-transform duration-300 hover:scale-105';
    var image = document.createElement('img');
    image.src = img.src;
    image.alt = img.alt || img.name || 'Temple gallery';
    image.loading = 'lazy';
    image.decoding = 'async';
    image.className = 'w-full h-full object-cover';
    wrap.appendChild(image);
    return wrap;
  }

  function makeGalleryItem(img) {
    var item = document.createElement('div');
    item.className = 'gallery-item sjt-gallery-dynamic-item';
    var image = document.createElement('img');
    image.src = img.src;
    image.alt = img.alt || img.name || 'Temple gallery';
    image.loading = 'lazy';
    image.decoding = 'async';
    image.className =
      'w-full h-full object-cover transition-transform duration-500 hover:scale-110 cursor-pointer';
    image.addEventListener('click', function () {
      window.open(img.src, '_blank', 'noopener,noreferrer');
    });
    item.appendChild(image);
    return item;
  }

  function patchHighlights(images) {
    if (!images.length) {
      return;
    }
    document.querySelectorAll('h2').forEach(function (h2) {
      if (!/gallery highlights/i.test(h2.textContent || '')) {
        return;
      }
      var section = h2.closest('section');
      if (!section) {
        return;
      }
      var grid = section.querySelector('.grid');
      if (!grid) {
        return;
      }
      if (grid.getAttribute('data-sjt-gallery-highlights') === String(images.length)) {
        return;
      }
      grid.setAttribute('data-sjt-gallery-highlights', String(images.length));
      grid.setAttribute('data-sjt-gallery-dynamic', '1');
      grid.innerHTML = '';
      images.slice(0, HIGHLIGHT_COUNT).forEach(function (img) {
        grid.appendChild(makeHighlightCell(img));
      });
    });
  }

  function patchGalleryPage(images) {
    if (!isGalleryRoute()) {
      return;
    }
    var grid = document.querySelector('.gallery-grid');
    if (!grid) {
      return;
    }
    if (grid.getAttribute('data-sjt-gallery-full') === String(images.length)) {
      return;
    }
    grid.setAttribute('data-sjt-gallery-full', String(images.length));
    grid.setAttribute('data-sjt-gallery-dynamic', '1');
    grid.innerHTML = '';
    images.forEach(function (img) {
      grid.appendChild(makeGalleryItem(img));
    });

    var container = grid.parentElement;
    if (container) {
      container.querySelectorAll('button').forEach(function (btn) {
        var row = btn.closest('.flex.justify-center, .flex.flex-wrap');
        if (row) {
          row.setAttribute('hidden', '');
        }
      });
    }

    var note = document.querySelector('[data-sjt-gallery-note]');
    if (!note && grid.parentElement) {
      var p = document.createElement('p');
      p.className = 'text-center text-sm text-gray-600 mt-4';
      p.setAttribute('data-sjt-gallery-note', '1');
      p.textContent =
        'Photos are loaded automatically from the home/gallery folder (newest first). Add or replace image files there to update this page.';
      grid.parentElement.appendChild(p);
    }
  }

  function run() {
    if (cached === null) {
      ensureFetch();
      return;
    }
    patchHighlights(cached);
    patchGalleryPage(cached);
  }

  run();
  var obs = new MutationObserver(function () {
    run();
  });
  obs.observe(document.documentElement, { childList: true, subtree: true });
})();