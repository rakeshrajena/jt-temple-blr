<?php
/** @var list<array<string,mixed>> $donations */
/** @var list<array<string,mixed>> $foodItems */
/** @var list<array{id: int, label: string, donor_name: string}> $pledges */
/** @var string $today */
?>
<div class="reveal-group">
<div class="action-bar">
  <button class="btn btn-outline" type="button" data-reveal="reveal-donation"><?= e(t('ui.record_donation')) ?></button>
</div>
<div class="panel reveal-panel" id="reveal-donation" hidden>
  <h3><?= e(t('ui.record_donation')) ?></h3>
  <form method="POST" action="<?= e(url('donations')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label for="donor_name"><?= e(t('common.donor')) ?></label>
        <div class="suggest">
          <input id="donor_name" type="text" name="donor_name" required data-suggest data-kind="donor" data-url="<?= e(url('api/donors/search')) ?>" data-id="donor_id" data-fill="donor_phone:phone,donor_email:email,donor_address:address,pan_number:pan_number">
          <input type="hidden" name="donor_id" value="0">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label><?= e(t('common.phone')) ?></label><input type="text" name="donor_phone" placeholder="used to match existing donor"></div>
      <div class="form-group"><label><?= e(t('ui.email_optional')) ?></label><input type="email" name="donor_email"></div>
      <div class="form-group"><label><?= e(t('ui.address_optional')) ?></label><input type="text" name="donor_address"></div>
      <div class="form-group"><label><?= e(t('ui.pan')) ?></label><input type="text" name="pan_number"></div>
      <div class="form-group">
        <label><?= e(t('ui.donation_type')) ?></label>
        <select name="donation_type" id="donation_type" required>
          <?php foreach (selection_pairs('donation_types') as $type): ?>
            <option value="<?= e($type['value']) ?>"><?= e($type['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-grid cols-3" id="cash_fields">
      <div class="form-group"><label><?= e(t('common.amount')) ?></label><input type="number" step="0.01" name="amount"></div>
      <div class="form-group"><label><?= e(t('common.payment')) ?></label>
        <select name="payment_mode">
          <?php foreach (payment_mode_names() as $mode): ?><option><?= e($mode) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= e(t('ui.pledge_optional')) ?></label>
        <select name="pledge_id">
          <option value=""><?= e(t('ui.not_pledge')) ?></option>
          <?php foreach ($pledges as $pledge): ?>
            <option value="<?= e((string) $pledge['id']) ?>"><?= e($pledge['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('common.purpose')) ?></label>
        <select name="purpose">
          <?php foreach (selection_values('purposes') as $purpose): ?><option><?= e($purpose) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('ui.donation_date')) ?></label><input type="date" name="donation_date" value="<?= e($today) ?>"></div>
      <div class="form-group"><label><?= e(t('ui.upi')) ?></label><input type="text" name="upi_reference" maxlength="64" placeholder="Required for UPI"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_no')) ?></label><input type="text" name="cheque_number" maxlength="30" placeholder="Required for cheque"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_date')) ?></label><input type="date" name="cheque_date"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_cleared')) ?></label><input type="checkbox" name="cheque_cleared" value="1"></div>
    </div>
    <div class="form-grid cols-3" id="food_fields" style="display:none;">
      <div class="form-group"><label><?= e(t('ui.food_item')) ?></label>
        <select name="food_item_id">
          <?php foreach ($foodItems as $f): ?><option value="<?= e((string) $f['id']) ?>"><?= e($f['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('ui.qty_donated')) ?></label><input type="number" step="0.1" name="food_quantity"></div>
    </div>
    <div class="form-grid cols-3" id="vastra_fields" style="display:none;">
      <div class="form-group"><label><?= e(t('common.deity')) ?></label>
        <select name="vastra_deity">
          <?php foreach (selection_values('deities') as $deity): ?><option><?= e($deity) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('ui.item_name')) ?></label>
        <div class="suggest">
          <input type="text" name="vastra_item_name" placeholder="e.g. Silk Pata" data-suggest data-kind="vastra" data-source="suggest-vastra" data-fill="vastra_color:color">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label><?= e(t('common.color')) ?></label>
        <div class="suggest">
          <input type="text" name="vastra_color" data-suggest data-kind="color" data-source="suggest-colors" data-filter="vastra_item_name:item">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="vastra_quantity" value="1"></div>
    </div>
    <div class="form-grid cols-3" id="inventory_fields" style="display:none;">
      <div class="form-group"><label><?= e(t('ui.item_name')) ?></label>
        <div class="suggest">
          <input type="text" name="inventory_name" data-suggest data-kind="inventory" data-source="suggest-inventory" data-filter="inventory_category:category" data-id="inventory_item_id" data-fill="inventory_unit:unit">
          <input type="hidden" name="inventory_item_id" value="0">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group">
        <label><?= e(t('common.category')) ?></label>
        <select name="inventory_category">
          <?php foreach (selection_values('inventory_categories') as $category): ?><option><?= e($category) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= e(t('common.unit')) ?></label>
        <select name="inventory_unit">
          <?php foreach (selection_values('units') as $unit): ?><option<?= $unit === 'pcs' ? ' selected' : '' ?>><?= e($unit) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="inventory_quantity" value="1"></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.record_donation_btn')) ?></button></div>
  </form>
</div>
</div>
<div class="panel">
  <h3><?= e(t('ui.all_donations')) ?> (<?= count($donations) ?>) <a href="<?= e(url('receipts')) ?>" class="btn btn-sm btn-outline"><?= e(t('ui.manage_receipts')) ?></a></h3>
  <?php if ($donations): ?>
  <table class="data-table">
    <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.donor')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.amount')) ?></th><th><?= e(t('common.purpose')) ?></th><th><?= e(t('common.payment')) ?></th><th><?= e(t('common.receipt')) ?></th><th></th></tr>
    <?php foreach ($donations as $d): ?>
    <tr>
      <td><?= e($d['donation_date']) ?></td>
      <td><a href="<?= e(url('donors/' . $d['donor_id'])) ?>"><?= e($d['donor_name']) ?></a><?php if (!empty($d['donor_phone'])): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e($d['donor_phone']) ?></span><?php endif; ?></td>
      <td><?= e($d['donation_type']) ?></td>
      <td><?= e(money_or_dash($d['amount'])) ?></td>
      <td><?= e(dash($d['purpose'])) ?></td>
      <td>
        <?= e($d['payment_mode']) ?>
        <?php if (($d['payment_mode'] ?? '') === 'UPI' && !empty($d['upi_reference'])): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e((string) $d['upi_reference']) ?></span><?php endif; ?>
        <?php if (($d['payment_mode'] ?? '') === 'Cheque' && !empty($d['cheque_number'])): ?>
          <br><span style="color:var(--ink-soft);font-size:12px;">Chq <?= e((string) $d['cheque_number']) ?></span>
          <?php if ((int) ($d['cheque_cleared'] ?? 0) === 1): ?><br><span class="badge badge-green">Cleared</span>
          <?php else: ?>
          <form method="POST" action="<?= e(url('donations/' . $d['id'] . '/clear-cheque')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit"><?= e(t('ui.mark_cleared')) ?></button></form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
      <td>
        <?php if ((int) $d['receipt_generated'] === 1): ?>
          <?= receipt_link($d['receipt_number']) ?>
          <?= receipt_cancel_badge($d['receipt_cancelled'] ?? 0, (string) ($d['cancel_reason'] ?? '')) ?>
          <?php if ((int) ($d['receipt_cancelled'] ?? 0) !== 1 && !empty($d['cancel_status'])): ?>
            <br><span class="badge badge-amber">Cancellation <?= e((string) $d['cancel_status']) ?></span>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge badge-amber">Not generated</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ((int) ($d['receipt_cancelled'] ?? 0) !== 1): ?>
        <form method="POST" action="<?= e(url('donations/' . $d['id'] . '/generate_receipt')) ?>" style="display:inline;">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-gold" type="submit" data-busy="Updating the receipt"><?= (int) $d['receipt_generated'] === 1 ? e(t('ui.update_receipt')) : e(t('ui.generate_receipt')) ?></button>
        </form>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline" href="<?= e(url('donations/' . $d['id'] . '/edit')) ?>"><?= e(t('ui.edit')) ?></a>
        <?php if (!empty($d['edit_status'])): ?><br><span class="badge badge-amber"><?= e(t('ui.edit_waiting')) ?></span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state"><?= e(t('dash.no_donations')) ?></div>
  <?php endif; ?>
</div>
<script type="application/json" id="suggest-vastra"><?= json_encode(suggest_vastra_names(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
<script type="application/json" id="suggest-colors"><?= json_encode(suggest_vastra_colors(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
<script type="application/json" id="suggest-inventory"><?= json_encode(suggest_inventory_catalog(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
<script>
function toggleFields() {
  const type = document.getElementById('donation_type').value;
  document.getElementById('cash_fields').style.display = (type === 'Cash' || type === 'Other') ? 'grid' : 'none';
  document.getElementById('food_fields').style.display = (type === 'Food') ? 'grid' : 'none';
  document.getElementById('vastra_fields').style.display = (type === 'Vastra') ? 'grid' : 'none';
  document.getElementById('inventory_fields').style.display = (type === 'Inventory') ? 'grid' : 'none';
}
document.getElementById('donation_type').addEventListener('change', toggleFields);
toggleFields();
</script>
