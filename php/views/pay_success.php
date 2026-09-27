<?php /** @var array<string,mixed> $inv */ /** @var string $ref */ ?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e(t('pay.success_title')) ?> — <?= e(app_display_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=14">
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
      <h1 style="color:var(--green); font-size:20px; margin:0 0 6px;"><?= e(t('pay.success_title')) ?></h1>
      <p style="color:var(--ink-soft); font-size:13.5px; margin:0 0 20px;"><?= e(t('pay.thanks', ['name' => (string) $inv['name']])) ?></p>
      <div style="text-align:left; background:var(--bg); border-radius:10px; padding:14px 16px; margin-bottom:18px;">
        <div class="pay-detail-row"><span><?= e(t('pay.amount_paid')) ?></span><span><strong><?= e(money($inv['amount'])) ?></strong></span></div>
        <div class="pay-detail-row"><span><?= e(t('pay.invoice')) ?></span><span><?= e($inv['invoice_number']) ?></span></div>
        <div class="pay-detail-row"><span><?= e(t('pay.reference')) ?></span><span><?= e($ref) ?></span></div>
        <?php $hasReceipt = (int) ($inv['receipt_generated'] ?? 0) === 1 && (int) ($inv['receipt_cancelled'] ?? 0) !== 1; ?>
        <div class="pay-detail-row"<?= $hasReceipt ? '' : ' style="border-bottom:none;"' ?>><span><?= e(t('common.plan')) ?></span><span><?= e($inv['plan_name']) ?></span></div>
        <?php if ($hasReceipt): ?>
        <div class="pay-detail-row" style="border-bottom:none;"><span><?= e(t('common.receipt')) ?></span><span><?= e((string) $inv['receipt_number']) ?></span></div>
        <?php endif; ?>
      </div>
      <?php $receiptUrl = $hasReceipt ? receipt_public_url((string) ($inv['receipt_share_token'] ?? '')) : ''; ?>
      <?php if ($receiptUrl !== ''): ?>
      <p style="margin:0 0 14px;"><a class="btn btn-gold" href="<?= e($receiptUrl) ?>"><?= e(t('pay.view_receipt')) ?></a></p>
      <?php endif; ?>
      <p style="color:var(--ink-soft); font-size:12px;"><?= e(t('pay.office')) ?></p>
    </div>
  </div>
</body>
</html>
