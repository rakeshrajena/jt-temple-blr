<?php /** @var array $rows @var string $status @var array $counts */ ?>
<div class="row-between">
  <h1>WhatsApp notifications</h1>
  <form method="post" action="<?= e(url('/admin/notifications/retry-all')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Retry all failed</button></form>
</div>
<p class="muted">"Sent" means WhatsApp accepted the message. "Delivered"/"Read" appear when Meta confirms delivery via the webhook. Failed messages are retried automatically when the error is temporary; for anything else, contact the devotee directly and mark it handled.</p>
<nav class="tabs">
  <?php foreach (['failed' => 'Failed', 'queued' => 'Queued', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'resolved' => 'Handled', 'all' => 'All'] as $k => $l): ?>
    <a href="<?= e(url('/admin/notifications', ['status' => $k])) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?><?= $k !== 'all' && !empty($counts[$k]) ? ' (' . $counts[$k] . ')' : '' ?></a>
  <?php endforeach; ?>
</nav>
<div class="panel table-wrap">
<?php if (!$rows): ?><p class="muted">Nothing here.</p><?php else: ?>
<table class="table compact">
  <thead><tr><th>When</th><th>Booking</th><th>To</th><th>Message</th><th>Status</th><th>Error</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $n): ?>
    <tr>
      <td><?= e(fmt_datetime($n['created_at'])) ?></td>
      <td><?php if ($n['booking_no']): ?><a href="<?= e(url('/admin/bookings/' . $n['booking_no'])) ?>"><?= e($n['booking_no']) ?></a><br><small><?= e($n['devotee_name']) ?></small><?php endif; ?></td>
      <td><?= e($n['audience']) ?><br><small><?= e($n['recipient'] === '-' ? '—' : fmt_mobile($n['recipient'])) ?></small></td>
      <td><?= e(str_replace('_', ' ', $n['event'])) ?><br><small class="muted"><?= e((string) $n['template']) ?></small></td>
      <td><?= badge('n', $n['status']) ?><?php if ($n['attempts']): ?><br><small>attempts: <?= (int) $n['attempts'] ?></small><?php endif; ?></td>
      <td><small><?= e((string) $n['error']) ?></small><?php if ($n['next_attempt_at'] && in_array($n['status'], ['failed', 'queued'], true)): ?><br><small class="muted">Next retry <?= e(fmt_datetime($n['next_attempt_at'])) ?></small><?php endif; ?></td>
      <td class="nowrap">
        <?php if (in_array($n['status'], ['failed', 'queued'], true)): ?>
          <?php if ($n['template']): ?><form method="post" action="<?= e(url('/admin/notifications/' . $n['id'] . '/retry')) ?>" class="form-inline"><?= csrf_field() ?><button class="btn btn-small" type="submit">Retry</button></form><?php endif; ?>
          <form method="post" action="<?= e(url('/admin/notifications/' . $n['id'] . '/resolve')) ?>" class="form-inline"><?= csrf_field() ?><input name="note" placeholder="e.g. called devotee" class="small-input"><button class="btn btn-small" type="submit">Mark handled</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?></tbody>
</table>
<?php endif; ?>
</div>
