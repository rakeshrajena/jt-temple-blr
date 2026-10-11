<?php
/**
 * Invoice summary + UPI QR payment page.
 * @var array $b @var array $items @var bool $canPay @var bool $upiAvailable @var ?string $qrSvg @var ?string $upiUri
 */
$step = 5; include __DIR__ . '/../partials/progress.php';
$no = $b['booking_no'];
$amount = money((int) $b['total_paise']);
?>
<?php if ($justBooked): ?>
  <div class="alert alert-ok" role="status"><strong>Booking request received.</strong> Your booking number is <strong><?= e($no) ?></strong>. Please complete the payment below. This is not yet a confirmed booking.</div>
<?php endif; ?>

<section class="card pay-card">
  <div class="pay-head">
    <div>
      <p class="booking-no-label">Booking number</p>
      <p class="booking-no"><?= e($no) ?></p>
      <button type="button" class="btn btn-small btn-secondary" data-copy="<?= e($no) ?>">Copy booking number</button>
    </div>
    <div class="amount-box">
      <p class="booking-no-label">Amount to pay</p>
      <p class="amount"><?= e($amount) ?></p>
    </div>
  </div>
  <?php include __DIR__ . '/../partials/status_badges.php'; ?>

  <?php if (!$canPay): ?>
    <div class="alert alert-info">
      <?php if ($b['payment_status'] === 'awaiting_verification'): ?>
        We have received your payment reference <strong><?= e($b['payment_ref']) ?></strong>. The temple office will verify it soon.
      <?php elseif ($b['payment_status'] === 'paid'): ?>
        Payment received and verified. Thank you.
      <?php elseif ($b['payment_status'] === 'not_required'): ?>
        No payment is due for this booking at the moment. The temple office will contact you if any charges apply.
      <?php else: ?>
        Payment is not available for this booking. Please contact the temple office.
      <?php endif; ?>
    </div>
    <p><a class="btn btn-primary" href="<?= e(url('/b/' . $no)) ?>">View booking status</a></p>
  <?php elseif (!$upiAvailable && !$gateway): ?>
    <div class="alert alert-warn">
      Online payment is being set up by the temple. Please pay <strong><?= e($amount) ?></strong> at the temple counter and quote booking number <strong><?= e($no) ?></strong>.
      <?php if ($temple['phone']): ?> Temple office: <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $temple['phone'])) ?>"><?= e($temple['phone']) ?></a>.<?php endif; ?>
    </div>
  <?php else: ?>
    <?php if ($b['payment_status'] === 'failed'): ?>
      <div class="alert alert-error" role="alert">The temple office could not verify your earlier payment reference. Please check your UPI app and submit the correct reference, or contact the temple office.</div>
    <?php endif; ?>

    <?php if ($upiAvailable): ?>
    <div class="qr-layout">
      <figure class="qr-box">
        <?php if ($upiMode === 'dynamic' && $qrSvg): ?>
          <div class="qr" role="img" aria-label="UPI QR code to pay <?= e($amount) ?> for booking <?= e($no) ?>"><?= $qrSvg /* generated locally, contains only SVG paths */ ?></div>
        <?php else: ?>
          <img class="qr" src="<?= e(url('/b/' . $no . '/qr.png')) ?>" alt="Temple UPI QR code" width="280" height="280">
        <?php endif; ?>
        <figcaption>
          <span class="qr-amount"><?= e($amount) ?></span>
          <span>Booking <?= e($no) ?></span>
          <?php if ($upiVpa): ?><span class="sub">UPI ID: <?= e($upiVpa) ?> (<?= e(setting('upi_payee_name')) ?>)</span><?php endif; ?>
        </figcaption>
      </figure>
      <div class="qr-help">
        <h2 class="h3">How to pay</h2>
        <ol class="pay-steps">
          <?php if ($upiMode === 'dynamic'): ?>
          <li>Scan this QR code with any UPI app. The amount <strong><?= e($amount) ?></strong> and booking number are filled in for you.</li>
          <?php else: ?>
          <li>Scan the temple's QR code with any UPI app and enter the amount <strong><?= e($amount) ?></strong> exactly.</li>
          <li>In the payment note / remarks, write your booking number <strong><?= e($no) ?></strong>.</li>
          <?php endif; ?>
          <li>After paying, copy the <strong>UPI reference number (UTR)</strong> from your UPI app — usually 12 digits.</li>
          <li>Enter it in the box below and press <strong>Submit payment reference</strong>.</li>
        </ol>
        <div class="actions">
          <?php if ($upiUri): ?><a class="btn btn-primary upi-link" href="<?= e($upiUri) ?>" data-mobile-only>Pay with a UPI app on this phone</a><?php endif; ?>
          <a class="btn btn-secondary" href="<?= e(url('/b/' . $no . '/qr.png', ['download' => 1])) ?>">Download QR code</a>
          <a class="btn btn-secondary" href="<?= e(url('/b/' . $no . '/qr.png')) ?>" target="_blank" rel="noopener">Open QR full screen</a>
        </div>
        <?php if ($instructions): ?><p class="hint"><?= e($instructions) ?></p><?php endif; ?>
      </div>
    </div>

    <form method="post" action="<?= e(url('/b/' . $no . '/payment')) ?>" class="card inset stack" id="payment-form" data-submit-once novalidate>
      <?= csrf_field() ?>
      <h2 class="h3">Submit your payment reference</h2>
      <div class="field<?= !empty($errors['reference']) ? ' has-error' : '' ?>">
        <label for="reference">UPI reference number (UTR) <span class="req">(required)</span></label>
        <input id="reference" name="reference" required maxlength="40" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="e.g. 427812345678" value="<?= e($oldRef) ?>" aria-describedby="reference-hint<?= !empty($errors['reference']) ? ' reference-error' : '' ?>" <?= !empty($errors['reference']) ? 'aria-invalid="true"' : '' ?>>
        <p class="hint" id="reference-hint">Find it in your UPI app under the payment details ("UPI Ref No.", "UTR" or "Transaction ID").</p>
        <?php if (!empty($errors['reference'])): ?><p class="field-error" id="reference-error" role="alert"><?= e($errors['reference']) ?></p><?php endif; ?>
      </div>
      <div class="field">
        <label for="payer_note">Paid from (name / UPI ID) <span class="opt">(optional — helps the temple match your payment)</span></label>
        <input id="payer_note" name="payer_note" maxlength="255">
      </div>
      <p class="hint">Submitting the reference does not confirm the booking by itself. The temple office checks every payment against its bank account.</p>
      <button class="btn btn-primary btn-lg" type="submit" data-busy-text="Submitting…">Submit payment reference</button>
    </form>
    <?php endif; ?>

    <?php if ($gateway): ?>
    <div class="card inset">
      <h2 class="h3">Or pay online</h2>
      <p>Pay securely with UPI, card or net banking. Your booking is updated automatically once the payment succeeds.</p>
      <button type="button" class="btn btn-primary" data-gateway-pay="<?= e(url('/b/' . $no . '/gateway/order')) ?>" data-gateway-verify="<?= e(url('/b/' . $no . '/gateway/verify')) ?>" data-csrf="<?= e(csrf_token()) ?>">Pay <?= e($amount) ?> online</button>
      <p class="field-error" data-gateway-error hidden></p>
    </div>
    <script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
    <?php endif; ?>

    <p class="center"><a href="<?= e(url('/b/' . $no)) ?>">I'll pay later — show my booking</a></p>
  <?php endif; ?>
</section>

<section class="card">
  <div class="row-between">
    <h2 class="h3">Invoice <?= e($b['invoice_no']) ?></h2>
    <div class="actions">
      <a class="btn btn-small btn-secondary" href="<?= e(url('/b/' . $no . '/invoice')) ?>">Print / view</a>
      <a class="btn btn-small btn-secondary" href="<?= e(url('/b/' . $no . '/invoice.pdf', ['download' => 1])) ?>">Download PDF</a>
    </div>
  </div>
  <dl class="kv">
    <dt>Devotee</dt><dd><?= e($b['devotee_name']) ?> · <?= e(fmt_mobile($b['mobile'])) ?></dd>
    <dt>Date</dt><dd><?= e(fmt_date($b['service_date'])) ?></dd>
  </dl>
  <?php $sub = $b['subtotal_paise']; $extra = $b['additional_paise']; $extraLabel = $b['additional_label']; $total = $b['total_paise'];
  include __DIR__ . '/../partials/items_table.php'; ?>
</section>
