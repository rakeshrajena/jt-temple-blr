<?php /** @var array<string,mixed> $inv */ /** @var string $ref */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Successful — <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=2">
  <style>
    .pay-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(160deg, var(--maroon-deep), var(--maroon)); padding: 20px; }
    .pay-card { background: #fff; border-radius: 14px; padding: 36px 28px; width: 380px; max-width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.25); text-align: center; }
    .success-icon { width: 64px; height: 64px; border-radius: 50%; background: var(--green-bg); color: var(--green); font-size: 34px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
    .pay-detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13.5px; }
    .pay-detail-row span:first-child { color: var(--ink-soft); }
  </style>
</head>
<body>
  <div class="pay-wrap">
    <div class="pay-card">
      <div class="success-icon">✓</div>
      <h1 style="color:var(--green); font-size:20px; margin:0 0 6px;">Payment Successful</h1>
      <p style="color:var(--ink-soft); font-size:13.5px; margin:0 0 20px;">Thank you, <?= e($inv['name']) ?> — your seva contribution has been received.</p>
      <div style="text-align:left; background:var(--bg); border-radius:10px; padding:14px 16px; margin-bottom:18px;">
        <div class="pay-detail-row"><span>Amount Paid</span><span><strong><?= e(money($inv['amount'])) ?></strong></span></div>
        <div class="pay-detail-row"><span>Invoice No.</span><span><?= e($inv['invoice_number']) ?></span></div>
        <div class="pay-detail-row"><span>Reference No.</span><span><?= e($ref) ?></span></div>
        <div class="pay-detail-row" style="border-bottom:none;"><span>Plan</span><span><?= e($inv['plan_name']) ?></span></div>
      </div>
      <p style="color:var(--ink-soft); font-size:12px;">A receipt can be generated from the temple office on request. This transaction is now recorded in Donations.</p>
    </div>
  </div>
</body>
</html>
