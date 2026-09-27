<?php
/** @var list<array<string,mixed>> $batches */
/** @var array<int, array{Valid:int,Redeemed:int,Expired:int,Invalid:int}> $couponCounts */
/** @var array<int, list<array{code:string,scanned_at:string,scanned_by:string}>> $couponScans */
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
<div class="reveal-group">
<div class="action-bar">
  <button class="btn btn-outline" type="button" data-reveal="reveal-coupon-record"><?= e(t('ui.record_coupon')) ?></button>
  <button class="btn btn-outline" type="button" data-reveal="reveal-coupon-invalid"><?= e(t('ui.invalidate_coupon')) ?></button>
  <button class="btn btn-outline" type="button" data-reveal="reveal-coupon-generate"><?= e(t('ui.generate_coupons')) ?></button>
</div>
<div class="panel reveal-panel" id="reveal-coupon-record" hidden>
  <h3><?= e(t('ui.record_coupon')) ?></h3>
  <p class="sub">Opening a coupon link while signed in records it at once. No extra choice is asked. It is a Cash donation under Coupon counter, and the purpose is Donation. Type a code here only when the devotee name or the payment mode should be different. A coupon past its expiry is invalidated and cannot be recorded. Scanning a coupon that is already in the books does not add it again.</p>
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
          <option value=""><?= e(t('ui.batch_payment')) ?></option>
          <?php foreach ($moneyModes as $mode): ?>
            <option value="<?= e($mode) ?>"><?= e($mode) ?></option>
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
</div>
<div class="panel reveal-panel" id="reveal-coupon-invalid" hidden>
  <h3><?= e(t('ui.invalidate_coupon')) ?></h3>
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
<div class="panel reveal-panel" id="reveal-coupon-generate" hidden>
  <h3><?= e(t('ui.generate_coupons')) ?></h3>
  <p class="sub"><?= e(t('ui.coupon_form_note', ['limit' => money(approval_limit('Treasurer'))])) ?></p>
  <form method="POST" action="<?= e(url('food/coupons')) ?>" data-coupon-expiry data-coupon-create>
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label for="coupon-name"><?= e(t('ui.coupon_name')) ?></label>
        <div class="suggest">
          <input id="coupon-name" type="text" name="coupon_name" maxlength="100" placeholder="e.g. Lunch Mahaprasad" required data-suggest data-kind="puja" data-source="puja-purposes" data-fill="cost:amount" data-open="focus" data-limit="30" autocomplete="off">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label for="coupon-cost"><?= e(t('ui.cost_per')) ?></label><input id="coupon-cost" type="number" step="0.01" min="0.01" name="cost" required></div>
      <div class="form-group"><label for="coupon-quantity"><?= e(t('common.quantity')) ?></label><input id="coupon-quantity" type="number" name="quantity" min="1" max="400" value="1" required></div>
      <div class="form-group">
        <label for="coupon-devotee"><?= e(t('ui.devotee_optional')) ?></label>
        <div class="suggest">
          <input id="coupon-devotee" type="text" name="donor_name" maxlength="150" placeholder="Coupon counter" data-suggest data-kind="donor" data-url="<?= e(url('api/donors/search')) ?>" data-fill="donor_phone:phone,donor_email:email,donor_address:address,donor_pan:pan_number" autocomplete="off">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label><?= e(t('ui.phone')) ?></label><input type="text" name="donor_phone" maxlength="20"></div>
      <div class="form-group"><label><?= e(t('ui.email')) ?></label><input type="email" name="donor_email" maxlength="120"></div>
      <div class="form-group"><label><?= e(t('ui.address')) ?></label><input type="text" name="donor_address" maxlength="500"></div>
      <div class="form-group"><label><?= e(t('ui.pan')) ?></label><input type="text" name="donor_pan" maxlength="10" autocapitalize="characters"></div>
      <div class="form-group">
        <label><?= e(t('ui.donation_type')) ?></label>
        <select name="donation_type">
          <option value=""><?= e(t('ui.leave_blank')) ?></option>
          <?php foreach (coupon_amount_types() as $type): ?>
            <option value="<?= e($type) ?>"><?= e($type) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= e(t('ui.payment_mode')) ?></label>
        <select name="payment_mode">
          <option value=""><?= e(t('ui.leave_blank')) ?></option>
          <?php foreach ($moneyModes as $mode): ?>
            <option value="<?= e($mode) ?>"><?= e($mode) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="coupon-purpose"><?= e(t('ui.purpose')) ?></label>
        <div class="suggest">
          <input id="coupon-purpose" type="text" name="purpose" maxlength="200" placeholder="Donation" data-suggest data-kind="puja" data-source="puja-purposes" data-open="focus" data-limit="30" autocomplete="off">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label><?= e(t('ui.upi_reference')) ?></label><input type="text" name="upi_reference" maxlength="64"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_no')) ?></label><input type="text" name="cheque_number" maxlength="30"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_date')) ?></label><input type="date" name="cheque_date"></div>
      <div class="form-group">
        <label class="check-line"><input type="checkbox" name="no_expiry" value="1" data-no-expiry checked> <?= e(t('ui.no_expiry')) ?></label>
      </div>
      <div class="form-group">
        <label><?= e(t('ui.expires')) ?></label>
        <input type="datetime-local" name="expires_at" data-expires>
      </div>
    </div>
    <p class="sub" data-coupon-mode data-one="<?= e(t('ui.coupon_one_ready')) ?>" data-batch="<?= e(t('ui.coupon_batch_waits')) ?>"></p>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit" data-coupon-submit data-one-label="<?= e(t('ui.generate_one')) ?>" data-batch-label="<?= e(t('ui.submit_batch')) ?>"><?= e(t('ui.generate_one')) ?></button>
    </div>
  </form>
</div>
</div>
<div class="panel">
  <h3>Coupon batches (<?= count($batches) ?>)</h3>
  <p class="sub">Edit the name, the cost, the quantity, or the expiry. A cost or quantity change goes back to approval. Removing a batch deletes its unused coupons. A batch with a sold coupon stays, because that income is already in the books. Serial numbers stay in their range, so a larger quantity is refused when it would overlap the next batch.</p>
  <?php if ($batches): ?>
  <div class="batch-list">
    <?php foreach ($batches as $b): ?>
    <?php
      $status = (string) ($b['approval_status'] ?? 'Waiting');
      $counts = $couponCounts[(int) $b['id']] ?? ['Valid' => 0, 'Redeemed' => 0, 'Expired' => 0, 'Invalid' => 0];
      $scans = $couponScans[(int) $b['id']] ?? [];
      $expiresLabel = coupon_expiry_label(isset($b['expires_at']) ? (string) $b['expires_at'] : null);
      $hasExpiry = $expiresLabel !== '';
      $badge = match ($status) {
          'Approved' => 'badge-green',
          'Waiting' => 'badge-amber',
          'Sent back' => 'badge-blue',
          'Rejected' => 'badge-red',
          default => 'badge-grey',
      };
    ?>
    <article class="batch-card">
      <div class="batch-head">
        <div>
          <strong><?= e($b['coupon_name']) ?></strong><?php if (trim((string) ($b['donor_name'] ?? '')) !== ''): ?> · <?= e((string) $b['donor_name']) ?><?php endif; ?>
          <p class="sub"><?= e($b['created_date']) ?> · <?= e(dash($b['created_by_name'])) ?><?= $hasExpiry ? ' · till ' . e($expiresLabel) : ' · ' . e(t('ui.no_expiry')) ?></p>
        </div>
        <span class="badge <?= e($badge) ?>"><?= e($status) ?></span>
      </div>
      <dl class="batch-facts">
        <div>
          <dt><?= e(t('ui.serial')) ?></dt>
          <dd><?= e(coupon_code((int) ($b['issued_unix'] ?? 0), (int) $b['start_sl_no'])) ?> – <?= e(coupon_code((int) ($b['issued_unix'] ?? 0), (int) $b['end_sl_no'])) ?></dd>
        </div>
        <div>
          <dt><?= e(t('ui.value')) ?></dt>
          <dd><?= e(money($b['cost'])) ?> each · <?= e(money($b['total_value'], 2)) ?> total</dd>
        </div>
        <div>
          <dt><?= e(t('common.status')) ?></dt>
          <dd><?= e((string) $b['quantity']) ?> coupons · <?= e((string) $counts['Valid']) ?> valid · <?= e((string) $counts['Redeemed']) ?> scanned · <?= e((string) $counts['Expired']) ?> expired · <?= e((string) $counts['Invalid']) ?> invalidated</dd>
        </div>
      </dl>
      <?php if ($scans === []): ?>
        <p class="sub">None scanned yet.</p>
      <?php else: ?>
        <details class="row-fold">
          <summary class="btn btn-sm btn-outline"><?= e((string) count($scans)) ?> scanned</summary>
          <table class="data-table fit">
            <tr><th>Coupon</th><th>When</th><th>By</th></tr>
            <?php foreach ($scans as $scan): ?>
              <tr>
                <td><?= e($scan['code']) ?></td>
                <td><?= e($scan['scanned_at'] !== '' ? $scan['scanned_at'] : '—') ?></td>
                <td><?= e($scan['scanned_by']) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </details>
      <?php endif; ?>
      <div class="batch-actions">
        <?php if ($status === 'Approved'): ?>
          <a href="<?= e(url('food/coupons/' . $b['id'] . '/print')) ?>" target="_blank" class="btn btn-sm btn-gold">Print PDF</a>
        <?php endif; ?>
        <?php if ($status !== 'Approved' || $role === 'Admin'): ?>
        <form method="POST" action="<?= e(url('food/coupons/' . $b['id'] . '/remove')) ?>" onsubmit="return confirm('Remove this batch and its unused coupons? Sold coupons stay in the books.');">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-outline" type="submit"><?= e(t('common.remove')) ?></button>
        </form>
        <?php endif; ?>
      </div>
      <details class="row-fold">
        <summary class="btn btn-sm btn-outline"><?= e(t('common.update')) ?></summary>
        <form class="cell-form" method="POST" action="<?= e(url('food/coupons/' . $b['id'])) ?>" data-coupon-expiry>
          <?= csrf_field() ?>
          <div class="form-grid cols-3">
            <div class="form-group">
              <label for="coupon-name-<?= e((string) $b['id']) ?>"><?= e(t('ui.coupon_name')) ?></label>
              <div class="suggest">
                <input id="coupon-name-<?= e((string) $b['id']) ?>" type="text" name="coupon_name" value="<?= e((string) $b['coupon_name']) ?>" maxlength="100" required data-suggest data-kind="puja" data-source="puja-purposes" data-fill="cost:amount" data-open="focus" data-limit="30" autocomplete="off">
                <div class="suggest-menu" hidden></div>
              </div>
            </div>
            <div class="form-group"><label><?= e(t('ui.value')) ?></label><input type="number" name="cost" step="0.01" min="0.01" value="<?= e(number_format((float) $b['cost'], 2, '.', '')) ?>" required></div>
            <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="quantity" min="1" max="400" value="<?= e((string) $b['quantity']) ?>" required></div>
            <div class="form-group"><label class="check-line"><input type="checkbox" name="no_expiry" value="1" data-no-expiry<?= $hasExpiry ? '' : ' checked' ?>> <?= e(t('ui.no_expiry')) ?></label></div>
            <div class="form-group"><label><?= e(t('ui.expires')) ?></label><input type="datetime-local" name="expires_at" data-expires value="<?= e($expiryInput($b['expires_at'] ?? '')) ?>"></div>
          </div>
          <details>
            <summary><?= e(t('ui.coupon_gift')) ?></summary>
            <div class="form-grid cols-3">
              <div class="form-group">
                <label for="coupon-devotee-<?= e((string) $b['id']) ?>"><?= e(t('ui.devotee_optional')) ?></label>
                <div class="suggest">
                  <input id="coupon-devotee-<?= e((string) $b['id']) ?>" type="text" name="donor_name" maxlength="150" value="<?= e((string) ($b['donor_name'] ?? '')) ?>" placeholder="Coupon counter" data-suggest data-kind="donor" data-url="<?= e(url('api/donors/search')) ?>" data-fill="donor_phone:phone,donor_email:email,donor_address:address,donor_pan:pan_number" autocomplete="off">
                  <div class="suggest-menu" hidden></div>
                </div>
              </div>
              <div class="form-group"><label><?= e(t('ui.phone')) ?></label><input type="text" name="donor_phone" maxlength="20" value="<?= e((string) ($b['donor_phone'] ?? '')) ?>"></div>
              <div class="form-group"><label><?= e(t('ui.email')) ?></label><input type="email" name="donor_email" maxlength="120" value="<?= e((string) ($b['donor_email'] ?? '')) ?>"></div>
              <div class="form-group"><label><?= e(t('ui.address')) ?></label><input type="text" name="donor_address" maxlength="500" value="<?= e((string) ($b['donor_address'] ?? '')) ?>"></div>
              <div class="form-group"><label><?= e(t('ui.pan')) ?></label><input type="text" name="donor_pan" maxlength="10" value="<?= e((string) ($b['donor_pan'] ?? '')) ?>"></div>
              <div class="form-group"><label><?= e(t('ui.donation_type')) ?></label>
                <select name="donation_type">
                  <option value=""><?= e(t('ui.donation_type')) ?></option>
                  <?php foreach (coupon_amount_types() as $type): ?>
                    <option value="<?= e($type) ?>"<?= (string) ($b['donation_type'] ?? '') === $type ? ' selected' : '' ?>><?= e($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label><?= e(t('ui.payment_mode')) ?></label>
                <select name="payment_mode">
                  <option value=""><?= e(t('ui.payment_mode')) ?></option>
                  <?php foreach ($moneyModes as $mode): ?>
                    <option value="<?= e($mode) ?>"<?= (string) ($b['payment_mode'] ?? '') === $mode ? ' selected' : '' ?>><?= e($mode) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="coupon-purpose-<?= e((string) $b['id']) ?>"><?= e(t('ui.purpose')) ?></label>
                <div class="suggest">
                  <input id="coupon-purpose-<?= e((string) $b['id']) ?>" type="text" name="purpose" maxlength="200" value="<?= e((string) ($b['purpose'] ?? '')) ?>" placeholder="Donation" data-suggest data-kind="puja" data-source="puja-purposes" data-open="focus" data-limit="30" autocomplete="off">
                  <div class="suggest-menu" hidden></div>
                </div>
              </div>
              <div class="form-group"><label><?= e(t('ui.upi_reference')) ?></label><input type="text" name="upi_reference" maxlength="64" value="<?= e((string) ($b['upi_reference'] ?? '')) ?>"></div>
              <div class="form-group"><label><?= e(t('ui.cheque_no')) ?></label><input type="text" name="cheque_number" maxlength="30" value="<?= e((string) ($b['cheque_number'] ?? '')) ?>"></div>
              <div class="form-group"><label><?= e(t('ui.cheque_date')) ?></label><input type="date" name="cheque_date" value="<?= e(substr((string) ($b['cheque_date'] ?? ''), 0, 10)) ?>"></div>
            </div>
          </details>
          <div class="form-actions"><button class="btn btn-sm btn-primary" type="submit"><?= e(t('common.save')) ?></button></div>
        </form>
      </details>
    </article>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state">No coupon batches yet — generate the first one above.</div>
  <?php endif; ?>
</div>
<script type="application/json" id="puja-purposes"><?= json_encode(puja_purposes(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
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
document.querySelectorAll('form[data-coupon-create]').forEach(function (form) {
  var quantity = form.querySelector('[name="quantity"]');
  var button = form.querySelector('[data-coupon-submit]');
  var note = form.querySelector('[data-coupon-mode]');
  if (!quantity || !button || !note) return;
  var syncMode = function () {
    var one = Number(quantity.value) === 1;
    button.textContent = one ? button.getAttribute('data-one-label') : button.getAttribute('data-batch-label');
    note.textContent = one ? note.getAttribute('data-one') : note.getAttribute('data-batch');
  };
  quantity.addEventListener('input', syncMode);
  syncMode();
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
