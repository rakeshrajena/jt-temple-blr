<?php
/** @var string $bulkEmailAction */
/** @var string $bulkKind */
$bulkKind = $bulkKind === 'receipt' ? 'receipt' : 'devotee';
?>
<div id="bulkComposer" hidden style="margin:12px 0; padding:12px; border:1px solid var(--line); border-radius:8px;">
  <h3 id="bulkTitle" style="margin-top:0;">Message</h3>
  <p class="sub" id="bulkHint"></p>
  <p id="bulkCount" style="font-size:13px; margin-top:0;"></p>
  <div class="form-group">
    <label for="bulkMessage">Message</label>
    <textarea id="bulkMessage" maxlength="1000" rows="5" style="width:100%;"></textarea>
  </div>
  <div class="form-actions">
    <button class="btn btn-gold btn-sm" type="button" id="bulkSend">Send</button>
    <button class="btn btn-outline btn-sm" type="button" id="bulkCancel">Cancel</button>
  </div>
  <p id="bulkStatus" style="font-size:13px;"></p>
</div>
<form id="bulkEmailForm" method="POST" action="<?= e($bulkEmailAction) ?>" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="message" id="bulkMessageField">
  <div id="bulkIdFields"></div>
</form>
<script>
(function () {
  const kind = <?= json_encode($bulkKind, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const composer = document.getElementById('bulkComposer');
  const title = document.getElementById('bulkTitle');
  const hint = document.getElementById('bulkHint');
  const count = document.getElementById('bulkCount');
  const message = document.getElementById('bulkMessage');
  const status = document.getElementById('bulkStatus');
  const emailButton = document.getElementById('bulkEmail');
  const whatsappButton = document.getElementById('bulkWhatsapp');
  const form = document.getElementById('bulkEmailForm');
  const messageField = document.getElementById('bulkMessageField');
  const idFields = document.getElementById('bulkIdFields');
  let mode = '';

  function selected() {
    return Array.from(document.querySelectorAll('.bulk-checkbox:checked')).map(function (box) {
      const row = box.closest('tr');
      return {
        id: box.value,
        digits: row ? (row.getAttribute('data-digits') || '') : '',
        extra: row ? (row.getAttribute('data-extra') || '') : '',
      };
    });
  }

  function refresh() {
    const n = selected().length;
    if (emailButton) emailButton.disabled = n === 0;
    if (whatsappButton) whatsappButton.disabled = n === 0;
    const emailCount = document.getElementById('bulkEmailCount');
    const whatsappCount = document.getElementById('bulkWhatsappCount');
    if (emailCount) emailCount.textContent = String(n);
    if (whatsappCount) whatsappCount.textContent = String(n);
    const boxes = document.querySelectorAll('.bulk-checkbox');
    const selectAllBox = document.getElementById('selectAll');
    if (selectAllBox && boxes.length > 0) {
      selectAllBox.checked = Array.from(boxes).every(function (box) { return box.checked; });
    }
  }
  window.jtRefreshBulk = refresh;

  function open(next) {
    const people = selected();
    if (people.length === 0) {
      return;
    }
    mode = next;
    title.textContent = next === 'email' ? 'Bulk email' : 'Bulk WhatsApp';
    if (kind === 'receipt' && next === 'email') {
      hint.textContent = 'This note is emailed to each selected devotee who has a valid address. The receipt PDF and the receipt link are added.';
    } else if (kind === 'receipt') {
      hint.textContent = 'A WhatsApp chat opens for each selected devotee who has a phone. The receipt link is added to the message. Allow pop-up windows if a chat does not open.';
    } else if (next === 'email') {
      hint.textContent = 'This note is emailed to each selected devotee who has a valid address.';
    } else {
      hint.textContent = 'A WhatsApp chat opens for each selected devotee who has a phone. Allow pop-up windows if a chat does not open.';
    }
    count.textContent = people.length + ' selected.';
    status.textContent = '';
    composer.hidden = false;
    message.focus();
  }

  function close() {
    mode = '';
    composer.hidden = true;
    status.textContent = '';
    message.value = '';
  }

  if (emailButton) emailButton.addEventListener('click', function () { open('email'); });
  if (whatsappButton) whatsappButton.addEventListener('click', function () { open('whatsapp'); });
  document.getElementById('bulkCancel').addEventListener('click', close);
  document.getElementById('bulkSend').addEventListener('click', function () {
    const text = message.value.trim();
    const people = selected();
    if (text === '') {
      status.textContent = 'Enter the message.';
      return;
    }
    if (text.length > 1000) {
      status.textContent = 'The message is too long.';
      return;
    }
    if (people.length === 0) {
      status.textContent = 'Select at least one person.';
      return;
    }
    if (people.length > 50) {
      status.textContent = 'Select up to 50 people at a time.';
      return;
    }
    if (mode === 'email') {
      messageField.value = text;
      idFields.replaceChildren();
      people.forEach(function (person) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = kind === 'receipt' ? 'donation_ids[]' : 'donor_ids[]';
        input.value = person.id;
        idFields.appendChild(input);
      });
      form.submit();
      return;
    }
    let opened = 0;
    let missing = 0;
    people.forEach(function (person) {
      if (person.digits === '') {
        missing++;
        return;
      }
      let body = text;
      if (person.extra !== '') {
        body += '\n\n' + person.extra;
      }
      const url = 'https://web.whatsapp.com/send?phone=' + person.digits + '&text=' + encodeURIComponent(body);
      const win = window.open(url, '_blank', 'noopener');
      if (win) {
        opened++;
      }
    });
    if (opened === 0 && missing === people.length) {
      status.textContent = 'None of the selected devotees have a phone number.';
      return;
    }
    status.textContent = 'Opened ' + opened + ' WhatsApp chat' + (opened === 1 ? '' : 's') + '.';
    if (missing > 0) {
      status.textContent += ' ' + missing + ' skipped because there is no phone number.';
    }
    if (opened < people.length - missing) {
      status.textContent += ' Allow pop-up windows and press Send again for any chat that did not open.';
    }
  });
  document.querySelectorAll('.bulk-checkbox').forEach(function (box) {
    box.addEventListener('change', refresh);
  });
  const selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.bulk-checkbox').forEach(function (box) { box.checked = selectAll.checked; });
      refresh();
    });
  }
  refresh();
})();
</script>
