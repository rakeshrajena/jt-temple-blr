<?php
/** @var list<array{0:string,1:string}> $flashes */
/** @var string $next */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in — <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=3">
</head>
<body>
  <div class="login-split">
    <section class="login-brand">
      <div>
        <img src="<?= e(asset('logo.svg')) ?>" alt="" width="56" height="56">
        <p class="place"><?= e(APP_PLACE) ?></p>
        <h1><?= e(APP_NAME) ?></h1>
        <p class="lede">One register for donations, food stock, deity vastra, expenses, bank reconciliation, and monthly seva subscriptions.</p>
        <ul class="login-points">
          <li>Receipts and food coupons print as PDF</li>
          <li>Bank statements match donations and expenses</li>
          <li>Subscription invoices include a devotee payment link</li>
        </ul>
      </div>
      <p class="place">Temple administration</p>
    </section>
    <section class="login-panel">
      <div class="login-card">
        <h2>Sign in</h2>
        <p class="sub">Use your staff or admin account.</p>
        <?php foreach ($flashes as [$category, $message]): ?>
          <div class="flash flash-<?= e($category) ?>"><?= e($message) ?></div>
        <?php endforeach; ?>
        <form method="POST" action="<?= e(url('login', $next !== '' ? ['next' => $next] : [])) ?>">
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" required autofocus autocomplete="username">
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
          </div>
          <button class="btn btn-primary" type="submit">Sign in</button>
        </form>
        <div class="demo-creds">
          <strong>Demo accounts</strong><br>
          admin / temple@123 — Admin<br>
          ramesh / ramesh@123 — Admin<br>
          staff1 / staff@123 — Staff
        </div>
      </div>
    </section>
  </div>
</body>
</html>
