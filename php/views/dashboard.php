<?php
/** @var array $summary */
/** @var list<array<string,mixed>> $recentDonations */
/** @var list<array<string,mixed>> $recentExpenses */
/** @var list<array<string,mixed>> $lowStockItems */
?>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($summary['total_donations'])) ?></div><div class="label"><?= e(t('kpi.donations')) ?></div></div>
  <div class="kpi-card danger"><div class="value"><?= e(money($summary['total_expenses'])) ?></div><div class="label"><?= e(t('kpi.expenses')) ?></div></div>
  <div class="kpi-card <?= $summary['net_balance'] >= 0 ? 'good' : 'danger' ?>"><div class="value"><?= e(money($summary['net_balance'])) ?></div><div class="label"><?= e(t('kpi.net')) ?></div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $summary['donor_count']) ?></div><div class="label"><?= e(t('kpi.donors')) ?></div></div>
  <div class="kpi-card<?= $summary['pending_receipts'] ? ' warn' : '' ?>"><div class="value"><?= e((string) $summary['pending_receipts']) ?></div><div class="label"><?= e(t('kpi.receipts_pending')) ?></div></div>
  <div class="kpi-card <?= $summary['low_stock'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['low_stock']) ?></div><div class="label"><?= e(t('kpi.low_stock')) ?></div></div>
  <div class="kpi-card <?= $summary['unmatched_txns'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['unmatched_txns']) ?></div><div class="label"><?= e(t('kpi.bank_review')) ?></div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $summary['inventory_count']) ?></div><div class="label"><?= e(t('kpi.inventory')) ?></div></div>
  <div class="kpi-card good"><div class="value"><?= e(money($summary['mrr'])) ?></div><div class="label"><?= e(t('kpi.mrr')) ?></div></div>
  <div class="kpi-card <?= $summary['pending_invoices'] ? 'warn' : 'good' ?>"><div class="value"><?= e((string) $summary['pending_invoices']) ?></div><div class="label"><?= e(t('kpi.invoices_due')) ?></div></div>
</div>

<div class="fit-row">
  <div class="panel fit">
    <h3><?= e(t('dash.recent_donations')) ?></h3>
    <?php if ($recentDonations): ?>
    <table class="data-table fit">
      <tr><th><?= e(t('common.donor')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.receipt')) ?></th></tr>
      <?php foreach ($recentDonations as $d): ?>
      <tr>
        <td><?= e($d['donor_name']) ?></td>
        <td><?= e($d['donation_type']) ?></td>
        <td><?= e(money_or_dash($d['amount'])) ?></td>
        <td><?= e($d['donation_date']) ?></td>
        <td>
          <?php if ((int) $d['receipt_generated'] === 1): ?>
            <?= receipt_link($d['receipt_number']) ?><?= receipt_cancel_badge($d['receipt_cancelled'] ?? 0) ?>
          <?php else: ?>
            <span class="badge badge-amber"><?= e(t('status.pending')) ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="empty-state"><?= e(t('dash.no_donations')) ?></div>
    <?php endif; ?>
  </div>
  <div class="panel fit">
    <h3><?= e(t('dash.low_stock')) ?></h3>
    <?php if ($lowStockItems): ?>
    <table class="data-table fit">
      <tr><th><?= e(t('common.item')) ?></th><th><?= e(t('common.stock')) ?></th><th><?= e(t('common.threshold')) ?></th></tr>
      <?php foreach ($lowStockItems as $f): ?>
      <tr>
        <td><?= e($f['name']) ?></td>
        <td><span class="badge badge-red"><?= e($f['current_stock']) ?> <?= e($f['unit']) ?></span></td>
        <td><?= e($f['minimum_threshold']) ?> <?= e($f['unit']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="empty-state"><?= e(t('dash.stock_healthy')) ?></div>
    <?php endif; ?>
  </div>
  <div class="panel fit">
  <h3><?= e(t('dash.recent_expenses')) ?></h3>
  <?php if ($recentExpenses): ?>
  <table class="data-table fit">
    <tr><th><?= e(t('common.category')) ?></th><th><?= e(t('common.description')) ?></th><th><?= e(t('common.paid_to')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('common.date')) ?></th></tr>
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
  <div class="empty-state"><?= e(t('dash.no_expenses')) ?></div>
  <?php endif; ?>
  </div>
</div>
