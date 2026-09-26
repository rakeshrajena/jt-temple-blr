<?php
/** @var list<array<string,mixed>> $batches */
/** @var float $totalCouponsValue */
/** @var int $totalCouponQty */
/** @var string $role */
?>
<a href="<?= e(url('food')) ?>" class="btn btn-outline btn-sm" style="margin-bottom:16px;">← Back to Food Stock</a>
<div class="kpi-grid">
  <div class="kpi-card good"><div class="value"><?= e(money($totalCouponsValue)) ?></div><div class="label">Approved coupon value</div></div>
  <div class="kpi-card"><div class="value"><?= count($batches) ?></div><div class="label">Batches</div></div>
  <div class="kpi-card"><div class="value"><?= e((string) $totalCouponQty) ?></div><div class="label">Approved coupons</div></div>
</div>
<div class="panel">
  <h3><?= e(t('ui.generate_batch')) ?></h3>
  <p class="sub">A batch is a print run. It does not enter the cash book, day book, or ledger. The amount that waits for approval is the face value of the whole batch, cost times quantity. A Treasurer can approve up to ₹<?= e(number_format(TREASURER_APPROVAL_LIMIT, 0)) ?>. Above that, an Admin decides. The person who prepared the batch cannot approve it. It can be printed only after approval.</p>
  <form method="POST" action="<?= e(url('food/coupons')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label><?= e(t('ui.coupon_name')) ?></label><input type="text" name="coupon_name" placeholder="e.g. Lunch Mahaprasad" required></div>
      <div class="form-group"><label><?= e(t('ui.cost_per')) ?></label><input type="number" step="0.01" min="0.01" name="cost" required></div>
      <div class="form-group"><label><?= e(t('common.quantity')) ?></label><input type="number" name="quantity" min="1" max="400" required></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.submit_batch')) ?></button></div>
  </form>
</div>
<div class="panel">
  <h3>Coupon batches (<?= count($batches) ?>)</h3>
  <p class="sub">Edit the name, the cost, or the quantity. A change goes back to approval, and the printed sheet is rebuilt only after it is approved. Removing a batch does not change the cash book. Serial numbers stay in their range, so a larger quantity is refused when it would overlap the next batch.</p>
  <?php if ($batches): ?>
  <table class="data-table">
    <tr><th><?= e(t('ui.coupon_name')) ?></th><th><?= e(t('ui.serial')) ?></th><th><?= e(t('ui.value')) ?></th><th><?= e(t('common.approval')) ?></th><th><?= e(t('common.update')) ?></th><th></th></tr>
    <?php foreach ($batches as $b): ?>
    <?php $status = (string) ($b['approval_status'] ?? 'Waiting'); ?>
    <tr>
      <td>
        <?= e($b['coupon_name']) ?><br>
        <span style="color:var(--ink-soft);font-size:12px;"><?= e($b['created_date']) ?> · <?= e(dash($b['created_by_name'])) ?></span>
      </td>
      <td><?= e((string) $b['start_sl_no']) ?> – <?= e((string) $b['end_sl_no']) ?><br><span style="color:var(--ink-soft);font-size:12px;"><?= e((string) $b['quantity']) ?> coupons</span></td>
      <td><?= e(money($b['cost'])) ?> each<br><?= e(money($b['total_value'], 2)) ?> total</td>
      <td>
        <?php
          $badge = match ($status) {
              'Approved' => 'badge-green',
              'Waiting' => 'badge-amber',
              'Sent back' => 'badge-blue',
              'Rejected' => 'badge-red',
              default => 'badge-grey',
          };
        ?>
        <span class="badge <?= e($badge) ?>"><?= e($status) ?></span>
      </td>
      <td>
        <form method="POST" action="<?= e(url('food/coupons/' . $b['id'])) ?>">
          <?= csrf_field() ?>
          <input type="text" name="coupon_name" value="<?= e((string) $b['coupon_name']) ?>" maxlength="100" required>
          <input type="number" name="cost" step="0.01" min="0.01" value="<?= e(number_format((float) $b['cost'], 2, '.', '')) ?>" required>
          <input type="number" name="quantity" min="1" max="400" value="<?= e((string) $b['quantity']) ?>" required>
          <button class="btn btn-sm btn-outline" type="submit"><?= e(t('common.save')) ?></button>
        </form>
      </td>
      <td>
        <?php if ($status === 'Approved'): ?>
          <a href="<?= e(url('food/coupons/' . $b['id'] . '/print')) ?>" target="_blank" class="btn btn-sm btn-gold">Print PDF</a>
        <?php endif; ?>
        <?php if ($status !== 'Approved' || $role === 'Admin'): ?>
        <form method="POST" action="<?= e(url('food/coupons/' . $b['id'] . '/remove')) ?>" onsubmit="return confirm('Remove this coupon batch? The cash book does not change.');">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-outline" type="submit"><?= e(t('common.remove')) ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state">No coupon batches yet — generate the first one above.</div>
  <?php endif; ?>
</div>
