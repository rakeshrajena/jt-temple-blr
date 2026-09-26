<?php
/** @var list<array<string,mixed>> $donations */
/** @var list<array<string,mixed>> $foodItems */
/** @var list<array{id: int, label: string, donor_name: string}> $pledges */
/** @var string $today */
?>
<div class="panel">
  <h3>Record a Donation</h3>
  <form method="POST" action="<?= e(url('donations')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Donor Name</label><input type="text" name="donor_name" required></div>
      <div class="form-group"><label>Phone</label><input type="text" name="donor_phone" placeholder="used to match existing donor"></div>
      <div class="form-group"><label>Email (optional)</label><input type="email" name="donor_email"></div>
      <div class="form-group"><label>Address (optional)</label><input type="text" name="donor_address"></div>
      <div class="form-group"><label>PAN (optional)</label><input type="text" name="pan_number"></div>
      <div class="form-group">
        <label>Donation Type</label>
        <select name="donation_type" id="donation_type" required>
          <option value="Cash">Cash</option>
          <option value="Food">Food (in-kind)</option>
          <option value="Vastra">Vastra / Cloths (in-kind)</option>
          <option value="Inventory">Inventory Item (in-kind)</option>
          <option value="Other">Other</option>
        </select>
      </div>
    </div>
    <div class="form-grid cols-3" id="cash_fields">
      <div class="form-group"><label>Amount (₹)</label><input type="number" step="0.01" name="amount"></div>
      <div class="form-group"><label>Payment Mode</label>
        <select name="payment_mode">
          <option>Cash</option><option>UPI</option><option>Bank Transfer</option><option>Cheque</option><option>In-Kind</option>
        </select>
      </div>
      <div class="form-group">
        <label>Pledge (optional)</label>
        <select name="pledge_id">
          <option value="">Not against a pledge</option>
          <?php foreach ($pledges as $pledge): ?>
            <option value="<?= e((string) $pledge['id']) ?>"><?= e($pledge['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Purpose</label>
        <select name="purpose">
          <option>General</option><option>Annadaan</option><option>Ratha Yatra</option><option>Construction</option><option>Vastra Seva</option>
        </select>
      </div>
      <div class="form-group"><label>Donation Date</label><input type="date" name="donation_date" value="<?= e($today) ?>"></div>
      <div class="form-group"><label>UPI transaction id</label><input type="text" name="upi_reference" maxlength="64" placeholder="Required for UPI"></div>
      <div class="form-group"><label>Cheque number</label><input type="text" name="cheque_number" maxlength="30" placeholder="Required for cheque"></div>
      <div class="form-group"><label>Cheque date</label><input type="date" name="cheque_date"></div>
      <div class="form-group"><label>Cheque cleared</label><input type="checkbox" name="cheque_cleared" value="1"></div>
    </div>
    <div class="form-grid cols-3" id="food_fields" style="display:none;">
      <div class="form-group"><label>Food Item</label>
        <select name="food_item_id">
          <?php foreach ($foodItems as $f): ?><option value="<?= e((string) $f['id']) ?>"><?= e($f['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Quantity Donated</label><input type="number" step="0.1" name="food_quantity"></div>
    </div>
    <div class="form-grid cols-3" id="vastra_fields" style="display:none;">
      <div class="form-group"><label>Deity</label>
        <select name="vastra_deity"><option>Jagannath</option><option>Balabhadra</option><option>Subhadra</option></select>
      </div>
      <div class="form-group"><label>Item Name</label><input type="text" name="vastra_item_name" placeholder="e.g. Silk Pata"></div>
      <div class="form-group"><label>Color</label><input type="text" name="vastra_color"></div>
      <div class="form-group"><label>Quantity</label><input type="number" name="vastra_quantity" value="1"></div>
    </div>
    <div class="form-grid cols-3" id="inventory_fields" style="display:none;">
      <div class="form-group"><label>Item Name</label><input type="text" name="inventory_name"></div>
      <div class="form-group"><label>Category</label><input type="text" name="inventory_category" placeholder="e.g. Kitchen Equipment"></div>
      <div class="form-group"><label>Unit</label><input type="text" name="inventory_unit" value="pcs"></div>
      <div class="form-group"><label>Quantity</label><input type="number" name="inventory_quantity" value="1"></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Record Donation</button></div>
  </form>
</div>
<div class="panel">
  <h3>All Donations (<?= count($donations) ?>) <a href="<?= e(url('receipts')) ?>" class="btn btn-sm btn-outline" style="margin-left:8px;">Manage receipts</a></h3>
  <?php if ($donations): ?>
  <table class="data-table">
    <tr><th>Date</th><th>Donor</th><th>Type</th><th>Amount</th><th>Purpose</th><th>Payment</th><th>Receipt</th><th></th></tr>
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
          <form method="POST" action="<?= e(url('donations/' . $d['id'] . '/clear-cheque')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit">Mark cleared</button></form>
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
        <?php if ((int) $d['receipt_generated'] !== 1): ?>
        <form method="POST" action="<?= e(url('donations/' . $d['id'] . '/generate_receipt')) ?>" style="display:inline;">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-gold" type="submit">Generate Receipt</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No donations recorded yet.</div>
  <?php endif; ?>
</div>
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
