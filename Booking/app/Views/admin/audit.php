<h1>Audit log</h1>
<form class="panel filters" method="get" action="<?= e(url('/admin/audit')) ?>">
  <label class="grow">Search <input name="q" value="<?= e($q) ?>" placeholder="Admin name, action or booking number"></label>
  <div class="filter-actions"><button class="btn btn-primary" type="submit">Search</button></div>
</form>
<div class="panel table-wrap">
<table class="table compact">
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Booking / item</th><th>Details</th><th>IP</th></tr></thead>
  <tbody><?php foreach ($rows as $a): ?>
    <tr><td class="nowrap"><?= e(fmt_datetime($a['created_at'])) ?></td><td><?= e($a['actor']) ?></td><td><?= e($a['action']) ?></td>
      <td><?php if ($a['booking_no']): ?><a href="<?= e(url('/admin/bookings/' . $a['booking_no'])) ?>"><?= e($a['booking_no']) ?></a><?php else: ?><?= e($a['entity'] . ($a['entity_id'] ? ' ' . $a['entity_id'] : '')) ?><?php endif; ?></td>
      <td><small class="pre"><?= e(\SJT\Service\AuditFormat::details($a['details_json'])) ?></small></td><td><small><?= e((string) $a['ip']) ?></small></td></tr>
  <?php endforeach; ?></tbody>
</table>
<p class="muted small">Showing the latest 500 entries. Entries cannot be edited or deleted from the dashboard.</p>
</div>
