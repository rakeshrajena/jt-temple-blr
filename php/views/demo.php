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
<p class="overview-meta"><?= e(t('overview.meta', ['name' => app_display_name(), 'place' => APP_PLACE, 'when' => $generatedOn])) ?></p>

<h3 class="overview-title"><?= e(t('overview.glance')) ?></h3>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($summary['total_donations'])) ?></div><div class="label"><?= e(t('kpi.donations')) ?></div></div>
  <div class="kpi-card danger"><div class="value"><?= e(money($summary['total_expenses'])) ?></div><div class="label"><?= e(t('kpi.expenses')) ?></div></div>
  <div class="kpi-card <?= $summary['net_balance'] >= 0 ? 'good' : 'danger' ?>"><div class="value"><?= e(money($summary['net_balance'])) ?></div><div class="label"><?= e(t('kpi.net')) ?></div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $summary['donor_count']) ?></div><div class="label"><?= e(t('kpi.donors')) ?></div></div>
  <div class="kpi-card good"><div class="value"><?= e(money($summary['mrr'])) ?></div><div class="label"><?= e(t('kpi.mrr')) ?></div></div>
  <div class="kpi-card <?= $summary['pending_invoices'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['pending_invoices']) ?></div><div class="label"><?= e(t('kpi.invoices_due')) ?></div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $summary['inventory_count']) ?></div><div class="label"><?= e(t('kpi.inventory')) ?></div></div>
  <div class="kpi-card <?= $summary['low_stock'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['low_stock']) ?></div><div class="label"><?= e(t('kpi.low_stock')) ?></div></div>
</div>

<h3 class="overview-title"><?= e(t('overview.subscriptions')) ?></h3>
<p class="overview-note"><?= e(t('overview.sub_note')) ?></p>
<div class="flow-strip">
  <div class="flow-step"><div class="n">1</div><?= e(t('overview.step1')) ?></div>
  <div class="flow-arrow">→</div>
  <div class="flow-step"><div class="n">2</div><?= e(t('overview.step2')) ?></div>
  <div class="flow-arrow">→</div>
  <div class="flow-step"><div class="n">3</div><?= e(t('overview.step3')) ?></div>
  <div class="flow-arrow">→</div>
  <div class="flow-step"><div class="n">4</div><?= e(t('overview.step4')) ?></div>
</div>
<div class="fit-row">
<div class="panel fit">
  <table class="data-table fit">
    <tr><th><?= e(t('overview.subscriber')) ?></th><th><?= e(t('common.plan')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('common.period')) ?></th><th><?= e(t('common.status')) ?></th></tr>
    <?php foreach ($recentInvoices as $invoice): ?>
    <tr>
      <td><?= e($invoice['subscriber_name']) ?></td>
      <td><?= e($invoice['plan_name']) ?></td>
      <td><?= e(money($invoice['amount'])) ?></td>
      <td><?= e($invoice['period_label']) ?></td>
      <td>
        <?php if ($invoice['status'] === 'Paid'): ?><span class="badge badge-green"><?= e(t('status.paid')) ?></span>
        <?php elseif ($invoice['status'] === 'Sent'): ?><span class="badge badge-blue"><?= e(t('status.sent')) ?></span>
        <?php elseif ($invoice['status'] === 'Overdue'): ?><span class="badge badge-red"><?= e(t('status.overdue')) ?></span>
        <?php else: ?><span class="badge badge-amber"><?= e(t('status.pending')) ?></span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
</div>

<h3 class="overview-title"><?= e(t('overview.donations')) ?></h3>
<div class="fit-row">
<div class="panel fit">
  <table class="data-table fit">
    <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.donor')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('common.purpose')) ?></th><th><?= e(t('common.receipt')) ?></th></tr>
    <?php foreach ($recentDonations as $donation): ?>
    <tr>
      <td><?= e($donation['donation_date']) ?></td>
      <td><?= e($donation['donor_name']) ?></td>
      <td><?= e($donation['donation_type']) ?></td>
      <td><?= e(money_or_dash($donation['amount'])) ?></td>
      <td><?= e(dash($donation['purpose'])) ?></td>
      <td><?php if ((int) $donation['receipt_generated'] === 1): ?><?= receipt_link($donation['receipt_number']) ?><?= receipt_cancel_badge($donation['receipt_cancelled'] ?? 0) ?><?php else: ?><span class="badge badge-amber"><?= e(t('status.pending')) ?></span><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
</div>

<h3 class="overview-title"><?= e(t('overview.stock')) ?></h3>
<div class="fit-row">
  <div class="panel fit">
    <h3><?= e(t('overview.inventory')) ?></h3>
    <table class="data-table fit">
      <tr><th><?= e(t('common.item')) ?></th><th><?= e(t('common.quantity')) ?></th></tr>
      <?php foreach ($inventorySample as $item): ?><tr><td><?= e($item['name']) ?></td><td><?= e($item['quantity']) ?> <?= e($item['unit']) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
  <div class="panel fit">
    <h3><?= e(t('overview.food')) ?></h3>
    <table class="data-table fit">
      <tr><th><?= e(t('common.item')) ?></th><th><?= e(t('common.stock')) ?></th><th><?= e(t('common.status')) ?></th></tr>
      <?php foreach ($foodSample as $food): ?>
      <tr>
        <td><?= e($food['name']) ?></td>
        <td><?= e($food['current_stock']) ?> <?= e($food['unit']) ?></td>
        <td><?php if ((float) $food['current_stock'] <= (float) $food['minimum_threshold']): ?><span class="badge badge-red"><?= e(t('status.low')) ?></span><?php else: ?><span class="badge badge-green"><?= e(t('status.ok')) ?></span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="panel fit">
    <h3><?= e(t('overview.vastra')) ?></h3>
    <table class="data-table fit">
      <tr><th><?= e(t('common.deity')) ?></th><th><?= e(t('common.item')) ?></th><th><?= e(t('common.status')) ?></th></tr>
      <?php foreach ($vastraSample as $vastra): ?><tr><td><?= e($vastra['deity_name']) ?></td><td><?= e($vastra['item_name']) ?></td><td><?= e($vastra['status']) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
</div>

<h3 class="overview-title"><?= e(t('overview.money')) ?></h3>
<div class="fit-row">
  <div class="panel fit">
    <h3><?= e(t('overview.expenses')) ?></h3>
    <table class="data-table fit">
      <tr><th><?= e(t('common.category')) ?></th><th><?= e(t('common.description')) ?></th><th><?= e(t('common.amount')) ?></th></tr>
      <?php foreach ($expenseSample as $expense): ?><tr><td><?= e($expense['category']) ?></td><td><?= e(dash($expense['description'])) ?></td><td><?= e(money($expense['amount'])) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
  <div class="panel fit">
    <h3><?= e(t('overview.bank')) ?></h3>
    <?php if ($bankSample): ?>
    <table class="data-table fit">
      <tr><th><?= e(t('common.description')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('common.status')) ?></th></tr>
      <?php foreach ($bankSample as $txn): ?>
      <tr>
        <td><?= e($txn['description']) ?></td>
        <td><?= e(money($txn['amount'])) ?></td>
        <td><?php if (in_array($txn['reconciled_status'], ['Matched', 'Manual'], true)): ?><span class="badge badge-green"><?= e(t_fixed('status', (string) $txn['reconciled_status'])) ?></span><?php else: ?><span class="badge badge-amber"><?= e(t('status.unmatched')) ?></span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="overview-note"><?= e(t('overview.bank_empty')) ?></p>
    <?php endif; ?>
  </div>
</div>
