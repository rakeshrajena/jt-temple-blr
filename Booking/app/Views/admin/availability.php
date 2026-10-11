<?php /** @var array $blocked @var array $services @var string $date @var array $day */
$wd = ['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '7' => 'Sun'];
?>
<h1>Availability</h1>
<div class="cols">
<section class="panel">
  <h2>Close dates for booking</h2>
  <p class="muted small">Use for festivals, temple closures or priest unavailability. Devotees cannot pick these dates. Existing bookings are not changed.</p>
  <form method="post" action="<?= e(url('/admin/availability/block')) ?>" class="form">
    <?= csrf_field() ?>
    <label>From <input type="date" name="from" required></label>
    <label>To <span class="muted">(same as From for one day)</span><input type="date" name="to"></label>
    <label>Applies to <select name="service_id"><option value="">All services (including food)</option><?php foreach ($services as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
    <label>Reason shown to devotees <input name="reason" maxlength="255" placeholder="e.g. Rath Yatra festival arrangements"></label>
    <button class="btn btn-primary" type="submit">Close these dates</button>
  </form>
</section>
<section class="panel">
  <h2>Closed dates</h2>
  <?php if (!$blocked): ?><p class="muted">No upcoming closed dates.</p><?php else: ?>
  <table class="table compact"><thead><tr><th>Date</th><th>Service</th><th>Reason</th><th></th></tr></thead><tbody>
  <?php foreach ($blocked as $bd): ?>
    <tr><td class="nowrap"><?= e(fmt_date($bd['block_date'])) ?></td><td><?= e($bd['service_name'] ?? 'All services') ?></td><td><?= e((string) $bd['reason']) ?></td>
    <td><form method="post" action="<?= e(url('/admin/availability/unblock')) ?>" class="form-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $bd['id'] ?>"><button class="btn btn-small" type="submit">Reopen</button></form></td></tr>
  <?php endforeach; ?></tbody></table>
  <?php endif; ?>
</section>
</div>

<section class="panel">
  <div class="row-between">
    <h2>Slot usage on <?= e(fmt_date($date)) ?></h2>
    <form method="get" action="<?= e(url('/admin/availability')) ?>" class="form-inline"><input type="date" name="date" value="<?= e($date) ?>"><button class="btn btn-small" type="submit">Show</button></form>
  </div>
  <table class="table compact">
    <thead><tr><th>Service</th><th>Time slot</th><th class="num">Booked</th><th class="num">Capacity</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($day as $d): $s = $d['service']; ?>
      <?php if (!$d['slots']): ?>
        <tr><td><?= e($s['name']) ?></td><td class="muted">No slots</td><td class="num"><?= $d['used'] ?></td><td class="num"><?= $s['daily_capacity'] ?? '∞' ?></td><td><?= $d['blocked'] ? '<span class="badge badge-bad">Closed: ' . e($d['blocked']) . '</span>' : '' ?></td></tr>
      <?php endif; ?>
      <?php foreach ($d['slots'] as $i => $sl): ?>
        <tr><td><?= $i === 0 ? e($s['name']) : '' ?></td><td><?= e($sl['label']) ?></td><td class="num"><?= $sl['used'] ?></td><td class="num"><?= $sl['capacity'] ?? '∞' ?></td>
          <td><?php if ($d['blocked']): ?><span class="badge badge-bad">Closed: <?= e($d['blocked']) ?></span><?php elseif (!$sl['offered']): ?><span class="badge badge-info">Not offered on <?= e($wd[date('N', strtotime($date))]) ?></span><?php elseif ($sl['capacity'] !== null && $sl['used'] >= $sl['capacity']): ?><span class="badge badge-warn">Full</span><?php else: ?><span class="badge badge-ok">Open</span><?php endif; ?></td></tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted small">Time slots, capacities and weekdays are edited on each service's page (Services &amp; prices).</p>
</section>
