<?php
/** @var string $serviceDate @var array $scheduleLines from booking_schedule_from_* */
$scheduleLines = $scheduleLines ?? [];
?>
<div class="booking-schedule">
  <dl class="kv booking-schedule-kv">
    <dt>Service date</dt>
    <dd><strong><?= e(fmt_date($serviceDate)) ?></strong></dd>
  </dl>
  <?php if ($scheduleLines !== []): ?>
  <h3 class="h4 booking-schedule-heading">Service times</h3>
  <ul class="booking-schedule-list">
    <?php foreach ($scheduleLines as $line): ?>
    <li class="booking-schedule-item<?= !empty($line['cancelled']) ? ' is-cancelled' : '' ?>">
      <span class="booking-schedule-name"><?= e($line['name']) ?></span>
      <?php if (!empty($line['time'])): ?>
        <span class="booking-schedule-time"><?= e($line['time']) ?></span>
      <?php elseif (!empty($line['needs_time'])): ?>
        <span class="booking-schedule-time muted">Time not selected</span>
      <?php endif; ?>
      <?php if (!empty($line['cancelled'])): ?><span class="booking-schedule-badge badge badge-bad">Cancelled</span><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
