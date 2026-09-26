<?php /** @var list<array<string,mixed>> $expenses */ /** @var list<string> $categories */ /** @var string $today */ ?>
<div class="panel">
  <h3>Record an Expense</h3>
  <form method="POST" action="<?= e(url('expenses')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label>Category</label>
        <select name="category" required>
          <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Amount (₹)</label><input type="number" step="0.01" name="amount" required></div>
      <div class="form-group"><label>Paid To</label><input type="text" name="paid_to"></div>
      <div class="form-group"><label>Expense Date</label><input type="date" name="expense_date" value="<?= e($today) ?>"></div>
      <div class="form-group">
        <label>Payment Mode</label>
        <select name="payment_mode">
          <?php foreach (money_payment_modes() as $mode): ?><option><?= e($mode) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Receipt / Invoice Ref</label><input type="text" name="receipt_ref"></div>
      <div class="form-group"><label>UPI transaction id</label><input type="text" name="upi_reference" maxlength="64" placeholder="Required for UPI"></div>
      <div class="form-group"><label>Cheque number</label><input type="text" name="cheque_number" maxlength="30" placeholder="Required for cheque"></div>
      <div class="form-group"><label>Cheque date</label><input type="date" name="cheque_date"></div>
      <div class="form-group"><label>Cheque cleared</label><input type="checkbox" name="cheque_cleared" value="1"></div>
      <div class="form-group"><label>Bill copy</label><input type="file" name="bill" accept=".pdf,.jpg,.jpeg,.png"></div>
      <div class="form-group full"><label>Description</label><textarea name="description" rows="2"></textarea></div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit" name="intent" value="submit">Submit for approval</button>
      <button class="btn btn-outline" type="submit" name="intent" value="draft">Save draft</button>
    </div>
  </form>
</div>
<div class="panel">
  <h3>All Expenses (<?= count($expenses) ?>)</h3>
  <?php if ($expenses): ?>
  <table class="data-table">
    <tr><th>Voucher</th><th>Date</th><th>Category</th><th>Description</th><th>Paid To</th><th>Amount</th><th>Payment</th><th>Approval</th><th>Bill</th><th>Bank Match</th></tr>
    <?php foreach ($expenses as $row): ?>
    <tr>
      <td><?= e(dash($row['voucher_number'] ?? '')) ?></td>
      <td><?= e($row['expense_date']) ?></td>
      <td><?= e($row['category']) ?></td>
      <td><?= e(dash($row['description'])) ?></td>
      <td><?= e(dash($row['paid_to'])) ?></td>
      <td><?= e(money($row['amount'])) ?></td>
      <td>
        <?= e($row['payment_mode']) ?>
        <?php if (($row['payment_mode'] ?? '') === 'UPI' && !empty($row['upi_reference'])): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e((string) $row['upi_reference']) ?></span><?php endif; ?>
        <?php if (($row['payment_mode'] ?? '') === 'Cheque' && !empty($row['cheque_number'])): ?>
          <br><span style="color:var(--ink-soft);font-size:12px;">Chq <?= e((string) $row['cheque_number']) ?><?php if (!empty($row['cheque_date'])): ?> · <?= e((string) $row['cheque_date']) ?><?php endif; ?></span>
          <?php if ((int) ($row['cheque_cleared'] ?? 0) === 1): ?><br><span class="badge badge-green">Cleared</span>
          <?php else: ?>
          <form method="POST" action="<?= e(url('expenses/' . $row['id'] . '/clear-cheque')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit">Mark cleared</button></form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
      <td>
        <?php
          $approvalStatus = (string) ($row['approval_status'] ?? 'Approved');
          $approvalBadge = match ($approvalStatus) {
              'Approved' => 'badge-green',
              'Waiting' => 'badge-amber',
              'Sent back' => 'badge-blue',
              'Rejected' => 'badge-red',
              default => 'badge-grey',
          };
        ?>
        <span class="badge <?= e($approvalBadge) ?>"><?= e($approvalStatus) ?></span>
        <?php if (!empty($row['decision_note']) && $approvalStatus === 'Sent back'): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e((string) $row['decision_note']) ?></span><?php endif; ?>
        <?php
          $me = (int) ($currentUser['id'] ?? 0);
          $mine = $me > 0 && $me === (int) ($row['prepared_by'] ?? 0);
          $approvalId = (int) ($row['approval_id'] ?? 0);
        ?>
        <?php if ($mine && $approvalId > 0 && $approvalStatus === 'Draft'): ?>
          <form method="POST" action="<?= e(url('approvals/' . $approvalId)) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit" name="decision" value="submit">Submit</button></form>
        <?php elseif ($mine && $approvalId > 0 && $approvalStatus === 'Sent back'): ?>
          <form method="POST" action="<?= e(url('approvals/' . $approvalId)) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit" name="decision" value="resubmit">Submit again</button></form>
        <?php endif; ?>
      </td>
      <td><?php if (!empty($row['bill_filename'])): ?><a href="<?= e(url('expenses/' . $row['id'] . '/bill')) ?>">Bill</a><?php elseif (!empty($row['receipt_ref'])): ?><?= e((string) $row['receipt_ref']) ?><?php else: ?>—<?php endif; ?></td>
      <td><?php if (!empty($row['reconciled_bank_txn_id'])): ?><span class="badge badge-green">Reconciled</span><?php else: ?><span class="badge badge-grey">Not yet</span><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No expenses recorded yet.</div>
  <?php endif; ?>
</div>
