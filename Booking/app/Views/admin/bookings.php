<?php
/** @var array $f @var array $result @var array $services */
$sel = fn (string $k, string $v) => ($f[$k] ?? '') === $v ? ' selected' : '';
$q = array_filter($f, fn ($v) => $v !== '');
?>
<div class="row-between">
  <h1>Bookings</h1>
  <a class="btn" href="<?= e(url('/admin/bookings/export.csv', $q)) ?>">Download CSV (<?= (int) $result['total'] ?>)</a>
</div>
<form class="panel filters" method="get" action="<?= e(url('/admin/bookings')) ?>">
  <label class="grow">Search <input name="q" value="<?= e($f['q']) ?>" placeholder="Booking no, name, mobile, UTR, invoice no"></label>
  <label>Service <select name="service_id"><option value="">All</option><?php foreach ($services as $s): ?><option value="<?= $s['id'] ?>"<?= $sel('service_id', (string) $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
  <label>Booking status <select name="booking_status"><option value="">All</option><?php foreach (\SJT\Service\Status::BOOKING as $k => $l): ?><option value="<?= $k ?>"<?= $sel('booking_status', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label>Payment <select name="payment_status"><option value="">All</option><?php foreach (\SJT\Service\Status::PAYMENT as $k => $l): ?><option value="<?= $k ?>"<?= $sel('payment_status', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label>Approval <select name="approval_status"><option value="">All</option><?php foreach (\SJT\Service\Status::APPROVAL as $k => $l): ?><option value="<?= $k ?>"<?= $sel('approval_status', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label>Service date from <input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></label>
  <label>to <input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></label>
  <label>Booked from <input type="date" name="created_from" value="<?= e($f['created_from']) ?>"></label>
  <label>to <input type="date" name="created_to" value="<?= e($f['created_to']) ?>"></label>
  <label class="inline"><input type="checkbox" name="has_food" value="1"<?= $f['has_food'] === '1' ? ' checked' : '' ?>> With food coupons</label>
  <div class="filter-actions"><button class="btn btn-primary" type="submit">Filter</button> <a href="<?= e(url('/admin/bookings')) ?>">Clear</a></div>
</form>

<div class="panel table-wrap">
<?php if (!$result['rows']): ?>
  <p class="muted">No bookings match these filters.</p>
<?php else: ?>
<table class="table">
  <thead><tr><th>Booking</th><th>Service date</th><th>Devotee</th><th>Services</th><th class="num">Total</th><th>Booking</th><th>Payment</th><th>Approval</th></tr></thead>
  <tbody>
  <?php foreach ($result['rows'] as $b): ?>
    <tr>
      <td><a href="<?= e(url('/admin/bookings/' . $b['booking_no'])) ?>"><strong><?= e($b['booking_no']) ?></strong></a><br><small class="muted"><?= e(fmt_datetime($b['created_at'])) ?></small></td>
      <td><?= e(date('D j M Y', strtotime($b['service_date']))) ?></td>
      <td><?= e($b['devotee_name']) ?><br><small class="muted"><?= e(fmt_mobile($b['mobile'])) ?></small></td>
      <td><?= e($b['services']) ?></td>
      <td class="num"><?= e(money((int) $b['total_paise'])) ?></td>
      <td><?= badge('booking', $b['booking_status']) ?></td>
      <td><?= badge('payment', $b['payment_status']) ?><?php if ($b['payment_ref']): ?><br><small class="muted"><?= e($b['payment_ref']) ?></small><?php endif; ?></td>
      <td><?= $b['approval_status'] === 'not_required' ? '<span class="muted">—</span>' : badge('approval', $b['approval_status']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if ($result['pages'] > 1): ?>
<nav class="pager" aria-label="Pages">
  <?php if ($result['page'] > 1): ?><a href="<?= e(url('/admin/bookings', $q + ['page' => $result['page'] - 1])) ?>">← Previous</a><?php endif; ?>
  <span>Page <?= $result['page'] ?> of <?= $result['pages'] ?></span>
  <?php if ($result['page'] < $result['pages']): ?><a href="<?= e(url('/admin/bookings', $q + ['page' => $result['page'] + 1])) ?>">Next →</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
</div>
