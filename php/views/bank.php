<?php
/** @var list<array<string,mixed>> $uploads */
/** @var list<array<string,mixed>> $unmatched */
/** @var list<array<string,mixed>> $matched */
/** @var list<array<string,mixed>> $openDonations */
/** @var list<array<string,mixed>> $openExpenses */
?>
<div class="reveal-group">
<div class="action-bar">
  <button class="btn btn-outline" type="button" data-reveal="reveal-bank"><?= e(t('ui.upload_bank')) ?></button>
</div>
<div class="panel reveal-panel" id="reveal-bank" hidden>
  <h3><?= e(t('ui.upload_bank')) ?></h3>
  <p style="color:var(--ink-soft); font-size:13px; margin-top:-6px;">
    Accepts CSV or Excel with Date, Description, Amount (or separate Credit/Debit columns), and optionally Balance.
    Credits are auto-matched against donations, debits against expenses — same amount, within 3 days.
  </p>
  <form method="POST" action="<?= e(url('bank')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group"><label>Statement File (.csv, .xlsx)</label><input type="file" name="statement_file" accept=".csv,.xlsx" required></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.upload_reconcile')) ?></button></div>
  </form>
</div>
</div>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= count($matched) ?></div><div class="label">Matched Transactions</div></div>
  <div class="kpi-card warn"><div class="value"><?= count($unmatched) ?></div><div class="label">Needs Manual Review</div></div>
  <div class="kpi-card"><div class="value"><?= count($uploads) ?></div><div class="label">Statements Uploaded</div></div>
</div>
<div class="panel">
  <h3>Needs Manual Review (<?= count($unmatched) ?>)</h3>
  <?php if ($unmatched): ?>
  <table class="data-table">
    <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.description')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('common.type')) ?></th><th>Link to</th></tr>
    <?php foreach ($unmatched as $u): ?>
    <tr>
      <td><?= e($u['txn_date']) ?></td>
      <td><?= e($u['description']) ?></td>
      <td><?= e(money($u['amount'], 2)) ?></td>
      <td><?php if ($u['txn_type'] === 'Credit'): ?><span class="badge badge-green">Credit</span><?php else: ?><span class="badge badge-red">Debit</span><?php endif; ?></td>
      <td>
        <form class="toolbar" method="POST" action="<?= e(url('bank/manual_match')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="txn_id" value="<?= e((string) $u['id']) ?>">
          <?php if ($u['txn_type'] === 'Credit'): ?>
            <input type="hidden" name="match_type" value="donation">
            <select name="match_id" style="max-width:220px;">
              <?php foreach ($openDonations as $od): ?>
                <option value="<?= e((string) $od['id']) ?>"><?= e($od['name']) ?> — <?= e(money($od['amount'])) ?> (<?= e($od['donation_date']) ?>)</option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="hidden" name="match_type" value="expense">
            <select name="match_id" style="max-width:220px;">
              <?php foreach ($openExpenses as $oe): ?>
                <option value="<?= e((string) $oe['id']) ?>"><?= e(dash($oe['description'])) ?> — <?= e(money($oe['amount'])) ?> (<?= e($oe['expense_date']) ?>)</option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <button class="btn btn-sm btn-outline" type="submit">Link</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">Nothing pending review — everything's reconciled.</div>
  <?php endif; ?>
</div>
<div class="panel">
  <h3>Matched Transactions (<?= count($matched) ?>)</h3>
  <?php if ($matched): ?>
  <table class="data-table">
    <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.description')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('ui.matched_to')) ?></th></tr>
    <?php foreach ($matched as $m): ?>
    <tr>
      <td><?= e($m['txn_date']) ?></td>
      <td><?= e($m['description']) ?></td>
      <td><?= e(money($m['amount'], 2)) ?></td>
      <td><?php if ($m['txn_type'] === 'Credit'): ?><span class="badge badge-green">Credit</span><?php else: ?><span class="badge badge-red">Debit</span><?php endif; ?></td>
      <td>
        <?php if (!empty($m['donor_name'])): ?>
          Donation — <?= e($m['donor_name']) ?><?php $receiptLink = receipt_link($m['receipt_number'] ?? ''); if ($receiptLink !== ''): ?> (<?= $receiptLink ?>)<?php endif; ?>
        <?php elseif (!empty($m['expense_desc'])): ?>
          Expense — <?= e($m['expense_desc']) ?>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No matched transactions yet — upload a statement to get started.</div>
  <?php endif; ?>
</div>
<div class="panel">
  <h3>Upload History</h3>
  <?php if ($uploads): ?>
  <table class="data-table">
    <tr><th><?= e(t('ui.file')) ?></th><th>Uploaded</th><th>Total Txns</th><th>Auto-Matched</th></tr>
    <?php foreach ($uploads as $u): ?>
    <tr><td><?= e($u['filename']) ?></td><td><?= e($u['upload_date']) ?></td><td><?= e((string) $u['total_transactions']) ?></td><td><?= e((string) $u['matched_count']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No statements uploaded yet.</div>
  <?php endif; ?>
</div>
