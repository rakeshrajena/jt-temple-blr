<?php
/**
 * @var array $b @var array $items @var array $payments @var array $notifications @var array $audit
 * @var array $slotOptions @var string $devoteeLink @var array $errors @var ?string $openAction
 */
$open = in_array($b['booking_status'], ['pending', 'confirmed'], true);
$pay = $b['payment_status'];
$submitted = array_values(array_filter($payments, fn ($p) => $p['status'] === 'submitted'));
$act = function (string $name, string $title, string $tone = '') use ($openAction) {
    return '<details class="action ' . $tone . '" id="act-' . $name . '"' . ($openAction === $name ? ' open' : '') . '><summary>' . e($title) . '</summary>';
};
$err = fn (string $k) => !empty($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$hidden = fn (string $a) => csrf_field() . '<input type="hidden" name="action" value="' . $a . '">';
$actionUrl = url('/admin/bookings/' . $b['booking_no'] . '/action');
?>
<p><a href="<?= e(url('/admin/bookings')) ?>">← All bookings</a></p>
<div class="row-between">
  <div>
    <h1 class="mono"><?= e($b['booking_no']) ?></h1>
    <p class="muted">Invoice <?= e($b['invoice_no']) ?> · booked <?= e(fmt_datetime($b['created_at'])) ?><?= $b['booking_type'] === 'food_only' ? ' · Food-only booking' : '' ?></p>
  </div>
  <div class="actions-inline">
    <a class="btn" href="<?= e(url('/admin/bookings/' . $b['booking_no'] . '/invoice.pdf')) ?>" target="_blank" rel="noopener">Invoice PDF</a>
    <button type="button" class="btn" data-copy="<?= e($devoteeLink) ?>">Copy devotee link</button>
  </div>
</div>

<div class="status-strip">
  <div><span class="muted small">Booking</span><?= badge('booking', $b['booking_status']) ?></div>
  <div><span class="muted small">Payment</span><?= badge('payment', $pay) ?></div>
  <div><span class="muted small">Approval</span><?= badge('approval', $b['approval_status']) ?></div>
  <div><span class="muted small">Total</span><strong><?= e(money((int) $b['total_paise'])) ?></strong></div>
  <?php if ($b['payment_ref']): ?><div><span class="muted small">Payment ref</span><strong class="mono"><?= e($b['payment_ref']) ?></strong></div><?php endif; ?>
</div>

<div class="cols">
<section class="panel">
  <h2>Devotee & service</h2>
  <dl class="kv">
    <dt>Name</dt><dd><?= e($b['devotee_name']) ?></dd>
    <dt>Mobile</dt><dd><a href="tel:+<?= e($b['mobile']) ?>"><?= e(fmt_mobile($b['mobile'])) ?></a> · <a href="https://wa.me/<?= e($b['mobile']) ?>" target="_blank" rel="noopener">WhatsApp</a></dd>
    <?php if ($b['email']): ?><dt>Email</dt><dd><a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a></dd><?php endif; ?>
    <dt>Service date</dt><dd><strong><?= e(fmt_date($b['service_date'])) ?></strong></dd>
    <?php if ($b['instructions']): ?><dt>Instructions</dt><dd class="pre"><?= e($b['instructions']) ?></dd><?php endif; ?>
    <?php if ($b['paid_at']): ?><dt>Paid / verified</dt><dd><?= e(fmt_datetime($b['paid_at'])) ?></dd><?php endif; ?>
  </dl>
  <table class="table compact">
    <thead><tr><th>Item</th><th class="num">Rate</th><th class="num">Qty</th><th class="num">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr><td><strong><?= e($it['name']) ?></strong>
        <?php if ($it['slot_label']): ?><br><small>Time: <?= e($it['slot_label']) ?></small><?php endif; ?>
        <?php if ($it['participants']): ?><br><small>Participants: <?= (int) $it['participants'] ?></small><?php endif; ?>
        <?php foreach ($it['details'] as $d): ?><br><small><?= e($d['label']) ?>: <?= e($d['value']) ?></small><?php endforeach; ?>
      </td><td class="num"><?= e(money((int) $it['unit_price_paise'])) ?></td><td class="num"><?= (int) $it['quantity'] ?></td><td class="num"><?= e(money((int) $it['amount_paise'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th colspan="3">Subtotal</th><td class="num"><?= e(money((int) $b['subtotal_paise'])) ?></td></tr>
      <tr><th colspan="3"><?= e($b['additional_label'] ?: 'Additional charges') ?></th><td class="num"><?= e(money((int) $b['additional_paise'])) ?></td></tr>
      <tr><th colspan="3">Total</th><td class="num"><strong><?= e(money((int) $b['total_paise'])) ?></strong></td></tr>
    </tfoot>
  </table>
</section>

<section class="panel">
  <h2>Actions</h2>

  <?php if ($open && in_array($pay, ['pending', 'awaiting_verification', 'failed'], true) && (int) $b['total_paise'] > 0): ?>
    <?= $act('verify_payment', $submitted ? 'Verify submitted payment' : 'Record payment received', 'primary') ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form">
        <?= $hidden('verify_payment') ?>
        <?php if ($submitted): $p = $submitted[0]; ?>
          <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
          <p>Devotee submitted UTR <strong class="mono"><?= e($p['reference']) ?></strong> on <?= e(fmt_datetime($p['submitted_at'])) ?><?= $p['payer_note'] ? ' — "' . e($p['payer_note']) . '"' : '' ?>.</p>
          <p class="callout">Check the temple bank account / UPI statement. Mark as paid <strong>only</strong> if a credit with this reference and amount is visible.</p>
        <?php else: ?>
          <p class="callout">Use this when the devotee paid at the counter or the payment was found in the bank statement.</p>
        <?php endif; ?>
        <label>Reference / UTR as shown in the bank statement <input name="reference" required value="<?= e($submitted[0]['reference'] ?? '') ?>" class="mono"></label><?= $err('reference') ?>
        <label>Amount received (₹) <input name="amount" required inputmode="decimal" value="<?= e(number_format((int) $b['total_paise'] / 100, 2, '.', '')) ?>"></label><?= $err('amount') ?>
        <label>Note <span class="muted">(required if amount differs)</span><input name="note" maxlength="255"></label><?= $err('note') ?>
        <button class="btn btn-primary" type="submit">Confirm payment verified</button>
      </form>
    </details>
  <?php endif; ?>

  <?php if ($open && $pay === 'awaiting_verification'): ?>
    <?= $act('reject_payment', 'Payment not found / not verified', 'danger') ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form">
        <?= $hidden('reject_payment') ?>
        <label>Reason (sent to the devotee) <input name="reason" required maxlength="255" placeholder="e.g. No credit found with this UTR"></label><?= $err('reason') ?>
        <button class="btn btn-danger" type="submit">Mark payment not verified</button>
      </form>
    </details>
  <?php endif; ?>

  <?php if ($open && $b['approval_status'] === 'pending'): ?>
    <?= $act('approve', 'Approve booking', 'primary') ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form">
        <?= $hidden('approve') ?>
        <label>Message to devotee <span class="muted">(optional)</span><input name="note" maxlength="255" placeholder="e.g. Please arrive by 6:30 AM with the batu"></label>
        <button class="btn btn-primary" type="submit">Approve</button>
      </form>
    </details>
  <?php endif; ?>

  <?php if ($open && in_array($pay, ['pending', 'failed', 'not_required'], true)): ?>
    <?= $act('adjust', 'Set additional charges') ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form">
        <?= $hidden('adjust') ?>
        <p class="muted small">For services priced on request (e.g. Other Temple Services). The devotee is notified of the new total.</p>
        <label>Label <input name="label" maxlength="100" value="<?= e($b['additional_label'] ?: 'Service charges') ?>"></label>
        <label>Amount (₹) <input name="amount" inputmode="decimal" value="<?= e(number_format((int) $b['additional_paise'] / 100, 2, '.', '')) ?>"></label><?= $err('amount') ?>
        <label>Note to devotee <span class="muted">(optional)</span><input name="note" maxlength="255"></label>
        <button class="btn" type="submit">Update charges</button>
      </form>
    </details>
  <?php endif; ?>

  <?php if ($open): ?>
    <?= $act('reschedule', 'Reschedule') ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form">
        <?= $hidden('reschedule') ?>
        <label>New date <input type="date" name="date" required value="<?= e($b['service_date']) ?>"></label><?= $err('date') ?>
        <?php foreach ($items as $it): if (!isset($slotOptions[$it['id']])) { continue; } ?>
          <label><?= e($it['name']) ?> — time
            <select name="slots[<?= (int) $it['id'] ?>]">
              <?php foreach ($slotOptions[$it['id']] as $sl): ?><option value="<?= $sl['id'] ?>"<?= (int) $it['slot_id'] === $sl['id'] ? ' selected' : '' ?>><?= e($sl['label']) ?><?= $sl['is_active'] ? '' : ' (inactive)' ?></option><?php endforeach; ?>
            </select>
          </label>
        <?php endforeach; ?><?= $err('slot') ?>
        <label class="inline"><input type="checkbox" name="override" value="1"> Override capacity / blocked date</label><?= $err('override') ?>
        <label>Message to devotee <span class="muted">(optional)</span><input name="note" maxlength="255"></label>
        <button class="btn" type="submit">Reschedule and notify devotee</button>
      </form>
    </details>

    <?php if ($b['booking_status'] === 'confirmed' && $b['service_date'] <= today()): ?>
    <form method="post" action="<?= e($actionUrl) ?>" class="form-inline"><?= $hidden('complete') ?><button class="btn" type="submit">Mark service completed</button></form>
    <?php endif; ?>

    <?php if ($b['approval_status'] !== 'not_required'): ?>
    <?= $act('reject', 'Do not approve', 'danger') ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form" data-confirm="Mark this booking as not approved? The devotee will be notified.">
        <?= $hidden('reject') ?>
        <label>Reason (sent to the devotee) <input name="reason" required maxlength="255"></label><?= $err('reason') ?>
        <button class="btn btn-danger" type="submit">Do not approve</button>
      </form>
    </details>
    <?php endif; ?>

    <?= $act('cancel', 'Cancel booking', 'danger') ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form" data-confirm="Cancel this booking? The devotee will be notified.">
        <?= $hidden('cancel') ?>
        <label>Reason (sent to the devotee) <input name="reason" required maxlength="255"></label><?= $err('reason') ?>
        <button class="btn btn-danger" type="submit">Cancel booking</button>
      </form>
    </details>
  <?php endif; ?>

  <?php if ($pay === 'paid' && in_array($b['booking_status'], ['cancelled', 'rejected'], true)): ?>
    <?= $act('refund', 'Record refund', 'danger') ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form">
        <?= $hidden('refund') ?>
        <label>Refund transaction reference <input name="reference" required maxlength="60"></label><?= $err('reference') ?>
        <label>Note <input name="note" maxlength="255"></label>
        <button class="btn btn-danger" type="submit">Record refund of <?= e(money((int) $b['total_paise'])) ?></button>
      </form>
    </details>
  <?php elseif ($pay === 'paid' && $open): ?>
    <p class="muted small">To refund, first cancel the booking.</p>
  <?php endif; ?>

  <?= $act('note', 'Add internal note') ?>
    <form method="post" action="<?= e($actionUrl) ?>" class="form">
      <?= $hidden('note') ?>
      <label>Note (staff only) <textarea name="note" rows="2" maxlength="1000" required></textarea></label><?= $err('note') ?>
      <button class="btn" type="submit">Add note</button>
    </form>
  </details>

  <?= $act('resend', 'Resend WhatsApp message') ?>
    <form method="post" action="<?= e($actionUrl) ?>" class="form">
      <?= $hidden('resend') ?>
      <label><input type="radio" name="type" value="ack" checked> Booking acknowledgement (devotee + administrators)</label>
      <label><input type="radio" name="type" value="status"> Current status update (devotee)</label>
      <button class="btn" type="submit">Send</button>
    </form>
  </details>
</section>
</div>

<section class="panel">
  <h2>Payments</h2>
  <?php if (!$payments): ?><p class="muted">No payment submitted yet.</p><?php else: ?>
  <table class="table compact">
    <thead><tr><th>When</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th>Status</th><th>Reviewed</th></tr></thead>
    <tbody><?php foreach ($payments as $p): ?>
      <tr><td><?= e(fmt_datetime($p['submitted_at'])) ?></td><td><?= e(str_replace('_', ' ', $p['method'])) ?></td><td class="mono"><?= e((string) $p['reference']) ?><?= $p['gateway_order_id'] ? '<br><small>' . e($p['gateway_order_id']) . '</small>' : '' ?></td><td class="num"><?= e(money((int) $p['amount_paise'])) ?></td><td><?= badge('pay', $p['status']) ?></td><td><?= $p['reviewed_at'] ? e(fmt_datetime($p['reviewed_at']) . ' by ' . $p['reviewed_by']) : '—' ?><?= $p['review_note'] ? '<br><small>' . e($p['review_note']) . '</small>' : '' ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>WhatsApp messages</h2>
  <?php if (!$notifications): ?><p class="muted">None.</p><?php else: ?>
  <table class="table compact">
    <thead><tr><th>When</th><th>To</th><th>Message</th><th>Status</th><th>Detail</th></tr></thead>
    <tbody><?php foreach ($notifications as $n): ?>
      <tr><td><?= e(fmt_datetime($n['created_at'])) ?></td><td><?= e($n['audience']) ?><br><small><?= e($n['recipient'] === '-' ? '—' : fmt_mobile($n['recipient'])) ?></small></td><td><?= e(str_replace('_', ' ', $n['event'])) ?></td><td><?= badge('n', $n['status']) ?></td><td><small><?= e((string) $n['error']) ?><?= $n['attempts'] ? ' (attempts: ' . (int) $n['attempts'] . ')' : '' ?></small></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>History (audit trail)</h2>
  <table class="table compact">
    <thead><tr><th>When</th><th>Who</th><th>What</th><th>Details</th></tr></thead>
    <tbody><?php foreach ($audit as $a): ?>
      <tr><td><?= e(fmt_datetime($a['created_at'])) ?></td><td><?= e($a['actor']) ?></td><td><?= e($a['action']) ?></td><td><small class="pre"><?= e(\SJT\Service\AuditFormat::details($a['details_json'])) ?></small></td></tr>
    <?php endforeach; ?></tbody>
  </table>
</section>
