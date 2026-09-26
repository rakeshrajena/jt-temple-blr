<?php
/** @var list<array<string, mixed>> $receipts */
/** @var int $onDisk */
/** @var bool $isAdmin */
?>
<div class="kpi-grid">
  <div class="kpi-card"><div class="value"><?= count($receipts) ?></div><div class="label">Generated receipts</div></div>
  <div class="kpi-card good"><div class="value"><?= (int) $onDisk ?></div><div class="label">PDF files ready</div></div>
</div>
<div class="panel">
  <h3>All receipts</h3>
  <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
    Click a receipt number to open the PDF. <?php if ($isAdmin): ?>Delete and bulk download are limited to Admin.<?php else: ?>Ask an Admin to delete a receipt or download several at once.<?php endif; ?>
  </p>
  <?php if ($receipts === []): ?>
    <p>No receipts yet. Generate one from <a href="<?= e(url('donations')) ?>">Donations</a>.</p>
  <?php else: ?>
  <form method="POST" id="receiptForm" action="<?= e(url('receipts/download')) ?>">
    <?= csrf_field() ?>
    <?php if ($isAdmin): ?>
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
      <button class="btn btn-gold btn-sm" type="submit" formaction="<?= e(url('receipts/download')) ?>" name="scope" value="selected" id="downloadSelected" disabled>Download selected (<span id="selCount">0</span>)</button>
      <button class="btn btn-outline btn-sm" type="submit" formaction="<?= e(url('receipts/download')) ?>" name="scope" value="all">Download all (<?= (int) $onDisk ?>)</button>
      <button class="btn btn-danger btn-sm" type="submit" formaction="<?= e(url('receipts/delete')) ?>" id="deleteSelected" disabled>Delete selected</button>
    </div>
    <?php endif; ?>
    <table class="data-table">
      <tr>
        <?php if ($isAdmin): ?><th style="width:36px;"><input type="checkbox" id="selectAll" aria-label="Select all receipts"></th><?php endif; ?>
        <th>Receipt</th>
        <th>Date</th>
        <th>Donor</th>
        <th>Amount</th>
        <th>Purpose</th>
        <th>Generated</th>
        <th>File</th>
      </tr>
      <?php foreach ($receipts as $row): ?>
      <?php
        $number = (string) $row['receipt_number'];
        $ready = preg_match('/^RCPT-\d{4}-\d{4}$/', $number) === 1
            && is_file(APP_ROOT . '/storage/receipts/' . $number . '.pdf');
        $generatedOn = (string) ($row['generated_date'] ?? '');
        $generatedBy = (string) ($row['generated_by_name'] ?? '');
      ?>
      <tr>
        <?php if ($isAdmin): ?>
        <td><input type="checkbox" name="donation_ids[]" value="<?= e((string) $row['donation_id']) ?>" class="receipt-checkbox" aria-label="Select <?= e($number) ?>"></td>
        <?php endif; ?>
        <td><?php if ($ready): ?><?= receipt_link($number) ?><?php else: ?><span class="badge badge-green"><?= e($number) ?></span><?php endif; ?></td>
        <td><?= e((string) $row['donation_date']) ?></td>
        <td><?= e((string) $row['donor_name']) ?><?php if (!empty($row['donor_phone'])): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e((string) $row['donor_phone']) ?></span><?php endif; ?></td>
        <td><?= e(money_or_dash($row['amount'])) ?></td>
        <td><?= e(dash((string) ($row['purpose'] ?? ''))) ?></td>
        <td><?= e($generatedOn !== '' ? $generatedOn : '—') ?><?php if ($generatedBy !== ''): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e($generatedBy) ?></span><?php endif; ?></td>
        <td><?php if ($ready): ?><span class="badge badge-green">Ready</span><?php else: ?><span class="badge badge-amber">Missing</span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>
  <?php endif; ?>
</div>
<?php if ($isAdmin && $receipts !== []): ?>
<script>
(function () {
  const selectAll = document.getElementById('selectAll');
  const downloadBtn = document.getElementById('downloadSelected');
  const deleteBtn = document.getElementById('deleteSelected');
  const count = document.getElementById('selCount');
  const boxes = () => Array.from(document.querySelectorAll('.receipt-checkbox'));
  function update() {
    const n = boxes().filter(function (box) { return box.checked; }).length;
    count.textContent = String(n);
    downloadBtn.disabled = n === 0;
    deleteBtn.disabled = n === 0;
    const all = boxes();
    selectAll.checked = all.length > 0 && n === all.length;
  }
  selectAll.addEventListener('change', function () {
    boxes().forEach(function (box) { box.checked = selectAll.checked; });
    update();
  });
  boxes().forEach(function (box) { box.addEventListener('change', update); });
  deleteBtn.addEventListener('click', function (event) {
    const n = boxes().filter(function (box) { return box.checked; }).length;
    if (n === 0 || !window.confirm('Delete ' + n + ' receipt' + (n === 1 ? '' : 's') + '? The donation stays, and a new receipt can be generated later.')) {
      event.preventDefault();
    }
  });
})();
</script>
<?php endif; ?>
