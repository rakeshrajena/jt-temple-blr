<section class="card narrow">
  <h1>Find my booking</h1>
  <p>Enter the booking number you received (for example <strong>SJT-2026-000123</strong>) and the mobile number used for the booking.</p>
  <?php if (!empty($errors['lookup'])): ?><div class="alert alert-error" role="alert"><?= e($errors['lookup']) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/lookup')) ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="booking_no">Booking number</label>
      <input id="booking_no" name="booking_no" required autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="30" value="<?= e($in['booking_no'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="mobile">Mobile number</label>
      <input id="mobile" name="mobile" type="tel" inputmode="tel" required autocomplete="tel" maxlength="20" value="<?= e($in['mobile'] ?? '') ?>">
    </div>
    <button class="btn btn-primary btn-lg" type="submit">Find booking</button>
  </form>
</section>
