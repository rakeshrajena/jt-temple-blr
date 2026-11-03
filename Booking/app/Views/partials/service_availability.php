<?php
/** @var array $s Service row from catalog */
if (!empty($s['is_food_only'])) {
    return;
}
$sid = (int) $s['id'];
$hasDaily = $s['daily_capacity'] !== null;
$hasSlots = !empty($s['requires_slot']);
if (!$hasDaily && !$hasSlots) {
    return;
}
?>
<div class="service-availability" data-service-availability="<?= $sid ?>">
  <div class="coupon-stat-grid">
    <?php if ($hasDaily): ?>
    <div class="coupon-stat">
      <span class="coupon-stat-k">Daily limit (all bookings)</span>
      <span class="coupon-stat-v"><?= (int) $s['daily_capacity'] ?> booking<?= (int) $s['daily_capacity'] === 1 ? '' : 's' ?> max</span>
    </div>
    <div class="coupon-stat coupon-stat-live" data-stat-live-row>
      <span class="coupon-stat-k">On your chosen date</span>
      <span class="coupon-stat-v" data-stat-live-value>Choose your date in step 2 to see live counts</span>
    </div>
    <?php elseif ($hasSlots): ?>
    <div class="coupon-stat coupon-stat-live" data-stat-live-row data-stat-slots-only>
      <span class="coupon-stat-k">Time slots on your date</span>
      <span class="coupon-stat-v" data-stat-live-value>Choose your date in step 2 to see open times</span>
    </div>
    <?php endif; ?>
  </div>
  <p class="coupon-status badge badge-info" data-service-status="<?= $sid ?>" hidden></p>
</div>
