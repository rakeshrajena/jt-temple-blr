<?php
/** @var array $b */
$tone = fn (string $s) => match ($s) {
    'confirmed', 'paid', 'approved', 'completed', 'not_required' => 'ok',
    'awaiting_verification', 'pending' => 'warn',
    'failed', 'rejected', 'cancelled' => 'bad',
    default => 'info',
};
?>
<dl class="status-grid">
  <div><dt>Booking</dt><dd><span class="badge badge-<?= $tone($b['booking_status']) ?>"><?= e(status_label('booking', $b['booking_status'])) ?></span></dd></div>
  <div><dt>Payment</dt><dd><span class="badge badge-<?= $tone($b['payment_status']) ?>"><?= e(status_label('payment', $b['payment_status'])) ?></span></dd></div>
  <?php if ($b['approval_status'] !== 'not_required'): ?>
  <div><dt>Temple approval</dt><dd><span class="badge badge-<?= $tone($b['approval_status']) ?>"><?= e(status_label('approval', $b['approval_status'])) ?></span></dd></div>
  <?php endif; ?>
</dl>
