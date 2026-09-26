<?php
/** @var list<array<string,mixed>> $items */
/** @var list<array<string,mixed>> $logs */
/** @var list<array<string,mixed>> $pending */
/** @var float $writeOffLimit */
?>
<div class="panel" style="border-left: 4px solid var(--gold); display:flex; align-items:center; justify-content:space-between;">
  <div>
    <h3 style="margin-bottom:2px;">🎟️ Food Coupon Generator</h3>
    <p style="color:var(--ink-soft); font-size:13px; margin:0;">Generate cost-tracked prasad/meal coupons in bulk, with sequential serial numbers and a print-ready PDF.</p>
  </div>
  <a href="<?= e(url('food/coupons')) ?>" class="btn btn-gold">Manage Coupons →</a>
</div>
<div class="panel-row">
  <div class="panel">
    <h3>Add New Food Item</h3>
    <form method="POST" action="<?= e(url('food')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="new_item">
      <div class="form-grid">
        <div class="form-group"><label>Item Name</label><input type="text" name="name" required></div>
        <div class="form-group">
          <label>Unit</label>
          <select name="unit">
            <?php foreach (selection_values('units') as $unit): ?><option<?= $unit === 'kg' ? ' selected' : '' ?>><?= e($unit) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Opening Stock</label><input type="number" step="0.1" name="current_stock" value="0"></div>
        <div class="form-group"><label>Low-Stock Threshold</label><input type="number" step="0.1" name="minimum_threshold" value="0"></div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Add Item</button></div>
    </form>
  </div>
  <div class="panel">
    <h3>Log Stock Movement</h3>
    <p class="sub">Kitchen use of <?= e((string) $writeOffLimit) ?> or less is recorded immediately. Above that, it waits for approval and the quantity stays until then.</p>
    <form method="POST" action="<?= e(url('food')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Item</label>
          <select name="food_item_id" required>
            <?php foreach ($items as $f): ?>
              <option value="<?= e((string) $f['id']) ?>"><?= e($f['name']) ?> (<?= e($f['current_stock']) ?> <?= e($f['unit']) ?> in stock)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Quantity</label><input type="number" step="0.1" name="quantity" required></div>
        <div class="form-group full"><label>Purpose / Note</label><input type="text" name="purpose" placeholder="e.g. Daily Mahaprasad, Purchased from supplier"></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-outline" type="submit" name="action" value="add_stock">+ Add Stock</button>
        <button class="btn btn-primary" type="submit" name="action" value="use_stock">− Log Usage</button>
      </div>
    </form>
  </div>
</div>
<div class="panel">
  <h3>Current Stock</h3>
  <table class="data-table">
    <tr><th>Item</th><th>Current Stock</th><th>Threshold</th><th>Status</th><th>Last Updated</th></tr>
    <?php foreach ($items as $f): ?>
    <tr>
      <td><?= e($f['name']) ?></td>
      <td><?= e($f['current_stock']) ?> <?= e($f['unit']) ?></td>
      <td><?= e($f['minimum_threshold']) ?> <?= e($f['unit']) ?></td>
      <td>
        <?php if ((float) $f['current_stock'] <= (float) $f['minimum_threshold']): ?>
          <span class="badge badge-red">Low Stock</span>
        <?php else: ?>
          <span class="badge badge-green">OK</span>
        <?php endif; ?>
      </td>
      <td><?= e($f['last_updated']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php if ($pending): ?>
<div class="panel">
  <h3>Waiting write-offs</h3>
  <table class="data-table">
    <tr><th>Date</th><th>Item</th><th>Movement</th><th>Quantity</th><th>Status</th></tr>
    <?php foreach ($pending as $row): ?>
    <tr>
      <td><?= e((string) $row['movement_date']) ?></td>
      <td><?= e((string) $row['item_name']) ?></td>
      <td><?= e((string) $row['movement_type']) ?></td>
      <td><?= e((string) $row['quantity']) ?></td>
      <td><span class="badge badge-amber"><?= e((string) $row['status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
<div class="panel">
  <h3>Recent Usage Log</h3>
  <?php if ($logs): ?>
  <table class="data-table">
    <tr><th>Date</th><th>Item</th><th>Type</th><th>Quantity</th><th>Purpose</th></tr>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td><?= e($l['txn_date']) ?></td>
      <td><?= e($l['food_name']) ?></td>
      <td><?php if ($l['txn_type'] === 'Added'): ?><span class="badge badge-green">Added</span><?php else: ?><span class="badge badge-amber">Used</span><?php endif; ?></td>
      <td><?= e($l['quantity']) ?> <?= e($l['unit']) ?></td>
      <td><?= e(dash($l['purpose'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No stock movements logged yet.</div>
  <?php endif; ?>
</div>
