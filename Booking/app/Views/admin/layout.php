<?php
/** Admin layout. @var ?array $admin */
$path = \SJT\Core\App::request()->path;
$nav = [
    '/admin' => 'Dashboard',
    '/admin/bookings' => 'Bookings',
    '/admin/availability' => 'Availability',
    '/admin/notifications' => 'Notifications',
    '/admin/reports' => 'Reports',
];
$superNav = ['/admin/services' => 'Services & prices', '/admin/coupons' => 'Food coupons', '/admin/settings' => 'Settings', '/admin/users' => 'Admins', '/admin/audit' => 'Audit log'];
$isActive = fn (string $p) => $p === '/admin' ? $path === '/admin' : str_starts_with($path, $p);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? 'Admin') . ' · Temple booking admin') ?></title>
<link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="admin">
<a class="skip-link" href="#main">Skip to main content</a>
<header class="a-header">
  <a class="a-brand" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="32" height="32"> Temple Booking Admin</a>
  <?php if (!empty($admin)): ?>
  <div class="a-user">
    <span><?= e($admin['name']) ?> <small>(<?= $admin['role'] === 'super' ? 'senior admin' : 'staff' ?>)</small></span>
    <a href="<?= e(url('/admin/password')) ?>">Password</a>
    <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button type="submit" class="linklike">Sign out</button></form>
  </div>
  <?php endif; ?>
</header>
<?php if (!empty($admin)): ?>
<nav class="a-nav" aria-label="Admin">
  <?php foreach ($nav as $p => $l): ?><a href="<?= e(url($p)) ?>"<?= $isActive($p) ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
  <?php if ($admin['role'] === 'super'): foreach ($superNav as $p => $l): ?><a href="<?= e(url($p)) ?>"<?= $isActive($p) ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; endif; ?>
  <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener" class="a-public">Public site ↗</a>
</nav>
<?php endif; ?>
<main id="main" class="a-main" tabindex="-1">
<?php foreach ($flashes ?? [] as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
<?php endforeach; ?>
<?= $content ?>
</main>
</body>
</html>
