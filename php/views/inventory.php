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
<div class="reveal-group">
<div class="action-bar">
  <button class="btn btn-outline" type="button" data-reveal="reveal-stock-hand"><?= e(t('ui.on_hand')) ?></button>
  <button class="btn btn-outline" type="button" data-reveal="reveal-stock-buy"><?= e(t('ui.buy_stock')) ?></button>
  <button class="btn btn-outline" type="button" data-reveal="reveal-stock-move"><?= e(t('ui.record_movement')) ?></button>
  <button class="btn btn-outline" type="button" data-reveal="reveal-stock-place"><?= e(t('ui.condition_location')) ?></button>
</div>
<div class="panel reveal-panel" id="reveal-stock-hand" hidden>
  <h3><?= e(t('ui.on_hand')) ?></h3>
  <p class="sub">This records quantity that is already in the store. It does not write a payment. Use the purchase form when money leaves the cash book.</p>
  <form method="POST" action="<?= e(url('inventory')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label><?= e(t('common.category')) ?></label>
        <select name="category" required>
          <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('ui.item_name')) ?></label>
        <div class="suggest">
          <input type="text" name="name" required data-suggest data-kind="inventory" data-source="suggest-inventory" data-filter="category:category" data-id="item_id" data-fill="unit:unit,unit_cost:unit_cost,item_condition:condition,location:location,description:description">
          <input type="hidden" name="item_id" value="0">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="quantity" min="0" value="1" required></div>
      <div class="form-group"><label><?= e(t('ui.rate')) ?></label><input type="number" name="unit_cost" min="0" step="0.01" value="0"></div>
      <div class="form-group">
        <label><?= e(t('common.unit')) ?></label>
        <select name="unit">
          <?php foreach (selection_values('units') as $unit): ?><option<?= $unit === 'pcs' ? ' selected' : '' ?>><?= e($unit) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= e(t('ui.condition')) ?></label>
        <select name="item_condition">
          <?php foreach ($conditions as $c): ?><option<?= $c === 'Good' ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('common.location')) ?></label>
        <div class="suggest">
          <input type="text" name="location" placeholder="e.g. Store Room, Kitchen" data-suggest data-kind="location" data-source="suggest-locations">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group">
        <label><?= e(t('common.source')) ?></label>
        <select name="source">
          <?php foreach (selection_values('sources') as $source): ?><option><?= e($source) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full"><label><?= e(t('ui.description_notes')) ?></label><textarea name="description" rows="2"></textarea></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.add_item')) ?></button></div>
  </form>
</div>
<div class="panel reveal-panel" id="reveal-stock-buy" hidden>
  <h3><?= e(t('ui.buy_stock')) ?></h3>
  <p class="sub">The purchase waits for approval. Stock and the cash book change together only after it is approved.</p>
  <form method="POST" action="<?= e(url('inventory')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="purchase">
    <div class="form-grid cols-3">
      <div class="form-group">
        <label><?= e(t('ui.existing_item')) ?></label>
        <select name="item_id">
          <option value="0">New item</option>
          <?php foreach ($items as $i): ?>
            <option value="<?= e((string) $i['id']) ?>"><?= e($i['name']) ?> (<?= e((string) $i['quantity']) ?> <?= e($i['unit']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('ui.new_item_name')) ?></label>
        <div class="suggest">
          <input type="text" name="name" placeholder="Used when Existing item is New item" data-suggest data-kind="inventory" data-source="suggest-inventory" data-filter="category:category" data-id="item_id" data-fill="unit:unit,unit_cost:unit_cost,location:location">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group">
        <label><?= e(t('common.category')) ?></label>
        <select name="category">
          <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="quantity" min="1" value="1" required></div>
      <div class="form-group"><label><?= e(t('ui.rate')) ?></label><input type="number" name="unit_cost" min="0.01" step="0.01" required></div>
      <div class="form-group">
        <label><?= e(t('common.unit')) ?></label>
        <select name="unit">
          <?php foreach (selection_values('units') as $unit): ?><option<?= $unit === 'pcs' ? ' selected' : '' ?>><?= e($unit) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('common.location')) ?></label>
        <div class="suggest">
          <input type="text" name="location" placeholder="For a new item" data-suggest data-kind="location" data-source="suggest-locations">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label><?= e(t('common.paid_to')) ?></label><input type="text" name="paid_to"></div>
      <div class="form-group"><label><?= e(t('common.date')) ?></label><input type="date" name="purchase_date" value="<?= e($today) ?>" required></div>
      <div class="form-group">
        <label><?= e(t('common.payment')) ?></label>
        <select name="payment_mode">
          <?php foreach (money_payment_modes() as $mode): ?><option><?= e($mode) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('ui.upi')) ?></label><input type="text" name="upi_reference" maxlength="64"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_no')) ?></label><input type="text" name="cheque_number" maxlength="30"></div>
      <div class="form-group"><label><?= e(t('ui.cheque_date')) ?></label><input type="date" name="cheque_date"></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.submit_purchase')) ?></button></div>
  </form>
</div>
  <div class="panel reveal-panel" id="reveal-stock-move" hidden>
    <h3><?= e(t('ui.record_movement')) ?></h3>
    <p class="sub">Issue and return are recorded immediately. Damage, loss, and retired quantities above <?= e((string) $writeOffLimit) ?> wait for approval.</p>
    <form method="POST" action="<?= e(url('inventory')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="move">
      <div class="form-grid">
        <div class="form-group">
          <label><?= e(t('common.item')) ?></label>
          <select name="item_id" required>
            <?php foreach ($items as $i): ?>
              <option value="<?= e((string) $i['id']) ?>"><?= e($i['name']) ?> (<?= e((string) $i['quantity']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label><?= e(t('common.movement')) ?></label>
          <select name="movement_type">
            <?php foreach ($movements as $m): if ($m === 'Added') { continue; } ?>
              <option><?= e($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="quantity" min="1" value="1" required></div>
        <div class="form-group"><label><?= e(t('common.date')) ?></label><input type="date" name="movement_date" value="<?= e($today) ?>" required></div>
        <div class="form-group full"><label><?= e(t('ui.note')) ?></label><input type="text" name="note" maxlength="255"></div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.record_movement_btn')) ?></button></div>
    </form>
  </div>
  <div class="panel reveal-panel" id="reveal-stock-place" hidden>
    <h3><?= e(t('ui.condition_location')) ?></h3>
    <p class="sub">Repair sets Needs Repair. A new location moves the item. Retired writes off the quantity still on hand.</p>
    <form method="POST" action="<?= e(url('inventory')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="place">
      <div class="form-grid">
        <div class="form-group">
          <label><?= e(t('common.item')) ?></label>
          <select name="item_id" required>
            <?php foreach ($items as $i): ?>
              <option value="<?= e((string) $i['id']) ?>"><?= e($i['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label><?= e(t('ui.condition')) ?></label>
          <select name="item_condition">
            <?php foreach ($conditions as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full"><label><?= e(t('common.location')) ?></label>
          <div class="suggest">
            <input type="text" name="location" maxlength="100" data-suggest data-kind="location" data-source="suggest-locations">
            <div class="suggest-menu" hidden></div>
          </div>
        </div>
      </div>
      <div class="form-actions"><button class="btn btn-outline" type="submit">Update</button></div>
    </form>
  </div>
</div>
<?php if ($pending): ?>
<div class="panel">
  <h3><?= e(t('ui.write_offs')) ?></h3>
  <table class="data-table">
    <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.item')) ?></th><th><?= e(t('common.movement')) ?></th><th><?= e(t('common.quantity')) ?></th><th><?= e(t('common.status')) ?></th></tr>
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
    <tr><th><?= e(t('common.category')) ?></th><th><?= e(t('common.name')) ?></th><th><?= e(t('ui.qty')) ?></th><th><?= e(t('ui.value')) ?></th><th><?= e(t('ui.value')) ?></th><th><?= e(t('ui.condition')) ?></th><th><?= e(t('common.location')) ?></th><th><?= e(t('common.source')) ?></th><th><?= e(t('ui.added')) ?></th></tr>
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
    <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.item')) ?></th><th><?= e(t('common.movement')) ?></th><th><?= e(t('common.quantity')) ?></th><th><?= e(t('ui.note')) ?></th></tr>
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
<script type="application/json" id="suggest-inventory"><?= json_encode(suggest_inventory_catalog(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
<script type="application/json" id="suggest-locations"><?= json_encode(suggest_location_catalog(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
