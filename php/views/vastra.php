<?php /** @var list<array<string,mixed>> $items */ ?>
<div class="panel">
  <h3><?= e(t('ui.add_vastra')) ?></h3>
  <form method="POST" action="<?= e(url('vastra')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group">
        <label><?= e(t('common.deity')) ?></label>
        <select name="deity_name" required>
          <?php foreach (selection_values('deities') as $deity): ?><option><?= e($deity) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('ui.item_name')) ?></label>
        <div class="suggest">
          <input type="text" name="item_name" placeholder="e.g. Silk Pata" required data-suggest data-kind="vastra" data-source="suggest-vastra" data-fill="color:color">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label><?= e(t('common.color')) ?></label>
        <div class="suggest">
          <input type="text" name="color" data-suggest data-kind="color" data-source="suggest-colors" data-filter="item_name:item">
          <div class="suggest-menu" hidden></div>
        </div>
      </div>
      <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="quantity" value="1" min="1"></div>
      <div class="form-group">
        <label><?= e(t('common.source')) ?></label>
        <select name="source">
          <?php foreach (selection_values('sources') as $source): ?><option><?= e($source) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= e(t('common.status')) ?></label>
        <select name="status">
          <?php foreach (selection_values('vastra_statuses') as $status): ?><option><?= e($status) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full"><label><?= e(t('common.notes')) ?></label><input type="text" name="notes"></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.add_vastra_btn')) ?></button></div>
    <script type="application/json" id="suggest-vastra"><?= json_encode(suggest_vastra_names(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
    <script type="application/json" id="suggest-colors"><?= json_encode(suggest_vastra_colors(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
  </form>
</div>
<div class="panel">
  <h3>Vastra Inventory (<?= count($items) ?> entries)</h3>
  <?php if ($items): ?>
  <table class="data-table">
    <tr><th><?= e(t('common.deity')) ?></th><th><?= e(t('common.item')) ?></th><th><?= e(t('common.color')) ?></th><th><?= e(t('ui.qty')) ?></th><th><?= e(t('common.source')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('ui.added')) ?></th></tr>
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
