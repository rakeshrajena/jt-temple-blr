<?php
/** @var array $summary */
/** @var list<array<string,mixed>> $recentDonations */
/** @var list<array<string,mixed>> $recentInvoices */
/** @var list<array<string,mixed>> $inventorySample */
/** @var list<array<string,mixed>> $foodSample */
/** @var list<array<string,mixed>> $vastraSample */
/** @var list<array<string,mixed>> $expenseSample */
/** @var list<array<string,mixed>> $bankSample */
/** @var string $generatedOn */
?>
<p class="overview-meta">Live snapshot of <?= e(APP_NAME) ?>, <?= e(APP_PLACE) ?>. Generated <?= e($generatedOn) ?>.</p>

<h3 class="overview-title">At a glance</h3>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($summary['total_donations'])) ?></div><div class="label">Total Donations Received</div></div>
  <div class="kpi-card danger"><div class="value"><?= e(money($summary['total_expenses'])) ?></div><div class="label">Total Expenses</div></div>
  <div class="kpi-card <?= $summary['net_balance'] >= 0 ? 'good' : 'danger' ?>"><div class="value"><?= e(money($summary['net_balance'])) ?></div><div class="label">Net Balance</div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $summary['donor_count']) ?></div><div class="label">Unique Donors</div></div>
  <div class="kpi-card good"><div class="value"><?= e(money($summary['mrr'])) ?></div><div class="label">Monthly Recurring Revenue</div></div>
  <div class="kpi-card <?= $summary['pending_invoices'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['pending_invoices']) ?></div><div class="label">Subscription Invoices Due</div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $summary['inventory_count']) ?></div><div class="label">Inventory Items Tracked</div></div>
  <div class="kpi-card <?= $summary['low_stock'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['low_stock']) ?></div><div class="label">Food Items Low on Stock</div></div>
</div>

<h3 class="overview-title">Monthly subscriptions</h3>
<p class="overview-note">An invoice is generated, the devotee is asked to pay, and a paid invoice is copied into Donations.</p>
<div class="flow-strip">
  <div class="flow-step"><div class="n">1</div>Invoice generated</div>
  <div class="flow-arrow">→</div>
  <div class="flow-step"><div class="n">2</div>Message sent with pay link</div>
  <div class="flow-arrow">→</div>
  <div class="flow-step"><div class="n">3</div>Devotee pays</div>
  <div class="flow-arrow">→</div>
  <div class="flow-step"><div class="n">4</div>Recorded in Donations</div>
</div>
<div class="panel">
  <table class="data-table">
    <tr><th>Subscriber</th><th>Plan</th><th>Amount</th><th>Period</th><th>Status</th></tr>
    <?php foreach ($recentInvoices as $invoice): ?>
    <tr>
      <td><?= e($invoice['subscriber_name']) ?></td>
      <td><?= e($invoice['plan_name']) ?></td>
      <td><?= e(money($invoice['amount'])) ?></td>
      <td><?= e($invoice['period_label']) ?></td>
      <td>
        <?php if ($invoice['status'] === 'Paid'): ?><span class="badge badge-green">Paid</span>
        <?php elseif ($invoice['status'] === 'Sent'): ?><span class="badge badge-blue">Sent</span>
        <?php elseif ($invoice['status'] === 'Overdue'): ?><span class="badge badge-red">Overdue</span>
        <?php else: ?><span class="badge badge-amber">Pending</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<h3 class="overview-title">Recent donations</h3>
<div class="panel">
  <table class="data-table">
    <tr><th>Date</th><th>Donor</th><th>Type</th><th>Amount</th><th>Purpose</th><th>Receipt</th></tr>
    <?php foreach ($recentDonations as $donation): ?>
    <tr>
      <td><?= e($donation['donation_date']) ?></td>
      <td><?= e($donation['donor_name']) ?></td>
      <td><?= e($donation['donation_type']) ?></td>
      <td><?= e(money_or_dash($donation['amount'])) ?></td>
      <td><?= e(dash($donation['purpose'])) ?></td>
      <td><?php if ((int) $donation['receipt_generated'] === 1): ?><?= receipt_link($donation['receipt_number']) ?><?= receipt_cancel_badge($donation['receipt_cancelled'] ?? 0) ?><?php else: ?><span class="badge badge-amber">Pending</span><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<h3 class="overview-title">Inventory, food stock, and deity vastra</h3>
<div class="overview-grid-3">
  <div class="panel">
    <h3>Inventory</h3>
    <table class="data-table">
      <tr><th>Item</th><th>Qty</th></tr>
      <?php foreach ($inventorySample as $item): ?><tr><td><?= e($item['name']) ?></td><td><?= e($item['quantity']) ?> <?= e($item['unit']) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
  <div class="panel">
    <h3>Food stock</h3>
    <table class="data-table">
      <tr><th>Item</th><th>Stock</th><th>Status</th></tr>
      <?php foreach ($foodSample as $food): ?>
      <tr>
        <td><?= e($food['name']) ?></td>
        <td><?= e($food['current_stock']) ?> <?= e($food['unit']) ?></td>
        <td><?php if ((float) $food['current_stock'] <= (float) $food['minimum_threshold']): ?><span class="badge badge-red">Low</span><?php else: ?><span class="badge badge-green">OK</span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="panel">
    <h3>Deity vastra</h3>
    <table class="data-table">
      <tr><th>Deity</th><th>Item</th><th>Status</th></tr>
      <?php foreach ($vastraSample as $vastra): ?><tr><td><?= e($vastra['deity_name']) ?></td><td><?= e($vastra['item_name']) ?></td><td><?= e($vastra['status']) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
</div>

<h3 class="overview-title">Expenses and bank</h3>
<div class="overview-grid-2">
  <div class="panel">
    <h3>Recent expenses</h3>
    <table class="data-table">
      <tr><th>Category</th><th>Description</th><th>Amount</th></tr>
      <?php foreach ($expenseSample as $expense): ?><tr><td><?= e($expense['category']) ?></td><td><?= e(dash($expense['description'])) ?></td><td><?= e(money($expense['amount'])) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
  <div class="panel">
    <h3>Bank reconciliation</h3>
    <?php if ($bankSample): ?>
    <table class="data-table">
      <tr><th>Description</th><th>Amount</th><th>Status</th></tr>
      <?php foreach ($bankSample as $txn): ?>
      <tr>
        <td><?= e($txn['description']) ?></td>
        <td><?= e(money($txn['amount'])) ?></td>
        <td><?php if (in_array($txn['reconciled_status'], ['Matched', 'Manual'], true)): ?><span class="badge badge-green">Matched</span><?php else: ?><span class="badge badge-amber">Review</span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="overview-note">Upload a bank statement to see matches here.</p>
    <?php endif; ?>
  </div>
</div>
