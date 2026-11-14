<?php
/** @var list<array<string,mixed>> $items */
/** @var list<string> $movements */
/** @var string $today */
/** @var list<array<string,mixed>> $history */
/** @var array{In Store: int, In Use: int, Retired: int, total: int, lines: int} $totals */
/** @var list<array<string,mixed>> $summary */
$totals = $totals ?? vastra_status_totals();
$summary = $summary ?? vastra_stock_summary();
$summarySum = vastra_summary_roll_up($summary);
?>
<div class="reveal-group">
<div class="action-bar">
  <button class="btn btn-outline" type="button" data-reveal="reveal-vastra"><?= e(t('ui.add_vastra')) ?></button>
  <button class="btn btn-outline" type="button" data-reveal="reveal-vastra-move" id="vastra-move-toolbar"><?= e(t('ui.record_movement')) ?></button>
</div>
<div class="panel reveal-panel" id="reveal-vastra" hidden>
  <h3><?= e(t('ui.add_vastra')) ?><?= help_tip('New stock is always In Store. Use Record a movement to issue to the deity, return, or retire.') ?></h3>
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
      <div class="form-group full"><label><?= e(t('common.notes')) ?></label><input type="text" name="notes"></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.add_vastra_btn')) ?></button></div>
    <script type="application/json" id="suggest-vastra"><?= json_encode(suggest_vastra_names(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
    <script type="application/json" id="suggest-colors"><?= json_encode(suggest_vastra_colors(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
  </form>
</div>
<div class="panel reveal-panel" id="reveal-vastra-move" hidden>
  <h3><?= e(t('ui.record_movement')) ?><?= help_tip('Issue, Return, and Retire update status and quantity. Partial moves split or merge lines automatically.') ?></h3>
  <form method="POST" action="<?= e(url('vastra')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="move">
    <div class="form-grid">
      <div class="form-group">
        <label><?= e(t('common.item')) ?></label>
        <select name="vastra_id" required id="vastra-move-select">
          <option value="">Choose…</option>
          <?php foreach ($items as $v): ?>
          <option value="<?= e((string) $v['id']) ?>" data-status="<?= e((string) $v['status']) ?>">
            <?= e($v['deity_name']) ?> — <?= e($v['item_name']) ?><?php if (($v['color'] ?? '') !== ''): ?> (<?= e($v['color']) ?>)<?php endif; ?>
            · qty <?= e((string) $v['quantity']) ?> · <?= e((string) $v['status']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= e(t('common.movement')) ?></label>
        <select name="movement_type" id="vastra-move-type">
          <?php foreach ($movements as $m): ?><option value="<?= e($m) ?>"><?= e($m) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="quantity" min="1" value="1" required></div>
      <div class="form-group"><label><?= e(t('common.date')) ?></label><input type="date" name="movement_date" value="<?= e($today) ?>" required></div>
      <div class="form-group full"><label><?= e(t('ui.note')) ?></label><input type="text" name="note" maxlength="255" placeholder="e.g. Rath Yatra dress"></div>
    </div>
    <p class="sub" id="vastra-move-hint">In Store → Issue · In Use → Return</p>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.record_movement_btn')) ?></button></div>
  </form>
</div>
</div>
<div class="kpi-grid vastra-kpi-grid">
  <div class="kpi-card warn">
    <div class="value"><?= e((string) $totals['In Store']) ?></div>
    <div class="label">In store (stock)</div>
  </div>
  <div class="kpi-card good">
    <div class="value"><?= e((string) $totals['In Use']) ?></div>
    <div class="label">On deity (in use)</div>
  </div>
  <div class="kpi-card">
    <div class="value"><?= e((string) $totals['Retired']) ?></div>
    <div class="label">Retired</div>
  </div>
  <div class="kpi-card">
    <div class="value"><?= e((string) $totals['total']) ?></div>
    <div class="label">Total pieces · <?= e((string) $totals['lines']) ?> lines</div>
  </div>
</div>
<div class="panel">
  <h3>Stock by item (<?= count($summary) ?> types)</h3>
  <p class="sub">One row per deity and item — quantities are combined, not duplicate lines.</p>
  <?php if ($summary): ?>
  <div class="table-scroll">
    <table class="data-table vastra-stock-table">
      <thead>
        <tr>
          <th class="col-text"><?= e(t('common.deity')) ?></th>
          <th class="col-text col-item"><?= e(t('common.item')) ?></th>
          <th class="col-text"><?= e(t('common.color')) ?></th>
          <th class="col-num">In store</th>
          <th class="col-num">In use</th>
          <th class="col-num">Retired</th>
          <th class="col-num">Purchased</th>
          <th class="col-num">Donated</th>
          <th class="col-num">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary as $row): ?>
        <tr>
          <td class="col-text"><?= e($row['deity_name']) ?></td>
          <td class="col-text col-item"><?= e($row['item_name']) ?></td>
          <td class="col-text"><?= e(dash($row['color'])) ?></td>
          <td class="col-num"><?= e((string) $row['in_store']) ?></td>
          <td class="col-num"><?= e((string) $row['in_use']) ?></td>
          <td class="col-num"><?= e((string) $row['retired']) ?></td>
          <td class="col-num"><?= e((string) $row['purchased']) ?></td>
          <td class="col-num"><?= e((string) $row['donated']) ?></td>
          <td class="col-num"><strong><?= e((string) $row['total']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="report-total-row">
          <td class="col-text" colspan="3"><strong>All items</strong></td>
          <td class="col-num"><strong><?= e((string) $summarySum['in_store']) ?></strong></td>
          <td class="col-num"><strong><?= e((string) $summarySum['in_use']) ?></strong></td>
          <td class="col-num"><strong><?= e((string) $summarySum['retired']) ?></strong></td>
          <td class="col-num"><strong><?= e((string) $summarySum['purchased']) ?></strong></td>
          <td class="col-num"><strong><?= e((string) $summarySum['donated']) ?></strong></td>
          <td class="col-num"><strong><?= e((string) $summarySum['total']) ?></strong></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state">No vastra stock yet.</div>
  <?php endif; ?>
</div>
<details class="panel vastra-line-detail">
  <summary class="vastra-line-detail-summary">Stock lines (<?= count($items) ?> ledger rows)</summary>
  <p class="sub">Separate rows after partial issue or return. Use the summary table above for totals.</p>
  <?php if ($items): ?>
  <div class="table-scroll">
  <table class="data-table vastra-stock-table">
    <thead>
      <tr>
        <th class="col-text"><?= e(t('common.deity')) ?></th>
        <th class="col-text col-item"><?= e(t('common.item')) ?></th>
        <th class="col-text"><?= e(t('common.color')) ?></th>
        <th class="col-num"><?= e(t('ui.qty')) ?></th>
        <th class="col-text"><?= e(t('common.source')) ?></th>
        <th class="col-text"><?= e(t('common.status')) ?></th>
        <th class="col-text"><?= e(t('ui.added')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $v): ?>
    <tr>
      <td class="col-text"><?= e($v['deity_name']) ?></td>
      <td class="col-text col-item"><?= e($v['item_name']) ?></td>
      <td class="col-text"><?= e(dash($v['color'])) ?></td>
      <td class="col-num"><?= e((string) $v['quantity']) ?></td>
      <td class="col-text"><?php if ($v['source'] === 'Donated'): ?><span class="badge badge-blue">Donated</span><?php else: ?>Purchased<?php endif; ?></td>
      <td class="col-text">
        <?php if ($v['status'] === 'In Use'): ?><span class="badge badge-green">In Use</span>
        <?php elseif ($v['status'] === 'Retired'): ?><span class="badge badge-grey">Retired</span>
        <?php else: ?><span class="badge badge-amber">In Store</span><?php endif; ?>
      </td>
      <td class="col-text"><?= e($v['date_added']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <div class="empty-state">No vastra items recorded yet.</div>
  <?php endif; ?>
</details>
<?php if ($history !== []): ?>
<div class="panel">
  <h3>Recent movements</h3>
  <div class="table-scroll">
  <table class="data-table vastra-stock-table">
    <thead>
      <tr>
        <th class="col-text"><?= e(t('common.date')) ?></th>
        <th class="col-text col-item"><?= e(t('common.item')) ?></th>
        <th class="col-text"><?= e(t('common.movement')) ?></th>
        <th class="col-num"><?= e(t('ui.qty')) ?></th>
        <th class="col-text"><?= e(t('ui.note')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($history as $m): ?>
      <tr>
        <td class="col-text"><?= e($m['movement_date']) ?></td>
        <td class="col-text col-item"><?= e($m['deity_name']) ?> — <?= e($m['item_name']) ?></td>
        <td class="col-text"><?= e($m['movement_type']) ?></td>
        <td class="col-num"><?= e((string) $m['quantity']) ?></td>
        <td class="col-text"><?= e(dash($m['note'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>
<script>
(function () {
  var select = document.getElementById('vastra-move-select');
  var typeSelect = document.getElementById('vastra-move-type');
  var hint = document.getElementById('vastra-move-hint');

  function defaultMovementForStatus(status) {
    if (status === 'In Use') return 'Return';
    if (status === 'In Store') return 'Issue';
    return 'Retire';
  }

  function syncMovementFromLine() {
    if (!select || !typeSelect) return;
    var opt = select.options[select.selectedIndex];
    var status = opt ? opt.getAttribute('data-status') : '';
    if (!status) return;
    typeSelect.value = defaultMovementForStatus(status);
    if (hint) hint.textContent = 'In Store → Issue · In Use → Return';
  }

  if (select) select.addEventListener('change', syncMovementFromLine);
})();
</script>