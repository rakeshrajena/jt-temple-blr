<?php /** @var array<string,mixed> $inv */ /** @var string $ref */ ?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e(t('pay.success_title')) ?> — <?= e(app_display_name()) ?></title>
  <?php app_favicon_link(); ?>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=48">
</head>
<body>
  <div class="pay-wrap">
    <div class="pay-card pay-card--center">
      <div class="success-icon">✓</div>
      <h1 class="pay-success-title"><?= e(t('pay.success_title')) ?></h1>
      <p class="pay-success-lead"><?= e(t('pay.thanks', ['name' => (string) $inv['name']])) ?></p>
      <div class="pay-details-box">
        <div class="pay-detail-row"><span><?= e(t('pay.amount_paid')) ?></span><span><strong><?= e(money($inv['amount'])) ?></strong></span></div>
        <div class="pay-detail-row"><span><?= e(t('pay.invoice')) ?></span><span><?= e($inv['invoice_number']) ?></span></div>
        <div class="pay-detail-row"><span><?= e(t('pay.reference')) ?></span><span><?= e($ref) ?></span></div>
        <?php $hasReceipt = (int) ($inv['receipt_generated'] ?? 0) === 1 && (int) ($inv['receipt_cancelled'] ?? 0) !== 1; ?>
        <div class="pay-detail-row<?= $hasReceipt ? '' : ' is-last' ?>"><span><?= e(t('common.plan')) ?></span><span><?= e($inv['plan_name']) ?></span></div>
        <?php if ($hasReceipt): ?>
        <div class="pay-detail-row is-last"><span><?= e(t('common.receipt')) ?></span><span><?= e((string) $inv['receipt_number']) ?></span></div>
        <?php endif; ?>
      </div>
      <?php $receiptUrl = $hasReceipt ? receipt_public_url((string) ($inv['receipt_share_token'] ?? '')) : ''; ?>
      <?php if ($receiptUrl !== ''): ?>
      <p class="pay-receipt-link"><a class="btn btn-gold" href="<?= e($receiptUrl) ?>"><?= e(t('pay.view_receipt')) ?></a></p>
      <?php endif; ?>
      <p class="pay-footnote pay-footnote--sm"><?= e(t('pay.office')) ?></p>
    </div>
  </div>
</body>
</html>
