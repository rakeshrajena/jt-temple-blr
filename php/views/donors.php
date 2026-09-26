<?php
/** @var list<array<string, mixed>> $donors */
/** @var string $from */
/** @var string $to */
/** @var string $query */
/** @var string $financialYear */
?>
<div class="panel">
  <h3>Devotees · <?= e($financialYear) ?></h3>
  <p class="sub">Open a name for every gift, the receipt, and what was promised against what was received. A pledge is not in the cash book.</p>
  <form method="GET" action="<?= e(app_script()) ?>" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-bottom:14px;">
    <input type="hidden" name="r" value="donors">
    <div class="form-group"><label>Name or phone</label><input type="text" name="q" value="<?= e($query) ?>"></div>
    <div class="form-group"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="form-group"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-outline btn-sm" type="submit">Show</button>
  </form>
  <?php if ($donors === []): ?>
    <p>No devotees match.</p>
  <?php else: ?>
  <table class="data-table">
    <tr><th>Name</th><th>Phone</th><th>PAN</th><th>Received</th><th>Still promised</th></tr>
    <?php foreach ($donors as $donor): ?>
      <tr>
        <td><a href="<?= e(url('donors/' . $donor['id'], ['from' => $from, 'to' => $to])) ?>"><?= e($donor['name']) ?></a></td>
        <td><?= e(dash($donor['phone'])) ?></td>
        <td><?= e(dash($donor['pan'])) ?></td>
        <td><?= e(money($donor['received'])) ?></td>
        <td><?= (float) $donor['outstanding'] > 0 ? e(money($donor['outstanding'])) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
