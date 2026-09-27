<?php
/** @var array{error:?string,code:?string,amount:?float,purpose:?string,status:?string} $preview */
/** @var bool $saved */
$code = (string) ($preview['code'] ?? '');
?>
<div class="panel">
  <?php if ($saved): ?>
    <h3>Coupon recorded</h3>
    <p><?= e($code) ?> is a <?= e(money((float) $preview['amount'], 2)) ?> donation for <?= e((string) $preview['purpose']) ?>. It is in the books.</p>
  <?php elseif ($preview['error'] !== null): ?>
    <h3>Coupon not recorded</h3>
    <p><?= e((string) $preview['error']) ?></p>
    <?php if ($code !== ''): ?><p class="sub"><?= e($code) ?></p><?php endif; ?>
  <?php else: ?>
    <h3>Checking coupon</h3>
    <p><?= e($code) ?> · <?= e((string) $preview['purpose']) ?> · <?= e(money((float) $preview['amount'], 2)) ?></p>
    <form id="coupon-scan-form" method="POST" action="<?= e(url('coupons/scan')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="code" value="<?= e($code) ?>">
      <button class="btn btn-primary" type="submit">Record this coupon</button>
    </form>
    <script>document.getElementById('coupon-scan-form').requestSubmit();</script>
  <?php endif; ?>
  <p class="form-actions"><a class="btn btn-outline" href="<?= e(url('food/coupons')) ?>">Food coupons</a></p>
</div>
