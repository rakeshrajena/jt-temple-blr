<?php
/**
 * @var array $summary
 * @var bool $justPaid
 * @var array|null $emailNotice
 * @var string $csrf
 */
$ref = (string) $summary['ref_no'];
$amountPaise = (int) ($summary['amount_paise'] ?? 0);
$links = $summary['links'] ?? null;
?>
<?php if ($justPaid): ?>
  <div class="alert alert-ok" role="status"><strong>Thank you.</strong> Your payment was received. Your donation receipt is below.</div>
<?php endif; ?>

<section class="card status-card status-ok">
  <p class="booking-no-label">Donation reference</p>
  <p class="booking-no" data-copy-text="<?= e($ref) ?>"><?= e($ref) ?></p>
  <button type="button" class="btn btn-small btn-secondary" data-copy="<?= e($ref) ?>">Copy reference</button>
  <h1 class="status-title">Donation recorded</h1>
  <p class="lead">Payment received. <?= $summary['receipt_number'] ? 'Receipt <strong>' . e((string) $summary['receipt_number']) . '</strong>.' : 'Your receipt is being prepared.' ?></p>
  <div class="actions">
    <?php if ($links): ?>
      <a class="btn btn-primary" href="<?= e($links['pdf_url']) ?>" target="_blank" rel="noopener">View receipt</a>
      <a class="btn btn-secondary" href="<?= e(donation_receipt_pdf_route($ref, true)) ?>">Download PDF</a>
    <?php elseif (!empty($summary['receipt_number'])): ?>
      <a class="btn btn-secondary" href="<?= e(donation_ref_url($ref)) ?>">Refresh for PDF</a>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(donation_url()) ?>">Donate again</a>
  </div>
</section>

<section class="card">
  <h2>Donation details</h2>
  <dl class="kv">
    <dt>Devotee</dt><dd><?= e((string) $summary['name']) ?></dd>
    <dt>Amount</dt><dd><?= e(money($amountPaise)) ?></dd>
    <dt>Reference</dt><dd><?= e($ref) ?></dd>
    <?php if (!empty($summary['receipt_number'])): ?>
      <dt>Receipt no.</dt><dd><?= e((string) $summary['receipt_number']) ?></dd>
    <?php endif; ?>
    <?php if (!empty($summary['purpose'])): ?>
      <dt>Purpose</dt><dd><?= e((string) $summary['purpose']) ?></dd>
    <?php endif; ?>
    <?php if (!empty($summary['paid_at'])): ?>
      <dt>Paid on</dt><dd><?= e(fmt_datetime((string) $summary['paid_at'])) ?></dd>
    <?php endif; ?>
  </dl>
</section>

<?php
$mailErr = is_array($emailNotice) ? ($emailNotice['error'] ?? null) : null;
if ($mailErr === 'no_email'): ?>
  <section class="card subtle">
    <p>Enter your email below to receive the receipt PDF.</p>
  </section>
<?php elseif ($mailErr !== null && $mailErr !== ''): ?>
  <div class="alert alert-warn" role="alert"><?= e(donation_sanitize_user_error((string) $mailErr)) ?></div>
<?php elseif (is_array($emailNotice) && ($emailNotice['email'] ?? '') !== ''): ?>
  <div class="alert alert-ok" role="status">Receipt emailed to <strong><?= e((string) $emailNotice['email']) ?></strong>.</div>
<?php endif; ?>

<section class="card subtle">
  <h2 class="h3">Email receipt</h2>
  <form id="donation-resend" class="form" method="post" action="<?= e(donation_ref_url($ref)) ?>">
    <input type="hidden" name="action" value="resend_receipt">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="ref_no" value="<?= e($ref) ?>">
    <label for="resend-email">Email</label>
    <input id="resend-email" type="email" name="email" maxlength="120" value="<?= e((string) ($summary['email'] ?? '')) ?>" required autocomplete="email">
    <p><button type="submit" class="btn btn-secondary">Send receipt by email</button></p>
    <p class="field-error" id="donation-resend-msg" hidden role="status"></p>
  </form>
</section>

<section class="card subtle">
  <h2 class="h3">Keep this page</h2>
  <p>Save donation reference <strong><?= e($ref) ?></strong>. You can open this link again from your browser history.</p>
  <?php $phone = setting('temple_phone'); if ($phone): ?>
    <p>Questions? Call the temple office on <a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $phone)) ?>"><?= e((string) $phone) ?></a> and quote your reference.</p>
  <?php endif; ?>
</section>
