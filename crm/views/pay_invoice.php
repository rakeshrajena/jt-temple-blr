<?php /** @var array<string,mixed> $inv */ /** @var bool $gateway */ /** @var string $csrf */ ?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e(t('pay.title')) ?> — <?= e(app_display_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=48">
</head>
<body>
  <div class="pay-wrap">
    <div class="pay-card">
      <img src="<?= e(app_logo_url()) ?>" alt="<?= e(app_display_name()) ?>" class="pay-logo">
      <h1 class="pay-title"><?= e(app_display_name()) ?></h1>
      <p class="pay-subtitle"><?= e(app_place()) ?></p>
      <div class="pay-amount"><?= e(money($inv['amount'])) ?></div>
      <div class="pay-label"><?= e($inv['plan_name']) ?> — <?= e($inv['period_label']) ?></div>
      <div class="pay-detail-row"><span><?= e(t('pay.invoice')) ?></span><span><?= e($inv['invoice_number']) ?></span></div>
      <div class="pay-detail-row"><span><?= e(t('pay.devotee')) ?></span><span><?= e($inv['name']) ?></span></div>
      <div class="pay-detail-row"><span><?= e(t('pay.due_date')) ?></span><span><?= e($inv['due_date']) ?></span></div>
      <div class="pay-detail-row"><span><?= e(t('common.status')) ?></span><span><span class="badge <?= $inv['status'] === 'Overdue' ? 'badge-red' : ($inv['status'] === 'Paid' ? 'badge-green' : 'badge-amber') ?>"><?= e(t_fixed('status', (string) $inv['status'])) ?></span></span></div>
      <?php if ($inv['status'] === 'Paid'): ?>
        <p class="pay-paid-note"><?= e(t('pay.already', ['ref' => (string) $inv['payment_reference']])) ?></p>
      <?php elseif ($gateway): ?>
        <p id="sub-pay-error" class="pay-error" hidden></p>
        <button class="btn btn-primary pay-btn-full" type="button" id="sub-pay-btn"><?= e(t('pay.now', ['amount' => money($inv['amount'])])) ?></button>
        <button class="btn btn-secondary pay-sync" type="button" id="sub-pay-sync" hidden><?= e(t('pay.sync', ['default' => 'Money deducted but page stuck? Tap here to finish'])) ?></button>
        <p class="pay-footnote"><?= e(t('pay.gateway_note', ['default' => 'Pay securely with UPI, card, or net banking. Your receipt will be emailed after payment.'])) ?></p>
      <?php else: ?>
      <form method="POST" action="<?= e(url('pay/' . $inv['payment_token'] . '/confirm')) ?>">
        <?= csrf_field() ?>
        <div class="pay-method">
          <label class="pay-method-box"><input type="radio" name="payment_method" value="UPI" checked><span>📱 UPI</span></label>
          <label class="pay-method-box"><input type="radio" name="payment_method" value="Card"><span>💳 Card</span></label>
          <label class="pay-method-box"><input type="radio" name="payment_method" value="Netbanking"><span>🏦 Netbanking</span></label>
        </div>
        <button class="btn btn-primary pay-btn-full" type="submit"><?= e(t('pay.now', ['amount' => money($inv['amount'])])) ?></button>
      </form>
      <p class="pay-footnote"><?= e(t('pay.demo')) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <?php app_busy_overlay(); ?>
  <?php if ($inv['status'] !== 'Paid' && !empty($gateway)): ?>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script>
    window.SJT_SUBSCRIPTION_PAY = {
      token: <?= json_encode((string) $inv['payment_token'], JSON_THROW_ON_ERROR) ?>,
      csrf: <?= json_encode($csrf, JSON_THROW_ON_ERROR) ?>,
      orderUrl: <?= json_encode(url('pay/' . $inv['payment_token'] . '/gateway/order'), JSON_THROW_ON_ERROR) ?>,
      verifyUrl: <?= json_encode(url('pay/' . $inv['payment_token'] . '/gateway/verify'), JSON_THROW_ON_ERROR) ?>,
      syncUrl: <?= json_encode(url('pay/' . $inv['payment_token'] . '/gateway/sync'), JSON_THROW_ON_ERROR) ?>
    };
  </script>
  <script src="<?= e(asset('js/pay-gateway.js')) ?>?v=1"></script>
  <?php endif; ?>
</body>
</html>
