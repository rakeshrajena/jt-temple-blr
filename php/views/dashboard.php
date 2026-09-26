<?php
/** @var array $summary */
/** @var list<array<string,mixed>> $recentDonations */
/** @var list<array<string,mixed>> $recentExpenses */
/** @var list<array<string,mixed>> $lowStockItems */
?>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($summary['total_donations'])) ?></div><div class="label">Total Donations Received</div></div>
  <div class="kpi-card danger"><div class="value"><?= e(money($summary['total_expenses'])) ?></div><div class="label">Total Expenses</div></div>
  <div class="kpi-card <?= $summary['net_balance'] >= 0 ? 'good' : 'danger' ?>"><div class="value"><?= e(money($summary['net_balance'])) ?></div><div class="label">Net Balance</div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $summary['donor_count']) ?></div><div class="label">Unique Donors</div></div>
  <div class="kpi-card<?= $summary['pending_receipts'] ? ' warn' : '' ?>"><div class="value"><?= e((string) $summary['pending_receipts']) ?></div><div class="label">Receipts Pending</div></div>
  <div class="kpi-card <?= $summary['low_stock'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['low_stock']) ?></div><div class="label">Food Items Low on Stock</div></div>
  <div class="kpi-card <?= $summary['unmatched_txns'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['unmatched_txns']) ?></div><div class="label">Bank Txns Needing Review</div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $summary['inventory_count']) ?></div><div class="label">Inventory Items Tracked</div></div>
  <div class="kpi-card good"><div class="value"><?= e(money($summary['mrr'])) ?></div><div class="label">Monthly Recurring Revenue</div></div>
  <div class="kpi-card <?= $summary['pending_invoices'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['pending_invoices']) ?></div><div class="label">Subscription Invoices Due</div></div>
</div>

<div class="panel-row">
  <div class="panel">
    <h3>Recent Donations</h3>
    <?php if ($recentDonations): ?>
    <table class="data-table">
      <tr><th>Donor</th><th>Type</th><th>Amount</th><th>Date</th><th>Receipt</th></tr>
      <?php foreach ($recentDonations as $d): ?>
      <tr>
        <td><?= e($d['donor_name']) ?></td>
        <td><?= e($d['donation_type']) ?></td>
        <td><?= e(money_or_dash($d['amount'])) ?></td>
        <td><?= e($d['donation_date']) ?></td>
        <td>
          <?php if ((int) $d['receipt_generated'] === 1): ?>
            <?= receipt_link($d['receipt_number']) ?>
          <?php else: ?>
            <span class="badge badge-amber">Pending</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="empty-state">No donations recorded yet.</div>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h3>Low Stock Alerts</h3>
    <?php if ($lowStockItems): ?>
    <table class="data-table">
      <tr><th>Item</th><th>Stock</th><th>Threshold</th></tr>
      <?php foreach ($lowStockItems as $f): ?>
      <tr>
        <td><?= e($f['name']) ?></td>
        <td><span class="badge badge-red"><?= e($f['current_stock']) ?> <?= e($f['unit']) ?></span></td>
        <td><?= e($f['minimum_threshold']) ?> <?= e($f['unit']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="empty-state">All food stock levels are healthy.</div>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <h3>Recent Expenses</h3>
  <?php if ($recentExpenses): ?>
  <table class="data-table">
    <tr><th>Category</th><th>Description</th><th>Paid To</th><th>Amount</th><th>Date</th></tr>
    <?php foreach ($recentExpenses as $row): ?>
    <tr>
      <td><?= e($row['category']) ?></td>
      <td><?= e(dash($row['description'])) ?></td>
      <td><?= e(dash($row['paid_to'])) ?></td>
      <td><?= e(money($row['amount'])) ?></td>
      <td><?= e($row['expense_date']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No expenses recorded yet.</div>
  <?php endif; ?>
</div>
