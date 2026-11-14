(function () {
  'use strict';

  var base = (window.SJT_PUBLIC_BASE || '/').replace(/\/?$/, '/');
  var origin = window.location.origin || '';
  var apiUrl =
    window.SJT_IDENTITY_API || origin + base + 'home/api/temple-identity.php';
  var identityCache = null;
  var fetchStarted = false;
  var schemaPatched = false;
  var domPatching = false;
  var runScheduled = false;

  function absolutize(url) {
    if (!url) {
      return '';
    }
    if (/^https?:\/\//i.test(url)) {
      return url;
    }
    if (url.charAt(0) === '/') {
      return origin + url;
    }
    return origin + base + url.replace(/^\//, '');
  }

  function setFavicon(id) {
    var href = absolutize(id.faviconUrl);
    if (!href) {
      return;
    }
    var type = id.faviconType || 'image/png';
    var link =
      document.querySelector('link[rel="icon"]') ||
      document.querySelector('link[rel="shortcut icon"]');
    if (!link) {
      link = document.createElement('link');
      link.rel = 'icon';
      document.head.appendChild(link);
    }
    if (link.getAttribute('href') === href && link.type === type) {
      return;
    }
    link.href = href;
    link.type = type;
  }

  function setMetaContent(selector, value) {
    if (!value) {
      return;
    }
    var el = document.querySelector(selector);
    if (el && el.getAttribute('content') !== value) {
      el.setAttribute('content', value);
    }
  }

  function patchNav(id) {
    var navTitle = id.navTitle || id.name;
    document.querySelectorAll('nav span, header span').forEach(function (span) {
      if (/jagannath temple/i.test(span.textContent || '')) {
        if (span.textContent !== navTitle) {
          span.textContent = navTitle;
        }
      }
    });
    var logoAbs = id.logoUrl ? absolutize(id.logoUrl) : '';
    document.querySelectorAll('nav img, header img').forEach(function (img) {
      var inLogo =
        img.closest('a[href="/"], a[to="/"]') ||
        /logo/i.test(img.alt || '') ||
        (img.parentElement && img.parentElement.className.indexOf('rounded-full') >= 0);
      if (inLogo && logoAbs && img.getAttribute('src') !== logoAbs) {
        img.src = logoAbs;
      }
    });
  }

  function patchHero(id) {
    var title = id.welcomeTitle || id.name;
    document.querySelectorAll('h1').forEach(function (h1) {
      var text = h1.textContent || '';
      if (/welcome to/i.test(text) && h1.textContent !== title) {
        h1.textContent = title;
      }
    });
  }

  function patchFooter(id) {
    document.querySelectorAll('footer h3').forEach(function (h3) {
      if (/jagannath temple/i.test(h3.textContent || '')) {
        var navTitle = id.navTitle || id.name;
        if (h3.textContent !== navTitle) {
          h3.textContent = navTitle;
        }
      }
    });

    document.querySelectorAll('footer h3').forEach(function (h3) {
      if (!/contact us/i.test(h3.textContent || '')) {
        return;
      }
      var parent = h3.parentElement;
      if (!parent || parent.getAttribute('data-sjt-identity') === '1') {
        return;
      }
      parent.setAttribute('data-sjt-identity', '1');
      var contactBlock = h3.nextElementSibling;
      if (contactBlock && contactBlock.tagName === 'ADDRESS') {
        contactBlock.innerHTML = '';
        (id.addressLines || []).forEach(function (line) {
          var p = document.createElement('p');
          p.className = 'mb-2';
          p.textContent = line;
          contactBlock.appendChild(p);
        });
        if (id.phoneDisplay) {
          var phoneP = document.createElement('p');
          phoneP.className = 'mb-2';
          if (id.tel) {
            var a = document.createElement('a');
            a.href = id.tel;
            a.className = 'text-inherit hover:underline';
            a.textContent = id.phoneDisplay;
            phoneP.appendChild(a);
          } else {
            phoneP.textContent = id.phoneDisplay;
          }
          contactBlock.appendChild(phoneP);
        }
        if (id.email) {
          var emailP = document.createElement('p');
          var mail = document.createElement('a');
          mail.href = 'mailto:' + id.email;
          mail.className = 'text-inherit hover:underline';
          mail.textContent = 'Email: ' + id.email;
          emailP.appendChild(mail);
          contactBlock.appendChild(emailP);
        }
      }
    });
  }

  function patchSchema(id) {
    if (schemaPatched) {
      return;
    }
    document.querySelectorAll('script[type="application/ld+json"]').forEach(function (node) {
      try {
        var data = JSON.parse(node.textContent || '');
        var graph = data['@graph'];
        if (!Array.isArray(graph)) {
          return;
        }
        graph.forEach(function (item) {
          if (item['@type'] === 'WebSite' || item['@type'] === 'HinduTemple' || item['@type'] === 'PlaceOfWorship') {
            if (Array.isArray(item['@type']) && item['@type'].indexOf('HinduTemple') >= 0) {
              item.name = id.name;
            } else if (item['@type'] === 'HinduTemple' || item['@type'] === 'PlaceOfWorship') {
              item.name = id.name;
            } else if (item['@type'] === 'WebSite') {
              item.name = id.title || id.name;
            }
          }
        });
        node.textContent = JSON.stringify(data);
        schemaPatched = true;
      } catch (e) {
        /* keep static JSON-LD */
      }
    });
  }

  function apply(id) {
    if (!id || !id.name) {
      return;
    }
    if (id.title && document.title !== id.title) {
      document.title = id.title;
    }
    setFavicon(id);
    setMetaContent('meta[name="author"]', id.name);
    setMetaContent('meta[property="og:site_name"]', id.name);
    patchNav(id);
    patchHero(id);
    patchFooter(id);
    patchSchema(id);
  }

  function patchFromCache() {
    if (!identityCache || domPatching) {
      return;
    }
    domPatching = true;
    try {
      apply(identityCache);
    } finally {
      domPatching = false;
    }
  }

  function schedulePatch() {
    if (runScheduled || domPatching) {
      return;
    }
    runScheduled = true;
    window.requestAnimationFrame(function () {
      runScheduled = false;
      patchFromCache();
    });
  }

  function ensureFetch() {
    if (fetchStarted) {
      return;
    }
    fetchStarted = true;
    fetch(apiUrl, { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) {
          throw new Error(String(res.status));
        }
        return res.json();
      })
      .then(function (data) {
        identityCache = data;
        schedulePatch();
      })
      .catch(function () {
        identityCache = null;
      });
  }

  ensureFetch();

  function attachObserver(target) {
    if (!target) {
      return;
    }
    var obs = new MutationObserver(function () {
      if (domPatching) {
        return;
      }
      if (!identityCache) {
        return;
      }
      schedulePatch();
    });
    obs.observe(target, { childList: true, subtree: true });
  }

  var root = document.getElementById('root');
  if (root) {
    attachObserver(root);
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      attachObserver(document.getElementById('root'));
    });
  }
})();