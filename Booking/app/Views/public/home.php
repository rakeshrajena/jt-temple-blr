<?php
/** @var array $services @var array $coupons */
$foodOnly = array_values(array_filter($services, fn ($s) => $s['is_food_only']));
$regular = array_values(array_filter($services, fn ($s) => !$s['is_food_only']));
?>
<section class="hero">
  <p class="hero-kicker">॥ Jai Jagannath ॥</p>
  <h1>Book a puja, ceremony or prasadam</h1>
  <p class="lead">Choose a seva, pick a date and time, add food coupons if you wish, and pay by UPI. You will get a booking number straight away.</p>
  <div class="hero-actions">
    <a class="btn btn-primary btn-lg" href="<?= e(url('/book')) ?>">Start booking</a>
    <a class="btn btn-secondary btn-lg" href="<?= e(url('/lookup')) ?>">Find my booking</a>
  </div>
</section>

<section aria-labelledby="svc-h">
  <h2 id="svc-h">Services you can book</h2>
  <ul class="service-list">
    <?php foreach ($regular as $s): ?>
    <li class="service-tile">
      <div>
        <h3><?= e($s['name']) ?></h3>
        <?php if ($s['description']): ?><p><?= e($s['description']) ?></p><?php endif; ?>
        <p class="meta">
          <?= $s['price_paise'] > 0 ? '<span class="price">' . e(money($s['price_paise'])) . '</span>' : '<span class="price">Charges confirmed by temple</span>' ?>
          <?php if ($s['requires_approval']): ?><span class="tag">Needs temple approval</span><?php endif; ?>
        </p>
      </div>
      <a class="btn btn-secondary" href="<?= e(url('/book', ['service' => $s['code']])) ?>" aria-label="Book <?= e($s['name']) ?>">Book</a>
    </li>
    <?php endforeach; ?>
  </ul>
</section>

<?php if ($coupons): ?>
<section class="food-callout" aria-labelledby="food-h">
  <h2 id="food-h">Food / Prasadam coupons</h2>
  <p>Add prasadam coupons to any booking, or book food coupons on their own.</p>
  <ul class="coupon-chips">
    <?php foreach ($coupons as $c): ?>
      <li><?= e($c['name']) ?> — <strong><?= e(money($c['price_paise'])) ?></strong> per person</li>
    <?php endforeach; ?>
  </ul>
  <?php if ($foodOnly): ?>
    <a class="btn btn-primary" href="<?= e(url('/book', ['service' => $foodOnly[0]['code']])) ?>">Book food coupons only</a>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="how" aria-labelledby="how-h">
  <h2 id="how-h">How it works</h2>
  <ol class="how-steps">
    <li><strong>Choose</strong> the service, date and time.</li>
    <li><strong>Review</strong> the price summary and submit. You get a booking number.</li>
    <li><strong>Pay</strong> by scanning the temple's UPI QR code and enter the UPI reference number.</li>
    <li><strong>Confirmation</strong> follows once the temple office verifies your payment<?= array_filter($regular, fn ($s) => $s['requires_approval']) ? ' (and approves ceremonies that need it)' : '' ?>.</li>
  </ol>
</section>
