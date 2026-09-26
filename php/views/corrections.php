<?php
/** @var list<array{value: string, label: string}> $targets */
/** @var list<array<string, mixed>> $rows */
/** @var string $today */
$badge = static function (string $status): string {
    return match ($status) {
        'Approved' => 'badge-green',
        'Waiting' => 'badge-amber',
        'Sent back' => 'badge-blue',
        'Rejected' => 'badge-red',
        default => 'badge-grey',
    };
};
?>
<div class="panel">
  <h3><?= e(t('ui.correct_line')) ?></h3>
  <p class="sub">The original donation or expense stays in the book. The correction is a second line, and it changes the balance only after someone else approves it.</p>
  <?php if ($targets === []): ?>
    <p>There is no cash or bank line that can be corrected.</p>
  <?php else: ?>
  <form method="POST" action="<?= e(url('corrections')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group full">
        <label>Line</label>
        <select name="target" required>
          <?php foreach ($targets as $target): ?>
            <option value="<?= e($target['value']) ?>"><?= e($target['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Change</label>
        <select name="kind" id="correction_kind">
          <option value="void">Void the line</option>
          <option value="adjust">Set a new amount</option>
        </select>
      </div>
      <div class="form-group" id="corrected_amount_group">
        <label>New amount (₹)</label>
        <input type="number" step="0.01" min="0" name="corrected_amount" value="0">
      </div>
      <div class="form-group">
        <label><?= e(t('common.date')) ?></label>
        <input type="date" name="entry_date" value="<?= e($today) ?>" required>
      </div>
      <div class="form-group full">
        <label>Reason</label>
        <input type="text" name="reason" maxlength="500" required placeholder="Why this line is wrong">
      </div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.submit_approval')) ?></button></div>
  </form>
  <?php endif; ?>
</div>
<div class="panel">
  <h3>Corrections</h3>
  <?php if ($rows === []): ?>
    <p>No corrections yet.</p>
  <?php else: ?>
  <table class="data-table">
    <tr><th><?= e(t('common.date')) ?></th><th>Line</th><th>Was</th><th>Now</th><th>Reason</th><th>Prepared by</th><th><?= e(t('common.status')) ?></th></tr>
    <?php foreach ($rows as $row): ?>
      <?php
        $who = (string) $row['subject_type'] === 'expense'
            ? trim(((string) ($row['voucher_number'] ?? '')) . ' ' . ((string) ($row['paid_to'] ?? '')))
            : (string) ($row['donor_name'] ?? 'Donation');
      ?>
      <tr>
        <td><?= e((string) $row['entry_date']) ?></td>
        <td><?= e(ucfirst((string) $row['subject_type']) . ' · ' . ($who !== '' ? $who : 'Entry')) ?></td>
        <td><?= e(money($row['original_amount'])) ?></td>
        <td><?= e(money($row['corrected_amount'])) ?></td>
        <td><?= e((string) $row['reason']) ?></td>
        <td><?= e((string) $row['prepared_name']) ?></td>
        <td><span class="badge <?= e($badge((string) $row['status'])) ?>"><?= e((string) $row['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<script>
(function () {
  const kind = document.getElementById('correction_kind');
  const group = document.getElementById('corrected_amount_group');
  if (!kind || !group) return;
  function toggle() { group.style.display = kind.value === 'adjust' ? '' : 'none'; }
  kind.addEventListener('change', toggle);
  toggle();
})();
</script>
