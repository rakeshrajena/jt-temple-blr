<?php
declare(strict_types=1);

require __DIR__ . '/includes/stream.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/icons.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['stream_url'])) {
    streaming_save_stream_from_post();
}

streaming_boot_session();

$url = streaming_read_url();
$embed = $url !== null ? streaming_embed_src($url) : null;
$messages = streaming_unavailable_lines();
$title = $url !== null ? 'Live darshan' : 'Live darshan unavailable';
$flash = streaming_pull_flash();
$autoOpenModal = isset($_GET['open']) && (string) $_GET['open'] === '1';

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <title><?= streaming_escape($title) ?> | Shree Jagannath Temple, Sarjapura</title>
  <style>
    :root { --maroon: #7a1d1d; --cream: #fbf5e9; --ink: #2a1b12; --ok: #1a6b3c; --err: #8b1a1a; }
    * { box-sizing: border-box; }
    html { overflow-x: clip; -webkit-text-size-adjust: 100%; height: 100%; }
    body {
      margin: 0; font-family: system-ui, 'Segoe UI', sans-serif; background: var(--cream); color: var(--ink); line-height: 1.5;
      overflow-x: clip; min-height: 100%; min-height: 100dvh; display: flex; flex-direction: column;
    }
    header { flex: none; background: var(--maroon); color: #fff3e6; padding: 0.85rem 3.25rem 0.85rem 1.25rem; }
    header a { color: #f2bd2c; }
    main { flex: 1 0 auto; width: 100%; max-width: 56rem; margin: 0 auto; padding: 1.25rem max(1.25rem, env(safe-area-inset-right)) 1.5rem max(1.25rem, env(safe-area-inset-left)); box-sizing: border-box; }
    .player { position: relative; width: 100%; padding-top: 56.25%; background: #111; border-radius: 10px; overflow: hidden;
      box-shadow: 0 4px 20px rgba(42,27,18,.15); }
    .player iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
    .unavail { background: #fffdf8; border: 1px solid #e3d3bb; border-radius: 10px; padding: 1.5rem 1.25rem; text-align: center; }
    .unavail h1 { font-size: 1.35rem; color: var(--maroon); margin: 0 0 1rem; }
    .lang { margin: 0.65rem 0; font-size: 1.05rem; }
    .actions { margin-top: 1.5rem; text-align: center; }
    .btn { display: inline-block; padding: 0.55rem 1.1rem; background: var(--maroon); color: #fff !important;
      text-decoration: none; border-radius: 8px; font-weight: 600; border: 0; font-size: 1rem; cursor: pointer; }
    .hint { font-size: 0.9rem; color: #5c4e42; margin-top: 1rem; text-align: center; }
    .flash { max-width: 56rem; margin: 0 auto; padding: 0.65rem 1.25rem; }
    .flash.ok { background: #e8f5ee; color: var(--ok); }
    .flash.error { background: #fdecec; color: var(--err); }
    .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(20,10,6,.55); z-index: 100; align-items: center; justify-content: center; padding: 1rem; }
    .modal-backdrop.open { display: flex; }
    .modal { background: #fffdf8; border-radius: 12px; max-width: 28rem; width: 100%; padding: 1.25rem; border: 1px solid #e3d3bb; box-shadow: 0 8px 32px rgba(0,0,0,.2); }
    .modal h2 { margin: 0 0 0.75rem; font-size: 1.15rem; color: var(--maroon); }
    .modal label { display: block; font-weight: 600; margin-bottom: 0.35rem; }
    .modal input[type=url], .modal input[type=password] { width: 100%; padding: 0.5rem 0.6rem; border: 1px solid #cbb99a; border-radius: 8px; font-size: 1rem; }
    .modal-actions { margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .modal .btn-secondary { background: transparent; color: var(--maroon) !important; border: 1px solid var(--maroon); }
    .stream-corner-icon { position: fixed; top: 0.65rem; right: 0.65rem; z-index: 200; display: flex; align-items: center; justify-content: center;
      width: 2.5rem; height: 2.5rem; border-radius: 50%; background: #fffdf8; color: #7a1d1d; border: 1px solid #e3d3bb;
      box-shadow: 0 2px 10px rgba(42, 27, 18, 0.18); cursor: pointer; padding: 0; }
    .stream-corner-icon:hover, .stream-corner-icon:focus { background: #f2bd2c; color: #4a0f0f; outline: 2px solid #f2bd2c; outline-offset: 2px; }
    @media (max-width: 640px) {
      header { padding: 0.75rem max(1rem, env(safe-area-inset-right)) 0.75rem max(3rem, env(safe-area-inset-left)); font-size: 0.95rem; }
      .stream-corner-icon { top: max(0.5rem, env(safe-area-inset-top)); right: max(0.5rem, env(safe-area-inset-right)); }
      .unavail h1 { font-size: 1.2rem; }
      .lang { font-size: 1rem; }
      .modal-actions { flex-direction: column; align-items: stretch; }
      .modal-actions .btn, .modal-actions .btn-secondary { width: 100%; text-align: center; justify-content: center; }
    }
    @media (min-width: 641px) and (max-width: 1024px) {
      main { padding-top: 1rem; padding-bottom: 1.25rem; }
    }
  </style>
</head>
<body>
  <?= streaming_corner_icon_button('Stream settings') ?>
  <header>
    <a href="/">← Temple home</a>
  </header>
  <?php if ($flash !== null): ?>
    <p class="flash <?= streaming_escape($flash[0]) ?>"><?= streaming_escape($flash[1]) ?></p>
  <?php endif; ?>
  <main>
    <?php if ($embed !== null): ?>
      <h1 style="color:var(--maroon);font-size:1.4rem;margin:0 0 1rem;">Live darshan</h1>
      <div class="player">
        <iframe src="<?= streaming_escape($embed) ?>"
          title="Live stream darshan"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          allowfullscreen
          referrerpolicy="strict-origin-when-cross-origin"></iframe>
      </div>
      <p class="hint">Video starts muted; use the player controls for sound. If it does not start, refresh the page or try again later.</p>
    <?php else: ?>
      <div class="unavail">
        <h1>Live stream darshan</h1>
        <?php foreach ($messages as $line): ?>
          <p class="lang"><?= streaming_escape($line) ?></p>
        <?php endforeach; ?>
      </div>
      <p class="actions"><a class="btn" href="/">Return to temple home</a></p>
    <?php endif; ?>
  </main>

  <div class="modal-backdrop<?= $autoOpenModal ? ' open' : '' ?>" id="stream-modal" role="dialog" aria-modal="true" aria-labelledby="stream-modal-title">
    <div class="modal">
      <h2 id="stream-modal-title">Live stream URL</h2>
      <form method="post" action="<?= streaming_escape(streaming_public_path('index.php')) ?>">
        <label for="stream_url_modal">YouTube or other stream URL</label>
        <input type="url" id="stream_url_modal" name="stream_url" required
          placeholder="https://www.youtube.com/watch?v=…"
          value="<?= streaming_escape($url ?? '') ?>">
        <label for="stream_key_modal" style="margin-top:0.75rem;">Staff save key</label>
        <input type="password" id="stream_key_modal" name="stream_key" autocomplete="off" required
          placeholder="Staff save key">
        <ul class="hint" style="text-align:left;margin:0.5rem 0 0;padding-left:1.2rem;">
          <li>Use the full <strong>https</strong> link.</li>
        </ul>
        <div class="modal-actions">
          <button class="btn" type="submit">Save</button>
          <button class="btn btn-secondary" type="button" id="close-stream-modal">Close</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    (function () {
      var modal = document.getElementById('stream-modal');
      var gear = document.getElementById('gear-settings');
      var closeBtn = document.getElementById('close-stream-modal');
      if (!modal) return;
      function openModal() {
        modal.classList.add('open');
        var i = document.getElementById('stream_url_modal');
        if (i) i.focus();
      }
      function closeModal() { modal.classList.remove('open'); }
      if (gear) gear.addEventListener('click', openModal);
      if (closeBtn) closeBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
    })();
  </script>
</body>
</html>
