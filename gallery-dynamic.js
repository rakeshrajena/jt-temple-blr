(function () {
  'use strict';

  var base = (window.SJT_PUBLIC_BASE || '/').replace(/\/?$/, '/');
  var origin = window.location.origin || '';
  var apiUrl =
    window.SJT_GALLERY_API ||
    origin + base + 'home/api/gallery-images.php';
  var HIGHLIGHT_COUNT = 4;
  var galleryData = null;
  var fetchStarted = false;
  var activeTab = 'all';
  var lightboxReady = false;
  var domPatching = false;
  var staffNoteCleaned = false;
  var runScheduled = false;
  var lastRoute = '';

  function isGalleryRoute() {
    var p = window.location.pathname || '';
    return /\/gallery\/?$/.test(p);
  }

  function ensureLightbox() {
    if (lightboxReady) {
      return;
    }
    lightboxReady = true;
    var root = document.createElement('div');
    root.id = 'sjt-gallery-lightbox';
    root.hidden = true;
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'Photo preview');
    root.innerHTML =
      '<div class="sjt-gallery-lightbox__inner">' +
      '<button type="button" class="sjt-gallery-lightbox__close" aria-label="Close">&times;</button>' +
      '<img class="sjt-gallery-lightbox__img" alt="" />' +
      '<p class="sjt-gallery-lightbox__caption"></p>' +
      '</div>';
    document.body.appendChild(root);
    var lb = root;
    var closeBtn = lb.querySelector('.sjt-gallery-lightbox__close');
    closeBtn.addEventListener('click', closeLightbox);
    lb.addEventListener('click', function (e) {
      if (e.target === lb) {
        closeLightbox();
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !lb.hidden) {
        closeLightbox();
      }
    });
  }

  function openLightbox(img) {
    ensureLightbox();
    var lb = document.getElementById('sjt-gallery-lightbox');
    if (!lb) {
      return;
    }
    var el = lb.querySelector('.sjt-gallery-lightbox__img');
    var cap = lb.querySelector('.sjt-gallery-lightbox__caption');
    el.src = img.src;
    el.alt = img.alt || img.name || 'Temple photo';
    cap.textContent = img.alt || img.name || '';
    lb.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    var lb = document.getElementById('sjt-gallery-lightbox');
    if (!lb) {
      return;
    }
    lb.hidden = true;
    document.body.style.overflow = '';
  }

  function withDomPatch(fn) {
    domPatching = true;
    try {
      fn();
    } finally {
      domPatching = false;
    }
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
        galleryData = data;
        scheduleRun();
      })
      .catch(function () {
        galleryData = { images: [], tabs: [] };
        scheduleRun();
      });
  }

  function bindImageClick(node, img) {
    node.addEventListener('click', function () {
      openLightbox(img);
    });
    node.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openLightbox(img);
      }
    });
    if (node.getAttribute('tabindex') === null) {
      node.setAttribute('tabindex', '0');
      node.setAttribute('role', 'button');
    }
  }

  function makeHighlightCell(img) {
    var wrap = document.createElement('div');
    wrap.className =
      'overflow-hidden rounded-lg h-48 md:h-64 shadow-md transition-transform duration-300 hover:scale-105 sjt-gallery-highlight-tile';
    var image = document.createElement('img');
    image.src = img.src;
    image.alt = img.alt || img.name || 'Temple gallery';
    image.loading = 'lazy';
    image.decoding = 'async';
    image.className = 'w-full h-full object-cover';
    wrap.appendChild(image);
    bindImageClick(wrap, img);
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
      'w-full h-full object-cover transition-transform duration-500 hover:scale-110';
    item.appendChild(image);
    bindImageClick(item, img);
    return item;
  }

  function imagesForTab() {
    if (!galleryData) {
      return [];
    }
    var tabs = galleryData.tabs;
    if (Array.isArray(tabs) && tabs.length) {
      var found = tabs.find(function (t) {
        return t.id === activeTab;
      });
      if (found && Array.isArray(found.images)) {
        return found.images;
      }
      var allTab = tabs.find(function (t) {
        return t.id === 'all';
      });
      if (allTab) {
        return allTab.images;
      }
    }
    return Array.isArray(galleryData.images) ? galleryData.images : [];
  }

  function gridRenderKey(imgs) {
    var parts = [activeTab, String(imgs.length)];
    for (var i = 0; i < imgs.length; i++) {
      parts.push(imgs[i].src);
    }
    return parts.join('|');
  }

  function renderGalleryGrid(grid) {
    var imgs = imagesForTab();
    var key = gridRenderKey(imgs);
    if (grid.getAttribute('data-sjt-gallery-render-key') === key) {
      return;
    }
    grid.classList.add('sjt-gallery-grid');
    grid.innerHTML = '';
    imgs.forEach(function (img) {
      grid.appendChild(makeGalleryItem(img));
    });
    grid.setAttribute('data-sjt-gallery-render-key', key);
    grid.setAttribute('data-sjt-gallery-full', String(imgs.length));
    grid.setAttribute('data-sjt-gallery-tab', activeTab);
    grid.setAttribute('data-sjt-gallery-dynamic', '1');
  }

  function tabsMetaKey() {
    var tabs = (galleryData && galleryData.tabs) || [];
    return tabs
      .map(function (t) {
        return t.id;
      })
      .join(',');
  }

  function updateTabActiveStates(tabBar) {
    tabBar.querySelectorAll('.sjt-gallery-tab').forEach(function (btn) {
      var id = btn.getAttribute('data-tab-id');
      if (id === activeTab) {
        btn.classList.add('is-active');
      } else {
        btn.classList.remove('is-active');
      }
    });
  }

  function hasFolderTabs(tabs) {
    return tabs.some(function (t) {
      return t.id !== 'all' && t.id !== 'root' && Array.isArray(t.images) && t.images.length > 0;
    });
  }

  function renderTabs(container, grid) {
    var tabs = (galleryData && galleryData.tabs) || [];
    var showTabs = tabs.length > 1 && hasFolderTabs(tabs);
    var tabBar = container.querySelector('.sjt-gallery-tabs');
    if (!showTabs) {
      if (tabBar) {
        tabBar.remove();
      }
      return;
    }
    var metaKey = tabsMetaKey() + '::' + activeTab;
    if (!tabBar) {
      tabBar = document.createElement('div');
      tabBar.className = 'sjt-gallery-tabs';
      grid.parentElement.insertBefore(tabBar, grid);
    }
    if (tabBar.getAttribute('data-sjt-tabs-key') === metaKey) {
      return;
    }
    if (
      tabBar.getAttribute('data-sjt-tabs-meta') === tabsMetaKey() &&
      tabBar.childElementCount === tabs.length
    ) {
      updateTabActiveStates(tabBar);
      tabBar.setAttribute('data-sjt-tabs-key', metaKey);
      return;
    }
    tabBar.innerHTML = '';
    tabBar.setAttribute('data-sjt-tabs-meta', tabsMetaKey());
    tabBar.setAttribute('data-sjt-tabs-key', metaKey);
    tabs.forEach(function (tab) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sjt-gallery-tab' + (tab.id === activeTab ? ' is-active' : '');
      btn.setAttribute('data-tab-id', tab.id);
      btn.textContent = tab.label;
      btn.addEventListener('click', function () {
        if (activeTab === tab.id) {
          return;
        }
        activeTab = tab.id;
        var g = document.querySelector('.gallery-grid');
        if (!g) {
          return;
        }
        withDomPatch(function () {
          renderTabs(container, g);
          renderGalleryGrid(g);
        });
      });
      tabBar.appendChild(btn);
    });
  }

  function removeStaffNote() {
    if (staffNoteCleaned) {
      return;
    }
    var removed = false;
    document.querySelectorAll('[data-sjt-gallery-note]').forEach(function (n) {
      n.remove();
      removed = true;
    });
    document.querySelectorAll('p').forEach(function (p) {
      if (/home\/gallery folder/i.test(p.textContent || '')) {
        p.remove();
        removed = true;
      }
    });
    if (removed || isGalleryRoute()) {
      staffNoteCleaned = true;
    }
  }

  function patchHighlights(images) {
    var list = images || imagesForTab();
    if (!list.length) {
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
      var key = list.length + '-' + (list[0] && list[0].src);
      if (grid.getAttribute('data-sjt-gallery-highlights') === key) {
        return;
      }
      grid.setAttribute('data-sjt-gallery-highlights', key);
      grid.setAttribute('data-sjt-gallery-dynamic', '1');
      grid.innerHTML = '';
      list.slice(0, HIGHLIGHT_COUNT).forEach(function (img) {
        grid.appendChild(makeHighlightCell(img));
      });
    });
  }

  function gallerySectionRoot() {
    var grid = document.querySelector('.gallery-grid');
    if (!grid) {
      return null;
    }
    return grid.closest('section') || grid.parentElement;
  }

  function hideLegacyReactFilters() {
    var scope = gallerySectionRoot();
    if (!scope) {
      return;
    }
    scope.querySelectorAll('button').forEach(function (btn) {
      if (btn.closest('.sjt-gallery-tabs')) {
        return;
      }
      var label = (btn.textContent || '').trim().toLowerCase();
      if (label !== 'temple' && label !== 'events') {
        return;
      }
      var row = btn.closest('.flex') || btn.parentElement;
      if (!row || row.classList.contains('sjt-gallery-tabs')) {
        return;
      }
      row.setAttribute('data-sjt-legacy-gallery-filters', '1');
      row.setAttribute('hidden', '');
      row.style.display = 'none';
    });
  }

  function patchGalleryPage() {
    if (!isGalleryRoute()) {
      document.body.classList.remove('sjt-on-gallery');
      return;
    }
    document.body.classList.add('sjt-on-gallery');
    var grid = document.querySelector('.gallery-grid');
    if (!grid) {
      return;
    }
    removeStaffNote();
    hideLegacyReactFilters();
    var container = grid.parentElement;
    if (container) {
      renderTabs(container, grid);
    }
    renderGalleryGrid(grid);
  }

  function run() {
    if (domPatching) {
      return;
    }
    var route = window.location.pathname || '';
    if (route !== lastRoute) {
      lastRoute = route;
      staffNoteCleaned = false;
      activeTab = 'all';
    }
    if (galleryData === null) {
      ensureFetch();
      return;
    }
    if (!isGalleryRoute() && !document.querySelector('h2')) {
      return;
    }
    withDomPatch(function () {
      var all = galleryData.images || imagesForTab();
      if (Array.isArray(all) && all.length) {
        patchHighlights(all);
      }
      if (isGalleryRoute()) {
        patchGalleryPage();
      }
    });
  }

  function scheduleRun() {
    if (runScheduled || domPatching) {
      return;
    }
    runScheduled = true;
    window.requestAnimationFrame(function () {
      runScheduled = false;
      run();
    });
  }

  scheduleRun();

  var root = document.getElementById('root');
  if (root) {
    var obs = new MutationObserver(function () {
      if (domPatching) {
        return;
      }
      scheduleRun();
    });
    obs.observe(root, { childList: true, subtree: true });
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      var r = document.getElementById('root');
      if (!r) {
        return;
      }
      var obs = new MutationObserver(function () {
        if (domPatching) {
          return;
        }
        scheduleRun();
      });
      obs.observe(r, { childList: true, subtree: true });
      scheduleRun();
    });
  }
})();