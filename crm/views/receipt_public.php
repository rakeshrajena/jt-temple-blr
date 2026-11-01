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
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=25">
  <style>
    .gift-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(160deg, var(--maroon-deep), var(--maroon)); padding: 20px; }
    .gift-card { background: #fff; border-radius: 14px; padding: 28px 24px; width: 420px; max-width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.25); }
    .gift-logo { width: 56px; height: 56px; display: block; margin: 0 auto 10px; object-fit: contain; }
    .gift-amount { text-align: center; font-size: 32px; font-weight: 800; color: var(--maroon-deep); margin: 12px 0 4px; }
    .gift-row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
    .gift-row span:first-child { color: var(--ink-soft); }
    .gift-row span:last-child { text-align: right; }
    .gift-note { text-align: center; margin: 16px 0 0; font-size: 13.5px; line-height: 1.45; color: var(--ink-soft); }
    .gift-note + .gift-note { margin-top: 8px; }
  </style>
</head>
<body>
  <div class="gift-wrap">
    <div class="gift-card">
      <img src="<?= e(app_logo_url()) ?>" alt="" class="gift-logo">
      <h1 style="text-align:center; color:var(--maroon-deep); font-size:18px; margin:0;"><?= e(app_display_name()) ?></h1>
      <p style="text-align:center; color:var(--ink-soft); font-size:12px; margin:2px 0 8px;"><?= e(app_place()) ?></p>
      <?php if (!is_array($gift)): ?>
        <h2 style="text-align:center; font-size:18px;">This gift could not be found</h2>
        <p class="sub" style="text-align:center;">The link does not match a receipt. Ask the temple office if you need a copy.</p>
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
