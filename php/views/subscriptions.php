<?php
/** @var list<array<string,mixed>> $subs */
/** @var list<array<string,mixed>> $invoices */
/** @var list<string> $planPresets */
/** @var float $mrr */
/** @var float $pendingAmount */
?>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($mrr)) ?></div><div class="label">Monthly Recurring Revenue (Active)</div></div>
  <div class="kpi-card warn"><div class="value"><?= e(money($pendingAmount)) ?></div><div class="label">Pending / Overdue Amount</div></div>
  <div class="kpi-card"><div class="value"><?= count($subs) ?></div><div class="label">Total Subscribers</div></div>
</div>
<div class="panel" style="border-left: 4px solid var(--amber);">
  <h3 style="color:var(--amber);">ℹ️ How this works</h3>
  <p style="color:var(--ink-soft); font-size:13px; margin:0;">
    <strong>Generate Invoice</strong> creates a billing record. <strong>Send</strong> texts the devotee a secure payment link (simulated in this demo —
    the message is written to <code>storage/logs/notifications.log</code>). The devotee opens the link, pays, and the invoice
    auto-updates to <strong>Paid</strong> here — with the payment mirrored into Donations and available in every report.
  </p>
</div>
<div class="panel-row">
  <div class="panel">
    <h3>Add Subscriber</h3>
    <form method="POST" action="<?= e(url('subscriptions')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
        <div class="form-group"><label>Mobile Number</label><input type="text" name="mobile" placeholder="9XXXXXXXXX" required></div>
        <div class="form-group"><label>Email (optional)</label><input type="email" name="email"></div>
        <div class="form-group">
          <label>Plan</label>
          <select name="plan_name">
            <?php foreach ($planPresets as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Amount (₹)</label><input type="number" step="0.01" name="plan_amount" required></div>
        <div class="form-group">
          <label>Billing Cycle</label>
          <select name="frequency"><option>Monthly</option><option>Quarterly</option><option>Yearly</option></select>
        </div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Add Subscriber</button></div>
    </form>
  </div>
  <div class="panel">
    <h3>Subscribers (<?= count($subs) ?>)</h3>
    <table class="data-table">
      <tr><th>Name</th><th>Plan</th><th>Amount</th><th>Status</th><th>Due</th><th></th></tr>
      <?php foreach ($subs as $s): ?>
      <tr>
        <td><?= e($s['name']) ?><br><span style="color:var(--ink-soft); font-size:12px;"><?= e($s['mobile']) ?></span></td>
        <td><?= e($s['plan_name']) ?></td>
        <td><?= e(money($s['plan_amount'])) ?> / <?= e($s['frequency']) ?></td>
        <td>
          <?php if ($s['status'] === 'Active'): ?><span class="badge badge-green">Active</span>
          <?php elseif ($s['status'] === 'Paused'): ?><span class="badge badge-amber">Paused</span>
          <?php else: ?><span class="badge badge-grey">Cancelled</span><?php endif; ?>
        </td>
        <td><?php if ((int) $s['due_count'] > 0): ?><span class="badge badge-amber"><?= e((string) $s['due_count']) ?> due</span><?php else: ?><span class="badge badge-green">Up to date</span><?php endif; ?></td>
        <td>
          <?php if ($s['status'] === 'Active'): ?>
          <form method="POST" action="<?= e(url('subscriptions/' . $s['id'] . '/generate_invoice')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline" type="submit">+ Invoice</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<div class="panel">
  <h3>All Invoices (<?= count($invoices) ?>)</h3>
  <form method="POST" action="<?= e(url('subscriptions/bulk_send')) ?>" id="bulkSendForm">
    <?= csrf_field() ?>
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
      <button class="btn btn-gold btn-sm" type="submit" id="bulkSendBtn" disabled>📲 Send Selected (<span id="selCount">0</span>)</button>
      <span style="color:var(--ink-soft); font-size:12.5px;">Select invoices below, or use the header checkbox to select all unpaid invoices.</span>
    </div>
    <table class="data-table">
      <tr>
        <th style="width:36px;"><input type="checkbox" id="selectAll"></th>
        <th>Invoice #</th><th>Subscriber</th><th>Contact</th><th>Period</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Payment Link</th><th></th>
      </tr>
      <?php foreach ($invoices as $i): ?>
      <tr>
        <td>
          <?php if ($i['status'] !== 'Paid'): ?>
          <input type="checkbox" name="invoice_ids[]" value="<?= e((string) $i['id']) ?>" class="invoice-checkbox">
          <?php endif; ?>
        </td>
        <td><?= e($i['invoice_number']) ?></td>
        <td><?= e($i['subscriber_name']) ?></td>
        <td style="font-size:12px; color:var(--ink-soft);"><?= e($i['mobile']) ?><?php if (!empty($i['email'])): ?><br><?= e($i['email']) ?><?php endif; ?></td>
        <td><?= e($i['period_label']) ?></td>
        <td><?= e(money($i['amount'])) ?></td>
        <td><?= e($i['due_date']) ?></td>
        <td>
          <?php if ($i['status'] === 'Paid'): ?><span class="badge badge-green">Paid</span>
          <?php elseif ($i['status'] === 'Sent'): ?><span class="badge badge-blue">Sent</span>
          <?php elseif ($i['status'] === 'Overdue'): ?><span class="badge badge-red">Overdue</span>
          <?php else: ?><span class="badge badge-amber">Pending</span><?php endif; ?>
        </td>
        <td>
          <?php if ($i['status'] !== 'Paid'): ?>
          <a href="<?= e(url('pay/' . $i['payment_token'])) ?>" target="_blank" class="btn btn-sm btn-outline">Preview Pay Page</a>
          <?php else: ?>
          <span style="color:var(--ink-soft); font-size:12px;">Ref: <?= e($i['payment_reference']) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (in_array($i['status'], ['Pending', 'Overdue'], true)): ?>
          <button class="btn btn-sm btn-gold" type="submit" form="singleSend<?= e((string) $i['id']) ?>">📲 Send</button>
          <?php elseif ($i['status'] === 'Sent'): ?>
          <button class="btn btn-sm btn-outline" type="submit" form="singleSend<?= e((string) $i['id']) ?>">Resend</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>
  <?php foreach ($invoices as $i): ?>
    <?php if ($i['status'] !== 'Paid'): ?>
    <form method="POST" action="<?= e(url('subscriptions/invoice/' . $i['id'] . '/send')) ?>" id="singleSend<?= e((string) $i['id']) ?>"><?= csrf_field() ?></form>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<script>
  const selectAll = document.getElementById('selectAll');
  const checkboxes = () => Array.from(document.querySelectorAll('.invoice-checkbox'));
  const bulkBtn = document.getElementById('bulkSendBtn');
  const selCount = document.getElementById('selCount');
  function updateBulkButton() {
    const checked = checkboxes().filter(c => c.checked).length;
    selCount.textContent = checked;
    bulkBtn.disabled = checked === 0;
  }
  if (selectAll) {
    selectAll.addEventListener('change', () => {
      checkboxes().forEach(c => { c.checked = selectAll.checked; });
      updateBulkButton();
    });
    checkboxes().forEach(c => c.addEventListener('change', updateBulkButton));
    updateBulkButton();
  }
</script>
