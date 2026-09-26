<?php
/** @var list<array<string, mixed>> $receipts */
/** @var int $onDisk */
/** @var bool $isAdmin */
/** @var string $countryCode */
?>
<div class="kpi-grid">
  <div class="kpi-card"><div class="value"><?= count($receipts) ?></div><div class="label">Generated receipts</div></div>
  <div class="kpi-card good"><div class="value"><?= (int) $onDisk ?></div><div class="label">PDF files ready</div></div>
</div>
<div class="panel">
  <h3><?= e(t('ui.all_receipts')) ?></h3>
  <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
    Click a receipt number to open the PDF. <strong>Email</strong> sends that PDF from the saved mail account. <strong>WhatsApp</strong> opens the devotee's chat with the receipt link in the message. On a phone, the same button also attaches the PDF when the device can share a file. <strong>Bulk email</strong> and <strong>Bulk WhatsApp</strong> open a message box for the selected receipts; Send delivers it and Cancel closes the box. Cancelling a receipt keeps the number and the file, and it waits for approval. <?php if ($isAdmin): ?>Bulk download is limited to Admin.<?php else: ?>Ask an Admin to download several at once.<?php endif; ?>
  </p>
  <?php if ($receipts === []): ?>
    <p>No receipts yet. Generate one from <a href="<?= e(url('donations')) ?>">Donations</a>.</p>
  <?php else: ?>
  <form method="POST" id="receiptForm" action="<?= e(url('receipts/cancel')) ?>">
    <?= csrf_field() ?>
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
      <?php if ($isAdmin): ?>
      <button class="btn btn-gold btn-sm" type="submit" formaction="<?= e(url('receipts/download')) ?>" name="scope" value="selected" id="downloadSelected" disabled>Download selected (<span id="selCount">0</span>)</button>
      <button class="btn btn-outline btn-sm" type="submit" formaction="<?= e(url('receipts/download')) ?>" name="scope" value="all">Download all (<?= (int) $onDisk ?>)</button>
      <?php endif; ?>
      <input type="text" name="reason" maxlength="500" placeholder="Reason for cancellation" style="min-width:220px;">
      <button class="btn btn-outline btn-sm" type="submit" id="cancelSelected" disabled><?= e(t('ui.request_cancel')) ?></button>
      <button class="btn btn-gold btn-sm" type="button" id="bulkEmail" disabled>Bulk email (<span id="bulkEmailCount">0</span>)</button>
      <button class="btn btn-outline btn-sm" type="button" id="bulkWhatsapp" disabled>Bulk WhatsApp (<span id="bulkWhatsappCount">0</span>)</button>
    </div>
    <table class="data-table">
      <tr>
        <th style="width:36px;"><input type="checkbox" id="selectAll" aria-label="Select all receipts"></th>
        <th><?= e(t('common.receipt')) ?></th>
        <th><?= e(t('common.date')) ?></th>
        <th><?= e(t('common.donor')) ?></th>
        <th><?= e(t('common.amount')) ?></th>
        <th><?= e(t('common.purpose')) ?></th>
        <th><?= e(t('ui.generated')) ?></th>
        <th><?= e(t('ui.file')) ?></th>
        <th><?= e(t('ui.share')) ?></th>
      </tr>
      <?php foreach ($receipts as $row): ?>
      <?php
        $number = (string) $row['receipt_number'];
        $ready = receipt_file_exists($number);
        $generatedOn = (string) ($row['generated_date'] ?? '');
        $generatedBy = (string) ($row['generated_by_name'] ?? '');
        $publicUrl = $ready ? receipt_public_url((string) ($row['receipt_share_token'] ?? '')) : '';
        $shareText = receipt_share_message($row, $publicUrl);
        $digits = whatsapp_phone_digits((string) ($row['donor_phone'] ?? ''), $countryCode) ?? '';
      ?>
      <tr data-digits="<?= e($digits) ?>" data-extra="<?= e($publicUrl !== '' ? 'Receipt: ' . $publicUrl : '') ?>">
        <td><input type="checkbox" name="donation_ids[]" value="<?= e((string) $row['donation_id']) ?>" class="receipt-checkbox bulk-checkbox" aria-label="Select <?= e($number) ?>"></td>
        <td>
          <?php if ($ready): ?><?= receipt_link($number) ?><?php else: ?><span class="badge badge-green"><?= e($number) ?></span><?php endif; ?>
          <?= receipt_cancel_badge($row['receipt_cancelled'] ?? 0, (string) ($row['cancel_reason'] ?? '')) ?>
          <?php if ((int) ($row['receipt_cancelled'] ?? 0) !== 1 && !empty($row['cancel_status'])): ?>
            <br><span class="badge badge-amber">Cancellation <?= e((string) $row['cancel_status']) ?></span>
          <?php endif; ?>
        </td>
        <td><?= e((string) $row['donation_date']) ?></td>
        <td><?= e((string) $row['donor_name']) ?><?php if (!empty($row['donor_phone'])): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e((string) $row['donor_phone']) ?></span><?php endif; ?><?php if (!empty($row['donor_email'])): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e((string) $row['donor_email']) ?></span><?php endif; ?></td>
        <td><?= e(money_or_dash($row['amount'])) ?></td>
        <td><?= e(dash((string) ($row['purpose'] ?? ''))) ?></td>
        <td><?= e($generatedOn !== '' ? $generatedOn : '—') ?><?php if ($generatedBy !== ''): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e($generatedBy) ?></span><?php endif; ?></td>
        <td><?php if ($ready): ?><span class="badge badge-green">Ready</span><?php else: ?><span class="badge badge-amber">Missing</span><?php endif; ?></td>
        <td style="white-space:nowrap;">
          <?php
            $shareEmail = trim((string) ($row['donor_email'] ?? ''));
            $waUrl = whatsapp_web_url((string) ($row['donor_phone'] ?? ''), $shareText, $countryCode);
          ?>
          <?php if ($shareEmail !== ''): ?>
          <button class="btn btn-sm btn-gold" type="submit" form="shareEmail<?= e((string) $row['donation_id']) ?>">Email</button>
          <?php else: ?>
          <span style="color:var(--ink-soft);font-size:12px;">No email</span>
          <?php endif; ?>
          <?php if ($waUrl !== null): ?>
          <a class="btn btn-sm btn-outline wa-share" href="<?= e($waUrl) ?>" target="_blank" rel="noopener"<?php if ($publicUrl !== ''): ?> data-pdf="<?= e(url('receipts/' . $number . '.pdf')) ?>" data-file="<?= e($number . '.pdf') ?>" data-text="<?= e($shareText) ?>"<?php endif; ?>><?= e(t('common.whatsapp')) ?></a>
          <?php else: ?>
          <span style="color:var(--ink-soft);font-size:12px;">No phone</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>
  <?php foreach ($receipts as $row): ?>
    <?php if (trim((string) ($row['donor_email'] ?? '')) !== ''): ?>
    <form method="POST" action="<?= e(url('receipts/' . $row['donation_id'] . '/email')) ?>" id="shareEmail<?= e((string) $row['donation_id']) ?>"><?= csrf_field() ?></form>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php
    $bulkEmailAction = url('receipts/bulk-email');
    $bulkKind = 'receipt';
    include __DIR__ . '/bulk_compose.php';
  ?>
  <?php endif; ?>
</div>
<?php if ($receipts !== []): ?>
<script>
(function () {
  const selectAll = document.getElementById('selectAll');
  const downloadBtn = document.getElementById('downloadSelected');
  const cancelBtn = document.getElementById('cancelSelected');
  const count = document.getElementById('selCount');
  const boxes = () => Array.from(document.querySelectorAll('.receipt-checkbox'));
  function update() {
    const n = boxes().filter(function (box) { return box.checked; }).length;
    if (count) count.textContent = String(n);
    if (downloadBtn) downloadBtn.disabled = n === 0;
    if (cancelBtn) cancelBtn.disabled = n === 0;
    if (window.jtRefreshBulk) window.jtRefreshBulk();
    const all = boxes();
    selectAll.checked = all.length > 0 && n === all.length;
  }
  selectAll.addEventListener('change', function () {
    boxes().forEach(function (box) { box.checked = selectAll.checked; });
    update();
  });
  boxes().forEach(function (box) { box.addEventListener('change', update); });
  cancelBtn.addEventListener('click', function (event) {
    const n = boxes().filter(function (box) { return box.checked; }).length;
    const reason = (document.querySelector('#receiptForm input[name="reason"]') || {}).value || '';
    if (n === 0 || reason.trim().length < 3 || !window.confirm('Request cancellation of ' + n + ' receipt' + (n === 1 ? '' : 's') + '? The number stays on the donation.')) {
      event.preventDefault();
    }
  });
  document.querySelectorAll('.wa-share').forEach(function (link) {
    link.addEventListener('click', function (event) {
      const pdfUrl = link.getAttribute('data-pdf') || '';
      const filename = link.getAttribute('data-file') || 'receipt.pdf';
      const text = link.getAttribute('data-text') || '';
      if (pdfUrl === '' || typeof navigator.canShare !== 'function') {
        return;
      }
      event.preventDefault();
      fetch(pdfUrl, { credentials: 'same-origin' })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('pdf');
          }
          return response.blob();
        })
        .then(function (blob) {
          const file = new File([blob], filename, { type: 'application/pdf' });
          if (!navigator.canShare({ files: [file] })) {
            window.open(link.href, '_blank', 'noopener');
            return;
          }
          return navigator.share({ files: [file], text: text, title: filename }).catch(function (error) {
            if (!error || error.name !== 'AbortError') {
              window.open(link.href, '_blank', 'noopener');
            }
          });
        })
        .catch(function () {
          window.open(link.href, '_blank', 'noopener');
        });
    });
  });
})();
</script>
<?php endif; ?>
