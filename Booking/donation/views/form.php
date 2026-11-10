<?php
/** @var bool $gatewayOn @var string $csrf @var string $checkoutScript */
?>
<p class="form-back"><a href="<?= e(url('/')) ?>">← Back to booking home</a></p>

<section class="card">
  <h1>Donate online</h1>
  <p class="lead">Support the temple with a secure online donation. Choose any amount up to <strong>₹<?= DONATION_MAX_RUPEES ?></strong> per transaction.</p>
  <div class="alert alert-info" role="note"><?= e(DONATION_OFFLINE_MESSAGE) ?></div>

  <?php if (!$gatewayOn): ?>
    <div class="alert alert-warn" role="alert">Online payment is not available right now. Please visit the temple office or try again later.</div>
  <?php else: ?>
    <form id="donation-form" class="form" method="post" action="<?= e(donation_url()) ?>" novalidate>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

      <fieldset class="card inset">
        <legend><h2 class="h3">Your details</h2></legend>
        <div class="grid-2">
          <div class="field">
            <label for="donation-name">Full name <span class="req">(required)</span></label>
            <input id="donation-name" name="name" type="text" maxlength="150" required autocomplete="name">
          </div>
          <div class="field">
            <label for="donation-phone">Mobile number <span class="req">(required)</span></label>
            <input id="donation-phone" name="phone" type="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required autocomplete="tel" placeholder="98765 43210" aria-describedby="donation-phone-hint">
            <p class="hint" id="donation-phone-hint">10-digit mobile number (used on your receipt).</p>
          </div>
          <div class="field">
            <label for="donation-email">Email <span class="req">(required)</span></label>
            <input id="donation-email" name="email" type="email" maxlength="120" required autocomplete="email" aria-describedby="donation-email-hint">
            <p class="hint" id="donation-email-hint">We email your donation receipt PDF to this address.</p>
          </div>
          <div class="field">
            <label for="donation-pan">PAN <span class="opt">(optional)</span></label>
            <input id="donation-pan" name="pan" type="text" maxlength="10" autocapitalize="characters" autocomplete="off" placeholder="AAAAA9999A">
          </div>
          <div class="field">
            <label for="donation-purpose">Purpose</label>
            <select id="donation-purpose" name="purpose">
              <option value="General temple donation">General temple donation</option>
              <option value="Annadanam">Annadanam</option>
              <option value="Temple maintenance">Temple maintenance</option>
              <option value="Festival / utsav">Festival / utsav</option>
            </select>
          </div>
          <div class="field field-narrow">
            <label for="donation-amount">Donation amount (₹) <span class="req">(required)</span></label>
            <input id="donation-amount" name="amount_rupees" type="number" min="1" max="<?= DONATION_MAX_RUPEES ?>" step="1" required inputmode="numeric" aria-describedby="donation-amount-hint">
            <p class="hint" id="donation-amount-hint">Maximum ₹<?= DONATION_MAX_RUPEES ?> per online transaction.</p>
          </div>
        </div>
      </fieldset>

      <p class="field-error" id="donation-error" hidden role="alert"></p>

      <div class="card inset stack pay-gateway">
        <h2 class="h3">Pay online</h2>
        <p>Pay securely with UPI, card or net banking. Your temple receipt is generated automatically once payment succeeds.</p>
        <p class="booking-no-label">Amount to pay</p>
        <p class="amount" id="donation-amount-display" aria-live="polite">₹0</p>
        <button
          type="button"
          class="btn btn-primary btn-lg"
          id="donation-submit"
        >Pay now</button>
        <button type="button" class="btn btn-secondary" id="donation-sync" hidden>I paid — check payment status</button>
      </div>
    </form>
  <?php endif; ?>
</section>

<section class="card subtle">
  <h2 class="h3">Book puja or prasadam</h2>
  <p><a class="btn btn-secondary" href="<?= e(url('/book')) ?>">Go to online booking</a></p>
</section>
