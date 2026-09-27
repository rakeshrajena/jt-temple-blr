<?php
/** @var array<string, mixed> $invitation */
/** @var list<array<string, string>> $blocks */
$clientBlocks = [];
foreach ($blocks as $block) {
    if (($block['type'] ?? '') === 'image') {
        $block['url'] = url('invitations/media/' . $block['token']);
    }
    $clientBlocks[] = $block;
}
?>
<div class="panel">
  <h3>Design invitation</h3>
  <p class="sub">What you see on the card is what the devotee receives. Add a heading, words, a picture, or a video link. The email starts with the devotee's name. A video is sent as a link, because mail apps do not play a video inside the message.</p>
  <form id="inviteForm" method="POST" action="<?= e(url('invitations/' . $invitation['id'])) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-2">
      <div class="form-group">
        <label for="inviteTitle">Name</label>
        <input id="inviteTitle" name="title" maxlength="120" required value="<?= e((string) $invitation['title']) ?>">
      </div>
      <div class="form-group">
        <label for="inviteSubject">Email subject</label>
        <input id="inviteSubject" name="subject" maxlength="160" required value="<?= e((string) $invitation['subject']) ?>">
      </div>
    </div>
    <input type="hidden" name="blocks" id="inviteBlocks">
    <div class="invite-toolbar">
      <button class="btn btn-outline btn-sm" type="button" id="addHeading">Heading</button>
      <button class="btn btn-outline btn-sm" type="button" id="addText">Text</button>
      <button class="btn btn-outline btn-sm" type="button" id="addImage">Image</button>
      <button class="btn btn-outline btn-sm" type="button" id="addVideo">Video link</button>
      <button class="btn btn-outline btn-sm" type="button" data-cmd="bold"><strong>B</strong></button>
      <button class="btn btn-outline btn-sm" type="button" data-cmd="italic"><em>I</em></button>
      <button class="btn btn-outline btn-sm" type="button" data-align="left">Left</button>
      <button class="btn btn-outline btn-sm" type="button" data-align="center">Center</button>
      <button class="btn btn-outline btn-sm" type="button" data-align="right">Right</button>
    </div>
    <input id="inviteFile" type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
    <div id="videoForm" class="invite-video-form" hidden>
      <div class="form-group">
        <label for="videoUrl">Video link</label>
        <input id="videoUrl" type="url" placeholder="https://">
      </div>
      <div class="form-group">
        <label for="videoLabel">Button words</label>
        <input id="videoLabel" type="text" maxlength="80" value="Watch">
      </div>
      <button class="btn btn-gold btn-sm" type="button" id="videoSave">Add video link</button>
    </div>
    <p id="inviteStatus" class="sub"></p>
    <div class="invite-stage">
      <div class="invite-card" id="inviteCard"></div>
    </div>
    <div class="form-actions">
      <button class="btn btn-gold" type="submit">Save design</button>
      <a class="btn btn-outline" href="<?= e(url('invitations/' . $invitation['id'] . '/send')) ?>">Choose devotees</a>
    </div>
  </form>
  <form method="POST" action="<?= e(url('invitations/' . $invitation['id'] . '/delete')) ?>" class="invite-delete" onsubmit="return confirm('Remove this invitation?');">
    <?= csrf_field() ?>
    <button class="btn btn-outline btn-sm" type="submit">Remove invitation</button>
  </form>
</div>
<script>
(function () {
  const initial = <?= json_encode($clientBlocks, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const uploadUrl = <?= json_encode(url('invitations/' . $invitation['id'] . '/image'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const csrf = document.querySelector('#inviteForm input[name="csrf"]').value;
  const card = document.getElementById('inviteCard');
  const status = document.getElementById('inviteStatus');
  const fileInput = document.getElementById('inviteFile');
  const videoForm = document.getElementById('videoForm');
  let blocks = Array.isArray(initial) ? initial : [];

  function focusedBlock() {
    const node = document.activeElement;
    return node ? node.closest('.invite-block') : null;
  }

  function render() {
    card.replaceChildren();
    if (blocks.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'invite-empty';
      empty.textContent = 'Add a heading, words, a picture, or a video link.';
      card.appendChild(empty);
      return;
    }
    blocks.forEach(function (block, index) {
      const wrap = document.createElement('div');
      wrap.className = 'invite-block';
      wrap.dataset.index = String(index);
      const bar = document.createElement('div');
      bar.className = 'invite-block-bar';
      [['Up', -1], ['Down', 1], ['Remove', 0]].forEach(function (item) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline btn-sm';
        button.textContent = item[0];
        button.addEventListener('click', function () {
          readText();
          if (item[0] === 'Remove') {
            blocks.splice(index, 1);
          } else {
            const next = index + item[1];
            if (next < 0 || next >= blocks.length) return;
            const moved = blocks.splice(index, 1)[0];
            blocks.splice(next, 0, moved);
          }
          render();
        });
        bar.appendChild(button);
      });
      wrap.appendChild(bar);
      if (block.type === 'text') {
        const text = document.createElement('div');
        text.className = 'invite-text' + (block.kind === 'heading' ? ' invite-heading' : '');
        text.contentEditable = 'true';
        text.dataset.align = block.align || 'left';
        text.style.textAlign = block.align || 'left';
        text.innerHTML = block.html || '';
        text.addEventListener('input', function () { block.html = text.innerHTML; });
        wrap.appendChild(text);
      } else if (block.type === 'image') {
        const img = document.createElement('img');
        img.src = block.url || '';
        img.alt = block.alt || '';
        img.className = 'invite-image';
        wrap.appendChild(img);
      } else if (block.type === 'video') {
        const link = document.createElement('a');
        link.className = 'invite-video';
        link.href = block.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = block.label || 'Watch';
        wrap.appendChild(link);
        const address = document.createElement('p');
        address.className = 'invite-video-url';
        address.textContent = block.url;
        wrap.appendChild(address);
      }
      card.appendChild(wrap);
    });
  }

  function readText() {
    card.querySelectorAll('.invite-block').forEach(function (wrap) {
      const index = Number(wrap.dataset.index);
      const text = wrap.querySelector('.invite-text');
      if (text && blocks[index] && blocks[index].type === 'text') {
        blocks[index].html = text.innerHTML;
        blocks[index].align = text.dataset.align || 'left';
      }
    });
  }

  function addText(kind) {
    readText();
    blocks.push({ type: 'text', kind: kind, align: kind === 'heading' ? 'center' : 'left', html: '' });
    render();
    const nodes = card.querySelectorAll('.invite-text');
    const last = nodes[nodes.length - 1];
    if (last) last.focus();
  }

  document.getElementById('addHeading').addEventListener('click', function () { addText('heading'); });
  document.getElementById('addText').addEventListener('click', function () { addText('body'); });
  document.getElementById('addImage').addEventListener('click', function () { fileInput.click(); });
  document.getElementById('addVideo').addEventListener('click', function () {
    videoForm.hidden = !videoForm.hidden;
    if (!videoForm.hidden) document.getElementById('videoUrl').focus();
  });
  document.querySelectorAll('[data-cmd]').forEach(function (button) {
    button.addEventListener('mousedown', function (event) { event.preventDefault(); });
    button.addEventListener('click', function () {
      document.execCommand(button.getAttribute('data-cmd'), false, null);
      readText();
    });
  });
  document.querySelectorAll('[data-align]').forEach(function (button) {
    button.addEventListener('mousedown', function (event) { event.preventDefault(); });
    button.addEventListener('click', function () {
      const wrap = focusedBlock();
      const text = wrap ? wrap.querySelector('.invite-text') : null;
      if (!text) return;
      const align = button.getAttribute('data-align');
      text.dataset.align = align;
      text.style.textAlign = align;
      readText();
    });
  });
  fileInput.addEventListener('change', function () {
    const file = fileInput.files && fileInput.files[0];
    fileInput.value = '';
    if (!file) return;
    status.textContent = 'Adding the picture…';
    if (window.jtBusy) window.jtBusy('Adding the picture');
    const body = new FormData();
    body.append('csrf', csrf);
    body.append('image', file);
    fetch(uploadUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
      .then(function (result) {
        if (window.jtBusyDone) window.jtBusyDone();
        if (!result.ok || !result.data.token) {
          status.textContent = (result.data && result.data.error) || 'That picture could not be added.';
          return;
        }
        readText();
        blocks.push({ type: 'image', token: result.data.token, url: result.data.url, alt: '' });
        status.textContent = '';
        render();
      })
      .catch(function () {
        if (window.jtBusyDone) window.jtBusyDone();
        status.textContent = 'That picture could not be added.';
      });
  });
  document.getElementById('videoSave').addEventListener('click', function () {
    const url = document.getElementById('videoUrl').value.trim();
    const label = document.getElementById('videoLabel').value.trim() || 'Watch';
    if (!/^https?:\/\//i.test(url)) {
      status.textContent = 'A video link must start with http:// or https://.';
      return;
    }
    readText();
    blocks.push({ type: 'video', url: url, label: label });
    document.getElementById('videoUrl').value = '';
    videoForm.hidden = true;
    status.textContent = '';
    render();
  });
  document.getElementById('inviteForm').addEventListener('submit', function () {
    readText();
    document.getElementById('inviteBlocks').value = JSON.stringify(blocks.map(function (block) {
      if (block.type === 'image') {
        return { type: 'image', token: block.token, alt: block.alt || '' };
      }
      if (block.type === 'video') {
        return { type: 'video', url: block.url, label: block.label || 'Watch' };
      }
      return { type: 'text', html: block.html || '', align: block.align || 'left', kind: block.kind || 'body' };
    }));
  });
  render();
})();
</script>
