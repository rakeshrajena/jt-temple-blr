(function () {
  'use strict';

  var base = (window.SJT_PUBLIC_BASE || '/').replace(/\/?$/, '/');
  var origin = window.location.origin || '';
  var apiUrl =
    window.SJT_HERO_API || origin + base + 'home/api/hero-media.php';
  var IMAGE_MS = Number(window.SJT_HERO_IMAGE_MS) || 7000;
  var VIDEO_MAX_MS = Number(window.SJT_HERO_VIDEO_MAX_MS) || 120000;
  var items = null;
  var slideIndex = 0;
  var timer = null;
  var layerNodes = null;
  var dotsEl = null;
  var dotButtons = [];
  var mounted = false;
  var fetchStarted = false;
  var schedulePending = false;
  var reducedMotion =
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function findHeroSection() {
    var existing = document.querySelector('[data-sjt-hero-slideshow]');
    if (existing) {
      return existing.closest('section');
    }
    var headings = document.querySelectorAll('h1');
    for (var i = 0; i < headings.length; i++) {
      var text = headings[i].textContent || '';
      if (/welcome to|jagannath temple/i.test(text)) {
        var sec = headings[i].closest('section');
        if (sec) {
          return sec;
        }
      }
    }
    return document.querySelector(
      'section.h-screen, section[class*="h-screen"], section[class*="min-h-screen"]'
    );
  }

  function findHeroOverlay(section) {
    var overlays = section.querySelectorAll('.absolute.inset-0, [class*="inset-0"]');
    for (var i = 0; i < overlays.length; i++) {
      var el = overlays[i];
      if (el.classList.contains('sjt-hero-slideshow') || el.closest('.sjt-hero-slideshow')) {
        continue;
      }
      if (/bg-black|bg-gradient|opacity/i.test(el.className || '')) {
        return el;
      }
    }
    return section.querySelector('.absolute.inset-0');
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
    updateDots(index);
  }

  function updateDots(index) {
    if (!dotButtons.length) {
      return;
    }
    dotButtons.forEach(function (btn, i) {
      var active = i === index;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  function setDotProgress(ms) {
    if (dotsEl && ms > 0) {
      dotsEl.style.setProperty('--sjt-hero-dot-duration', ms + 'ms');
    }
    dotButtons.forEach(function (btn) {
      if (!btn.classList.contains('is-active')) {
        return;
      }
      btn.classList.remove('is-active');
      void btn.offsetWidth;
      btn.classList.add('is-active');
    });
  }

  function goToSlide(index, layers) {
    if (!items || index < 0 || index >= items.length) {
      return;
    }
    pauseAllVideos(layers);
    clearTimer();
    slideIndex = index;
    setActive(layers, slideIndex);
    scheduleNext(layers);
  }

  function buildDots(section, count, layers) {
    var old = section.querySelector('.sjt-hero-dots');
    if (old) {
      old.remove();
    }
    dotsEl = null;
    dotButtons = [];
    if (count < 2) {
      return;
    }
    dotsEl = document.createElement('div');
    dotsEl.className = 'sjt-hero-dots';
    dotsEl.setAttribute('role', 'tablist');
    dotsEl.setAttribute('aria-label', 'Hero slideshow');
    for (var i = 0; i < count; i++) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sjt-hero-dot';
      btn.setAttribute('role', 'tab');
      btn.setAttribute('aria-label', 'Slide ' + (i + 1) + ' of ' + count);
      btn.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
      (function (idx) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          if (idx === slideIndex) {
            return;
          }
          goToSlide(idx, layers);
        });
      })(i);
      dotsEl.appendChild(btn);
      dotButtons.push(btn);
    }
    section.appendChild(dotsEl);
  }

  function pauseAllVideos(layers) {
    layers.forEach(function (layer) {
      var v = layer.querySelector('video');
      if (v) {
        v.onended = null;
        v.onerror = null;
        try {
          v.pause();
        } catch (e) {
          /* ignore */
        }
      }
    });
  }

  function playVideo(layer) {
    var v = layer.querySelector('video');
    if (!v) {
      return Promise.resolve(false);
    }
    v.currentTime = 0;
    return v.play().then(
      function () {
        return true;
      },
      function () {
        return false;
      }
    );
  }

  function advance(layers) {
    if (!items || items.length < 2) {
      return;
    }
    pauseAllVideos(layers);
    clearTimer();
    slideIndex = (slideIndex + 1) % items.length;
    setActive(layers, slideIndex);
    scheduleNext(layers);
  }

  function scheduleNext(layers) {
    clearTimer();
    if (!items || !items.length || reducedMotion) {
      return;
    }
    if (items.length < 2) {
      var only = items[0];
      if (only && only.type === 'video') {
        playVideo(layers[0]);
      }
      return;
    }
    var layer = layers[slideIndex];
    var item = items[slideIndex];
    if (!layer || !item) {
      return;
    }

    if (item.type === 'video') {
      var v = layer.querySelector('video');
      if (!v) {
        setDotProgress(IMAGE_MS);
        timer = window.setTimeout(function () {
          advance(layers);
        }, IMAGE_MS);
        return;
      }
      var done = false;
      function finish() {
        if (done) {
          return;
        }
        done = true;
        v.onended = null;
        v.onerror = null;
        advance(layers);
      }
      v.onended = finish;
      v.onerror = finish;
      setDotProgress(VIDEO_MAX_MS);
      timer = window.setTimeout(finish, VIDEO_MAX_MS);

      function armVideoTimer() {
        if (v.duration && isFinite(v.duration) && v.duration > 0) {
          var ms = Math.ceil(v.duration * 1000) + 400;
          clearTimer();
          setDotProgress(ms);
          timer = window.setTimeout(finish, ms);
        }
      }
      v.addEventListener('loadedmetadata', armVideoTimer, { once: true });

      playVideo(layer).then(function (played) {
        if (!played) {
          finish();
          return;
        }
        armVideoTimer();
      });
      return;
    }

    setDotProgress(IMAGE_MS);
    timer = window.setTimeout(function () {
      advance(layers);
    }, IMAGE_MS);
  }

  function buildLayer(item) {
    var layer = document.createElement('div');
    layer.className = 'sjt-hero-slideshow__layer';
    if (item.type === 'video') {
      var video = document.createElement('video');
      video.muted = true;
      video.defaultMuted = true;
      video.playsInline = true;
      video.setAttribute('playsinline', '');
      video.setAttribute('webkit-playsinline', '');
      video.setAttribute('muted', '');
      video.preload = 'auto';
      video.setAttribute('aria-hidden', 'true');
      var source = document.createElement('source');
      source.src = item.src;
      if (item.ext === 'webm') {
        source.type = 'video/webm';
      } else {
        source.type = 'video/mp4';
      }
      video.appendChild(source);
      layer.appendChild(video);
    } else {
      var img = document.createElement('img');
      img.src = item.src;
      img.alt = '';
      img.decoding = 'async';
      img.loading = 'eager';
      img.setAttribute('aria-hidden', 'true');
      layer.appendChild(img);
    }
    return layer;
  }

  function teardownIfMissing() {
    if (!mounted) {
      return;
    }
    var sec = findHeroSection();
    if (!sec || !sec.querySelector('[data-sjt-hero-slideshow]')) {
      mounted = false;
      items = null;
      layerNodes = null;
      slideIndex = 0;
      dotsEl = null;
      dotButtons = [];
      clearTimer();
      var dots = sec && sec.querySelector('.sjt-hero-dots');
      if (dots) {
        dots.remove();
      }
    }
  }

  function mount(itemsList) {
    if (!itemsList.length) {
      return;
    }
    var section = findHeroSection();
    if (!section) {
      return;
    }
    if (mounted && section.querySelector('[data-sjt-hero-slideshow]')) {
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
    var oldDots = section.querySelector('.sjt-hero-dots');
    if (oldDots) {
      oldDots.remove();
    }

    var root = document.createElement('div');
    root.className = 'sjt-hero-slideshow';
    root.setAttribute('data-sjt-hero-slideshow', '1');

    layerNodes = items.map(function (item) {
      var layer = buildLayer(item);
      root.appendChild(layer);
      return layer;
    });

    var dim = document.createElement('div');
    dim.className = 'sjt-hero-slideshow__dim';
    root.appendChild(dim);

    var overlay = findHeroOverlay(section);
    if (overlay && overlay.parentElement === section) {
      section.insertBefore(root, overlay);
    } else {
      section.insertBefore(root, section.firstChild);
    }

    buildDots(section, items.length, layerNodes);
    slideIndex = 0;
    setActive(layerNodes, 0);
    scheduleNext(layerNodes);
  }

  function fetchAndMount() {
    if (fetchStarted) {
      return;
    }
    fetchStarted = true;
    fetch(apiUrl, { credentials: 'same-origin', cache: 'no-store' })
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
        } else {
          mounted = false;
        }
      })
      .catch(function () {
        mounted = false;
      })
      .then(function () {
        fetchStarted = false;
      });
  }

  function tryMount() {
    teardownIfMissing();
    if (mounted) {
      return;
    }
    if (findHeroSection()) {
      fetchAndMount();
    }
  }

  function scheduleTry() {
    if (schedulePending) {
      return;
    }
    schedulePending = true;
    window.requestAnimationFrame(function () {
      schedulePending = false;
      tryMount();
    });
  }

  scheduleTry();

  var rootEl = document.getElementById('root');
  if (rootEl) {
    var obs = new MutationObserver(scheduleTry);
    obs.observe(rootEl, { childList: true, subtree: true });
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      var r = document.getElementById('root');
      if (r) {
        var obs = new MutationObserver(scheduleTry);
        obs.observe(r, { childList: true, subtree: true });
      }
      scheduleTry();
    });
  }
})();