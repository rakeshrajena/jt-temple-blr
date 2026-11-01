<?php
/** @var list<array<string,mixed>> $bookingBlocked @var list<array<string,mixed>> $bookingServicesActive @var string $bookingAvailDate @var list<array<string,mixed>> $bookingDayView @var bool $bookingCatalogCanEdit */
$wd = ['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '7' => 'Sun'];
$settingsAvailUrl = url('settings') . '#ob-availability';
?>
<article id="ob-availability" class="settings-card settings-ob-card booking-availability-panel">
  <header class="settings-ob-card-head">
    <h5><?= e(t('settings.ob_availability')) ?><?= help_tip(t('settings.ob_availability_hint')) ?></h5>
    <p class="muted small"><?= e(t('settings.ob_availability_intro')) ?></p>
  </header>

  <div class="settings-booking-cols">
    <div class="settings-card settings-ob-subcard">
      <h5><?= e(t('settings.ob_close_dates')) ?></h5>
      <?php if ($bookingCatalogCanEdit): ?>
      <form method="POST" action="<?= e(url('settings')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="booking_block_dates">
        <div class="form-group"><label><?= e(t('settings.ob_from')) ?></label><input type="date" name="from" required></div>
        <div class="form-group"><label><?= e(t('settings.ob_to')) ?></label><input type="date" name="to"><span class="muted small"><?= e(t('settings.ob_to_hint')) ?></span></div>
        <div class="form-group full"><label><?= e(t('settings.ob_applies')) ?></label>
          <select name="service_id">
            <option value=""><?= e(t('settings.ob_all_services')) ?></option>
            <?php foreach ($bookingServicesActive as $s): ?>
              <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full"><label><?= e(t('settings.ob_block_reason')) ?></label><input name="reason" maxlength="255" placeholder="<?= e(t('settings.ob_block_reason_ph')) ?>"></div>
        <div class="form-group full"><button class="btn btn-primary" type="submit"><?= e(t('settings.ob_close_submit')) ?></button></div>
      </form>
      <?php else: ?>
        <p class="muted"><?= e(t('settings.ob_prasadam_readonly')) ?></p>
      <?php endif; ?>
    </div>

    <div class="settings-card settings-ob-subcard">
      <h5><?= e(t('settings.ob_closed_list')) ?></h5>
      <?php if (!$bookingBlocked): ?>
        <p class="muted"><?= e(t('settings.ob_no_closed')) ?></p>
      <?php else: ?>
        <div class="choice-table-wrap">
          <table class="choice-table compact">
            <tr><th><?= e(t('common.date')) ?></th><th><?= e(t('settings.ob_applies')) ?></th><th><?= e(t('settings.ob_block_reason')) ?></th><th></th></tr>
            <?php foreach ($bookingBlocked as $bd): ?>
              <tr>
                <td><?= e(booking_fmt_date($bd['block_date'])) ?></td>
                <td><?= e($bd['service_name'] ?? t('settings.ob_all_services')) ?></td>
                <td><?= e((string) ($bd['reason'] ?? '')) ?></td>
                <td><?php if ($bookingCatalogCanEdit): ?>
                  <form method="POST" action="<?= e(url('settings')) ?>" class="form-inline"><?= csrf_field() ?>
                    <input type="hidden" name="form" value="booking_unblock_date">
                    <input type="hidden" name="block_id" value="<?= (int) $bd['id'] ?>">
                    <button class="btn btn-outline btn-sm" type="submit"><?= e(t('settings.ob_reopen')) ?></button>
                  </form>
                <?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="settings-card settings-ob-subcard settings-ob-subcard-wide">
    <div class="settings-head-inline">
      <h5><?= e(t('settings.ob_slot_usage', ['date' => booking_fmt_date($bookingAvailDate)])) ?></h5>
      <form method="GET" action="<?= e(url('settings')) ?>#ob-availability" class="form-inline">
        <input type="date" name="avail_date" value="<?= e($bookingAvailDate) ?>">
        <button class="btn btn-outline btn-sm" type="submit"><?= e(t('settings.ob_show_date')) ?></button>
      </form>
    </div>
    <div class="choice-table-wrap">
      <table class="choice-table compact">
        <tr><th><?= e(t('settings.ob_applies')) ?></th><th><?= e(t('settings.ob_time_slot')) ?></th><th class="num"><?= e(t('settings.ob_booked')) ?></th><th class="num"><?= e(t('settings.ob_capacity')) ?></th><th><?= e(t('common.status')) ?></th></tr>
        <?php foreach ($bookingDayView as $d): $s = $d['service']; ?>
          <?php if (!$d['slots']): ?>
            <tr>
              <td><?= e($s['name']) ?></td>
              <td class="muted"><?= e(t('settings.ob_no_slots')) ?></td>
              <td class="num"><?= (int) $d['used'] ?></td>
              <td class="num"><?= $s['daily_capacity'] ?? '∞' ?></td>
              <td><?php if ($d['blocked']): ?><span class="badge badge-amber"><?= e($d['blocked']) ?></span><?php endif; ?></td>
            </tr>
          <?php endif; ?>
          <?php foreach ($d['slots'] as $i => $sl): ?>
            <tr>
              <td><?= $i === 0 ? e($s['name']) : '' ?></td>
              <td><?= e($sl['label']) ?></td>
              <td class="num"><?= (int) $sl['used'] ?></td>
              <td class="num"><?= $sl['capacity'] ?? '∞' ?></td>
              <td><?php
                if ($d['blocked']) {
                    echo '<span class="badge badge-amber">' . e($d['blocked']) . '</span>';
                } elseif (!$sl['offered']) {
                    echo '<span class="muted">' . e(t('settings.ob_not_offered', ['day' => $wd[booking_catalog_weekday($bookingAvailDate)] ?? ''])) . '</span>';
                } elseif ($sl['capacity'] !== null && $sl['used'] >= $sl['capacity']) {
                    echo e(t('settings.ob_full'));
                } else {
                    echo e(t('settings.ob_open'));
                }
              ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </table>
    </div>
    <p class="muted small"><?= e(t('settings.ob_slots_edit_hint')) ?></p>
  </div>
</article>
