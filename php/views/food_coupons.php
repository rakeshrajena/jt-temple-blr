<?php
/** @var list<array<string,mixed>> $batches */
/** @var array<int, array{Valid:int,Redeemed:int,Expired:int,Invalid:int}> $couponCounts */
/** @var list<string> $moneyModes */
/** @var float $totalCouponsValue */
/** @var float $couponIncome */
/** @var int $totalCouponQty */
/** @var string $role */

$expiryInput = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    $stamp = strtotime($raw);
    return $stamp === false ? '' : date('Y-m-d\TH:i', $stamp);
};
?>
<a href="<?= e(url('food')) ?>" class="btn btn-outline btn-sm" style="margin-bottom:16px;">← Back to Food Stock</a>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($couponIncome)) ?></div><div class="label">Coupon income in the books</div></div>
  <div class="kpi-card"><div class="value"><?= e(money($totalCouponsValue)) ?></div><div class="label">Approved face value</div></div>
  <div class="kpi-card"><div class="value"><?= count($batches) ?></div><div class="label">Batches</div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $totalCouponQty) ?></div><div class="label">Approved coupons</div></div>
</div>
<div class="panel">
  <h3><?= e(t('ui.record_coupon')) ?></h3>
  <p class="sub">Scan the QR code or type the code. Recording a sold coupon adds its amount as a donation and posts it to the cash book or the bank book. Leave the devotee name blank to record it under Coupon counter. A coupon past its expiry is invalidated and cannot be recorded.</p>
  <form method="POST" action="<?= e(url('food/coupons/validate')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label for="coupon-code"><?= e(t('ui.coupon_code')) ?></label>
        <input id="coupon-code" type="text" name="code" placeholder="CU-…" required autocomplete="off">
      </div>
      <div class="form-group">
        <label><?= e(t('ui.devotee_optional')) ?></label>
        <input type="text" name="donor_name" maxlength="150" placeholder="Coupon counter">
      </div>
      <div class="form-group">
        <label><?= e(t('ui.payment_mode')) ?></label>
        <select name="payment_mode">
          <?php foreach ($moneyModes as $mode): ?>
            <option value="<?= e($mode) ?>"<?= $mode === 'Cash' ? ' selected' : '' ?>><?= e($mode) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= e(t('ui.upi_reference')) ?></label>
        <input type="text" name="upi_reference" maxlength="64" placeholder="Required for UPI">
      </div>
      <div class="form-group">
        <label><?= e(t('ui.cheque_no')) ?></label>
        <input type="text" name="cheque_number" maxlength="30">
      </div>
      <div class="form-group">
        <label><?= e(t('ui.cheque_date')) ?></label>
        <input type="date" name="cheque_date">
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= e(t('ui.record_income')) ?></button>
      <button class="btn btn-outline" type="button" id="coupon-scan" hidden>Scan QR</button>
    </div>
  </form>
  <form method="POST" action="<?= e(url('food/coupons/invalidate')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label><?= e(t('ui.invalidate_coupon')) ?></label>
        <input type="text" name="code" placeholder="CU-…" required autocomplete="off">
      </div>
    </div>
    <p class="sub">Invalidating a coupon does not add income. A coupon is invalidated on its own once the expiry time has passed.</p>
    <div class="form-actions"><button class="btn btn-outline" type="submit"><?= e(t('ui.invalidate_coupon')) ?></button></div>
  </form>
</div>
<div class="panel">
  <h3><?= e(t('ui.generate_batch')) ?></h3>
  <p class="sub">A batch is a print run. Each coupon is stored on its own. The face value waits for approval and does not enter the books until a coupon is sold. Choose an expiry, or leave no expiry. A Treasurer can approve up to ₹<?= e(number_format(TREASURER_APPROVAL_LIMIT, 0)) ?>. Above that, an Admin decides. The person who prepared the batch cannot approve it. It can be printed only after approval.</p>
  <form method="POST" action="<?= e(url('food/coupons')) ?>" data-coupon-expiry>
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label><?= e(t('ui.coupon_name')) ?></label><input type="text" name="coupon_name" placeholder="e.g. Lunch Mahaprasad" required></div>
      <div class="form-group"><label><?= e(t('ui.cost_per')) ?></label><input type="number" step="0.01" min="0.01" name="cost" required></div>
      <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="quantity" min="1" max="400" required></div>
      <div class="form-group">
        <label class="check-line"><input type="checkbox" name="no_expiry" value="1" data-no-expiry checked> <?= e(t('ui.no_expiry')) ?></label>
      </div>
      <div class="form-group">
        <label><?= e(t('ui.expires')) ?></label>
        <input type="datetime-local" name="expires_at" data-expires>
      </div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.submit_batch')) ?></button></div>
  </form>
</div>
<div class="panel">
  <h3>Coupon batches (<?= count($batches) ?>)</h3>
  <p class="sub">Edit the name, the cost, the quantity, or the expiry. A cost or quantity change goes back to approval. Removing a batch deletes its unused coupons. A batch with a sold coupon stays, because that income is already in the books. Serial numbers stay in their range, so a larger quantity is refused when it would overlap the next batch.</p>
  <?php if ($batches): ?>
  <table class="data-table">
    <tr><th><?= e(t('ui.coupon_name')) ?></th><th><?= e(t('ui.serial')) ?></th><th><?= e(t('ui.value')) ?></th><th><?= e(t('common.approval')) ?></th><th><?= e(t('common.update')) ?></th><th></th></tr>
    <?php foreach ($batches as $b): ?>
    <?php
      $status = (string) ($b['approval_status'] ?? 'Waiting');
      $counts = $couponCounts[(int) $b['id']] ?? ['Valid' => 0, 'Redeemed' => 0, 'Expired' => 0, 'Invalid' => 0];
      $expiresLabel = coupon_expiry_label(isset($b['expires_at']) ? (string) $b['expires_at'] : null);
      $hasExpiry = $expiresLabel !== '';
    ?>
    <tr>
      <td>
        <?= e($b['coupon_name']) ?><br>
        <span style="color:var(--ink-soft);font-size:12px;"><?= e($b['created_date']) ?> · <?= e(dash($b['created_by_name'])) ?><?= $hasExpiry ? ' · till ' . e($expiresLabel) : ' · ' . e(t('ui.no_expiry')) ?></span>
      </td>
      <td><?= e(coupon_code((int) ($b['issued_unix'] ?? 0), (int) $b['start_sl_no'])) ?> – <?= e(coupon_code((int) ($b['issued_unix'] ?? 0), (int) $b['end_sl_no'])) ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e((string) $b['quantity']) ?> coupons · <?= e((string) $counts['Valid']) ?> valid · <?= e((string) $counts['Redeemed']) ?> sold · <?= e((string) $counts['Expired']) ?> expired · <?= e((string) $counts['Invalid']) ?> invalidated</span></td>
      <td><?= e(money($b['cost'])) ?> each<br><?= e(money($b['total_value'], 2)) ?> total</td>
      <td>
        <?php
          $badge = match ($status) {
              'Approved' => 'badge-green',
              'Waiting' => 'badge-amber',
              'Sent back' => 'badge-blue',
              'Rejected' => 'badge-red',
              default => 'badge-grey',
          };
        ?>
        <span class="badge <?= e($badge) ?>"><?= e($status) ?></span>
      </td>
      <td>
        <form method="POST" action="<?= e(url('food/coupons/' . $b['id'])) ?>" data-coupon-expiry>
          <?= csrf_field() ?>
          <input type="text" name="coupon_name" value="<?= e((string) $b['coupon_name']) ?>" maxlength="100" required>
          <input type="number" name="cost" step="0.01" min="0.01" value="<?= e(number_format((float) $b['cost'], 2, '.', '')) ?>" required>
          <input type="number" name="quantity" min="1" max="400" value="<?= e((string) $b['quantity']) ?>" required>
          <label class="check-line"><input type="checkbox" name="no_expiry" value="1" data-no-expiry<?= $hasExpiry ? '' : ' checked' ?>> <?= e(t('ui.no_expiry')) ?></label>
          <input type="datetime-local" name="expires_at" data-expires value="<?= e($expiryInput($b['expires_at'] ?? '')) ?>">
          <button class="btn btn-sm btn-outline" type="submit"><?= e(t('common.save')) ?></button>
        </form>
      </td>
      <td>
        <?php if ($status === 'Approved'): ?>
          <a href="<?= e(url('food/coupons/' . $b['id'] . '/print')) ?>" target="_blank" class="btn btn-sm btn-gold">Print PDF</a>
        <?php endif; ?>
        <?php if ($status !== 'Approved' || $role === 'Admin'): ?>
        <form method="POST" action="<?= e(url('food/coupons/' . $b['id'] . '/remove')) ?>" onsubmit="return confirm('Remove this batch and its unused coupons? Sold coupons stay in the books.');">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-outline" type="submit"><?= e(t('common.remove')) ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No coupon batches yet — generate the first one above.</div>
  <?php endif; ?>
</div>
<script>
document.querySelectorAll('form[data-coupon-expiry]').forEach(function (form) {
  var box = form.querySelector('[data-no-expiry]');
  var field = form.querySelector('[data-expires]');
  if (!box || !field) return;
  var sync = function () {
    field.disabled = box.checked;
    if (box.checked) field.removeAttribute('required');
    else field.setAttribute('required', 'required');
  };
  box.addEventListener('change', sync);
  sync();
});
var scanButton = document.getElementById('coupon-scan');
if (scanButton && 'BarcodeDetector' in window) {
  scanButton.hidden = false;
  scanButton.addEventListener('click', function () {
    var input = document.getElementById('coupon-code');
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
      var video = document.createElement('video');
      video.setAttribute('playsinline', 'playsinline');
      video.style.width = '100%';
      video.style.maxWidth = '360px';
      video.style.borderRadius = '8px';
      scanButton.insertAdjacentElement('afterend', video);
      video.srcObject = stream;
      return video.play().then(function () {
        var detector = new BarcodeDetector({ formats: ['qr_code'] });
        var timer = window.setInterval(function () {
          detector.detect(video).then(function (codes) {
            if (!codes.length) return;
            var raw = codes[0].rawValue || '';
            var found = raw.match(/CU-\d{9,12}-\d{4,6}/);
            input.value = found ? found[0] : raw;
            window.clearInterval(timer);
            stream.getTracks().forEach(function (track) { track.stop(); });
            video.remove();
          }).catch(function () {});
        }, 400);
      });
    }).catch(function () {
      scanButton.insertAdjacentHTML('afterend', '<p class="sub">The camera could not be opened. Type the code instead.</p>');
    });
  });
}
</script>
