<?php
/** @var array<string, mixed> $book */
/** @var list<array{date: string, particulars: string, entry: string, amount: float, entered_by: string}> $rows */
?>
<div class="panel">
  <h3>Day book · <?= e($book['financial_year']) ?></h3>
  <form method="GET" action="<?= e(app_script()) ?>" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-bottom:14px;">
    <input type="hidden" name="r" value="day-book">
    <div class="form-group"><label><?= e(t('common.from')) ?></label><input type="date" name="from" value="<?= e($book['from']) ?>"></div>
    <div class="form-group"><label><?= e(t('common.to')) ?></label><input type="date" name="to" value="<?= e($book['to']) ?>"></div>
    <button class="btn btn-outline btn-sm" type="submit"><?= e(t('common.show')) ?></button>
    <a class="btn btn-outline btn-sm" href="<?= e(url('cash-book', ['from' => $book['from'], 'to' => $book['to']])) ?>">Cash book</a>
  </form>
  <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">Every receipt, payment, and cash-to-bank movement in this period, with the person who entered it.</p>
  <?php if ($rows === []): ?>
    <div class="empty-state">No entries in this period.</div>
  <?php else: ?>
  <table class="data-table">
    <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('ui.particulars')) ?></th><th>Entry</th><th class="text-right">Amount</th><th><?= e(t('ui.entered_by')) ?></th></tr>
    <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e($row['date']) ?></td>
      <td><?= e($row['particulars']) ?></td>
      <td><?= e($row['entry']) ?></td>
      <td class="text-right"><?= e(money($row['amount'], 2)) ?></td>
      <td><?= e(dash($row['entered_by'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
