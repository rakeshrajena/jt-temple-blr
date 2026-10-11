<?php /** @var array $services @var array $slots @var bool $pricesReviewed */ ?>
<div class="row-between">
  <h1>Services &amp; prices</h1>
  <a class="btn btn-primary" href="<?= e(url('/admin/services/new')) ?>">Add a service</a>
</div>
<?php if (!$pricesReviewed): ?>
<div class="alert alert-warn">
  The prices below were filled in during installation as <strong>placeholders</strong>. Please check each one with the temple committee, edit as needed, then confirm.
  <form method="post" action="<?= e(url('/admin/prices/confirm')) ?>" class="form-inline" data-confirm="Confirm that all service and food coupon prices are correct?"><?= csrf_field() ?><button class="btn btn-small" type="submit">I have reviewed all prices</button></form>
</div>
<?php endif; ?>
<div class="panel table-wrap">
<table class="table">
  <thead><tr><th>Service</th><th class="num">Price</th><th>Rules</th><th>Time slots</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($services as $s): ?>
    <tr>
      <td><strong><?= e($s['name']) ?></strong><?= $s['is_food_only'] ? '<br><small class="muted">Priced by food coupons</small>' : '' ?></td>
      <td class="num"><?= $s['is_food_only'] ? '—' : ($s['price_paise'] > 0 ? e(money($s['price_paise'])) : '<span class="muted">On request</span>') ?></td>
      <td><small><?= implode(' · ', array_filter([
          $s['requires_approval'] ? 'needs approval' : null,
          $s['collect_participants'] ? 'participants ≤ ' . $s['max_participants'] : null,
          $s['allow_food'] ? 'food allowed' : 'no food',
          $s['is_combinable'] ? null : 'book alone',
          $s['daily_capacity'] !== null ? 'max ' . $s['daily_capacity'] . '/day' : null,
          $s['fields'] ? count($s['fields']) . ' extra field(s)' : null,
      ])) ?></small></td>
      <td><small><?php foreach ($slots[$s['id']] ?? [] as $sl): ?><?= e($sl['label']) ?> (<?= $sl['capacity'] ?? '∞' ?>)<?= $sl['is_active'] ? '' : ' <em>off</em>' ?><br><?php endforeach; ?></small></td>
      <td><?= $s['is_active'] ? '<span class="badge badge-ok">Bookable</span>' : '<span class="badge badge-bad">Disabled</span>' ?></td>
      <td><a class="btn btn-small" href="<?= e(url('/admin/services/' . $s['id'])) ?>">Edit</a></td>
    </tr>
  <?php endforeach; ?></tbody>
</table>
</div>
<p class="muted small">Food coupon prices are on the <a href="<?= e(url('/admin/coupons')) ?>">Food coupons</a> page. Price changes apply to new bookings only.</p>
