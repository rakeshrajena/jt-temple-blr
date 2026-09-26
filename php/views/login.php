<?php
/** @var list<array{0:string,1:string}> $flashes */
/** @var string $next */
$returnTo = (string) ($_SERVER['REQUEST_URI'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('page.login')) ?> — <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=9">
</head>
<body>
  <div class="login-split">
    <section class="login-brand">
      <div>
        <img src="<?= e(asset('logo.svg')) ?>" alt="" width="56" height="56">
        <p class="place"><?= e(APP_PLACE) ?></p>
        <h1><?= e(APP_NAME) ?></h1>
        <p class="lede"><?= e(t('login.lede')) ?></p>
        <ul class="login-points">
          <li><?= e(t('login.point_receipts')) ?></li>
          <li><?= e(t('login.point_bank')) ?></li>
          <li><?= e(t('login.point_subscription')) ?></li>
        </ul>
      </div>
      <p class="place"><?= e(t('login.place')) ?></p>
    </section>
    <section class="login-panel">
      <div class="login-card">
        <form method="POST" action="<?= e(url('language')) ?>" class="locale-switch login-locale">
          <?= csrf_field() ?>
          <input type="hidden" name="next" value="<?= e($returnTo) ?>">
          <label class="sr-only" for="login-locale"><?= e(t('locale.language')) ?></label>
          <select id="login-locale" name="code" onchange="this.form.submit()">
            <?php foreach (language_catalog() as $language): ?>
              <option value="<?= e($language['code']) ?>"<?= current_locale() === $language['code'] ? ' selected' : '' ?>><?= e($language['native']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <h2><?= e(t('login.heading')) ?></h2>
        <p class="sub"><?= e(t('login.sub')) ?></p>
        <?php foreach ($flashes as [$category, $message]): ?>
          <div class="flash flash-<?= e($category) ?>"><?= e($message) ?></div>
        <?php endforeach; ?>
        <form method="POST" action="<?= e(url('login', $next !== '' ? ['next' => $next] : [])) ?>">
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="username"><?= e(t('login.username')) ?></label>
            <input id="username" type="text" name="username" required autofocus autocomplete="username">
          </div>
          <div class="form-group">
            <label for="password"><?= e(t('login.password')) ?></label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
          </div>
          <button class="btn btn-primary" type="submit"><?= e(t('login.submit')) ?></button>
        </form>
        <div class="demo-creds">
          <strong><?= e(t('login.demo')) ?></strong><br>
          admin / temple@123 — <?= e(t_fixed('role', 'Admin')) ?><br>
          ramesh / ramesh@123 — <?= e(t_fixed('role', 'Admin')) ?><br>
          treasurer / treasurer@123 — <?= e(t_fixed('role', 'Treasurer')) ?><br>
          staff1 / staff@123 — <?= e(t_fixed('role', 'Staff')) ?>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
