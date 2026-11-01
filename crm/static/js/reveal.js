(function () {
  document.querySelectorAll('.reveal-group').forEach(function (group) {
    group.querySelectorAll('[data-reveal]').forEach(function (button) {
      button.addEventListener('click', function () {
        var panel = document.getElementById(button.getAttribute('data-reveal') || '');
        if (!panel || !group.contains(panel)) {
          return;
        }
        var show = panel.hidden;
        group.querySelectorAll('.reveal-panel').forEach(function (other) {
          other.hidden = true;
        });
        group.querySelectorAll('[data-reveal]').forEach(function (other) {
          other.classList.remove('is-on');
        });
        if (!show) {
          return;
        }
        panel.hidden = false;
        button.classList.add('is-on');
        var field = panel.querySelector('input:not([type="hidden"]):not(:disabled), select, textarea');
        if (field) {
          field.focus();
        }
      });
    });
  });
})();
