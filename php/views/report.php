<?php
/** @var string $title */
/** @var string $reportType */
/** @var list<array<string,mixed>> $data */
/** @var ?string $start */
/** @var ?string $end */
/** @var string $generatedOn */
$total = 0.0;
foreach ($data as $row) {
    if (isset($row['amount']) && $row['amount'] !== null && $row['amount'] !== '') {
        $total += (float) $row['amount'];
    }
}
$filterable = $reportType === 'donations' || $reportType === 'expenses';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=3">
</head>
<body style="background:#fff;">
<div class="report-sheet">
  <div class="print-header">
    <img src="<?= e(asset('logo.svg')) ?>" alt="Temple Logo" class="print-logo">
    <h1><?= e(APP_NAME) ?>, Sarjapura</h1>
    <p><?= e($title) ?> — Generated on <?= e($generatedOn) ?></p>
  </div>
  <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; flex-wrap:wrap;">
    <a href="<?= e(url('reports')) ?>" class="btn btn-outline btn-sm">← Back to Reports</a>
    <div>
      <?php if ($filterable): ?>
      <form method="GET" action="<?= e(app_script()) ?>" style="display:inline-flex; gap:8px; align-items:center;">
        <input type="hidden" name="r" value="reports/<?= e($reportType) ?>">
        <input type="date" name="start" value="<?= e((string) $start) ?>" style="padding:6px 10px; border:1px solid var(--border); border-radius:6px;">
        <span>to</span>
        <input type="date" name="end" value="<?= e((string) $end) ?>" style="padding:6px 10px; border:1px solid var(--border); border-radius:6px;">
        <button class="btn btn-sm btn-outline" type="submit"><?= e(t('common.filter')) ?></button>
      </form>
      <?php endif; ?>
      <button class="btn btn-sm btn-primary" onclick="window.print()">🖨️ Print</button>
    </div>
  </div>
  <div class="report-meta">
    <?= count($data) ?> <?= e($reportType === 'donations' ? 'donation(s)' : ($reportType === 'expenses' ? 'expense(s)' : ($reportType === 'reconciliation' ? 'transaction(s)' : 'item(s)'))) ?>
    <?php if ($filterable && ($start || $end)): ?> between <?= e($start ?: 'earliest') ?> and <?= e($end ?: 'today') ?><?php endif; ?>
    <?php if ($filterable): ?> &nbsp;•&nbsp; Total: <?= e(money($total, 2)) ?><?php endif; ?>
  </div>
  <table class="data-table">
    <?php if ($reportType === 'donations'): ?>
      <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.donor')) ?></th><th><?= e(t('common.type')) ?></th><th class="text-right">Amount</th><th><?= e(t('common.purpose')) ?></th><th><?= e(t('common.payment')) ?></th><th><?= e(t('ui.receipt_no')) ?></th></tr>
      <?php foreach ($data as $d): ?>
      <tr>
        <td><?= e($d['donation_date']) ?></td>
        <td><?= e($d['donor_name']) ?></td>
        <td><?= e($d['donation_type']) ?></td>
        <td class="text-right"><?= e(money_or_dash($d['amount'], 2)) ?></td>
        <td><?= e(dash($d['purpose'])) ?></td>
        <td><?= e($d['payment_mode']) ?></td>
        <td><?php $receiptLink = receipt_link($d['receipt_number'] ?? ''); ?><?= $receiptLink !== '' ? $receiptLink : 'Not generated' ?><?= receipt_cancel_badge($d['receipt_cancelled'] ?? 0) ?></td>
      </tr>
      <?php endforeach; ?>
    <?php elseif ($reportType === 'expenses'): ?>
      <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.category')) ?></th><th><?= e(t('common.description')) ?></th><th><?= e(t('common.paid_to')) ?></th><th class="text-right">Amount</th><th><?= e(t('common.payment')) ?></th></tr>
      <?php foreach ($data as $row): ?>
      <tr>
        <td><?= e($row['expense_date']) ?></td>
        <td><?= e($row['category']) ?></td>
        <td><?= e(dash($row['description'])) ?></td>
        <td><?= e(dash($row['paid_to'])) ?></td>
        <td class="text-right"><?= e(money($row['amount'], 2)) ?></td>
        <td><?= e($row['payment_mode']) ?></td>
      </tr>
      <?php endforeach; ?>
    <?php elseif ($reportType === 'inventory'): ?>
      <tr><th><?= e(t('common.category')) ?></th><th><?= e(t('common.name')) ?></th><th><?= e(t('ui.qty')) ?></th><th><?= e(t('ui.condition')) ?></th><th><?= e(t('common.location')) ?></th><th><?= e(t('common.source')) ?></th><th><?= e(t('ui.added')) ?></th></tr>
      <?php foreach ($data as $i): ?>
      <tr>
        <td><?= e($i['category']) ?></td><td><?= e($i['name']) ?></td><td><?= e($i['quantity']) ?> <?= e($i['unit']) ?></td>
        <td><?= e($i['item_condition']) ?></td><td><?= e(dash($i['location'])) ?></td><td><?= e($i['source']) ?></td><td><?= e($i['added_date']) ?></td>
      </tr>
      <?php endforeach; ?>
    <?php elseif ($reportType === 'food'): ?>
      <tr><th><?= e(t('common.item')) ?></th><th><?= e(t('ui.current_stock_col')) ?></th><th><?= e(t('ui.low_threshold')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('ui.last_updated')) ?></th></tr>
      <?php foreach ($data as $f): ?>
      <tr>
        <td><?= e($f['name']) ?></td>
        <td><?= e($f['current_stock']) ?> <?= e($f['unit']) ?></td>
        <td><?= e($f['minimum_threshold']) ?> <?= e($f['unit']) ?></td>
        <td><?= (float) $f['current_stock'] <= (float) $f['minimum_threshold'] ? 'Low Stock' : 'OK' ?></td>
        <td><?= e($f['last_updated']) ?></td>
      </tr>
      <?php endforeach; ?>
    <?php elseif ($reportType === 'vastra'): ?>
      <tr><th><?= e(t('common.deity')) ?></th><th><?= e(t('common.item')) ?></th><th><?= e(t('common.color')) ?></th><th><?= e(t('ui.qty')) ?></th><th><?= e(t('common.source')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('ui.added')) ?></th></tr>
      <?php foreach ($data as $v): ?>
      <tr>
        <td><?= e($v['deity_name']) ?></td><td><?= e($v['item_name']) ?></td><td><?= e(dash($v['color'])) ?></td>
        <td><?= e((string) $v['quantity']) ?></td><td><?= e($v['source']) ?></td><td><?= e($v['status']) ?></td><td><?= e($v['date_added']) ?></td>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.description')) ?></th><th class="text-right">Amount</th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('ui.matched_to')) ?></th></tr>
      <?php foreach ($data as $t): ?>
      <tr>
        <td><?= e($t['txn_date']) ?></td>
        <td><?= e($t['description']) ?></td>
        <td class="text-right"><?= e(money($t['amount'], 2)) ?></td>
        <td><?= e($t['txn_type']) ?></td>
        <td><?= e($t['reconciled_status']) ?></td>
        <td>
          <?php if (!empty($t['donor_name'])): ?>
            Donation — <?= e($t['donor_name']) ?><?php $receiptLink = receipt_link($t['donation_receipt'] ?? ''); if ($receiptLink !== ''): ?> (<?= $receiptLink ?>)<?php endif; ?>
          <?php elseif (!empty($t['expense_description'])): ?>
            Expense — <?= e($t['expense_description']) ?>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </table>
</div>
</body>
</html>
