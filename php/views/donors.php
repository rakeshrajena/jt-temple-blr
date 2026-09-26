<?php
/** @var list<array<string, mixed>> $donors */
/** @var string $from */
/** @var string $to */
/** @var string $query */
/** @var string $financialYear */
/** @var string $countryCode */
?>
<div class="panel">
  <h3>Devotees · <?= e($financialYear) ?></h3>
  <p class="sub">Add a devotee, or open a name to update details, gifts, and pledges. Select several names, then Bulk email or Bulk WhatsApp. A message box opens so the note can be written before Send or Cancel. A devotee who already has a gift or a pledge stays on record. A pledge is not in the cash book.</p>
  <p class="no-print"><a class="btn btn-gold btn-sm" href="<?= e(url('donors/new')) ?>">Add devotee</a></p>
  <form method="GET" action="<?= e(app_script()) ?>" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-bottom:14px;">
    <input type="hidden" name="r" value="donors">
    <div class="form-group"><label>Name or phone</label><input type="text" name="q" value="<?= e($query) ?>"></div>
    <div class="form-group"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="form-group"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-outline btn-sm" type="submit">Show</button>
  </form>
  <?php if ($donors === []): ?>
    <p>No devotees match.</p>
  <?php else: ?>
  <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
    <button class="btn btn-gold btn-sm" type="button" id="bulkEmail" disabled>Bulk email (<span id="bulkEmailCount">0</span>)</button>
    <button class="btn btn-outline btn-sm" type="button" id="bulkWhatsapp" disabled>Bulk WhatsApp (<span id="bulkWhatsappCount">0</span>)</button>
  </div>
  <table class="data-table">
    <tr>
      <th style="width:36px;"><input type="checkbox" id="selectAll" aria-label="Select all devotees"></th>
      <th>Name</th><th>Phone</th><th>PAN</th><th>Received</th><th>Still promised</th>
    </tr>
    <?php foreach ($donors as $donor): ?>
      <?php $digits = whatsapp_phone_digits((string) $donor['phone'], $countryCode) ?? ''; ?>
      <tr data-digits="<?= e($digits) ?>">
        <td><input type="checkbox" class="bulk-checkbox" value="<?= e((string) $donor['id']) ?>" aria-label="Select <?= e($donor['name']) ?>"></td>
        <td><a href="<?= e(url('donors/' . $donor['id'], ['from' => $from, 'to' => $to])) ?>"><?= e($donor['name']) ?></a><?php if ($donor['email'] !== ''): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e($donor['email']) ?></span><?php endif; ?></td>
        <td><?= e(dash($donor['phone'])) ?></td>
        <td><?= e(dash($donor['pan'])) ?></td>
        <td><?= e(money($donor['received'])) ?></td>
        <td><?= (float) $donor['outstanding'] > 0 ? e(money($donor['outstanding'])) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php
    $bulkEmailAction = url('donors/bulk-email');
    $bulkKind = 'devotee';
    include __DIR__ . '/bulk_compose.php';
  ?>
  <?php endif; ?>
</div>
