<?php
/** @var list<array<string,mixed>> $batches */
/** @var float $totalCouponsValue */
/** @var int $totalCouponQty */
?>
<a href="<?= e(url('food')) ?>" class="btn btn-outline btn-sm" style="margin-bottom:16px;">← Back to Food Stock</a>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($totalCouponsValue)) ?></div><div class="label">Total Value of All Coupons Generated</div></div>
  <div class="kpi-card"><div class="value"><?= count($batches) ?></div><div class="label">Batches Generated</div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $totalCouponQty) ?></div><div class="label">Total Coupons Printed</div></div>
</div>
<div class="panel">
  <h3>Generate a New Coupon Batch</h3>
  <p style="color:var(--ink-soft); font-size:13px; margin-top:-8px;">
    Serial numbers continue automatically from the last batch, so coupons never overlap or repeat —
    useful for reconciling how many were actually redeemed later.
  </p>
  <form method="POST" action="<?= e(url('food/coupons')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Coupon Name</label><input type="text" name="coupon_name" placeholder="e.g. Lunch Mahaprasad" required></div>
      <div class="form-group"><label>Cost per Coupon (₹)</label><input type="number" step="0.01" name="cost" required></div>
      <div class="form-group"><label>Quantity</label><input type="number" name="quantity" min="1" max="400" required></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Generate &amp; Prepare for Print</button></div>
  </form>
</div>
<div class="panel">
  <h3>Coupon Batches (<?= count($batches) ?>)</h3>
  <?php if ($batches): ?>
  <table class="data-table">
    <tr><th>Coupon Name</th><th>Cost</th><th>Sl No Range</th><th>Quantity</th><th>Total Value</th><th>Created</th><th>By</th><th></th></tr>
    <?php foreach ($batches as $b): ?>
    <tr>
      <td><?= e($b['coupon_name']) ?></td>
      <td><?= e(money($b['cost'])) ?></td>
      <td><?= e((string) $b['start_sl_no']) ?> – <?= e((string) $b['end_sl_no']) ?></td>
      <td><?= e((string) $b['quantity']) ?></td>
      <td><?= e(money($b['total_value'])) ?></td>
      <td><?= e($b['created_date']) ?></td>
      <td><?= e(dash($b['created_by_name'])) ?></td>
      <td><a href="<?= e(url('food/coupons/' . $b['id'] . '/print')) ?>" target="_blank" class="btn btn-sm btn-gold">🖨️ Print PDF</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No coupon batches yet — generate the first one above.</div>
  <?php endif; ?>
</div>
