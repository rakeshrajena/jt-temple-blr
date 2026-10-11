<h1>Dashboard</h1>
<?php if ($setup): ?>
<section class="panel setup">
  <h2>Before going live</h2>
  <ul><?php foreach ($setup as [$msg, $link]): ?><li><?= e($msg) ?> <a href="<?= e(url($link)) ?>">Fix →</a></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

<div class="tiles">
  <a class="tile tile-warn" href="<?= e(url('/admin/bookings', ['payment_status' => 'awaiting_verification'])) ?>"><span class="n"><?= $counts['verify'] ?></span><span>Payments to verify</span></a>
  <a class="tile tile-warn" href="<?= e(url('/admin/bookings', ['approval_status' => 'pending', 'booking_status' => 'pending'])) ?>"><span class="n"><?= $counts['approve'] ?></span><span>Awaiting approval</span></a>
  <a class="tile" href="<?= e(url('/admin/bookings', ['date_from' => today(), 'date_to' => today()])) ?>"><span class="n"><?= $counts['today'] ?></span><span>Services today</span></a>
  <a class="tile" href="<?= e(url('/admin/bookings', ['created_from' => today()])) ?>"><span class="n"><?= $counts['new_today'] ?></span><span>New bookings today</span></a>
  <a class="tile <?= $counts['failed_notifications'] ? 'tile-bad' : '' ?>" href="<?= e(url('/admin/notifications')) ?>"><span class="n"><?= $counts['failed_notifications'] ?></span><span>Failed WhatsApp messages</span></a>
</div>

<section class="panel">
  <div class="row-between"><h2>Summary</h2><a href="<?= e(url('/admin/reports')) ?>">Full reports →</a></div>
  <table class="table">
    <thead><tr><th>Period</th><th class="num">Bookings</th><th class="num">Booked value</th><th class="num">Awaiting payment</th><th class="num">Verified revenue</th><th class="num">Food coupon persons</th></tr></thead>
    <tbody>
    <?php foreach ($summary as $label => $t): ?>
      <tr><th scope="row"><?= e($label) ?></th><td class="num"><?= $t['bookings'] ?></td><td class="num"><?= e(money($t['value'])) ?></td><td class="num"><?= e(money($t['pending_value'])) ?></td><td class="num"><strong><?= e(money($t['revenue'] - $t['refunded'])) ?></strong></td><td class="num"><?= $t['food_persons'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted small">Booked value counts bookings made in the period (excluding cancelled / not approved). Verified revenue counts payments verified in the period, minus refunds.</p>
</section>

<div class="cols">
<section class="panel">
  <h2>Next 7 days</h2>
  <?php if (!$upcoming): ?><p class="muted">No open bookings in the next 7 days.</p><?php else: ?>
  <table class="table compact">
    <thead><tr><th>Date</th><th>Booking</th><th>Devotee</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($upcoming as $b): ?>
      <tr><td><?= e(date('D j M', strtotime($b['service_date']))) ?></td><td><a href="<?= e(url('/admin/bookings/' . $b['booking_no'])) ?>"><?= e($b['booking_no']) ?></a></td><td><?= e($b['devotee_name']) ?></td><td><?= badge('booking', $b['booking_status']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php endif; ?>
</section>
<section class="panel">
  <h2>Latest bookings</h2>
  <?php if (!$recent): ?><p class="muted">No bookings yet.</p><?php else: ?>
  <table class="table compact">
    <thead><tr><th>Booking</th><th>Devotee</th><th class="num">Total</th><th>Payment</th></tr></thead>
    <tbody><?php foreach ($recent as $b): ?>
      <tr><td><a href="<?= e(url('/admin/bookings/' . $b['booking_no'])) ?>"><?= e($b['booking_no']) ?></a><br><small class="muted"><?= e($b['services']) ?></small></td><td><?= e($b['devotee_name']) ?></td><td class="num"><?= e(money((int) $b['total_paise'])) ?></td><td><?= badge('payment', $b['payment_status']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php endif; ?>
</section>
</div>
