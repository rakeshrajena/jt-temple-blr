<?php
/** @var array<string, mixed> $statement */
/** @var string $today */
/** @var ?string $deleteReason */
$lines = $statement['lines'];
$pledges = $statement['pledges'];
?>
<div class="print-header">
  <img src="<?= e(asset('logo.svg')) ?>" alt="" class="print-logo">
  <h1><?= e(APP_NAME) ?></h1>
  <p><?= e(APP_PLACE) ?> · Yearly statement <?= e($statement['financial_year']) ?></p>
</div>
<div class="panel">
  <h3><?= e($statement['name']) ?></h3>
  <p class="sub">
    <?= e(dash($statement['phone'])) ?>
    <?php if ($statement['email'] !== ''): ?> · <?= e($statement['email']) ?><?php endif; ?>
    <?php if ($statement['pan'] !== ''): ?> · PAN <?= e($statement['pan']) ?><?php endif; ?>
    <?php if ($statement['address'] !== ''): ?><br><?= e($statement['address']) ?><?php endif; ?>
  </p>
  <div class="no-print" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-bottom:14px;">
    <form method="GET" action="<?= e(app_script()) ?>" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
      <input type="hidden" name="r" value="donors/<?= e((string) $statement['donor_id']) ?>">
      <div class="form-group"><label>From</label><input type="date" name="from" value="<?= e($statement['from']) ?>"></div>
      <div class="form-group"><label>To</label><input type="date" name="to" value="<?= e($statement['to']) ?>"></div>
      <button class="btn btn-outline btn-sm" type="submit">Show</button>
    </form>
    <a class="btn btn-outline btn-sm" href="<?= e(url('donors', ['from' => $statement['from'], 'to' => $statement['to']])) ?>">All devotees</a>
    <button class="btn btn-outline btn-sm" type="button" onclick="window.print()">Print statement</button>
  </div>
  <form method="POST" action="<?= e(url('donors/' . (int) $statement['donor_id'] . '/save')) ?>" class="no-print" style="margin-bottom:16px;">
    <?= csrf_field() ?>
    <h3>Details</h3>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Name</label><input type="text" name="name" maxlength="150" required value="<?= e($statement['name']) ?>"></div>
      <div class="form-group"><label>Phone</label><input type="text" name="phone" maxlength="20" value="<?= e($statement['phone']) ?>"></div>
      <div class="form-group"><label>Email</label><input type="text" name="email" maxlength="120" value="<?= e($statement['email']) ?>"></div>
      <div class="form-group"><label>Address</label><input type="text" name="address" maxlength="500" value="<?= e($statement['address']) ?>"></div>
      <div class="form-group"><label>PAN</label><input type="text" name="pan" maxlength="10" value="<?= e($statement['pan']) ?>" placeholder="ABCDE1234F"></div>
    </div>
    <div class="form-actions"><button class="btn btn-gold btn-sm" type="submit">Save details</button></div>
  </form>
  <?php if ($deleteReason === null): ?>
  <form method="POST" action="<?= e(url('donors/' . (int) $statement['donor_id'] . '/delete')) ?>" class="no-print" id="deleteDevotee" style="margin-bottom:16px;">
    <?= csrf_field() ?>
    <button class="btn btn-outline btn-sm" type="submit">Remove devotee</button>
  </form>
  <?php else: ?>
  <p class="no-print" style="color:var(--ink-soft); font-size:13px;"><?= e($deleteReason) ?> Gifts and receipts stay in the books.</p>
  <?php endif; ?>
  <div class="kpi-grid">
    <div class="kpi-card good"><div class="value"><?= e(money($statement['received'])) ?></div><div class="label">Received this period</div></div>
  </div>
  <h3>Gifts</h3>
  <?php if ($lines === []): ?>
    <p>No gifts in this period.</p>
  <?php else: ?>
  <table class="data-table">
    <tr><th>Date</th><th>Particulars</th><th>Mode</th><th>Amount</th><th>Receipt</th></tr>
    <?php foreach ($lines as $line): ?>
      <tr>
        <td><?= e($line['date']) ?></td>
        <td><?= e($line['purpose']) ?><?php if ($line['note'] !== ''): ?> — <?= e($line['note']) ?><?php endif; ?><?php if (!$line['in_book']): ?> <span class="badge badge-grey">Not in cash book</span><?php endif; ?></td>
        <td><?= e(dash($line['payment_mode'])) ?></td>
        <td><?= e(money($line['amount'])) ?></td>
        <td>
          <?php $link = receipt_link($line['receipt_number']); ?>
          <?= $link !== '' ? $link : '—' ?>
          <?= $line['receipt_cancelled'] ? receipt_cancel_badge(1) : '' ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<div class="panel">
  <h3>Pledge and received</h3>
  <p class="sub">A pledge is a promise. Only the amount received enters the cash book.</p>
  <?php if ($pledges === []): ?>
    <p>No pledge in this period, and nothing is still promised.</p>
  <?php else: ?>
  <table class="data-table">
    <tr><th>Date</th><th>Purpose</th><th>Promised</th><th>Received</th><th>Still to come</th><th class="no-print"></th></tr>
    <?php foreach ($pledges as $pledge): ?>
      <tr>
        <td><?= e($pledge['date']) ?></td>
        <td><?= e($pledge['purpose']) ?><?php if ($pledge['note'] !== ''): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e($pledge['note']) ?></span><?php endif; ?></td>
        <td><?= e(money($pledge['pledged'])) ?></td>
        <td><?= e(money($pledge['received'])) ?></td>
        <td><?= (float) $pledge['outstanding'] > 0 ? e(money($pledge['outstanding'])) : 'Received in full' ?></td>
        <td class="no-print">
          <?php if ((float) $pledge['outstanding'] > 0): ?>
          <form method="POST" action="<?= e(url('donors/' . (int) $statement['donor_id'] . '/receive')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="pledge_id" value="<?= e((string) $pledge['id']) ?>">
            <input type="number" step="0.01" min="0.01" name="amount" placeholder="Received" required style="width:110px;">
            <input type="date" name="donation_date" value="<?= e($today) ?>" required>
            <select name="payment_mode">
              <option>Cash</option><option>Bank Transfer</option><option>UPI</option><option>Cheque</option>
            </select>
            <input type="text" name="upi_reference" maxlength="64" placeholder="UPI id" style="width:120px;">
            <input type="text" name="cheque_number" maxlength="30" placeholder="Cheque no." style="width:110px;">
            <input type="date" name="cheque_date">
            <button class="btn btn-sm btn-primary" type="submit">Receive</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <form method="POST" action="<?= e(url('donors/' . (int) $statement['donor_id'] . '/pledge')) ?>" class="no-print" style="margin-top:16px;">
    <?= csrf_field() ?>
    <h3>Record a pledge</h3>
    <div class="form-grid">
      <div class="form-group"><label>Purpose</label><input type="text" name="purpose" maxlength="200" required placeholder="Annadaan, construction, festival"></div>
      <div class="form-group"><label>Amount promised (₹)</label><input type="number" step="0.01" min="0.01" name="pledged_amount" required></div>
      <div class="form-group"><label>Date</label><input type="date" name="pledge_date" value="<?= e($today) ?>" required></div>
      <div class="form-group"><label>Note</label><input type="text" name="note" maxlength="255"></div>
    </div>
    <div class="form-actions"><button class="btn btn-outline" type="submit">Save pledge</button></div>
  </form>
</div>
<script>
(function () {
  const form = document.getElementById('deleteDevotee');
  if (!form) return;
  form.addEventListener('submit', function (event) {
    if (!window.confirm('Remove this devotee? The name, phone, and email will be deleted.')) {
      event.preventDefault();
    }
  });
})();
</script>
