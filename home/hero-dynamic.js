(function () {
  'use strict';

  var base = (window.SJT_PUBLIC_BASE || '/').replace(/\/?$/, '/');
  var origin = window.location.origin || '';
  var apiUrl =
    window.SJT_HERO_API || origin + base + 'home/api/hero-media.php';
  var IMAGE_MS = Number(window.SJT_HERO_IMAGE_MS) || 7000;
  var items = null;
  var slideIndex = 0;
  var timer = null;
  var mounted = false;
  var reducedMotion =
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function findHeroSection() {
    var headings = document.querySelectorAll('h1');
    for (var i = 0; i < headings.length; i++) {
      if (/welcome to shree jagannath/i.test(headings[i].textContent || '')) {
        var sec = headings[i].closest('section');
        if (sec) {
          return sec;
        }
      }
    }
    return document.querySelector('section.h-screen, section[class*="h-screen"]');
  }

  function clearTimer() {
    if (timer !== null) {
      window.clearTimeout(timer);
      timer = null;
    }
  }

  function setActive(layers, index) {
    layers.forEach(function (layer, i) {
      layer.classList.toggle('is-active', i === index);
    });
  }

  function pauseAllVideos(layers) {
    layers.forEach(function (layer) {
      var v = layer.querySelector('video');
      if (v) {
        v.pause();
      }
    });
  }

  function playVideo(layer) {
    var v = layer.querySelector('video');
    if (!v) {
      return Promise.resolve();
    }
    v.currentTime = 0;
    return v.play().catch(function () {
      return undefined;
    });
  }

  function scheduleNext(layers) {
    clearTimer();
    if (!items || !items.length) {
      return;
    }
    if (reducedMotion) {
      return;
    }
    var layer = layers[slideIndex];
    var item = items[slideIndex];
    if (item.type === 'video') {
      var v = layer.querySelector('video');
      if (v) {
        v.onended = function () {
          advance(layers);
        };
        playVideo(layer);
        return;
      }
    }
    timer = window.setTimeout(function () {
      advance(layers);
    }, IMAGE_MS);
  }

  function advance(layers) {
    if (!items || items.length < 2) {
      return;
    }
    pauseAllVideos(layers);
    slideIndex = (slideIndex + 1) % items.length;
    setActive(layers, slideIndex);
    scheduleNext(layers);
  }

  function buildLayer(item) {
    var layer = document.createElement('div');
    layer.className = 'sjt-hero-slideshow__layer';
    if (item.type === 'video') {
      var video = document.createElement('video');
      video.src = item.src;
      video.muted = true;
      video.playsInline = true;
      video.setAttribute('playsinline', '');
      video.setAttribute('muted', '');
      video.preload = 'metadata';
      video.setAttribute('aria-hidden', 'true');
      if (item.ext === 'webm') {
        video.type = 'video/webm';
      } else if (item.ext === 'mp4') {
        video.type = 'video/mp4';
      }
      layer.appendChild(video);
    } else {
      var img = document.createElement('img');
      img.src = item.src;
      img.alt = '';
      img.decoding = 'async';
      img.setAttribute('aria-hidden', 'true');
      layer.appendChild(img);
    }
    return layer;
  }

  function mount(itemsList) {
    if (mounted || !itemsList.length) {
      return;
    }
    var section = findHeroSection();
    if (!section) {
      return;
    }
    mounted = true;
    items = itemsList;
    section.classList.add('sjt-hero-has-slideshow');
    section.style.backgroundImage = 'none';

    var existing = section.querySelector('.sjt-hero-slideshow');
    if (existing) {
      existing.remove();
    }

    var root = document.createElement('div');
    root.className = 'sjt-hero-slideshow';
    root.setAttribute('data-sjt-hero-slideshow', '1');

    var layers = items.map(function (item) {
      var layer = buildLayer(item);
      root.appendChild(layer);
      return layer;
    });

    var dim = document.createElement('div');
    dim.className = 'sjt-hero-slideshow__dim';
    root.appendChild(dim);

    var overlay = section.querySelector('.absolute.inset-0.bg-black');
    if (overlay && overlay.parentElement === section) {
      section.insertBefore(root, overlay);
    } else {
      section.insertBefore(root, section.firstChild);
    }

    slideIndex = 0;
    setActive(layers, 0);
    if (items[0].type === 'video' && !reducedMotion) {
      playVideo(layers[0]);
    }
    scheduleNext(layers);
  }

  function fetchAndMount() {
    fetch(apiUrl, { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) {
          throw new Error(String(res.status));
        }
        return res.json();
      })
      .then(function (data) {
        var list = Array.isArray(data.items) ? data.items : [];
        if (list.length) {
          mount(list);
        }
      })
      .catch(function () {
        /* keep built-in React hero background */
      });
  }

  function tryMount() {
    if (mounted) {
      return;
    }
    var sec = findHeroSection();
    if (sec) {
      fetchAndMount();
    }
  }

  tryMount();
  var obs = new MutationObserver(tryMount);
  obs.observe(document.documentElement, { childList: true, subtree: true });
})();