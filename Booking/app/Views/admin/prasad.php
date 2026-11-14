<h1>Food / Prasad booking</h1>

<section class="panel" id="prasad-dashboard">
  <div class="row-between">
    <h2 class="h3">By service date</h2>
    <form method="get" action="<?= e(url('/admin/prasad')) ?>" class="filters">
      <label>Service date
        <input type="date" name="date" value="<?= e($prasadDate ?? today()) ?>" required>
      </label>
      <div class="filter-actions"><button type="submit" class="btn btn-primary">Show</button></div>
    </form>
  </div>
  <p class="muted small">
    Counts are for the chosen <strong>service date</strong> (persons per prasad type). Limits come from the active food / prasadam menu in
    <?php if (!empty($erp_food_coupons_url)): ?>
      <a href="<?= e($erp_food_coupons_url) ?>">Temple ERP › Prasadam menu</a>.
    <?php else: ?>
      <a href="<?= e(url('/admin/coupons')) ?>">Booking › Coupons</a>.
    <?php endif; ?>
  </p>
  <?php if (empty($prasadCards)): ?>
    <p class="muted">No active prasadam / food coupon types are configured.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table prasad-dash-table">
      <thead>
        <tr>
          <th>Prasad / food</th>
          <th class="num">Daily limit</th>
          <th class="num">Per booking</th>
          <th class="num">Confirmed</th>
          <th class="num">Pending</th>
          <th class="num">Booked total</th>
          <th class="num">Remaining</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($prasadCards as $c): ?>
        <?php
          $daily = $c['daily_max'];
          $remaining = $c['remaining'];
          $nearFull = $daily !== null && $daily > 0 && $remaining !== null && $remaining <= max(1, (int) floor($daily * 0.15));
          $full = $daily !== null && $remaining === 0;
        ?>
        <tr class="prasad-dash-row<?= $full ? ' prasad-dash-full' : ($nearFull ? ' prasad-dash-warn' : '') ?>">
          <td>
            <strong><?= e($c['name']) ?></strong>
            <?php if ($c['description'] !== ''): ?><br><span class="muted small"><?= e($c['description']) ?></span><?php endif; ?>
            <?php if ($c['price_paise'] > 0): ?><br><span class="muted small"><?= e(money((int) $c['price_paise'])) ?> per person</span><?php endif; ?>
          </td>
          <td class="num"><?= $daily === null ? '<span class="muted">No cap</span>' : (int) $daily ?></td>
          <td class="num"><?= (int) $c['max_per_booking'] ?></td>
          <td class="num"><span class="badge badge-ok"><?= (int) $c['confirmed'] ?></span></td>
          <td class="num"><span class="badge badge-warn"><?= (int) $c['pending'] ?></span></td>
          <td class="num"><strong><?= (int) $c['used'] ?></strong></td>
          <td class="num"><?= $remaining === null ? '<span class="muted">—</span>' : (int) $remaining ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted small">Pending and confirmed bookings both count toward the daily limit. Cancelled and rejected bookings are excluded.</p>
  <?php endif; ?>
</section>