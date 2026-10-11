<?php
/**
 * Invoice — one template for the browser print view; the PDF is drawn by
 * InvoiceRenderer with the dependency-free PdfDocument (tables only, no flex/grid).
 * @var array $b @var array $items @var array $temple @var ?array $payment @var ?string $qr @var bool $forPdf
 */
$paid = $b['payment_status'] === 'paid';
$stamp = match (true) {
    $b['booking_status'] === 'cancelled' => 'CANCELLED',
    $b['booking_status'] === 'rejected' => 'NOT APPROVED',
    $b['payment_status'] === 'refunded' => 'REFUNDED',
    $paid => 'PAID',
    $b['payment_status'] === 'not_required' => 'NO PAYMENT DUE',
    $b['payment_status'] === 'awaiting_verification' => 'PAYMENT UNDER VERIFICATION',
    default => 'PAYMENT PENDING',
};
$css = (string) @file_get_contents(\SJT\Core\App::publicPath() . '/assets/css/invoice.css');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Invoice <?= e($b['invoice_no']) ?> · <?= e($b['booking_no']) ?></title>
<?php if ($forPdf): ?>
<style><?= $css ?></style>
<?php else: ?>
<link rel="stylesheet" href="<?= e(asset('css/invoice.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php endif; ?>
</head>
<body class="<?= $forPdf ? 'pdf' : 'screen' ?>">
<?php if (!$forPdf): ?>
<div class="toolbar no-print">
  <a href="<?= e(url('/b/' . $b['booking_no'])) ?>">&larr; Back to booking</a>
  <span>
    <button type="button" data-print>Print</button>
    <a class="btn" href="<?= e(url('/b/' . $b['booking_no'] . '/invoice.pdf', ['download' => 1])) ?>">Download PDF</a>
  </span>
</div>
<?php endif; ?>
<div class="invoice">
  <table class="head">
    <tr>
      <td class="temple">
        <div class="t-name"><?= e($temple['name']) ?></div>
        <?php if ($temple['address']): ?><div><?= e($temple['address']) ?></div><?php endif; ?>
        <?php if ($temple['phone']): ?><div>Phone: <?= e($temple['phone']) ?></div><?php endif; ?>
        <?php if ($temple['email']): ?><div>Email: <?= e($temple['email']) ?></div><?php endif; ?>
        <?php if ($temple['website']): ?><div><?= e(preg_replace('#^https?://#', '', $temple['website'])) ?></div><?php endif; ?>
        <?php if ($temple['gstin']): ?><div>GSTIN: <?= e($temple['gstin']) ?></div><?php endif; ?>
      </td>
      <td class="doc">
        <div class="doc-title">INVOICE</div>
        <table class="meta">
          <tr><th>Invoice no.</th><td><?= e($b['invoice_no']) ?></td></tr>
          <tr><th>Booking no.</th><td><strong><?= e($b['booking_no']) ?></strong></td></tr>
          <tr><th>Booking date</th><td><?= e(date('d M Y', strtotime($b['created_at']))) ?></td></tr>
          <tr><th>Service date</th><td><?= e(date('d M Y (D)', strtotime($b['service_date']))) ?></td></tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="stamp stamp-<?= $paid ? 'paid' : (in_array($b['booking_status'], ['cancelled', 'rejected'], true) ? 'void' : 'due') ?>"><?= e($stamp) ?></div>

  <table class="parties">
    <tr>
      <td>
        <div class="label">Billed to</div>
        <div class="strong"><?= e($b['devotee_name']) ?></div>
        <div>Mobile: <?= e(fmt_mobile($b['mobile'])) ?></div>
        <?php if ($b['email']): ?><div>Email: <?= e($b['email']) ?></div><?php endif; ?>
      </td>
      <td>
        <div class="label">Status</div>
        <div>Booking: <?= e(status_label('booking', $b['booking_status'])) ?></div>
        <div>Payment: <?= e(status_label('payment', $b['payment_status'])) ?></div>
        <?php if ($b['approval_status'] !== 'not_required'): ?><div>Approval: <?= e(status_label('approval', $b['approval_status'])) ?></div><?php endif; ?>
        <?php if ($b['payment_ref']): ?><div>Payment ref.: <strong><?= e($b['payment_ref']) ?></strong></div><?php endif; ?>
        <?php if ($payment && $payment['reviewed_at']): ?><div>Paid / verified: <?= e(date('d M Y', strtotime($b['paid_at'] ?? $payment['reviewed_at']))) ?></div><?php endif; ?>
      </td>
    </tr>
  </table>

  <table class="lines">
    <thead>
      <tr><th class="n">#</th><th>Description</th><th class="r">Unit price</th><th class="r">Qty</th><th class="r">Amount</th></tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($items as $it): $n++; ?>
      <tr>
        <td class="n"><?= $n ?></td>
        <td>
          <div class="strong"><?= e($it['name']) ?><?= $it['item_type'] === 'food' ? ' (per person)' : '' ?></div>
          <?php if ($it['slot_label']): ?><div class="sub">Time: <?= e($it['slot_label']) ?></div><?php endif; ?>
          <?php if ($it['participants']): ?><div class="sub">Participants: <?= (int) $it['participants'] ?></div><?php endif; ?>
          <?php foreach ($it['details'] as $d): ?><div class="sub"><?= e($d['label']) ?>: <?= e($d['value']) ?></div><?php endforeach; ?>
        </td>
        <td class="r"><?= e(money((int) $it['unit_price_paise'])) ?></td>
        <td class="r"><?= (int) $it['quantity'] ?></td>
        <td class="r"><?= e(money((int) $it['amount_paise'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <table class="totals-wrap">
    <tr>
      <td class="qr-cell">
        <?php if ($qr): ?>
          <img src="<?= e($qr) ?>" width="130" height="130" alt="UPI QR code">
          <div class="sub">Scan with any UPI app to pay <?= e(money((int) $b['total_paise'])) ?><?= $upi_vpa ? ' to ' . e($upi_vpa) : '' ?>. Note: <?= e($b['booking_no']) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <table class="totals">
          <tr><th>Subtotal</th><td><?= e(money((int) $b['subtotal_paise'])) ?></td></tr>
          <tr><th><?= e($b['additional_label'] ?: 'Additional charges') ?></th><td><?= e(money((int) $b['additional_paise'])) ?></td></tr>
          <tr class="grand"><th>Total payable</th><td><?= e(money((int) $b['total_paise'])) ?></td></tr>
          <?php if ($paid): ?><tr><th>Amount paid</th><td><?= e(money((int) ($payment['amount_paise'] ?? $b['total_paise']))) ?></td></tr><?php endif; ?>
        </table>
      </td>
    </tr>
  </table>

  <?php if ($b['instructions']): ?><p class="note"><strong>Devotee's instructions:</strong> <?= e($b['instructions']) ?></p><?php endif; ?>
  <?php if ($terms): ?><p class="note"><?= e($terms) ?></p><?php endif; ?>
  <p class="foot">This is a computer-generated invoice. Please quote booking number <?= e($b['booking_no']) ?> in all communication. Jai Jagannath!</p>
</div>
</body>
</html>
