<?php
/** @var string $from */
/** @var string $to */
/** @var string $financialYear */
/** @var list<array<string, mixed>> $heads */
/** @var array<string, mixed>|null $selected */
$received = 0.0;
$spent = 0.0;
foreach ($heads as $head) {
    $received += (float) $head['received'];
    $spent += (float) $head['spent'];
}
?>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($received, 2)) ?></div><div class="label">Received in this period</div></div>
  <div class="kpi-card danger"><div class="value"><?= e(money($spent, 2)) ?></div><div class="label">Spent in this period</div></div>
  <div class="kpi-card"><div class="value"><?= count($heads) ?></div><div class="label">Heads</div></div>
</div>
<div class="panel">
  <h3>Ledger by head · <?= e($financialYear) ?></h3>
  <nav class="book-nav" aria-label="Books">
    <a href="<?= e(url('cash-book', ['from' => $from, 'to' => $to])) ?>">Cash book</a>
    <a href="<?= e(url('day-book', ['from' => $from, 'to' => $to])) ?>">Day book</a>
    <a class="is-on" href="<?= e(url('ledger', ['from' => $from, 'to' => $to])) ?>">Ledger</a>
  </nav>
  <form class="filters" method="GET" action="<?= e(app_script()) ?>">
    <input type="hidden" name="r" value="ledger">
    <div class="form-group"><label><?= e(t('common.from')) ?></label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="form-group"><label><?= e(t('common.to')) ?></label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-primary" type="submit"><?= e(t('common.show')) ?></button>
  </form>
  <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
    Each donation purpose and each expense category is a head. Received money increases it. Spending decreases it. A gift and an expense with the same name share one balance.
  </p>
  <?php if ($heads === []): ?>
    <div class="empty-state">No receipts or payments in this period.</div>
  <?php else: ?>
  <table class="data-table">
    <tr>
      <th><?= e(t('ui.head')) ?></th>
      <th class="text-right"><?= e(t('ui.opening')) ?></th>
      <th class="text-right"><?= e(t('ui.received')) ?></th>
      <th class="text-right"><?= e(t('ui.spent')) ?></th>
      <th class="text-right"><?= e(t('ui.balance')) ?></th>
    </tr>
    <?php foreach ($heads as $head): ?>
    <tr>
      <td><a class="row-link" href="<?= e(url('ledger', ['from' => $from, 'to' => $to, 'head' => (string) $head['head']])) ?>"><?= e((string) $head['head']) ?></a></td>
      <td class="text-right"><?= e(money($head['opening'], 2)) ?></td>
      <td class="text-right"><?= e(money($head['received'], 2)) ?></td>
      <td class="text-right"><?= e(money($head['spent'], 2)) ?></td>
      <td class="text-right"><?= e(money($head['balance'], 2)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php if ($selected !== null): ?>
<div class="panel">
  <h3><?= e((string) $selected['head']) ?></h3>
  <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
    Opening <?= e(money($selected['opening'], 2)) ?> · Balance <?= e(money($selected['balance'], 2)) ?>
    <a class="text-link" href="<?= e(url('ledger', ['from' => $from, 'to' => $to])) ?>">All heads</a>
  </p>
  <?php if ($selected['lines'] === []): ?>
    <div class="empty-state">Nothing new in this period. The balance is the opening brought forward.</div>
  <?php else: ?>
  <table class="data-table">
    <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('ui.particulars')) ?></th><th class="text-right"><?= e(t('ui.received')) ?></th><th class="text-right"><?= e(t('ui.spent')) ?></th><th class="text-right"><?= e(t('ui.balance')) ?></th><th><?= e(t('ui.entered_by')) ?></th></tr>
    <?php foreach ($selected['lines'] as $line): ?>
    <tr>
      <td><?= e((string) $line['date']) ?></td>
      <td><?= e((string) $line['particulars']) ?></td>
      <td class="text-right"><?= (float) $line['received'] > 0 ? e(money($line['received'], 2)) : '' ?></td>
      <td class="text-right"><?= (float) $line['spent'] > 0 ? e(money($line['spent'], 2)) : '' ?></td>
      <td class="text-right"><?= e(money($line['balance'], 2)) ?></td>
      <td><?= e(dash((string) $line['entered_by'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
