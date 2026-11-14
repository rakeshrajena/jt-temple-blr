<?php
/** @var string $reportType */
/** @var array<string, string> $filters */
/** @var array<string, list<string>> $filterOptions */
$select = static function (string $name, string $label, array $choices, string $current, string $allLabel = 'All'): void {
    ?>
    <div class="form-group report-filter-field">
      <label for="report-<?= e($name) ?>"><?= e($label) ?></label>
      <select id="report-<?= e($name) ?>" name="<?= e($name) ?>">
        <option value=""><?= e($allLabel) ?></option>
        <?php foreach ($choices as $choice): ?>
        <option value="<?= e($choice) ?>"<?= strcasecmp($current, $choice) === 0 ? ' selected' : '' ?>><?= e($choice) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
};
?>
<div class="form-group report-filter-field report-filter-search">
  <label for="report-q">Search</label>
  <input type="search" id="report-q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name, note, category…" autocomplete="off">
</div>
<?php if ($reportType === 'donations'): ?>
  <?php $select('dtype', 'Gift type', $filterOptions['dtype'] ?? [], $filters['dtype'] ?? ''); ?>
  <?php $select('pay', 'Payment', $filterOptions['pay'] ?? [], $filters['pay'] ?? ''); ?>
<?php elseif ($reportType === 'expenses'): ?>
  <?php $select('cat', 'Category', $filterOptions['cat'] ?? [], $filters['cat'] ?? ''); ?>
  <?php $select('pay', 'Payment', $filterOptions['pay'] ?? [], $filters['pay'] ?? ''); ?>
<?php elseif ($reportType === 'reconciliation'): ?>
  <?php $select('status', 'Status', $filterOptions['status'] ?? [], $filters['status'] ?? ''); ?>
  <?php $select('ttype', 'Line type', $filterOptions['ttype'] ?? [], $filters['ttype'] ?? ''); ?>
<?php elseif ($reportType === 'food'): ?>
  <div class="form-group report-filter-field">
    <label for="report-stock">Stock</label>
    <select id="report-stock" name="stock">
      <?php
        $stock = $filters['stock'] ?? 'all';
        foreach (['all' => 'All items', 'low' => 'Low stock only', 'ok' => 'OK only'] as $value => $label):
      ?>
      <option value="<?= e($value) ?>"<?= $stock === $value ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
<?php elseif ($reportType === 'inventory'): ?>
  <?php $select('cat', 'Category', $filterOptions['cat'] ?? [], $filters['cat'] ?? ''); ?>
<?php elseif ($reportType === 'vastra'): ?>
  <?php $select('deity', 'Deity', $filterOptions['deity'] ?? [], $filters['deity'] ?? ''); ?>
  <?php $select('vstatus', 'Status', $filterOptions['vstatus'] ?? [], $filters['vstatus'] ?? ''); ?>
<?php endif; ?>