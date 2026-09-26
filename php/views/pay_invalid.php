<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invalid Link — <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=2">
  <style>
    .pay-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(160deg, var(--maroon-deep), var(--maroon)); padding: 20px; }
    .pay-card { background: #fff; border-radius: 14px; padding: 36px 28px; width: 380px; max-width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.25); text-align: center; }
  </style>
</head>
<body>
  <div class="pay-wrap">
    <div class="pay-card">
      <div style="font-size:40px;">⚠️</div>
      <h1 style="color:var(--red); font-size:19px; margin:10px 0 6px;">Link Not Valid</h1>
      <p style="color:var(--ink-soft); font-size:13.5px;">This payment link has already been used, expired, or doesn't exist. Please contact the temple office if you believe this is an error.</p>
    </div>
  </div>
</body>
</html>
