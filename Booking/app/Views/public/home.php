<?php
/**
 * @var array|null $foodOnlyService First food-only catalog row (code food-only)
 * @var array $coupons Prasadam menu for subtitle on Book Prasad card
 */
$prasadLead = 'Madhyanha Prasad and food coupons for your chosen day.';
if ($coupons !== []) {
    $names = array_map(fn ($c) => $c['name'], array_slice($coupons, 0, 2));
    $prasadLead = implode(' and ', $names) . ' for a chosen day.';
}
if ($foodOnlyService && trim((string) ($foodOnlyService['description'] ?? '')) !== '') {
    $prasadLead = (string) $foodOnlyService['description'];
}
$prasadUrl = url('/book', ['service' => $foodOnlyService['code'] ?? 'food-only']);
?>
<section class="home-menu" aria-labelledby="home-menu-title">
  <header class="home-menu-head">
    <h1 id="home-menu-title">What would you like to do?</h1>
    <p class="home-menu-lead">Choose one option to continue.</p>
  </header>

  <ul class="home-paths">
    <li>
      <a class="path-card" href="<?= e($prasadUrl) ?>">
        <span class="path-card-title">Book Prasad</span>
        <span class="path-card-desc"><?= e($prasadLead) ?></span>
        <span class="path-card-action">Continue <span aria-hidden="true">→</span></span>
      </a>
    </li>
    <li>
      <a class="path-card" href="<?= e(url('/book')) ?>">
        <span class="path-card-title">Book Puja</span>
        <span class="path-card-desc">Choose a puja or ceremony and add prasad for your group if you wish.</span>
        <span class="path-card-action">Continue <span aria-hidden="true">→</span></span>
      </a>
    </li>
    <li>
      <a class="path-card" href="<?= e(url('/lookup')) ?>">
        <span class="path-card-title">Find my booking</span>
        <span class="path-card-desc">Check status with your booking number and mobile number.</span>
        <span class="path-card-action">Continue <span aria-hidden="true">→</span></span>
      </a>
    </li>
  </ul>
</section>

<section class="how home-how" aria-labelledby="how-h">
  <h2 id="how-h">How it works</h2>
  <ol class="how-steps">
    <li><strong>Choose</strong> prasad only, or a puja with optional food coupons.</li>
    <li><strong>Review</strong> the summary and submit. You receive a booking number.</li>
    <li><strong>Pay</strong> online when payment is due.</li>
    <li><strong>Confirmation</strong> follows once payment is verified and, where required, the temple approves your seva.</li>
  </ol>
</section>
