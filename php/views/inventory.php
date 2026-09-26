<?php
/** @var list<array<string,mixed>> $items */
/** @var list<string> $categories */
/** @var list<string> $conditions */
/** @var list<string> $movements */
/** @var float $writeOffLimit */
/** @var string $today */
/** @var list<array<string,mixed>> $history */
/** @var list<array<string,mixed>> $pending */
?>
<div class="panel">
  <h3>Add stock already on hand</h3>
  <p class="sub">This records quantity that is already in the store. It does not write a payment. Use the purchase form when money leaves the cash book.</p>
  <form method="POST" action="<?= e(url('inventory')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label>Category</label>
        <select name="category" required>
          <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Item Name</label><input type="text" name="name" required></div>
      <div class="form-group"><label>Quantity</label><input type="number" name="quantity" min="0" value="1" required></div>
      <div class="form-group"><label>Rate per unit (₹)</label><input type="number" name="unit_cost" min="0" step="0.01" value="0"></div>
      <div class="form-group"><label>Unit</label><input type="text" name="unit" value="pcs"></div>
      <div class="form-group">
        <label>Condition</label>
        <select name="item_condition">
          <?php foreach ($conditions as $c): ?><option<?= $c === 'Good' ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Location</label><input type="text" name="location" placeholder="e.g. Store Room, Kitchen"></div>
      <div class="form-group">
        <label>Source</label>
        <select name="source"><option>Purchased</option><option>Donated</option></select>
      </div>
      <div class="form-group full"><label>Description / Notes</label><textarea name="description" rows="2"></textarea></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Add Item</button></div>
  </form>
</div>
<div class="panel">
  <h3>Buy stock and record the payment</h3>
  <p class="sub">The purchase waits for approval. Stock and the cash book change together only after it is approved.</p>
  <form method="POST" action="<?= e(url('inventory')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="purchase">
    <div class="form-grid cols-3">
      <div class="form-group">
        <label>Existing item</label>
        <select name="item_id">
          <option value="0">New item</option>
          <?php foreach ($items as $i): ?>
            <option value="<?= e((string) $i['id']) ?>"><?= e($i['name']) ?> (<?= e((string) $i['quantity']) ?> <?= e($i['unit']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>New item name</label><input type="text" name="name" placeholder="Used when Existing item is New item"></div>
      <div class="form-group">
        <label>Category</label>
        <select name="category">
          <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Quantity</label><input type="number" name="quantity" min="1" value="1" required></div>
      <div class="form-group"><label>Rate per unit (₹)</label><input type="number" name="unit_cost" min="0.01" step="0.01" required></div>
      <div class="form-group"><label>Unit</label><input type="text" name="unit" value="pcs"></div>
      <div class="form-group"><label>Location</label><input type="text" name="location" placeholder="For a new item"></div>
      <div class="form-group"><label>Paid to</label><input type="text" name="paid_to"></div>
      <div class="form-group"><label>Date</label><input type="date" name="purchase_date" value="<?= e($today) ?>" required></div>
      <div class="form-group">
        <label>Payment</label>
        <select name="payment_mode">
          <option>Cash</option><option>Bank Transfer</option><option>UPI</option><option>Cheque</option>
        </select>
      </div>
      <div class="form-group"><label>UPI transaction id</label><input type="text" name="upi_reference" maxlength="64"></div>
      <div class="form-group"><label>Cheque number</label><input type="text" name="cheque_number" maxlength="30"></div>
      <div class="form-group"><label>Cheque date</label><input type="date" name="cheque_date"></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Submit purchase for approval</button></div>
  </form>
</div>
<div class="panel-row">
  <div class="panel">
    <h3>Record a movement</h3>
    <p class="sub">Issue and return are recorded immediately. Damage, loss, and retired quantities above <?= e((string) $writeOffLimit) ?> wait for approval.</p>
    <form method="POST" action="<?= e(url('inventory')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="move">
      <div class="form-grid">
        <div class="form-group">
          <label>Item</label>
          <select name="item_id" required>
            <?php foreach ($items as $i): ?>
              <option value="<?= e((string) $i['id']) ?>"><?= e($i['name']) ?> (<?= e((string) $i['quantity']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Movement</label>
          <select name="movement_type">
            <?php foreach ($movements as $m): if ($m === 'Added') { continue; } ?>
              <option><?= e($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Quantity</label><input type="number" name="quantity" min="1" value="1" required></div>
        <div class="form-group"><label>Date</label><input type="date" name="movement_date" value="<?= e($today) ?>" required></div>
        <div class="form-group full"><label>Note</label><input type="text" name="note" maxlength="255"></div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Record movement</button></div>
    </form>
  </div>
  <div class="panel">
    <h3>Condition and location</h3>
    <p class="sub">Repair sets Needs Repair. A new location moves the item. Retired writes off the quantity still on hand.</p>
    <form method="POST" action="<?= e(url('inventory')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="place">
      <div class="form-grid">
        <div class="form-group">
          <label>Item</label>
          <select name="item_id" required>
            <?php foreach ($items as $i): ?>
              <option value="<?= e((string) $i['id']) ?>"><?= e($i['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Condition</label>
          <select name="item_condition">
            <?php foreach ($conditions as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full"><label>Location</label><input type="text" name="location" maxlength="100"></div>
      </div>
      <div class="form-actions"><button class="btn btn-outline" type="submit">Update</button></div>
    </form>
  </div>
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
  <h3>Inventory List (<?= count($items) ?> items)</h3>
  <?php if ($items): ?>
  <table class="data-table">
    <tr><th>Category</th><th>Name</th><th>Qty</th><th>Rate</th><th>Value</th><th>Condition</th><th>Location</th><th>Source</th><th>Added</th></tr>
    <?php foreach ($items as $i): ?>
    <tr>
      <td><?= e($i['category']) ?></td>
      <td><?= e($i['name']) ?><?php if (!empty($i['description'])): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e($i['description']) ?></span><?php endif; ?></td>
      <td><?= e((string) $i['quantity']) ?> <?= e($i['unit']) ?></td>
      <td><?= e(money($i['unit_cost'] ?? 0)) ?></td>
      <td><?= e(money(stock_value((float) $i['quantity'], (float) ($i['unit_cost'] ?? 0)))) ?></td>
      <td>
        <?php if (in_array($i['item_condition'], ['New', 'Good'], true)): ?>
          <span class="badge badge-green"><?= e($i['item_condition']) ?></span>
        <?php elseif (in_array($i['item_condition'], ['Fair', 'Needs Repair'], true)): ?>
          <span class="badge badge-amber"><?= e($i['item_condition']) ?></span>
        <?php else: ?>
          <span class="badge badge-red"><?= e($i['item_condition']) ?></span>
        <?php endif; ?>
      </td>
      <td><?= e(dash($i['location'])) ?></td>
      <td><?php if ($i['source'] === 'Donated'): ?><span class="badge badge-blue">Donated</span><?php else: ?>Purchased<?php endif; ?></td>
      <td><?= e($i['added_date']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No inventory items yet — add the first one above.</div>
  <?php endif; ?>
</div>
<div class="panel">
  <h3>Recent movements</h3>
  <?php if ($history): ?>
  <table class="data-table">
    <tr><th>Date</th><th>Item</th><th>Movement</th><th>Quantity</th><th>Note</th></tr>
    <?php foreach ($history as $row): ?>
    <tr>
      <td><?= e((string) $row['movement_date']) ?></td>
      <td><?= e((string) $row['name']) ?></td>
      <td><?= e((string) $row['movement_type']) ?></td>
      <td><?= e((string) $row['quantity']) ?> <?= e((string) $row['unit']) ?></td>
      <td><?= e(dash($row['note'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No movements yet.</div>
  <?php endif; ?>
</div>
