<?php
/** @var array{error:?string,code:?string,amount:?float,purpose:?string,status:?string} $preview */
/** @var bool $saved */
$code = (string) ($preview['code'] ?? '');
?>
<div class="panel">
  <?php if ($saved): ?>
    <h3>Coupon recorded</h3>
    <p><?= e($code) ?> is a <?= e(money((float) $preview['amount'], 2)) ?> <?= e((string) ($preview['payment_mode'] ?? 'Cash')) ?> donation for <?= e((string) $preview['purpose']) ?>, under <?= e((string) ($preview['donor_name'] ?? 'Coupon counter')) ?>. It is in the books.</p>
  <?php elseif ($preview['error'] !== null): ?>
    <h3>Coupon not recorded</h3>
    <p><?= e((string) $preview['error']) ?></p>
    <?php if ($code !== ''): ?><p class="sub"><?= e($code) ?></p><?php endif; ?>
  <?php else: ?>
    <h3>Coupon</h3>
    <p>Open a coupon link to record it.</p>
  <?php endif; ?>
  <p class="form-actions"><a class="btn btn-outline" href="<?= e(url('food/coupons')) ?>">Food coupons</a></p>
</div>
