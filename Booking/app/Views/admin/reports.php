<?php /** @var string $from @var string $to @var string $group @var array $report */
$qs = ['from' => $from, 'to' => $to, 'group' => $group];
$tot = ['bookings' => 0, 'value' => 0, 'pending_value' => 0, 'revenue' => 0, 'refunded' => 0, 'food_persons' => 0];
foreach ($report['rows'] as $r) { foreach ($tot as $k => $_) { $tot[$k] += $r[$k]; } }
?>
<h1>Reports</h1>
<form class="panel filters" method="get" action="<?= e(url('/admin/reports')) ?>">
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <label>Group by <select name="group"><?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $k => $l): ?><option value="<?= $k ?>"<?= $group === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
  <div class="filter-actions"><button class="btn btn-primary" type="submit">Show</button></div>
  <div class="quick">
    <a href="<?= e(url('/admin/reports', ['from' => today(), 'to' => today(), 'group' => 'day'])) ?>">Today</a>
    <a href="<?= e(url('/admin/reports', ['from' => date('Y-m-d', strtotime('monday this week')), 'to' => today(), 'group' => 'day'])) ?>">This week</a>
    <a href="<?= e(url('/admin/reports', ['from' => date('Y-m-01'), 'to' => today(), 'group' => 'day'])) ?>">This month</a>
    <a href="<?= e(url('/admin/reports', ['from' => date('Y-m-d', strtotime('-12 weeks monday')), 'to' => today(), 'group' => 'week'])) ?>">Last 12 weeks</a>
    <a href="<?= e(url('/admin/reports', ['from' => date('Y-m-01', strtotime('-11 months')), 'to' => today(), 'group' => 'month'])) ?>">Last 12 months</a>
  </div>
</form>

<div class="actions-inline">
  <a class="btn" href="<?= e(url('/admin/reports/export.csv', $qs + ['type' => 'summary'])) ?>">Summary CSV</a>
  <a class="btn" href="<?= e(url('/admin/reports/export.csv', $qs + ['type' => 'items'])) ?>">Line items CSV</a>
  <a class="btn" href="<?= e(url('/admin/reports/export.csv', $qs + ['type' => 'payments'])) ?>">Payments CSV</a>
  <a class="btn" href="<?= e(url('/admin/bookings/export.csv', ['created_from' => $from, 'created_to' => $to])) ?>">Bookings CSV</a>
</div>

<section class="panel table-wrap">
  <h2><?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?></h2>
  <table class="table">
    <thead><tr><th><?= ucfirst($group) ?></th><th class="num">Bookings</th><th class="num">Booked value</th><th class="num">Awaiting payment</th><th class="num">Verified revenue</th><th class="num">Refunded</th><th class="num">Food persons</th></tr></thead>
    <tbody>
    <?php if (!$report['rows']): ?><tr><td colspan="7" class="muted">No activity in this period.</td></tr><?php endif; ?>
    <?php foreach ($report['rows'] as $period => $r): ?>
      <tr><th scope="row"><?= e($period) ?></th><td class="num"><?= $r['bookings'] ?></td><td class="num"><?= e(money($r['value'])) ?></td><td class="num"><?= e(money($r['pending_value'])) ?></td><td class="num"><?= e(money($r['revenue'])) ?></td><td class="num"><?= e(money($r['refunded'])) ?></td><td class="num"><?= $r['food_persons'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th>Total</th><td class="num"><?= $tot['bookings'] ?></td><td class="num"><?= e(money($tot['value'])) ?></td><td class="num"><?= e(money($tot['pending_value'])) ?></td><td class="num"><?= e(money($tot['revenue'])) ?></td><td class="num"><?= e(money($tot['refunded'])) ?></td><td class="num"><?= $tot['food_persons'] ?></td></tr></tfoot>
  </table>
  <p class="muted small">Net verified revenue for the period: <strong><?= e(money($tot['revenue'] - $tot['refunded'])) ?></strong>.</p>
</section>

<div class="cols">
<section class="panel">
  <h2>By service</h2>
  <table class="table compact"><thead><tr><th>Service</th><th class="num">Bookings</th><th class="num">Value</th></tr></thead><tbody>
  <?php foreach ($report['services'] as $n => $s): ?><tr><td><?= e($n) ?></td><td class="num"><?= $s['count'] ?></td><td class="num"><?= e(money($s['amount'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$report['services']): ?><tr><td colspan="3" class="muted">—</td></tr><?php endif; ?>
  </tbody></table>
</section>
<section class="panel">
  <h2>Food coupons</h2>
  <table class="table compact"><thead><tr><th>Coupon</th><th class="num">Persons</th><th class="num">Value</th></tr></thead><tbody>
  <?php foreach ($report['coupons'] as $n => $c): ?><tr><td><?= e($n) ?></td><td class="num"><?= $c['persons'] ?></td><td class="num"><?= e(money($c['amount'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$report['coupons']): ?><tr><td colspan="3" class="muted">—</td></tr><?php endif; ?>
  </tbody></table>
</section>
</div>
