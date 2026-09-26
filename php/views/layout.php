<?php
/** @var string $content */
/** @var string $title */
/** @var string $pageTitle */
/** @var string $active */
/** @var array $currentUser */
/** @var list<array{0:string,1:string}> $flashes */
$active = $active ?? '';
$pageTitle = $pageTitle ?? '';
$title = $title ?? 'Temple Admin';
$fullName = (string) ($currentUser['full_name'] ?? '');
$initial = $fullName !== '' ? mb_strtoupper(mb_substr($fullName, 0, 1)) : '?';
$role = (string) ($currentUser['role'] ?? '');

$icon = static function (string $name): string {
    $paths = [
        'star' => '<path d="M12 3.2l2.1 4.6 5 .6-3.7 3.4.9 5L12 14.6 7.7 16.8l.9-5L4.9 8.4l5-.6L12 3.2z"/>',
        'grid' => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.2"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.2"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.2"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.2"/>',
        'box' => '<path d="M4 8.2 12 4l8 4.2v7.6L12 20 4 15.8V8.2z"/><path d="M12 12.2 20 8.2M12 12.2V20M12 12.2 4 8.2"/>',
        'leaf' => '<path d="M5 19s1.2-7.2 8.2-11.2C16.8 5.6 20 5 20 5s-.2 3.4-2.2 6.6C14.6 16.6 8 18.2 5 19z"/><path d="M9 14c1.4-1.2 3.2-2.6 5.4-3.8"/>',
        'cloth' => '<path d="M6 5h12l-1.2 14.2a2 2 0 0 1-2 1.8H9.2a2 2 0 0 1-2-1.8L6 5z"/><path d="M9 5c0 1.6.8 2.5 3 2.5S15 6.6 15 5"/>',
        'hands' => '<path d="M8 11V6.8a1.3 1.3 0 0 1 2.6 0V11"/><path d="M10.6 10.2V5.8a1.3 1.3 0 0 1 2.6 0v5"/><path d="M13.2 10.6V7.2a1.3 1.3 0 0 1 2.6 0V13c0 3.2-1.8 6-5.2 6-2.8 0-4.6-1.6-4.6-4.2V9.2a1.3 1.3 0 0 1 2.6 0V11"/>',
        'bell' => '<path d="M6 16h12l-1.2-2.1V10a4.8 4.8 0 0 0-9.6 0v3.9L6 16z"/><path d="M10 16a2 2 0 0 0 4 0"/>',
        'card' => '<rect x="3.5" y="6" width="17" height="12" rx="2"/><path d="M3.5 10h17"/>',
        'bank' => '<path d="M4 10h16L12 5 4 10z"/><path d="M6 10v6M10 10v6M14 10v6M18 10v6M4 18h16"/>',
        'print' => '<path d="M7 8V4h10v4"/><rect x="5" y="8" width="14" height="8" rx="1.5"/><path d="M8 13h8v7H8z"/>',
        'receipt' => '<path d="M7 3.5h7l4.5 4.5V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M14 3.5V8h4.5"/><path d="M9 13h6M9 16.5h4"/>',
        'users' => '<circle cx="9" cy="9" r="2.4"/><circle cx="15.5" cy="9.5" r="2"/><path d="M4.8 17.2c.6-2.2 2.3-3.4 4.2-3.4s3.6 1.2 4.2 3.4"/><path d="M13 13.8c1.4-.3 2.8.2 3.6 1.6"/>',
    ];
    $path = $paths[$name] ?? $paths['grid'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

$sections = [
    'Overview' => [
        ['demo', 'star', 'Overview', 'demo'],
        ['dashboard', 'grid', 'Dashboard', ''],
    ],
    'Temple' => [
        ['inventory', 'box', 'Inventory', 'inventory'],
        ['food', 'leaf', 'Food stock', 'food'],
        ['vastra', 'cloth', 'Deity vastra', 'vastra'],
    ],
    'Finance' => [
        ['donations', 'hands', 'Donations', 'donations'],
        ['receipts', 'receipt', 'Receipts', 'receipts'],
        ['subscriptions', 'bell', 'Subscriptions', 'subscriptions'],
        ['expenses', 'card', 'Expenses', 'expenses'],
        ['bank', 'bank', 'Bank reconciliation', 'bank'],
        ['reports', 'print', 'Reports', 'reports'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> — <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=3">
</head>
<body>
  <div class="app-shell">
    <input type="checkbox" id="nav-toggle" class="nav-toggle">
    <aside class="sidebar no-print">
      <div class="brand">
        <div class="brand-mark"><img src="<?= e(asset('logo.svg')) ?>" alt="" class="brand-logo"></div>
        <h1><?= e(APP_NAME) ?></h1>
        <p>Administration</p>
      </div>
      <?php foreach ($sections as $section => $links): ?>
        <div class="nav-label"><?= e($section) ?></div>
        <?php foreach ($links as [$key, $glyph, $label, $route]): ?>
          <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($route)) ?>"><?= $icon($glyph) ?> <?= e($label) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if ($role === 'Admin'): ?>
        <div class="nav-label">Administration</div>
        <a class="nav-link<?= $active === 'users' ? ' active' : '' ?>" href="<?= e(url('users')) ?>"><?= $icon('users') ?> Users</a>
      <?php endif; ?>
      <div class="sidebar-footer">Signed in as<br><strong><?= e($fullName) ?></strong><br><?= e($role) ?></div>
    </aside>

    <div class="main">
      <div class="topbar no-print">
        <div style="display:flex; align-items:center; min-width:0;">
          <label for="nav-toggle" class="nav-toggle-btn">Menu</label>
          <div>
            <h2><?= e($pageTitle) ?></h2>
            <span class="topbar-kicker"><?= e(APP_PLACE) ?></span>
          </div>
        </div>
        <div class="user-chip">
          <div class="avatar"><?= e($initial) ?></div>
          <span class="user-name"><?= e($fullName) ?><span class="user-role"><?= e($role) ?></span></span>
          <a class="logout-link" href="<?= e(url('logout')) ?>">Sign out</a>
        </div>
      </div>
      <div class="content">
        <?php foreach ($flashes as [$category, $message]): ?>
          <div class="flash flash-<?= e($category) ?> no-print"><?= e($message) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
      </div>
    </div>
  </div>
  <script>
    document.querySelectorAll('.nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        var toggle = document.getElementById('nav-toggle');
        if (toggle) toggle.checked = false;
      });
    });
  </script>
</body>
</html>
