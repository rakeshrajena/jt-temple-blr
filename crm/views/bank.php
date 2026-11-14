<?php
/** @var list<array<string,mixed>> $uploads */
/** @var list<array<string,mixed>> $unmatched */
/** @var list<array<string,mixed>> $matched */
/** @var list<array<string,mixed>> $openDonations */
/** @var list<array<string,mixed>> $openExpenses */
/** @var string $today */
$defaultTxnType = $openDonations === [] && $openExpenses !== [] ? 'Debit' : 'Credit';
$expenseOptionLabel = static function (array $oe): string {
    $parts = [];
    $voucher = trim((string) ($oe['voucher_number'] ?? ''));
    if ($voucher !== '') {
        $parts[] = $voucher;
    }
    $desc = trim((string) ($oe['description'] ?? ''));
    if ($desc !== '') {
        $parts[] = $desc;
    } elseif (trim((string) ($oe['category'] ?? '')) !== '') {
        $parts[] = (string) $oe['category'];
    }
    $paid = trim((string) ($oe['paid_to'] ?? ''));
    if ($paid !== '') {
        $parts[] = $paid;
    }
  $head = $parts !== [] ? implode(' · ', $parts) : 'Expense #' . (string) ($oe['id'] ?? '');
  return $head . ' — ' . money($oe['amount']) . ' (' . (string) $oe['expense_date'] . ')';
};
?>
<div class="reveal-group">
<div class="action-bar">
  <button class="btn btn-outline" type="button" data-reveal="reveal-bank"><?= e(t('ui.upload_bank')) ?></button>
  <button class="btn btn-outline" type="button" data-reveal="reveal-manual-bank">Manual match</button>
</div>
<div class="panel reveal-panel" id="reveal-manual-bank" hidden>
  <h3>Manual bank match</h3>
  <p style="color:var(--ink-soft); font-size:13px; margin-top:-6px;">
    Type a bank line and link it to an open donation (credit) or approved expense (debit). Use statement upload for bulk auto-match; this does not change that flow.
  </p>
  <form method="POST" action="<?= e(url('bank/manual_entry')) ?>" id="bankManualEntryForm">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label for="manual-txn-date"><?= e(t('common.date')) ?></label>
        <input id="manual-txn-date" type="date" name="txn_date" value="<?= e($today) ?>" required>
      </div>
      <div class="form-group">
        <label for="manual-txn-type"><?= e(t('common.type')) ?></label>
        <select id="manual-txn-type" name="txn_type" required>
          <option value="Credit"<?= $defaultTxnType === 'Credit' ? ' selected' : '' ?>>Credit (donation)</option>
          <option value="Debit"<?= $defaultTxnType === 'Debit' ? ' selected' : '' ?>>Debit (expense)</option>
        </select>
      </div>
      <div class="form-group">
        <label for="manual-txn-amount"><?= e(t('common.amount')) ?></label>
        <input id="manual-txn-amount" type="number" name="amount" step="0.01" min="0.01" required>
      </div>
      <div class="form-group">
        <label for="manual-txn-balance">Balance (optional)</label>
        <input id="manual-txn-balance" type="number" name="balance" step="0.01">
      </div>
      <div class="form-group full">
        <label for="manual-txn-desc"><?= e(t('common.description')) ?></label>
        <input id="manual-txn-desc" type="text" name="description" maxlength="255" placeholder="Narration as on statement">
      </div>
      <div class="form-group full manual-match-section" id="manual-match-donation-wrap">
        <label for="manual-match-donation">Link to donation <span style="color:var(--ink-soft);font-weight:400;">(<?= count($openDonations) ?> open)</span></label>
        <select id="manual-match-donation"<?= $defaultTxnType === 'Credit' ? ' name="match_id" required' : ' disabled' ?>>
          <?php if ($openDonations === []): ?>
          <option value="">No open donations — record one under Donations first</option>
          <?php else: ?>
          <?php foreach ($openDonations as $od): ?>
          <option value="<?= e((string) $od['id']) ?>"><?= e($od['name']) ?> — <?= e(money($od['amount'])) ?> (<?= e($od['donation_date']) ?>)</option>
          <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>
      <div class="form-group full manual-match-section" id="manual-match-expense-wrap">
        <label for="manual-match-expense">Link to expense <span style="color:var(--ink-soft);font-weight:400;">(<?= count($openExpenses) ?> approved, not reconciled)</span></label>
        <select id="manual-match-expense"<?= $defaultTxnType === 'Debit' ? ' name="match_id" required' : ' disabled' ?>>
          <?php if ($openExpenses === []): ?>
          <option value="">No approved open expenses — approve under Expenses first</option>
          <?php else: ?>
          <?php foreach ($openExpenses as $oe): ?>
          <option value="<?= e((string) $oe['id']) ?>"><?= e($expenseOptionLabel($oe)) ?></option>
          <?php endforeach; ?>
          <?php endif; ?>
        </select>
        <?php if ($openExpenses === []): ?>
        <p style="color:var(--ink-soft);font-size:12px;margin:6px 0 0;">Only <strong>Approved</strong> expenses that are not already reconciled appear here.</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit" data-busy="Saving manual match"<?= $openDonations === [] && $openExpenses === [] ? ' disabled' : '' ?>>Save and link</button>
    </div>
  </form>
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
            <select name="match_id" style="max-width:min(100%,280px);">
              <?php if ($openExpenses === []): ?>
              <option value="">No approved open expenses</option>
              <?php else: ?>
              <?php foreach ($openExpenses as $oe): ?>
                <option value="<?= e((string) $oe['id']) ?>"><?= e($expenseOptionLabel($oe)) ?></option>
              <?php endforeach; ?>
              <?php endif; ?>
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
<script>
(function () {
  var typeSel = document.getElementById('manual-txn-type');
  var donationWrap = document.getElementById('manual-match-donation-wrap');
  var expenseWrap = document.getElementById('manual-match-expense-wrap');
  var donationSel = document.getElementById('manual-match-donation');
  var expenseSel = document.getElementById('manual-match-expense');
  if (!typeSel || !donationSel || !expenseSel) return;
  function syncManualMatchLinks() {
    var credit = typeSel.value === 'Credit';
    donationWrap.classList.toggle('is-inactive', !credit);
    expenseWrap.classList.toggle('is-inactive', credit);
    donationSel.disabled = !credit;
    expenseSel.disabled = credit;
    donationSel.required = credit;
    expenseSel.required = !credit;
    if (credit) {
      donationSel.setAttribute('name', 'match_id');
      expenseSel.removeAttribute('name');
    } else {
      expenseSel.setAttribute('name', 'match_id');
      donationSel.removeAttribute('name');
    }
  }
  typeSel.addEventListener('change', syncManualMatchLinks);
  syncManualMatchLinks();
})();
</script>
