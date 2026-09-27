(function () {
  const screen = document.getElementById('appBusy');
  const message = document.getElementById('appBusyMessage');
  if (!screen || !message) {
    return;
  }
  let shown = false;

  function clean(text) {
    return String(text || '').replace(/\s+/g, ' ').trim();
  }

  function finish(text) {
    const line = clean(text);
    if (line === '') {
      return 'Please wait…';
    }
    if (/[….]$/.test(line)) {
      return line;
    }
    return line + '…';
  }

  function show(text) {
    if (shown) {
      return;
    }
    shown = true;
    message.textContent = finish(text);
    screen.hidden = false;
    document.body.classList.add('is-busy');
  }

  function hide() {
    shown = false;
    screen.hidden = true;
    document.body.classList.remove('is-busy');
  }

  window.jtBusy = show;
  window.jtBusyDone = hide;

  document.addEventListener('submit', function (event) {
    if (event.defaultPrevented) {
      return;
    }
    if (shown) {
      event.preventDefault();
      return;
    }
    const form = event.target;
    const submitter = event.submitter || null;
    if (form && form.classList.contains('locale-switch')) {
      show('Changing the language');
      return;
    }
    const custom = submitter ? submitter.getAttribute('data-busy') : '';
    if (custom) {
      show(custom);
      return;
    }
    const label = submitter ? clean(submitter.innerText || submitter.value) : '';
    show(label || 'Please wait');
  });

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0) {
      return;
    }
    if (shown) {
      event.preventDefault();
      return;
    }
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    const link = event.target.closest ? event.target.closest('a') : null;
    if (!link || link.hasAttribute('download') || link.target === '_blank' || link.hasAttribute('data-no-busy')) {
      return;
    }
    const href = link.getAttribute('href') || '';
    if (href === '' || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
      return;
    }
    let url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (error) {
      return;
    }
    if (url.origin !== window.location.origin) {
      return;
    }
    if (url.pathname === window.location.pathname && url.search === window.location.search) {
      return;
    }
    const label = clean(link.innerText);
    show(label === '' ? 'Opening the page' : 'Opening ' + label);
  });

  window.addEventListener('pageshow', hide);
})();
