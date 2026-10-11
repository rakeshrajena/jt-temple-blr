<?php /** @var array $coupons */ ?>
<h1>Food / prasadam coupons</h1>
<p class="muted">These categories appear under "Add Food / Prasadam Coupons" and in Food-Only Booking. Price changes apply to new bookings only.</p>
<?php foreach (array_merge($coupons, [['id' => 0, 'name' => '', 'description' => '', 'price_paise' => 0, 'max_per_booking' => 200, 'is_active' => true, 'sort_order' => count($coupons)]]) as $c): ?>
<form method="post" action="<?= e(url('/admin/coupons/save')) ?>" class="panel form coupon-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
  <h2 class="h3"><?= $c['id'] ? e($c['name']) : 'Add a coupon category' ?></h2>
  <div class="grid-3">
    <label>Name <input name="name" value="<?= e($c['name']) ?>" maxlength="100" required></label>
    <label>Price per person (₹) <input name="price" inputmode="decimal" value="<?= $c['id'] ? e(number_format($c['price_paise'] / 100, 2, '.', '')) : '' ?>" required></label>
    <label>Max persons per booking <input type="number" name="max_per_booking" min="1" value="<?= (int) $c['max_per_booking'] ?>"></label>
    <label>Short description <input name="description" value="<?= e((string) $c['description']) ?>" maxlength="255"></label>
    <label>Display order <input type="number" name="sort_order" value="<?= (int) $c['sort_order'] ?>"></label>
    <label class="inline"><input type="checkbox" name="is_active" value="1"<?= $c['is_active'] ? ' checked' : '' ?>> Available</label>
  </div>
  <button class="btn<?= $c['id'] ? '' : ' btn-primary' ?>" type="submit"><?= $c['id'] ? 'Save' : 'Add coupon' ?></button>
</form>
<?php endforeach; ?>
