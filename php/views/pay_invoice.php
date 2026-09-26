<?php /** @var array<string,mixed> $inv */ ?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e(t('pay.title')) ?> — <?= e(app_display_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=14">
  <style>
    .pay-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(160deg, var(--maroon-deep), var(--maroon)); padding: 20px; }
    .pay-card { background: #fff; border-radius: 14px; padding: 32px 28px; width: 380px; max-width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.25); }
    .pay-logo { width: 56px; height: 56px; display: block; margin: 0 auto 10px; object-fit: contain; }
    .pay-amount { text-align: center; font-size: 34px; font-weight: 800; color: var(--maroon-deep); margin: 14px 0 2px; }
    .pay-label { text-align: center; color: var(--ink-soft); font-size: 13px; margin-bottom: 18px; }
    .pay-detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13.5px; }
    .pay-detail-row span:first-child { color: var(--ink-soft); }
    .pay-method { display: flex; gap: 8px; margin: 16px 0; }
    .pay-method label { flex: 1; border: 1.5px solid var(--border); border-radius: 8px; padding: 10px 6px; text-align: center; font-size: 12.5px; cursor: pointer; }
    .pay-method input { display: none; }
    .pay-method input:checked + span { color: var(--maroon); font-weight: 700; }
    .pay-method-box:has(input:checked) { border-color: var(--gold); background: var(--amber-bg); }
  </style>
</head>
<body>
  <div class="pay-wrap">
    <div class="pay-card">
      <img src="<?= e(app_logo_url()) ?>" alt="<?= e(app_display_name()) ?>" class="pay-logo">
      <h1 style="text-align:center; color:var(--maroon-deep); font-size:18px; margin:0;"><?= e(app_display_name()) ?></h1>
      <p style="text-align:center; color:var(--ink-soft); font-size:12px; margin:2px 0 16px;"><?= e(APP_PLACE) ?></p>
      <div class="pay-amount"><?= e(money($inv['amount'])) ?></div>
      <div class="pay-label"><?= e($inv['plan_name']) ?> — <?= e($inv['period_label']) ?></div>
      <div class="pay-detail-row"><span><?= e(t('pay.invoice')) ?></span><span><?= e($inv['invoice_number']) ?></span></div>
      <div class="pay-detail-row"><span><?= e(t('pay.devotee')) ?></span><span><?= e($inv['name']) ?></span></div>
      <div class="pay-detail-row"><span><?= e(t('pay.due_date')) ?></span><span><?= e($inv['due_date']) ?></span></div>
      <div class="pay-detail-row"><span><?= e(t('common.status')) ?></span><span><span class="badge <?= $inv['status'] === 'Overdue' ? 'badge-red' : ($inv['status'] === 'Paid' ? 'badge-green' : 'badge-amber') ?>"><?= e(t_fixed('status', (string) $inv['status'])) ?></span></span></div>
      <?php if ($inv['status'] === 'Paid'): ?>
        <p style="text-align:center; color:var(--green); font-size:13.5px; margin-top:16px;"><?= e(t('pay.already', ['ref' => (string) $inv['payment_reference']])) ?></p>
      <?php else: ?>
      <form method="POST" action="<?= e(url('pay/' . $inv['payment_token'] . '/confirm')) ?>">
        <?= csrf_field() ?>
        <div class="pay-method">
          <label class="pay-method-box"><input type="radio" name="payment_method" value="UPI" checked><span>📱 UPI</span></label>
          <label class="pay-method-box"><input type="radio" name="payment_method" value="Card"><span>💳 Card</span></label>
          <label class="pay-method-box"><input type="radio" name="payment_method" value="Netbanking"><span>🏦 Netbanking</span></label>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%; justify-content:center; padding:12px;"><?= e(t('pay.now', ['amount' => money($inv['amount'])])) ?></button>
      </form>
      <p style="text-align:center; color:var(--ink-soft); font-size:11px; margin-top:14px;">
        <?= e(t('pay.demo')) ?>
      </p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
