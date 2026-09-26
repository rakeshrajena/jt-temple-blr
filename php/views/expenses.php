<?php /** @var list<array<string,mixed>> $expenses */ /** @var list<string> $categories */ /** @var string $today */ ?>
<div class="panel">
  <h3>Record an Expense</h3>
  <form method="POST" action="<?= e(url('expenses')) ?>">
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
          <option>Cash</option><option>Bank Transfer</option><option>UPI</option><option>Cheque</option>
        </select>
      </div>
      <div class="form-group"><label>Receipt / Invoice Ref</label><input type="text" name="receipt_ref"></div>
      <div class="form-group full"><label>Description</label><textarea name="description" rows="2"></textarea></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Record Expense</button></div>
  </form>
</div>
<div class="panel">
  <h3>All Expenses (<?= count($expenses) ?>)</h3>
  <?php if ($expenses): ?>
  <table class="data-table">
    <tr><th>Date</th><th>Category</th><th>Description</th><th>Paid To</th><th>Amount</th><th>Payment</th><th>Bank Match</th></tr>
    <?php foreach ($expenses as $row): ?>
    <tr>
      <td><?= e($row['expense_date']) ?></td>
      <td><?= e($row['category']) ?></td>
      <td><?= e(dash($row['description'])) ?></td>
      <td><?= e(dash($row['paid_to'])) ?></td>
      <td><?= e(money($row['amount'])) ?></td>
      <td><?= e($row['payment_mode']) ?></td>
      <td><?php if (!empty($row['reconciled_bank_txn_id'])): ?><span class="badge badge-green">Reconciled</span><?php else: ?><span class="badge badge-grey">Not yet</span><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No expenses recorded yet.</div>
  <?php endif; ?>
</div>
