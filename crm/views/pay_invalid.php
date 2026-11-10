<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e(t('pay.invalid_title')) ?> — <?= e(app_display_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=48">
</head>
<body>
  <div class="pay-wrap">
    <div class="pay-card pay-card--center">
      <h1 class="pay-invalid-title"><?= e(t('pay.invalid_title')) ?></h1>
      <p class="pay-invalid-body"><?= e(t('pay.invalid_body')) ?></p>
    </div>
  </div>
</body>
</html>
