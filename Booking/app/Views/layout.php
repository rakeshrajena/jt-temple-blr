<?php
/** @var string $content */
$templeName = setting('temple_name', 'Shree Jagannatha Temple Bengaluru');
$phone = setting('temple_phone');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ? $title . ' · ' : '') . $templeName) ?></title>
<meta name="description" content="Book pujas, ceremonies and prasadam coupons at <?= e($templeName) ?>.">
<meta name="theme-color" content="#7a1d1d">
<meta name="robots" content="<?= str_starts_with(\SJT\Core\App::request()->path, '/b/') ? 'noindex, nofollow' : 'index, follow' ?>">
<link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= e(url('/')) ?>">
      <img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="44" height="44">
      <span class="brand-text"><span class="brand-name"><?= e($templeName) ?></span><span class="brand-sub">Online Seva &amp; Prasadam Booking</span></span>
    </a>
    <nav class="site-nav" aria-label="Main">
      <a href="<?= e(url('/book')) ?>">Book</a>
      <a href="<?= e(url('/lookup')) ?>">Find my booking</a>
    </nav>
  </div>
</header>
<main id="main" class="wrap" tabindex="-1">
<?php foreach ($flashes ?? [] as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
<?php endforeach; ?>
<?= $content ?>
</main>
<footer class="site-footer">
  <div class="wrap">
    <p class="jai">॥ Jai Jagannath ॥</p>
    <p><?= e($templeName) ?><?php if (setting('temple_address')): ?> · <?= e(setting('temple_address')) ?><?php endif; ?></p>
    <?php if ($phone || setting('temple_email')): ?>
    <p>Temple office: <?php if ($phone): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a><?php endif; ?><?php if ($phone && setting('temple_email')): ?> · <?php endif; ?><?php if (setting('temple_email')): ?><a href="mailto:<?= e(setting('temple_email')) ?>"><?= e(setting('temple_email')) ?></a><?php endif; ?></p>
    <?php endif; ?>
    <p><a href="<?= e(setting('temple_website', 'https://sjtbengaluru.org')) ?>">Temple website</a></p>
  </div>
</footer>
</body>
</html>
