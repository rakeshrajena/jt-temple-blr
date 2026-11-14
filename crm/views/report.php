<?php
/** @var string $title */
/** @var string $reportType */
/** @var list<array<string,mixed>> $data */
/** @var ?string $start */
/** @var ?string $end */
/** @var string $generatedOn */
/** @var bool $dateFiltered */
/** @var array<string, string> $filters */
/** @var array<string, list<string>> $filterOptions */
/** @var bool $filtersActive */
/** @var int $dataTotal */
$dateFiltered = $dateFiltered ?? report_uses_date_filter($reportType);
$filters = $filters ?? report_filters_from_request($reportType);
$filterOptions = $filterOptions ?? [];
$filtersActive = $filtersActive ?? report_filters_active($filters);
$dataTotal = $dataTotal ?? count($data);
$moneyReport = $reportType === 'donations' || $reportType === 'expenses';
$total = 0.0;
foreach ($data as $row) {
    if (isset($row['amount']) && $row['amount'] !== null && $row['amount'] !== '') {
        $total += (float) $row['amount'];
    }
}
$rowCount = count($data);
$rowLabel = match ($reportType) {
    'donations' => 'donation(s)',
    'expenses' => 'expense(s)',
    'reconciliation' => 'transaction(s)',
    default => 'item(s)',
};
$periodNote = $dateFiltered ? report_period_label($start, $end, true) : '';
$lowStock = 0;
if ($reportType === 'food') {
    foreach ($data as $f) {
        if ((float) $f['current_stock'] <= (float) $f['minimum_threshold']) {
            $lowStock++;
        }
    }
}
$unmatched = 0;
if ($reportType === 'reconciliation') {
    foreach ($data as $t) {
        if (($t['reconciled_status'] ?? '') === 'Unmatched') {
            $unmatched++;
        }
    }
}
$accent = match ($reportType) {
    'donations' => 'gold',
    'expenses' => 'red',
    'inventory' => 'navy',
    'food' => 'green',
    'vastra' => 'blue',
    'reconciliation' => 'amber',
    default => 'gold',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php app_favicon_link(); ?>
  <title><?= e($title) ?> — <?= e(app_display_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=55">
</head>
<body class="report-page report-page--<?= e($reportType) ?>"
  data-table-filter-label="Quick filter in table"
  data-table-filter-count="{shown} of {total} rows visible">
<div class="report-sheet report-sheet--<?= e($accent) ?>">
  <header class="report-letterhead print-header">
    <img src="<?= e(app_logo_url()) ?>" alt="" class="report-letterhead-logo print-logo">
    <div class="report-letterhead-text">
      <p class="report-letterhead-place"><?= e(app_place()) ?></p>
      <h1 class="report-letterhead-title"><?= e(app_display_name()) ?></h1>
      <p class="report-letterhead-sub"><?= e($title) ?></p>
      <p class="report-letterhead-meta">
        Generated <?= e($generatedOn) ?>
        <?php if ($dateFiltered): ?> · Period <?= e($periodNote) ?><?php endif; ?>
      </p>
    </div>
  </header>

  <div class="report-toolbar no-print">
    <a href="<?= e(url('reports')) ?>" class="btn btn-outline btn-sm report-back"><?= e(t('nav.reports')) ?></a>
  </div>

  <section class="report-controls no-print">
    <form class="report-controls-form" method="GET" action="<?= e(app_script()) ?>">
      <input type="hidden" name="r" value="reports/<?= e($reportType) ?>">
      <div class="report-controls-grid<?= $dateFiltered ? '' : ' report-controls-grid--snap' ?>">
        <?php if ($dateFiltered): ?>
        <div class="report-control-block">
          <p class="report-control-label">Date range</p>
          <div class="report-control-fields">
            <div class="form-group report-filter-field">
              <label for="report-start"><?= e(t('common.from')) ?></label>
              <input type="date" id="report-start" name="start" value="<?= e((string) $start) ?>">
            </div>
            <div class="form-group report-filter-field">
              <label for="report-end"><?= e(t('common.to')) ?></label>
              <input type="date" id="report-end" name="end" value="<?= e((string) $end) ?>">
            </div>
          </div>
        </div>
        <?php endif; ?>
        <div class="report-control-block report-control-block--wide">
          <p class="report-control-label">Refine results</p>
          <div class="report-control-fields report-refine-fields">
            <?php require APP_ROOT . '/views/partials/report_refine_filters.php'; ?>
          </div>
        </div>
        <div class="report-control-block report-control-block--actions">
          <p class="report-control-label">Apply</p>
          <div class="report-control-actions">
            <button class="btn btn-gold" type="submit"><?= e(t('common.filter')) ?></button>
            <?php if ($filtersActive): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(report_clear_filters_url($reportType, $start, $end, $dateFiltered)) ?>">Clear filters</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if ($filtersActive): ?>
      <p class="report-active-filters sub">Narrowing <?= e((string) $dataTotal) ?> rows — downloads use these filters.</p>
      <?php endif; ?>
    </form>

    <div class="report-export-bar">
      <p class="report-control-label">Download & print</p>
      <div class="report-export-inner">
        <div class="report-downloads">
          <a class="btn btn-outline btn-sm" href="<?= e(report_download_url($reportType, 'pdf', $start, $end, $filters)) ?>">PDF</a>
          <a class="btn btn-outline btn-sm" href="<?= e(report_download_url($reportType, 'csv', $start, $end, $filters)) ?>">CSV</a>
          <a class="btn btn-outline btn-sm" href="<?= e(report_download_url($reportType, 'xlsx', $start, $end, $filters)) ?>">Excel</a>
        </div>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Print view</button>
      </div>
    </div>
  </section>

  <div class="kpi-grid report-kpi-grid no-print">
    <div class="kpi-card">
      <div class="value"><?= e((string) $rowCount) ?></div>
      <div class="label"><?= $filtersActive && $dataTotal !== $rowCount ? 'Rows shown (filtered)' : 'Rows in this report' ?></div>
    </div>
    <?php if ($moneyReport): ?>
    <div class="kpi-card good">
      <div class="value"><?= e(money($total, 2)) ?></div>
      <div class="label"><?= $reportType === 'expenses' ? 'Total spent' : 'Total received' ?></div>
    </div>
    <?php endif; ?>
    <?php if ($reportType === 'food'): ?>
    <div class="kpi-card <?= $lowStock > 0 ? 'warn' : 'good' ?>">
      <div class="value"><?= e((string) $lowStock) ?></div>
      <div class="label">Items at or below threshold</div>
    </div>
    <?php endif; ?>
    <?php if ($reportType === 'reconciliation'): ?>
    <div class="kpi-card <?= $unmatched > 0 ? 'warn' : 'good' ?>">
      <div class="value"><?= e((string) $unmatched) ?></div>
      <div class="label">Unmatched lines</div>
    </div>
    <div class="kpi-card good">
      <div class="value"><?= e((string) ($rowCount - $unmatched)) ?></div>
      <div class="label">Matched lines</div>
    </div>
    <?php endif; ?>
  </div>

  <section class="panel report-table-panel">
    <p class="report-summary">
      <?php if ($filtersActive && $dataTotal !== $rowCount): ?>
        Showing <strong><?= e((string) $rowCount) ?></strong> of <strong><?= e((string) $dataTotal) ?></strong> <?= e($rowLabel) ?>
      <?php else: ?>
        <strong><?= e((string) $rowCount) ?></strong> <?= e($rowLabel) ?>
      <?php endif; ?>
      <?php if ($dateFiltered): ?> · <?= e($periodNote) ?><?php endif; ?>
      <?php if ($moneyReport): ?> · Total <strong><?= e(money($total, 2)) ?></strong><?php endif; ?>
    </p>

    <?php if ($rowCount === 0): ?>
      <div class="empty-state">
        <?php if ($dataTotal > 0 && $filtersActive): ?>
        <p>No rows match your filters<?php if ($dateFiltered): ?> in <?= e($periodNote) ?><?php endif; ?>.</p>
        <p class="sub"><a href="<?= e(report_clear_filters_url($reportType, $start, $end, $dateFiltered)) ?>">Clear filters</a> or widen your search.</p>
        <?php else: ?>
        <p>No records to show<?php if ($dateFiltered && $periodNote !== ''): ?> for <?= e($periodNote) ?><?php endif; ?>.</p>
        <?php if ($dateFiltered): ?>
        <p class="sub">Try widening the date range.</p>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="table-scroll">
      <table class="data-table report-data-table" data-filter>
        <?php if ($reportType === 'donations'): ?>
        <thead>
          <tr>
            <th><?= e(t('common.date')) ?></th>
            <th><?= e(t('common.donor')) ?></th>
            <th><?= e(t('common.type')) ?></th>
            <th class="text-right"><?= e(t('common.amount')) ?></th>
            <th><?= e(t('common.purpose')) ?></th>
            <th><?= e(t('common.payment')) ?></th>
            <th><?= e(t('ui.receipt_no')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $d): ?>
          <tr>
            <td><?= e($d['donation_date']) ?></td>
            <td><?= e($d['donor_name']) ?></td>
            <td><?= e($d['donation_type']) ?></td>
            <td class="text-right num"><?= e(money_or_dash($d['amount'], 2)) ?></td>
            <td><?= e(dash($d['purpose'])) ?></td>
            <td><?= e($d['payment_mode']) ?></td>
            <td><?php $receiptLink = receipt_link($d['receipt_number'] ?? ''); ?><?= $receiptLink !== '' ? $receiptLink : '—' ?><?= receipt_cancel_badge($d['receipt_cancelled'] ?? 0) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="report-total-row">
            <td colspan="3"><strong>Total</strong></td>
            <td class="text-right num"><strong><?= e(money($total, 2)) ?></strong></td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
        <?php elseif ($reportType === 'expenses'): ?>
        <thead>
          <tr>
            <th><?= e(t('common.date')) ?></th>
            <th><?= e(t('common.category')) ?></th>
            <th><?= e(t('common.description')) ?></th>
            <th><?= e(t('common.paid_to')) ?></th>
            <th class="text-right"><?= e(t('common.amount')) ?></th>
            <th><?= e(t('common.payment')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $row): ?>
          <tr>
            <td><?= e($row['expense_date']) ?></td>
            <td><?= e($row['category']) ?></td>
            <td><?= e(dash($row['description'])) ?></td>
            <td><?= e(dash($row['paid_to'])) ?></td>
            <td class="text-right num"><?= e(money($row['amount'], 2)) ?></td>
            <td><?= e($row['payment_mode']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="report-total-row">
            <td colspan="4"><strong>Total</strong></td>
            <td class="text-right num"><strong><?= e(money($total, 2)) ?></strong></td>
            <td></td>
          </tr>
        </tfoot>
        <?php elseif ($reportType === 'inventory'): ?>
        <thead>
          <tr>
            <th><?= e(t('common.category')) ?></th>
            <th><?= e(t('common.name')) ?></th>
            <th><?= e(t('ui.qty')) ?></th>
            <th><?= e(t('ui.condition')) ?></th>
            <th><?= e(t('common.location')) ?></th>
            <th><?= e(t('common.source')) ?></th>
            <th><?= e(t('ui.added')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $i): ?>
          <tr>
            <td><?= e($i['category']) ?></td>
            <td><?= e($i['name']) ?></td>
            <td><?= e($i['quantity']) ?> <?= e($i['unit']) ?></td>
            <td><?= e($i['item_condition']) ?></td>
            <td><?= e(dash($i['location'])) ?></td>
            <td><?= e($i['source']) ?></td>
            <td><?= e($i['added_date']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php elseif ($reportType === 'food'): ?>
        <thead>
          <tr>
            <th><?= e(t('common.item')) ?></th>
            <th><?= e(t('ui.current_stock_col')) ?></th>
            <th><?= e(t('ui.low_threshold')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('ui.last_updated')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $f): ?>
          <?php $isLow = (float) $f['current_stock'] <= (float) $f['minimum_threshold']; ?>
          <tr class="<?= $isLow ? 'report-row-warn' : '' ?>">
            <td><?= e($f['name']) ?></td>
            <td><?= e($f['current_stock']) ?> <?= e($f['unit']) ?></td>
            <td><?= e($f['minimum_threshold']) ?> <?= e($f['unit']) ?></td>
            <td><?php if ($isLow): ?><span class="badge badge-amber">Low stock</span><?php else: ?><span class="badge badge-green">OK</span><?php endif; ?></td>
            <td><?= e($f['last_updated']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php elseif ($reportType === 'vastra'): ?>
        <thead>
          <tr>
            <th><?= e(t('common.deity')) ?></th>
            <th><?= e(t('common.item')) ?></th>
            <th><?= e(t('common.color')) ?></th>
            <th><?= e(t('ui.qty')) ?></th>
            <th><?= e(t('common.source')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('ui.added')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $v): ?>
          <tr>
            <td><?= e($v['deity_name']) ?></td>
            <td><?= e($v['item_name']) ?></td>
            <td><?= e(dash($v['color'])) ?></td>
            <td><?= e((string) $v['quantity']) ?></td>
            <td><?= e($v['source']) ?></td>
            <td><?= e($v['status']) ?></td>
            <td><?= e($v['date_added']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php else: ?>
        <thead>
          <tr>
            <th><?= e(t('common.date')) ?></th>
            <th><?= e(t('common.description')) ?></th>
            <th class="text-right"><?= e(t('common.amount')) ?></th>
            <th><?= e(t('common.type')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('ui.matched_to')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $t): ?>
          <?php $isUnmatched = ($t['reconciled_status'] ?? '') === 'Unmatched'; ?>
          <tr class="<?= $isUnmatched ? 'report-row-warn' : '' ?>">
            <td><?= e($t['txn_date']) ?></td>
            <td><?= e($t['description']) ?></td>
            <td class="text-right num"><?= e(money($t['amount'], 2)) ?></td>
            <td><?= e($t['txn_type']) ?></td>
            <td><?php if ($isUnmatched): ?><span class="badge badge-amber"><?= e($t['reconciled_status']) ?></span><?php else: ?><span class="badge badge-green"><?= e($t['reconciled_status']) ?></span><?php endif; ?></td>
            <td>
              <?php if (!empty($t['donor_name'])): ?>
                Donation — <?= e($t['donor_name']) ?><?php $receiptLink = receipt_link($t['donation_receipt'] ?? ''); if ($receiptLink !== ''): ?> (<?= $receiptLink ?>)<?php endif; ?>
              <?php elseif (!empty($t['expense_description'])): ?>
                Expense — <?= e($t['expense_description']) ?>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php endif; ?>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <p class="report-print-note no-print sub">Use <strong>Print view</strong> for paper; use <strong>PDF / CSV / Excel</strong> for files matching your filters.</p>
</div>
<script src="<?= e(asset('js/table_filter.js')) ?>?v=1"></script>
</body>
</html>