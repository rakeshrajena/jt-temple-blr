<?php
/** @var array<string, mixed> $donation */
/** @var array<string, mixed>|null $waiting */
/** @var list<array<string, mixed>> $pledges */
/** @var bool $typeLocked */
$purposes = selection_values('purposes');
$currentPurpose = trim((string) ($donation['purpose'] ?? ''));
if ($currentPurpose !== '' && !in_array($currentPurpose, $purposes, true)) {
    $purposes[] = $currentPurpose;
}
$modes = money_payment_modes();
$currentMode = (string) $donation['payment_mode'];
if (!in_array($currentMode, $modes, true)) {
    $modes[] = $currentMode;
}
$types = $typeLocked ? [(string) $donation['donation_type']] : donation_amount_types();
$currentPledge = (int) ($donation['pledge_id'] ?? 0);
?>
<div class="panel">
  <h3><?= e(t('ui.edit_donation')) ?><?= help_tip(donation_edit_needs_both((string) ($donation['created_at'] ?? '')) ? t('ui.edit_both') : t('ui.edit_scope')) ?></h3>
  <p class="sub"><a href="<?= e(url('donations')) ?>"><?= e(t('ui.all_donations')) ?></a></p>
  <?php if ((int) ($donation['receipt_cancelled'] ?? 0) !== 1): ?>
    <form method="POST" action="<?= e(url('donations/' . $donation['id'] . '/generate_receipt')) ?>" style="margin-bottom:12px;">
      <?= csrf_field() ?>
      <button class="btn btn-sm btn-gold" type="submit" data-busy="Updating the receipt"><?= (int) ($donation['receipt_generated'] ?? 0) === 1 ? e(t('ui.update_receipt')) : e(t('ui.generate_receipt')) ?></button>
    </form>
  <?php endif; ?>
  <?php if ($waiting !== null): ?>
    <p>An edit is already waiting (<?= e((string) $waiting['status']) ?>). <?= e((string) $waiting['donor_name']) ?> · <?= e(money_or_dash($waiting['amount'])) ?> · <?= e((string) ($waiting['purpose'] ?? '')) ?> · <?= e((string) $waiting['donation_date']) ?> · <?= e((string) $waiting['payment_mode']) ?></p>
    <p><?= e((string) $waiting['reason']) ?></p>
  <?php else: ?>
  <form method="POST" action="<?= e(url('donations/' . $donation['id'] . '/edit')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label><?= e(t('common.donor')) ?></label><input type="text" name="donor_name" required value="<?= e((string) $donation['donor_name']) ?>"></div>
      <div class="form-group"><label><?= e(t('common.phone')) ?></label><input type="text" name="donor_phone" value="<?= e((string) ($donation['donor_phone'] ?? '')) ?>"></div>
      <div class="form-group"><label><?= e(t('ui.email_optional')) ?></label><input type="email" name="donor_email" value="<?= e((string) ($donation['donor_email'] ?? '')) ?>"></div>
      <div class="form-group"><label><?= e(t('ui.address_optional')) ?></label><input type="text" name="donor_address" value="<?= e((string) ($donation['donor_address'] ?? '')) ?>"></div>
      <div class="form-group"><label><?= e(t('ui.pan')) ?></label><input type="text" name="pan_number" value="<?= e((string) ($donation['donor_pan'] ?? '')) ?>"></div>
      <div class="form-group">
        <label><?= e(t('ui.donation_type')) ?></label>
        <select name="donation_type" <?= $typeLocked ? 'disabled' : '' ?>>
          <?php foreach ($types as $type): ?>
            <option<?= $type === (string) $donation['donation_type'] ? ' selected' : '' ?>><?= e($type) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($typeLocked): ?><input type="hidden" name="donation_type" value="<?= e((string) $donation['donation_type']) ?>"><?php endif; ?>
      </div>
      <div class="form-group"><label><?= e(t('common.amount')) ?></label><input type="number" step="0.01" name="amount" value="<?= $donation['amount'] === null ? '' : e((string) $donation['amount']) ?>"></div>
      <div class="form-group">
        <label><?= e(t('common.payment')) ?></label>
        <select name="payment_mode">
          <?php foreach ($modes as $mode): ?><option<?= $mode === $currentMode ? ' selected' : '' ?>><?= e($mode) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= e(t('ui.pledge_optional')) ?></label>
        <select name="pledge_id">
          <option value=""><?= e(t('ui.not_pledge')) ?></option>
          <?php foreach ($pledges as $pledge): ?>
            <option value="<?= e((string) $pledge['id']) ?>"<?= (int) $pledge['id'] === $currentPledge ? ' selected' : '' ?>><?= e((string) $pledge['pledge_date'] . ' · ' . $pledge['purpose'] . ' · ' . money($pledge['pledged_amount'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= e(t('common.purpose')) ?></label>
        <select name="purpose">
          <?php foreach ($purposes as $purpose): ?><option<?= $purpose === $currentPurpose ? ' selected' : '' ?>><?= e($purpose) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('ui.donation_date')) ?></label><input type="date" name="donation_date" value="<?= e((string) $donation['donation_date']) ?>"></div>
      <div class="form-group"><label><?= e(t('ui.upi')) ?></label><input type="text" name="upi_reference" maxlength="64" value="<?= e((string) ($donation['upi_reference'] ?? '')) ?>"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_no')) ?></label><input type="text" name="cheque_number" maxlength="30" value="<?= e((string) ($donation['cheque_number'] ?? '')) ?>"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_date')) ?></label><input type="date" name="cheque_date" value="<?= e(substr((string) ($donation['cheque_date'] ?? ''), 0, 10)) ?>"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_cleared')) ?></label><input type="checkbox" name="cheque_cleared" value="1"<?= (int) ($donation['cheque_cleared'] ?? 0) === 1 ? ' checked' : '' ?>></div>
      <div class="form-group full"><label><?= e(t('ui.edit_reason')) ?></label><textarea name="reason" rows="2" required maxlength="500"></textarea></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit" data-busy="Submitting the edit"><?= e(t('ui.submit_edit')) ?></button></div>
  </form>
  <?php endif; ?>
</div>
