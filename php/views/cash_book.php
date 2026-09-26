<?php
/** @var array<string, mixed> $book */
/** @var array{cash: float, bank: float, note: string} $yearOpening */
/** @var bool $isAdmin */
/** @var bool $canSetOpening */
/** @var int $waitingCount */
/** @var array{source_year: string, next_year: string, cash: float, bank: float, error: ?string}|null $carry */
$lines = $book['lines'];
?>
<div class="kpi-grid">
  <div class="kpi-card"><div class="value"><?= e(money($book['opening_cash'], 2)) ?></div><div class="label">Opening cash</div></div>
  <div class="kpi-card"><div class="value"><?= e(money($book['opening_bank'], 2)) ?></div><div class="label">Opening bank</div></div>
  <div class="kpi-card good"><div class="value"><?= e(money($book['closing_cash'], 2)) ?></div><div class="label">Closing cash</div></div>
  <div class="kpi-card good"><div class="value"><?= e(money($book['closing_bank'], 2)) ?></div><div class="label">Closing bank</div></div>
</div>
<?php if (($waitingCount ?? 0) > 0): ?>
<p class="sub"><?= (int) $waitingCount === 1 ? '1 item is waiting for approval and is not in this balance.' : (int) $waitingCount . ' items are waiting for approval and are not in this balance.' ?> <a href="<?= e(url('approvals')) ?>">Open approvals</a>.</p>
<?php endif; ?>
<div class="panel">
  <h3>Cash book · <?= e($book['financial_year']) ?></h3>
  <form method="GET" action="<?= e(app_script()) ?>" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-bottom:14px;">
    <input type="hidden" name="r" value="cash-book">
    <div class="form-group"><label><?= e(t('common.from')) ?></label><input type="date" name="from" value="<?= e($book['from']) ?>"></div>
    <div class="form-group"><label><?= e(t('common.to')) ?></label><input type="date" name="to" value="<?= e($book['to']) ?>"></div>
    <button class="btn btn-outline btn-sm" type="submit"><?= e(t('common.show')) ?></button>
    <a class="btn btn-outline btn-sm" href="<?= e(url('day-book', ['from' => $book['from'], 'to' => $book['to']])) ?>">Day book</a>
  </form>
  <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
    Receipts and payments between these dates. The opening balance includes the year opening<?php if ($yearOpening['note'] !== ''): ?> (<?= e($yearOpening['note']) ?>)<?php endif; ?> plus anything earlier in <?= e($book['financial_year']) ?>.
  </p>
  <table class="data-table">
    <tr>
      <th><?= e(t('common.date')) ?></th><th><?= e(t('ui.particulars')) ?></th>
      <th class="text-right">Receipt cash</th><th class="text-right">Receipt bank</th>
      <th class="text-right">Payment cash</th><th class="text-right">Payment bank</th>
      <th><?= e(t('ui.entered_by')) ?></th>
    </tr>
    <tr>
      <td><?= e($book['from']) ?></td>
      <td>Opening balance</td>
      <td class="text-right"><?= e(money($book['opening_cash'], 2)) ?></td>
      <td class="text-right"><?= e(money($book['opening_bank'], 2)) ?></td>
      <td></td><td></td><td></td>
    </tr>
    <?php foreach ($lines as $line): ?>
    <tr>
      <td><?= e((string) $line['date']) ?></td>
      <td><?= e((string) $line['particulars']) ?></td>
      <td class="text-right"><?= (float) $line['receipt_cash'] > 0 ? e(money($line['receipt_cash'], 2)) : '' ?></td>
      <td class="text-right"><?= (float) $line['receipt_bank'] > 0 ? e(money($line['receipt_bank'], 2)) : '' ?></td>
      <td class="text-right"><?= (float) $line['payment_cash'] > 0 ? e(money($line['payment_cash'], 2)) : '' ?></td>
      <td class="text-right"><?= (float) $line['payment_bank'] > 0 ? e(money($line['payment_bank'], 2)) : '' ?></td>
      <td><?= e(dash((string) $line['entered_by'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr>
      <td><?= e($book['to']) ?></td>
      <td><strong>Closing balance</strong></td>
      <td class="text-right"><strong><?= e(money($book['closing_cash'], 2)) ?></strong></td>
      <td class="text-right"><strong><?= e(money($book['closing_bank'], 2)) ?></strong></td>
      <td></td><td></td><td></td>
    </tr>
  </table>
</div>
<div class="panel-row">
  <div class="panel">
    <h3>Cash deposited or withdrawn</h3>
    <form method="POST" action="<?= e(url('cash-book/contra')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="from" value="<?= e($book['from']) ?>">
      <input type="hidden" name="to" value="<?= e($book['to']) ?>">
      <div class="form-grid">
        <div class="form-group">
          <label><?= e(t('common.movement')) ?></label>
          <select name="direction">
            <option value="Deposit">Deposit cash into bank</option>
            <option value="Withdraw">Withdraw cash from bank</option>
          </select>
        </div>
        <div class="form-group"><label><?= e(t('common.amount')) ?></label><input type="number" step="0.01" min="0.01" name="amount" required></div>
        <div class="form-group"><label><?= e(t('common.date')) ?></label><input type="date" name="entry_date" value="<?= e($book['to']) ?>" required></div>
        <div class="form-group"><label><?= e(t('ui.note')) ?></label><input type="text" name="note" maxlength="255"></div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.submit_approval')) ?></button></div>
    </form>
  </div>
  <?php if ($canSetOpening): ?>
  <div class="panel">
    <h3>Opening balance for <?= e($book['financial_year']) ?></h3>
    <p class="sub">A change stays pending until another person approves it. These boxes show the last approved figures.</p>
    <form method="POST" action="<?= e(url('cash-book/opening')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="financial_year" value="<?= e($book['financial_year']) ?>">
      <input type="hidden" name="from" value="<?= e($book['from']) ?>">
      <input type="hidden" name="to" value="<?= e($book['to']) ?>">
      <div class="form-grid">
        <div class="form-group"><label>Cash in hand (₹)</label><input type="number" step="0.01" min="0" name="cash_amount" value="<?= e(number_format($yearOpening['cash'], 2, '.', '')) ?>" required></div>
        <div class="form-group"><label>Bank (₹)</label><input type="number" step="0.01" min="0" name="bank_amount" value="<?= e(number_format($yearOpening['bank'], 2, '.', '')) ?>" required></div>
        <div class="form-group full"><label><?= e(t('ui.note')) ?></label><input type="text" name="note" maxlength="255" value="<?= e($yearOpening['note']) ?>"></div>
      </div>
      <div class="form-actions"><button class="btn btn-outline" type="submit">Submit opening balance</button></div>
    </form>
    <?php if ($carry !== null): ?>
    <p class="sub">Closing for the whole of <?= e($carry['source_year']) ?> is cash <?= e(money($carry['cash'], 2)) ?> and bank <?= e(money($carry['bank'], 2)) ?>. Carrying those figures into <?= e($carry['next_year']) ?> waits for approval. The filtered dates above do not change what is carried.</p>
    <?php if ($carry['error'] === null): ?>
    <form method="POST" action="<?= e(url('cash-book/carry')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="financial_year" value="<?= e($carry['source_year']) ?>">
      <input type="hidden" name="from" value="<?= e($book['from']) ?>">
      <input type="hidden" name="to" value="<?= e($book['to']) ?>">
      <div class="form-actions"><button class="btn btn-primary" type="submit">Carry into <?= e($carry['next_year']) ?></button></div>
    </form>
    <?php else: ?>
    <p class="sub"><?= e($carry['error']) ?></p>
    <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
