<?php
/** @var list<array<string,mixed>> $items */
/** @var list<array<string,mixed>> $logs */
/** @var list<array<string,mixed>> $pending */
/** @var float $writeOffLimit */
?>
<div class="panel panel-banner">
  <div>
    <h3 style="margin-bottom:2px;">🎟️ Food Coupon Generator</h3>
    <p style="color:var(--ink-soft); font-size:13px; margin:0;">Generate cost-tracked prasad/meal coupons in bulk, with sequential serial numbers and a print-ready PDF.</p>
  </div>
  <a href="<?= e(url('food/coupons')) ?>" class="btn btn-gold"><?= e(t('ui.manage_coupons')) ?> →</a>
</div>
<div class="panel-row">
  <div class="panel">
    <h3><?= e(t('ui.add_food')) ?></h3>
    <form method="POST" action="<?= e(url('food')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="new_item">
      <div class="form-grid">
        <div class="form-group"><label><?= e(t('ui.item_name')) ?></label>
          <div class="suggest">
            <input type="text" name="name" required data-suggest data-kind="food" data-source="suggest-food" data-fill="unit:unit,minimum_threshold:minimum_threshold">
            <div class="suggest-menu" hidden></div>
          </div>
        </div>
        <div class="form-group">
          <label><?= e(t('common.unit')) ?></label>
          <select name="unit">
            <?php foreach (selection_values('units') as $unit): ?><option<?= $unit === 'kg' ? ' selected' : '' ?>><?= e($unit) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label><?= e(t('ui.opening_stock')) ?></label><input type="number" step="0.1" name="current_stock" value="0"></div>
        <div class="form-group"><label><?= e(t('ui.low_threshold')) ?></label><input type="number" step="0.1" name="minimum_threshold" value="0"></div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.add_item')) ?></button></div>
      <script type="application/json" id="suggest-food"><?= json_encode(array_map(static function (array $row): array {
          return [
              'name' => (string) $row['name'],
              'unit' => (string) $row['unit'],
              'stock' => (string) $row['current_stock'],
              'minimum_threshold' => (string) $row['minimum_threshold'],
          ];
      }, $items), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
    </form>
  </div>
  <div class="panel">
    <h3><?= e(t('ui.log_movement')) ?></h3>
    <p class="sub">Kitchen use of <?= e((string) $writeOffLimit) ?> or less is recorded immediately. Above that, it waits for approval and the quantity stays until then.</p>
    <form method="POST" action="<?= e(url('food')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group">
          <label><?= e(t('common.item')) ?></label>
          <select name="food_item_id" required>
            <?php foreach ($items as $f): ?>
              <option value="<?= e((string) $f['id']) ?>"><?= e($f['name']) ?> (<?= e($f['current_stock']) ?> <?= e($f['unit']) ?> in stock)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" step="0.1" name="quantity" required></div>
        <div class="form-group full"><label><?= e(t('ui.purpose_note')) ?></label><input type="text" name="purpose" placeholder="e.g. Daily Mahaprasad, Purchased from supplier"></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-outline" type="submit" name="action" value="add_stock">+ Add Stock</button>
        <button class="btn btn-primary" type="submit" name="action" value="use_stock">− Log Usage</button>
      </div>
    </form>
  </div>
</div>
<div class="panel">
    <h3><?= e(t('ui.current_stock')) ?></h3>
  <table class="data-table">
    <tr><th><?= e(t('common.item')) ?></th><th><?= e(t('ui.current_stock_col')) ?></th><th><?= e(t('common.threshold')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('ui.last_updated')) ?></th></tr>
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
  <h3><?= e(t('ui.usage_log')) ?></h3>
  <?php if ($logs): ?>
  <table class="data-table">
    <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('common.item')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.quantity')) ?></th><th><?= e(t('common.purpose')) ?></th></tr>
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
