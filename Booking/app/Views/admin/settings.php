<?php
/** @var array $s settings @var bool $waConfigured @var bool $staticQr @var string $webhookUrl */
$v = fn (string $k) => e((string) ($s[$k] ?? ''));
$on = fn (string $k) => ($s[$k] ?? '0') === '1' ? ' checked' : '';
?>
<h1>Settings</h1>
<nav class="tabs"><a href="#temple">Temple details</a><a href="#booking">Booking rules</a><a href="#payment">UPI payment</a><a href="#whatsapp">WhatsApp</a></nav>

<form method="post" action="<?= e(url('/admin/settings')) ?>" class="panel form" id="temple">
  <?= csrf_field() ?><input type="hidden" name="section" value="temple">
  <h2>Temple details <small class="muted">(shown on invoices, pages and messages)</small></h2>
  <div class="grid-2">
    <label>Temple name <input name="temple_name" value="<?= $v('temple_name') ?>" required></label>
    <label>Phone (temple office) <input name="temple_phone" value="<?= $v('temple_phone') ?>" placeholder="+91 80 1234 5678"></label>
    <label>Email <input name="temple_email" type="email" value="<?= $v('temple_email') ?>"></label>
    <label>Website <input name="temple_website" value="<?= $v('temple_website') ?>"></label>
    <label>GSTIN <span class="muted">(only if registered)</span><input name="temple_gstin" value="<?= $v('temple_gstin') ?>"></label>
  </div>
  <label>Address <textarea name="temple_address" rows="2"><?= $v('temple_address') ?></textarea></label>
  <label>Terms shown on review page and invoice <textarea name="terms_text" rows="3"><?= $v('terms_text') ?></textarea></label>
  <button class="btn btn-primary" type="submit">Save temple details</button>
</form>

<form method="post" action="<?= e(url('/admin/settings')) ?>" class="panel form" id="booking">
  <?= csrf_field() ?><input type="hidden" name="section" value="booking">
  <h2>Booking rules</h2>
  <div class="grid-3">
    <label>Minimum notice (days) <input type="number" name="booking_lead_days" min="0" value="<?= $v('booking_lead_days') ?>"><small class="muted">0 = same-day bookings allowed</small></label>
    <label>Book up to (days ahead) <input type="number" name="booking_max_days" min="1" value="<?= $v('booking_max_days') ?>"></label>
    <span></span>
    <label>Additional charge label <input name="additional_charge_label" value="<?= $v('additional_charge_label') ?>" placeholder="e.g. Convenience fee"></label>
    <label>Additional charge per booking (₹) <input name="additional_charge" inputmode="decimal" value="<?= e(number_format(((int) ($s['additional_charge_paise'] ?? 0)) / 100, 2, '.', '')) ?>"><small class="muted">0 = none</small></label>
    <span></span>
    <label>Booking number prefix <input name="booking_prefix" value="<?= $v('booking_prefix') ?>" maxlength="8"><small class="muted">e.g. SJT → SJT-2026-000123</small></label>
    <label>Invoice number prefix <input name="invoice_prefix" value="<?= $v('invoice_prefix') ?>" maxlength="8"><small class="muted">e.g. SJTB → SJTB/2026-27/000123</small></label>
  </div>
  <button class="btn btn-primary" type="submit">Save booking rules</button>
</form>

<section class="panel" id="payment">
  <h2>UPI payment</h2>
  <div class="callout">
    <strong>Safety check before going live.</strong> Use only the temple's own bank UPI ID / QR. Make a ₹1 test payment from a personal phone, confirm with the temple treasurer that it arrived in the temple's bank account, and only then tick <em>"verified"</em>. Until then devotees are asked to pay at the counter.
  </div>
  <form method="post" action="<?= e(url('/admin/settings')) ?>" class="form">
    <?= csrf_field() ?><input type="hidden" name="section" value="payment">
    <fieldset class="checks">
      <legend>QR type</legend>
      <label class="inline"><input type="radio" name="upi_qr_mode" value="dynamic"<?= ($s['upi_qr_mode'] ?? 'dynamic') !== 'static' ? ' checked' : '' ?>> <strong>Dynamic (recommended)</strong> — a QR per booking with the exact amount and booking number filled in</label>
      <label class="inline"><input type="radio" name="upi_qr_mode" value="static"<?= ($s['upi_qr_mode'] ?? '') === 'static' ? ' checked' : '' ?>> Static — show the temple's existing printed QR image; devotee types the amount</label>
    </fieldset>
    <div class="grid-2">
      <label>Temple UPI ID (VPA) <input name="upi_vpa" value="<?= $v('upi_vpa') ?>" placeholder="templename@sbi" autocapitalize="none" spellcheck="false"></label>
      <label>Payee name (as registered with the bank) <input name="upi_payee_name" value="<?= $v('upi_payee_name') ?>"></label>
    </div>
    <label class="inline"><input type="checkbox" name="upi_include_tr" value="1"<?= $on('upi_include_tr') ?>> Include merchant transaction reference (only for merchant / business UPI IDs)</label>
    <label>Payment instructions shown to devotees <textarea name="payment_instructions" rows="3"><?= $v('payment_instructions') ?></textarea></label>
    <label class="inline verify"><input type="checkbox" name="upi_verified" value="1"<?= $on('upi_verified') ?>> <strong>I verified that this UPI ID / QR pays into the temple's bank account</strong></label>
    <label class="inline"><input type="checkbox" name="reverify" value="1"> I have also tested the new details after changing them</label>
    <label>Verification note (who checked, when, which account) <input name="upi_verified_note" value="<?= $v('upi_verified_note') ?>" maxlength="200"></label>
    <button class="btn btn-primary" type="submit">Save payment settings</button>
  </form>
  <div class="cols">
    <div>
      <h3>Static QR image</h3>
      <form method="post" action="<?= e(url('/admin/settings/qr-upload')) ?>" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>
        <label>Upload the temple's official UPI QR (PNG/JPG, max 3 MB) <input type="file" name="qr" accept="image/png,image/jpeg,image/webp" required></label>
        <button class="btn" type="submit">Upload QR image</button>
        <p class="muted small"><?= $staticQr ? 'A QR image is uploaded.' : 'No QR image uploaded yet.' ?> Uploading a new image resets "verified".</p>
      </form>
    </div>
    <div>
      <h3>Preview</h3>
      <?php if (($s['upi_qr_mode'] ?? 'dynamic') === 'static' ? $staticQr : ($s['upi_vpa'] ?? '') !== ''): ?>
        <img src="<?= e(url('/admin/settings/qr-preview.png')) ?>" alt="QR preview" width="180" height="180" class="qr-preview">
        <p class="muted small"><?= ($s['upi_qr_mode'] ?? 'dynamic') === 'static' ? 'Uploaded QR.' : 'Test QR for ₹1.00 — scan it to check the payee name before verifying.' ?></p>
      <?php else: ?><p class="muted">Nothing to preview yet.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="panel" id="whatsapp">
  <h2>WhatsApp notifications</h2>
  <p>API credentials: <?= $waConfigured ? '<span class="badge badge-ok">Present in .env</span>' : '<span class="badge badge-bad">Not set — add WA_ACCESS_TOKEN and WA_PHONE_NUMBER_ID to .env</span>' ?></p>
  <p class="muted small">Credentials are kept only in the server's <code>.env</code> file and are never shown here or sent to browsers. Webhook URL for delivery reports: <code><?= e($webhookUrl) ?></code></p>
  <form method="post" action="<?= e(url('/admin/settings')) ?>" class="form">
    <?= csrf_field() ?><input type="hidden" name="section" value="whatsapp">
    <label class="inline"><input type="checkbox" name="whatsapp_enabled" value="1"<?= $on('whatsapp_enabled') ?>> Send WhatsApp notifications</label>
    <label>Administrator WhatsApp number(s) for new-booking alerts <span class="muted">(comma separated)</span><input name="admin_whatsapp_numbers" value="<?= $v('admin_whatsapp_numbers') ?>" placeholder="+91 98450 00000"></label>
    <div class="grid-3">
      <label>Template language code <input name="whatsapp_language" value="<?= $v('whatsapp_language') ?>"></label>
    </div>
    <div class="grid-2">
      <label>Template: booking received (devotee) <input name="wa_tpl_devotee_ack" value="<?= $v('wa_tpl_devotee_ack') ?>"></label>
      <label>Template: booking update (devotee) <input name="wa_tpl_devotee_update" value="<?= $v('wa_tpl_devotee_update') ?>"></label>
      <label>Template: new booking (admin) <input name="wa_tpl_admin_new" value="<?= $v('wa_tpl_admin_new') ?>"></label>
      <label>Template: payment submitted (admin) <input name="wa_tpl_admin_payment" value="<?= $v('wa_tpl_admin_payment') ?>"></label>
    </div>
    <p class="muted small">Template names must match templates approved in Meta WhatsApp Manager. The exact wording to submit is in docs/WHATSAPP-TEMPLATES.md.</p>
    <button class="btn btn-primary" type="submit">Save WhatsApp settings</button>
  </form>
  <form method="post" action="<?= e(url('/admin/settings/whatsapp-test')) ?>" class="form-inline test-form">
    <?= csrf_field() ?>
    <label>Send a test message to <input name="to" type="tel" placeholder="98765 43210" required></label>
    <button class="btn" type="submit">Send test</button>
  </form>
</section>
