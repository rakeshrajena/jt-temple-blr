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
        'ticket' => '<path d="M4 8.2A1.8 1.8 0 0 1 5.8 6.4h12.4A1.8 1.8 0 0 1 20 8.2v1.6a1.7 1.7 0 0 0 0 3.4v1.6a1.8 1.8 0 0 1-1.8 1.8H5.8A1.8 1.8 0 0 1 4 14.8v-1.6a1.7 1.7 0 0 0 0-3.4V8.2z"/><path d="M12 7.2v9.6"/>',
        'hands' => '<path d="M8 11V6.8a1.3 1.3 0 0 1 2.6 0V11"/><path d="M10.6 10.2V5.8a1.3 1.3 0 0 1 2.6 0v5"/><path d="M13.2 10.6V7.2a1.3 1.3 0 0 1 2.6 0V13c0 3.2-1.8 6-5.2 6-2.8 0-4.6-1.6-4.6-4.2V9.2a1.3 1.3 0 0 1 2.6 0V11"/>',
        'bell' => '<path d="M6 16h12l-1.2-2.1V10a4.8 4.8 0 0 0-9.6 0v3.9L6 16z"/><path d="M10 16a2 2 0 0 0 4 0"/>',
        'card' => '<rect x="3.5" y="6" width="17" height="12" rx="2"/><path d="M3.5 10h17"/>',
        'book' => '<path d="M6 4.5h9.5A2.5 2.5 0 0 1 18 7v12.5H8.5A2.5 2.5 0 0 0 6 22V4.5z"/><path d="M6 4.5A2.5 2.5 0 0 1 8.5 7H18"/>',
        'ledger' => '<path d="M8 6h11M8 12h11M8 18h11"/><circle cx="4.5" cy="6" r="0.8" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="0.8" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="0.8" fill="currentColor" stroke="none"/>',
        'bank' => '<path d="M4 10h16L12 5 4 10z"/><path d="M6 10v6M10 10v6M14 10v6M18 10v6M4 18h16"/>',
        'print' => '<path d="M7 8V4h10v4"/><rect x="5" y="8" width="14" height="8" rx="1.5"/><path d="M8 13h8v7H8z"/>',
        'receipt' => '<path d="M7 3.5h7l4.5 4.5V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M14 3.5V8h4.5"/><path d="M9 13h6M9 16.5h4"/>',
        'users' => '<circle cx="9" cy="9" r="2.4"/><circle cx="15.5" cy="9.5" r="2"/><path d="M4.8 17.2c.6-2.2 2.3-3.4 4.2-3.4s3.6 1.2 4.2 3.4"/><path d="M13 13.8c1.4-.3 2.8.2 3.6 1.6"/>',
        'person' => '<circle cx="12" cy="8" r="3"/><path d="M6.2 18.6c.9-2.8 3-4.2 5.8-4.2s4.9 1.4 5.8 4.2"/>',
        'mail' => '<rect x="3.5" y="6" width="17" height="12" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.2M12 18.3v2.2M3.5 12h2.2M18.3 12h2.2M6 6l1.6 1.6M16.4 16.4 18 18M18 6l-1.6 1.6M7.6 16.4 6 18"/>',
        'globe' => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.2 2.4 3.3 5.2 3.3 8s-1.1 5.6-3.3 8c-2.2-2.4-3.3-5.2-3.3-8s1.1-5.6 3.3-8z"/>',
    ];
    $path = $paths[$name] ?? $paths['grid'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

$sections = [
    t('nav.section.overview') => [
        ['demo', 'star', t('nav.overview'), 'demo'],
        ['dashboard', 'grid', t('nav.dashboard'), ''],
    ],
    t('nav.section.temple') => [
        ['inventory', 'box', t('nav.inventory'), 'inventory'],
        ['food', 'leaf', t('nav.food'), 'food'],
        ['coupons', 'ticket', t('nav.coupons'), 'food/coupons'],
        ['vastra', 'cloth', t('nav.vastra'), 'vastra'],
    ],
    t('nav.section.finance') => [
        ['donations', 'hands', t('nav.donations'), 'donations'],
        ['receipts', 'receipt', t('nav.receipts'), 'receipts'],
        ['subscriptions', 'bell', t('nav.subscriptions'), 'subscriptions'],
        ['expenses', 'card', t('nav.expenses'), 'expenses'],
        ['approvals', 'users', t('nav.approvals'), 'approvals'],
        ['corrections', 'ledger', t('nav.corrections'), 'corrections'],
        ['cash-book', 'book', t('nav.cash_book'), 'cash-book'],
        ['day-book', 'book', t('nav.day_book'), 'day-book'],
        ['ledger', 'ledger', t('nav.ledger'), 'ledger'],
        ['bank', 'bank', t('nav.bank'), 'bank'],
        ['reports', 'print', t('nav.reports'), 'reports'],
    ],
];
$returnTo = (string) ($_SERVER['REQUEST_URI'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?> — <?= e(app_display_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=39">
</head>
<body>
  <div class="app-shell">
    <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
    <label for="nav-toggle" class="nav-scrim" aria-hidden="true"></label>
    <aside class="sidebar no-print">
      <div class="brand">
        <div class="brand-mark"><img src="<?= e(app_logo_url()) ?>" alt="" class="brand-logo"></div>
        <h1><?= e(app_display_name()) ?></h1>
        <p><?= e(t('shell.brand')) ?></p>
      </div>
      <?php foreach ($sections as $section => $links): ?>
        <div class="nav-label"><?= e($section) ?></div>
        <?php foreach ($links as [$key, $glyph, $label, $route]): ?>
          <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($route)) ?>"><?= $icon($glyph) ?> <?= e($label) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <div class="nav-label"><?= e(t('nav.section.administration')) ?></div>
      <a class="nav-link<?= $active === 'donors' ? ' active' : '' ?>" href="<?= e(url('donors')) ?>"><?= $icon('users') ?> <?= e(t('nav.donors')) ?></a>
      <a class="nav-link<?= $active === 'invitations' ? ' active' : '' ?>" href="<?= e(url('invitations')) ?>"><?= $icon('mail') ?> <?= e(t('nav.invitations')) ?></a>
      <a class="nav-link<?= $active === 'contributors' ? ' active' : '' ?>" href="<?= e(url('contributors')) ?>"><?= $icon('person') ?> <?= e(t('nav.contributors')) ?></a>
      <?php if ($role === 'Admin'): ?>
        <a class="nav-link<?= $active === 'users' ? ' active' : '' ?>" href="<?= e(url('users')) ?>"><?= $icon('users') ?> <?= e(t('nav.users')) ?></a>
        <a class="nav-link<?= $active === 'settings' ? ' active' : '' ?>" href="<?= e(url('settings')) ?>"><?= $icon('gear') ?> <?= e(t('nav.settings')) ?></a>
        <a class="nav-link<?= $active === 'localization' ? ' active' : '' ?>" href="<?= e(url('localization')) ?>"><?= $icon('globe') ?> <?= e(t('nav.localization')) ?></a>
      <?php endif; ?>
      <div class="sidebar-footer"><?= e(t('shell.signed_in')) ?><br><strong><?= e($fullName) ?></strong><br><?= e(t_fixed('role', $role)) ?></div>
    </aside>

    <div class="main">
      <div class="topbar no-print">
        <div class="topbar-title">
          <label for="nav-toggle" class="nav-toggle-btn"><?= e(t('shell.menu')) ?></label>
          <div>
            <h2><?= e($pageTitle) ?></h2>
            <span class="topbar-kicker"><?= e(app_place()) ?></span>
          </div>
        </div>
        <div class="user-chip">
          <div class="avatar"><?= e($initial) ?></div>
          <span class="user-name"><?= e($fullName) ?><span class="user-role"><?= e(t_fixed('role', $role)) ?></span></span>
          <form method="POST" action="<?= e(url('language')) ?>" class="locale-switch">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($returnTo) ?>">
            <label class="sr-only" for="locale-code"><?= e(t('locale.language')) ?></label>
            <select id="locale-code" name="code" onchange="this.form.requestSubmit()">
              <?php foreach (language_catalog() as $language): ?>
                <option value="<?= e($language['code']) ?>"<?= current_locale() === $language['code'] ? ' selected' : '' ?>><?= e($language['native']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <a class="logout-link" href="<?= e(url('account/password')) ?>"><?= e(t('nav.password')) ?></a>
          <a class="logout-link" href="<?= e(url('logout')) ?>"><?= e(t('shell.sign_out')) ?></a>
        </div>
      </div>
      <div class="content">
        <?php foreach ($flashes as [$category, $message]): ?>
          <div class="flash flash-<?= e($category) ?> no-print"><?= e($message) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
      </div>
      <p class="app-copy no-print"><?= e(t('shell.copyright', ['name' => app_display_name(), 'year' => date('Y')])) ?></p>
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
  <script src="<?= e(asset('js/suggest.js')) ?>?v=3"></script>
  <script src="<?= e(asset('js/reveal.js')) ?>?v=1"></script>
  <?php app_busy_overlay(); ?>
</body>
</html>
