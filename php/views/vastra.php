<?php /** @var list<array<string,mixed>> $items */ ?>
<div class="panel">
  <h3>Add Vastra Item</h3>
  <form method="POST" action="<?= e(url('vastra')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label>Deity</label>
        <select name="deity_name" required>
          <option>Jagannath</option><option>Balabhadra</option><option>Subhadra</option><option>Sudarshan</option>
        </select>
      </div>
      <div class="form-group"><label>Item Name</label><input type="text" name="item_name" placeholder="e.g. Silk Pata" required></div>
      <div class="form-group"><label>Color</label><input type="text" name="color"></div>
      <div class="form-group"><label>Quantity</label><input type="number" name="quantity" value="1" min="1"></div>
      <div class="form-group">
        <label>Source</label>
        <select name="source"><option>Purchased</option><option>Donated</option></select>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status"><option>In Store</option><option>In Use</option><option>Retired</option></select>
      </div>
      <div class="form-group full"><label>Notes</label><input type="text" name="notes"></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Add Vastra</button></div>
  </form>
</div>
<div class="panel">
  <h3>Vastra Inventory (<?= count($items) ?> entries)</h3>
  <?php if ($items): ?>
  <table class="data-table">
    <tr><th>Deity</th><th>Item</th><th>Color</th><th>Qty</th><th>Source</th><th>Status</th><th>Added</th></tr>
    <?php foreach ($items as $v): ?>
    <tr>
      <td><?= e($v['deity_name']) ?></td>
      <td><?= e($v['item_name']) ?></td>
      <td><?= e(dash($v['color'])) ?></td>
      <td><?= e((string) $v['quantity']) ?></td>
      <td><?php if ($v['source'] === 'Donated'): ?><span class="badge badge-blue">Donated</span><?php else: ?>Purchased<?php endif; ?></td>
      <td>
        <?php if ($v['status'] === 'In Use'): ?><span class="badge badge-green">In Use</span>
        <?php elseif ($v['status'] === 'Retired'): ?><span class="badge badge-grey">Retired</span>
        <?php else: ?><span class="badge badge-amber">In Store</span><?php endif; ?>
      </td>
      <td><?= e($v['date_added']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No vastra items recorded yet.</div>
  <?php endif; ?>
</div>
