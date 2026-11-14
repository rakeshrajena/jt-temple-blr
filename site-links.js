(function () {
  'use strict';

  var base = (window.SJT_PUBLIC_BASE || '/').replace(/\/?$/, '/');
  var origin = window.location.origin || '';
  var BOOKING = (window.SJT_BOOKING_URL || origin + base + 'Booking/book').replace(/\/$/, '');
  var LOOKUP = (window.SJT_BOOKING_LOOKUP_URL || origin + base + 'Booking/lookup').replace(/\/$/, '');
  var FOOD_BOOKING =
    window.SJT_BOOKING_FOOD_URL ||
    (BOOKING.indexOf('?') === -1 ? BOOKING + '?service=food-only' : BOOKING);
  var LIVE = (window.SJT_LIVE_DARSHAN_URL || origin + base + 'Streaming').replace(/\/$/, '');
  var DONATION = (window.SJT_DONATION_URL || origin + base + 'Booking/donation/').replace(/\/$/, '');

  function navMobileClose(template) {
    if (template.getAttribute('onClick')) {
      return function () {
        var menuBtn = document.querySelector('header button.md\\:hidden, header button[class*="md:hidden"]');
        if (menuBtn && menuBtn.getAttribute('aria-expanded') === 'true') {
          menuBtn.click();
        }
      };
    }
    return null;
  }

  function insertNavLink(ul, before, template, href, label, marker) {
    if (ul.querySelector('[' + marker + ']')) {
      return;
    }
    var li = document.createElement('li');
    var a = document.createElement('a');
    a.href = href;
    a.setAttribute(marker, '1');
    a.textContent = label;
    a.className = template.className;
    var onClose = navMobileClose(template);
    if (onClose) {
      a.addEventListener('click', onClose);
    }
    li.appendChild(a);
    before.parentNode.insertBefore(li, before);
  }

  function navHasLabel(ul, pattern) {
    return Array.prototype.some.call(ul.querySelectorAll('a'), function (a) {
      return pattern.test((a.textContent || '').trim());
    });
  }

  function navHasHrefPart(ul, part) {
    return Array.prototype.some.call(ul.querySelectorAll('a'), function (a) {
      return (a.getAttribute('href') || '').indexOf(part) !== -1;
    });
  }

  function patchNavList(ul) {
    if (!ul) {
      return;
    }
    var anchorLi = null;
    var template = null;
    ul.querySelectorAll('li').forEach(function (li) {
      var a = li.querySelector('a');
      if (!a) {
        return;
      }
      var href = a.getAttribute('href') || '';
      if (href === '#donate' || /^donate$/i.test((a.textContent || '').trim())) {
        anchorLi = li;
        template = a;
      }
    });
    if (!anchorLi || !template) {
      ul.querySelectorAll('li').forEach(function (li) {
        var a = li.querySelector('a');
        if (!a || template) {
          return;
        }
        var href = a.getAttribute('href') || '';
        var label = (a.textContent || '').trim();
        if (/online booking/i.test(label) || href.indexOf('Booking/book') !== -1) {
          anchorLi = li;
          template = a;
        }
      });
    }
    if (!anchorLi || !template) {
      var lis = ul.querySelectorAll('li');
      if (lis.length) {
        anchorLi = lis[lis.length - 1];
        template = anchorLi.querySelector('a');
      }
    }
    if (!anchorLi || !template) {
      return;
    }
    var donateA = ul.querySelector('a[href="#donate"], a[href="/#donate"]');
    if (donateA && !donateA.getAttribute('data-sjt-donation-nav')) {
      donateA.href = DONATION;
      donateA.setAttribute('data-sjt-donation-nav', '1');
    }
    if (!ul.querySelector('[data-sjt-donate-nav]') && !navHasHrefPart(ul, 'Booking/donation')) {
      insertNavLink(ul, anchorLi, template, DONATION, 'Donate online', 'data-sjt-donate-nav');
    }
    if (!ul.querySelector('[data-sjt-booking-nav]') && !navHasHrefPart(ul, 'Booking/book')) {
      insertNavLink(ul, anchorLi, template, BOOKING, 'Online Booking', 'data-sjt-booking-nav');
    }
    if (!ul.querySelector('[data-sjt-live-nav]') && !navHasLabel(ul, /live darshan/i)) {
      insertNavLink(ul, anchorLi, template, LIVE, 'Live Darshan', 'data-sjt-live-nav');
    }
  }

  function patchHeaderNav() {
    var seen = new Set();
    document.querySelectorAll('header ul, nav ul').forEach(function (ul) {
      if (seen.has(ul)) {
        return;
      }
      seen.add(ul);
      patchNavList(ul);
    });
  }

  /** `/#section` ignores <base> and breaks in subfolders; use `#section` so base applies. */
  function patchInPageHashLinks() {
    document.querySelectorAll('a[href^="/#"]').forEach(function (a) {
      var raw = a.getAttribute('href') || '';
      if (raw.indexOf('/#') !== 0 || raw.length < 3) {
        return;
      }
      var next = raw.slice(1);
      if (a.getAttribute('href') === next && a.getAttribute('data-sjt-nav-hash') === '1') {
        return;
      }
      a.setAttribute('href', next);
      a.setAttribute('data-sjt-nav-hash', '1');
    });
  }

  function patchHeroButtons() {
    document.querySelectorAll('a[href="#donate"]').forEach(function (a) {
      if (a.closest('footer')) {
        return;
      }
      if (/donate now/i.test((a.textContent || '').trim())) {
        a.href = BOOKING;
        a.textContent = 'Online Booking';
        a.setAttribute('data-sjt-booking-cta', '1');
      }
    });
  }

  function patchHeroLiveButton() {
    if (document.querySelector('[data-sjt-hero-live]')) {
      return;
    }
    document.querySelectorAll('a[href="#about"], a[href="/#about"]').forEach(function (learn) {
      if (!/learn more/i.test((learn.textContent || '').trim())) {
        return;
      }
      var row = learn.closest('.flex');
      if (!row || row.querySelector('[data-sjt-hero-live]')) {
        return;
      }
      var live = document.createElement('a');
      live.href = LIVE;
      live.setAttribute('data-sjt-hero-live', '1');
      live.textContent = 'Live Darshan';
      live.className =
        learn.className ||
        'inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm font-medium h-11 rounded-md px-8 bg-temple-saffron hover:bg-temple-orange text-white';
      live.setAttribute('role', 'button');
      row.appendChild(live);
    });
  }

  function patchDonateSection() {
    var sec = document.getElementById('donate');
    if (!sec || sec.dataset.sjtPatched === '1') {
      return;
    }
    if (!sec.querySelector('.container, [class*="container"]')) {
      return;
    }
    sec.dataset.sjtPatched = '1';

    sec.querySelectorAll('form, select, input, button, [role="combobox"]').forEach(function (el) {
      var block = el.closest('[class*="Card"], [class*="card"], form') || el;
      if (sec.contains(block)) {
        block.setAttribute('hidden', '');
        block.classList.add('sjt-donation-form-hidden');
      }
    });

    sec.querySelectorAll('[class*="Card"], [class*="card"]').forEach(function (card) {
      if (/make a donation|donate now|select donation|razorpay/i.test(card.textContent || '')) {
        card.setAttribute('hidden', '');
        card.classList.add('sjt-donation-form-hidden');
      }
    });

    var container = sec.querySelector('.container') || sec.querySelector('[class*="container"]') || sec;
    if (container.querySelector('[data-sjt-donation-cta]')) {
      return;
    }

    var box = document.createElement('div');
    box.className = 'sjt-donation-cta';
    box.setAttribute('data-sjt-donation-cta', '1');
    box.innerHTML =
      '<p class="sjt-donation-cta-lead">Support the temple with a secure online donation (up to ₹10,000 per transaction) by card or UPI.</p>' +
      '<p class="sjt-donation-cta-note">For donations above ₹10,000, please contact temple management for confirmation and pay offline after confirmation.</p>' +
      '<a class="sjt-donation-cta-btn" href="' +
      DONATION +
      '">Donate online</a>' +
      '<a class="sjt-donation-cta-link" href="' +
      BOOKING +
      '">Book puja &amp; services</a>' +
      '<a class="sjt-donation-cta-link" href="' +
      LOOKUP +
      '">Look up an existing booking</a>';

    container.appendChild(box);
    patchDonateSupportUI();
  }

  function sevaIconSvg(kind) {
    var attrs =
      'class="sjt-seva-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" aria-hidden="true" focusable="false"';
    if (kind === 'anna') {
      return (
        '<svg ' +
        attrs +
        '><path fill="currentColor" d="M16 6c-3.5 2.5-7 5.5-7 9.5a7 7 0 0 0 14 0C23 11.5 19.5 8.5 16 6zm-1 3.2L18.5 13H13.5L15 9.2zM8 18h16v1.5H8V18z"/></svg>'
      );
    }
    if (kind === 'education') {
      return (
        '<svg ' +
        attrs +
        '><path fill="currentColor" d="M16 4 5 10.5V12h22v-1.5L16 4zm-9 4.2 9 5.6 9-5.6v7.3l-9 5.6-9-5.6V8.2zM7 21v7h18v-7H7zm2 2h14v3H9v-3z"/></svg>'
      );
    }
    return (
      '<svg ' +
      attrs +
      '><path fill="currentColor" d="M16 2 7 7.5V11h18V7.5L16 2zm-8 11v15h16V13H8zm2 2h12v11H10V15zm4 2v7h4v-7h-4z"/></svg>'
    );
  }

  function patchDonateSupportUI() {
    var sec = document.getElementById('donate');
    if (!sec) {
      return;
    }
    var grid = sec.querySelector('.grid');
    if (!grid) {
      return;
    }
    if (sec.dataset.sjtDonateUi !== '1') {
      sec.dataset.sjtDonateUi = '1';
      sec.classList.add('sjt-donate-section');
      grid.classList.add('sjt-donate-layout');
      var left = grid.children[0];
      if (left) {
        left.classList.add('sjt-donate-support-col');
        var list = left.querySelector('.mt-6');
        if (list) {
          list.classList.add('sjt-donate-seva-list');
          list.classList.remove('space-y-4', 'space-y-3', 'space-y-6');
        }
      }
      if (grid.children[1]) {
        grid.children[1].classList.add('sjt-donate-action-col');
      }
    }

    sec.querySelectorAll('.flex.items-center').forEach(function (row) {
      if (!sec.contains(row) || row.closest('[data-sjt-donation-cta]')) {
        return;
      }
      var h4 = row.querySelector('h4');
      if (!h4) {
        return;
      }
      row.classList.add('sjt-donate-seva-row');
      var iconWrap = row.querySelector('.rounded-full');
      if (!iconWrap) {
        return;
      }
      var kind = 'temple';
      var title = h4.textContent || '';
      if (/anna daan/i.test(title)) {
        kind = 'anna';
      } else if (/educational/i.test(title)) {
        kind = 'education';
      }
      if (iconWrap.getAttribute('data-sjt-seva-icon') === kind) {
        return;
      }
      iconWrap.setAttribute('data-sjt-seva-icon', kind);
      iconWrap.className = 'sjt-donate-seva-icon sjt-donate-seva-icon--' + kind;
      iconWrap.innerHTML = sevaIconSvg(kind);
    });
  }

  var SERVICE_CARDS = [
    {
      icon: '🪔',
      title: 'Special Puja',
      text: 'Puja with sankalpa in your family name. Choose a morning or evening slot.',
      href: BOOKING,
      cta: 'Book Special Puja',
    },
    {
      icon: '🚗',
      title: 'Vehicle Puja',
      text: 'Blessing for your vehicle at the temple. Bring the vehicle at your chosen time.',
      href: BOOKING,
      cta: 'Book Vehicle Puja',
    },
    {
      icon: '🙏',
      title: 'Shraddha Karma',
      text: 'Ancestral rites by temple priests. The temple confirms approval after you apply.',
      href: BOOKING,
      cta: 'Request Shraddha',
    },
    {
      icon: '🍚',
      title: 'Prasadam',
      text: 'Book prasadam coupons only, or add prasadam when you book an eligible puja online.',
      href: FOOD_BOOKING,
      cta: 'Book Prasadam',
    },
    {
      icon: '📿',
      title: 'Life-cycle ceremonies',
      text: 'Upanayana, marriage-related rituals, and other services listed in online booking.',
      href: BOOKING,
      cta: 'View services',
    },
    {
      icon: '🔍',
      title: 'Existing booking',
      text: 'Open your booking, pay by UPI, or download your invoice with booking number and mobile.',
      href: LOOKUP,
      cta: 'Find my booking',
    },
  ];

  function serviceCardsHtml() {
    return SERVICE_CARDS.map(function (card) {
      return (
        '<article class="sjt-service-card">' +
        '<a class="sjt-service-card-inner" href="' +
        card.href +
        '">' +
        '<span class="sjt-service-icon" aria-hidden="true">' +
        card.icon +
        '</span>' +
        '<h3 class="sjt-service-title">' +
        card.title +
        '</h3>' +
        '<p class="sjt-service-text">' +
        card.text +
        '</p>' +
        '<span class="sjt-service-cta">' +
        card.cta +
        ' →</span>' +
        '</a></article>'
      );
    }).join('');
  }

  function hideDuplicateServiceGrids() {
    var sec = document.getElementById('services');
    if (!sec) {
      return;
    }
    var container = sec.querySelector('.container, [class*="container"]');
    if (!container) {
      return;
    }
    container.querySelectorAll('.grid').forEach(function (grid) {
      if (grid.classList.contains('sjt-services-grid')) {
        return;
      }
      if (grid.closest('[data-sjt-services-online]')) {
        return;
      }
      grid.classList.add('sjt-legacy-grid-hidden');
      grid.setAttribute('hidden', '');
      grid.setAttribute('aria-hidden', 'true');
    });
  }

  function patchServicesSection() {
    var sec = document.getElementById('services');
    if (!sec) {
      return;
    }
    var container = sec.querySelector('.container, [class*="container"]');
    if (!container) {
      return;
    }
    var existing = container.querySelector('[data-sjt-services-online]');
    if (existing && existing.getAttribute('data-sjt-version') === '3') {
      return;
    }
    if (existing) {
      existing.remove();
    }
    var title = container.querySelector('h2');
    var box = document.createElement('div');
    box.className = 'sjt-services-online';
    box.setAttribute('data-sjt-services-online', '1');
    box.setAttribute('data-sjt-version', '3');
    box.innerHTML =
      '<p class="sjt-services-intro">Book puja, prasadam, and ceremonies online. Pick a service, date, and time slot; pay by UPI; receive your booking number and invoice by email.</p>' +
      '<div class="sjt-services-grid">' +
      serviceCardsHtml() +
      '</div>' +
      '<p class="sjt-services-note">Daily arati, bhajan, and weekend discourses at the temple are open to everyone and are not booked through this system.</p>' +
      '<p class="sjt-services-actions">' +
      '<a class="sjt-donation-cta-btn" href="' +
      BOOKING +
      '">Start booking</a>' +
      '</p>';
    if (title) {
      title.insertAdjacentElement('afterend', box);
    } else {
      container.prepend(box);
    }
    sec.dataset.sjtServicesPatched = '1';
    hideDuplicateServiceGrids();
  }

  function patchFooterDonateLinks() {
    document.querySelectorAll('footer a[href="#donate"], footer a[href*="donate"]').forEach(function (a) {
      if (a.getAttribute('data-sjt-footer-donate')) {
        return;
      }
      if (/donate/i.test(a.textContent || '') || a.getAttribute('href') === '#donate') {
        a.href = DONATION;
        a.setAttribute('data-sjt-footer-donate', '1');
      }
    });
  }

  function patchBottomCta() {
    if (!document.querySelector('[data-sjt-bottom-cta]')) {
      var bar = document.createElement('div');
      bar.className = 'sjt-bottom-cta';
      bar.setAttribute('data-sjt-bottom-cta', '1');
      bar.setAttribute('role', 'navigation');
      bar.setAttribute('aria-label', 'Quick links');
      bar.innerHTML =
        '<a class="sjt-bottom-cta-btn sjt-bottom-cta-donate" href="' +
        DONATION +
        '">Donate online</a>' +
        '<a class="sjt-bottom-cta-btn sjt-bottom-cta-book" href="' +
        BOOKING +
        '">Book service</a>';
      document.body.appendChild(bar);
    }

    if (document.querySelector('[data-sjt-fab]')) {
      return;
    }

    var fab = document.createElement('div');
    fab.className = 'sjt-fab';
    fab.setAttribute('data-sjt-fab', '1');
    fab.innerHTML =
      '<div class="sjt-fab-menu" role="menu" aria-label="Quick actions">' +
      '<a class="sjt-fab-menu-item sjt-fab-donate" role="menuitem" href="' +
      DONATION +
      '">Donate online</a>' +
      '<a class="sjt-fab-menu-item sjt-fab-book" role="menuitem" href="' +
      BOOKING +
      '">Book service</a>' +
      '</div>' +
      '<button type="button" class="sjt-fab-toggle" aria-expanded="false" aria-haspopup="true" aria-label="Open services: donate and booking">' +
      '<span class="sjt-fab-toggle-icon" aria-hidden="true">+</span>' +
      '<span class="sjt-fab-toggle-label">Services</span>' +
      '</button>';

    var toggle = fab.querySelector('.sjt-fab-toggle');
    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = fab.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      if (!fab.classList.contains('is-open')) {
        return;
      }
      if (!fab.contains(e.target)) {
        fab.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && fab.classList.contains('is-open')) {
        fab.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.body.appendChild(fab);
  }

  function run() {
    patchInPageHashLinks();
    patchHeaderNav();
    patchHeroButtons();
    patchHeroLiveButton();
    patchDonateSection();
    patchServicesSection();
    hideDuplicateServiceGrids();
    patchFooterDonateLinks();
    patchBottomCta();
    patchDonateSupportUI();
  }

  run();
  var obs = new MutationObserver(function () {
    run();
  });
  obs.observe(document.documentElement, { childList: true, subtree: true });
})();
