<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e(t('pay.invalid_title')) ?> — <?= e(app_display_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=14">
  <style>
    .pay-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(160deg, var(--maroon-deep), var(--maroon)); padding: 20px; }
    .pay-card { background: #fff; border-radius: 14px; padding: 36px 28px; width: 380px; max-width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.25); text-align: center; }
  </style>
</head>
<body>
  <div class="pay-wrap">
    <div class="pay-card">
      <h1 style="color:var(--red); font-size:19px; margin:0 0 6px;"><?= e(t('pay.invalid_title')) ?></h1>
      <p style="color:var(--ink-soft); font-size:13.5px;"><?= e(t('pay.invalid_body')) ?></p>
    </div>
  </div>
</body>
</html>
