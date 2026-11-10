<?php
/** @var array<string, mixed>|null $gift */
$cancelled = is_array($gift) && (int) ($gift['receipt_cancelled'] ?? 0) === 1;
$amount = is_array($gift) ? $gift['amount'] : null;
$amountText = $amount === null || $amount === '' ? 'In-kind gift' : money($amount, 2);
$dateText = '';
if (is_array($gift)) {
    $stamp = strtotime((string) $gift['donation_date']);
    $dateText = $stamp === false ? (string) $gift['donation_date'] : date('j M Y', $stamp);
}
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex">
  <title><?= e(is_array($gift) ? (string) $gift['receipt_number'] : 'Gift') ?> — <?= e(app_display_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=48">
</head>
<body>
  <div class="gift-wrap">
    <div class="gift-card">
      <img src="<?= e(app_logo_url()) ?>" alt="" class="gift-logo">
      <h1 class="gift-title"><?= e(app_display_name()) ?></h1>
      <p class="gift-subtitle"><?= e(app_place()) ?></p>
      <?php if (!is_array($gift)): ?>
        <h2 class="gift-empty-title">This gift could not be found</h2>
        <p class="gift-empty-body sub">The link does not match a receipt. Ask the temple office if you need a copy.</p>
      <?php else: ?>
        <div class="gift-amount"><?= e($amountText) ?></div>
        <p style="text-align:center; margin:0 0 14px;"><?php if ($cancelled): ?><span class="badge badge-red">Cancelled</span><?php else: ?><span class="badge badge-green">Recorded</span><?php endif; ?></p>
        <div class="gift-row"><span>Receipt</span><span><?= e((string) $gift['receipt_number']) ?></span></div>
        <div class="gift-row"><span>Date</span><span><?= e($dateText) ?></span></div>
        <div class="gift-row"><span>Devotee</span><span><?= e((string) $gift['donor_name']) ?></span></div>
        <div class="gift-row"><span>Purpose</span><span><?= e((string) (($gift['purpose'] ?? '') !== '' ? $gift['purpose'] : 'General')) ?></span></div>
        <div class="gift-row"><span>Type</span><span><?= e((string) $gift['donation_type']) ?></span></div>
        <div class="gift-row"><span>Payment</span><span><?= e((string) $gift['payment_mode']) ?></span></div>
        <p class="gift-note">This page confirms the gift. It is not the receipt PDF.</p>
        <p class="gift-note"><?= e(receipt_public_thanks()) ?> 🙏</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
