<?php /** @var list<array<string,mixed>> $items */ /** @var list<string> $categories */ ?>
<div class="panel">
  <h3>Add Inventory Item</h3>
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
      <div class="form-group"><label>Unit</label><input type="text" name="unit" value="pcs"></div>
      <div class="form-group">
        <label>Condition</label>
        <select name="item_condition">
          <option>New</option><option selected>Good</option><option>Fair</option>
          <option>Needs Repair</option><option>Damaged</option>
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
  <h3>Inventory List (<?= count($items) ?> items)</h3>
  <?php if ($items): ?>
  <table class="data-table">
    <tr><th>Category</th><th>Name</th><th>Qty</th><th>Condition</th><th>Location</th><th>Source</th><th>Added</th></tr>
    <?php foreach ($items as $i): ?>
    <tr>
      <td><?= e($i['category']) ?></td>
      <td><?= e($i['name']) ?><?php if (!empty($i['description'])): ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e($i['description']) ?></span><?php endif; ?></td>
      <td><?= e($i['quantity']) ?> <?= e($i['unit']) ?></td>
      <td>
        <?php if (in_array($i['item_condition'], ['New', 'Good'], true)): ?>
          <span class="badge badge-green"><?= e($i['item_condition']) ?></span>
        <?php elseif ($i['item_condition'] === 'Fair'): ?>
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
