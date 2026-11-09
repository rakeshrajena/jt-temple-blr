(function () {
  'use strict';

  var BOOKING = (window.SJT_BOOKING_URL || '/Booking/book').replace(/\/$/, '');
  var LOOKUP =
    (window.SJT_BOOKING_LOOKUP_URL || 'https://sjtbengaluru.org/Booking/lookup').replace(/\/$/, '');
  var FOOD_BOOKING =
    window.SJT_BOOKING_FOOD_URL ||
    (BOOKING.indexOf('?') === -1 ? BOOKING + '?service=food-only' : BOOKING);

  function patchNavList(ul) {
    if (!ul || ul.querySelector('[data-sjt-booking-nav]')) {
      return;
    }
    var donateLi = null;
    var template = null;
    ul.querySelectorAll('li').forEach(function (li) {
      var a = li.querySelector('a');
      if (!a) {
        return;
      }
      var href = a.getAttribute('href') || '';
      if (href === '#donate' || /^donate$/i.test((a.textContent || '').trim())) {
        donateLi = li;
        template = a;
      }
    });
    if (!donateLi || !template) {
      return;
    }
    var li = document.createElement('li');
    var a = document.createElement('a');
    a.href = BOOKING;
    a.setAttribute('data-sjt-booking-nav', '1');
    a.textContent = 'Online Booking';
    a.className = template.className;
    if (template.getAttribute('onClick')) {
      a.addEventListener('click', function () {
        var menuBtn = document.querySelector('header button.md\\:hidden, header button[class*="md:hidden"]');
        if (menuBtn && menuBtn.getAttribute('aria-expanded') === 'true') {
          menuBtn.click();
        }
      });
    }
    li.appendChild(a);
    donateLi.parentNode.insertBefore(li, donateLi);
  }

  function patchHeaderNav() {
    document.querySelectorAll('header ul').forEach(patchNavList);
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
      '<p class="sjt-donation-cta-lead">Book puja, prasadam, and temple services online. Pay by UPI and receive your invoice by email.</p>' +
      '<p class="sjt-donation-cta-note">Cash and bank donations are still welcomed at the temple office. The online donation form on this page is temporarily unavailable.</p>' +
      '<a class="sjt-donation-cta-btn" href="' +
      BOOKING +
      '">Book online</a>' +
      '<a class="sjt-donation-cta-link" href="' +
      LOOKUP +
      '">Look up an existing booking</a>';

    container.appendChild(box);
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

  function patchServicesSection() {
    var sec = document.getElementById('services');
    if (!sec) {
      return;
    }
    var container = sec.querySelector('.container, [class*="container"]');
    if (!container) {
      return;
    }
    var grid = container.querySelector('.grid');
    if (grid) {
      grid.classList.add('sjt-legacy-grid-hidden');
      grid.setAttribute('hidden', '');
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
  }

  function run() {
    patchHeaderNav();
    patchHeroButtons();
    patchDonateSection();
    patchServicesSection();
  }

  run();
  var obs = new MutationObserver(function () {
    run();
  });
  obs.observe(document.documentElement, { childList: true, subtree: true });
})();
