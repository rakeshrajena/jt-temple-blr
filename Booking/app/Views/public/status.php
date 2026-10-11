<?php
/** Booking acknowledgement / status. @var array $b @var array $items @var array $headline */
[$tone, $head, $explain] = $headline;
$canPay = in_array($b['payment_status'], ['pending', 'failed'], true) && in_array($b['booking_status'], ['pending', 'confirmed'], true) && (int) $b['total_paise'] > 0;
$step = $b['booking_status'] === 'confirmed' || $b['payment_status'] === 'awaiting_verification' ? 6 : 5;
include __DIR__ . '/../partials/progress.php';
?>
<?php if ($ack === 'payment'): ?>
  <div class="alert alert-ok" role="status"><strong>Thank you.</strong> Your payment reference has been submitted. The temple office will verify it and you will be informed on WhatsApp.</div>
<?php elseif ($ack === 'booking'): ?>
  <div class="alert alert-ok" role="status"><strong>Your booking request has been received.</strong> Please note your booking number below.</div>
<?php endif; ?>

<section class="card status-card status-<?= e($tone) ?>">
  <p class="booking-no-label">Booking number</p>
  <p class="booking-no" data-copy-text="<?= e($b['booking_no']) ?>"><?= e($b['booking_no']) ?></p>
  <button type="button" class="btn btn-small btn-secondary" data-copy="<?= e($b['booking_no']) ?>">Copy booking number</button>
  <h1 class="status-title"><?= e($head) ?></h1>
  <p class="lead"><?= e($explain) ?></p>
  <?php include __DIR__ . '/../partials/status_badges.php'; ?>
  <?php if ($b['payment_ref']): ?><p class="hint">Payment reference: <strong><?= e($b['payment_ref']) ?></strong></p><?php endif; ?>
  <div class="actions">
    <?php if ($canPay): ?><a class="btn btn-primary btn-lg" href="<?= e(url('/b/' . $b['booking_no'] . '/pay')) ?>">Pay <?= e(money((int) $b['total_paise'])) ?> now</a><?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('/b/' . $b['booking_no'] . '/invoice')) ?>">View invoice</a>
    <a class="btn btn-secondary" href="<?= e(url('/b/' . $b['booking_no'] . '/invoice.pdf', ['download' => 1])) ?>">Download PDF</a>
  </div>
</section>

<section class="card">
  <h2>Booking details</h2>
  <dl class="kv">
    <dt>Devotee</dt><dd><?= e($b['devotee_name']) ?></dd>
    <dt>Mobile</dt><dd><?= e(fmt_mobile($b['mobile'])) ?></dd>
    <dt>Date</dt><dd><?= e(fmt_date($b['service_date'])) ?></dd>
    <dt>Booked on</dt><dd><?= e(fmt_datetime($b['created_at'])) ?></dd>
    <dt>Invoice no.</dt><dd><?= e($b['invoice_no']) ?></dd>
  </dl>
  <?php $sub = $b['subtotal_paise']; $extra = $b['additional_paise']; $extraLabel = $b['additional_label']; $total = $b['total_paise'];
  include __DIR__ . '/../partials/items_table.php'; ?>
</section>

<section class="card subtle">
  <h2 class="h3">Keep this link</h2>
  <p>Save your booking number. You can open this page again any time from <a href="<?= e(url('/lookup')) ?>">Find my booking</a> using your booking number and mobile number.</p>
  <?php if ($temple['phone']): ?><p>Questions? Call the temple office on <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $temple['phone'])) ?>"><?= e($temple['phone']) ?></a> and quote your booking number.</p><?php endif; ?>
</section>
