<?php
/**
 * Booking form (steps 1-3). Works without JavaScript (all steps shown);
 * with JavaScript it becomes a step-by-step wizard with a live price estimate.
 * @var array $services @var array $coupons @var array $slots @var array $in @var array $errors
 */
$sel = array_map('strval', (array) ($in['services'] ?? []));
$err = fn (string $k) => $errors[$k] ?? null;
$val = fn (string $k) => is_string($in[$k] ?? null) ? $in[$k] : '';
$nested = fn (string $k, $sid, ?string $sub = null) => (string) ($sub === null ? (($in[$k] ?? [])[$sid] ?? '') : ((($in[$k] ?? [])[$sid] ?? [])[$sub] ?? ''));
$addFood = !empty($in['add_food']);
?>
<?php $step = 1; include __DIR__ . '/../partials/progress.php'; ?>

<form method="post" action="<?= e(url('/book/review')) ?>" class="booking-form" id="booking-form" novalidate data-pricing="<?= e($pricingJson) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="form_id" value="<?= e($formId) ?>">

  <?php include __DIR__ . '/../partials/errors.php'; ?>

  <!-- STEP 1: services -->
  <fieldset class="step card" data-step="1" id="services-section">
    <legend><h1>1. Choose a service</h1></legend>
    <p class="hint">Tick one or more services. To book only food / prasadam coupons, choose <strong>Food-Only Booking</strong>.</p>
    <?php if ($err('services')): ?><p class="field-error" id="services-error"><?= e($err('services')) ?></p><?php endif; ?>
    <div class="service-options">
      <?php foreach ($services as $s): $checked = in_array((string) $s['id'], $sel, true); ?>
      <label class="option-card<?= $s['is_food_only'] ? ' option-food' : '' ?>">
        <input type="checkbox" name="services[]" value="<?= (int) $s['id'] ?>" <?= $checked ? 'checked' : '' ?>
               data-service="<?= (int) $s['id'] ?>" data-food-only="<?= $s['is_food_only'] ? '1' : '0' ?>" data-combinable="<?= $s['is_combinable'] ? '1' : '0' ?>">
        <span class="option-body">
          <span class="option-title"><?= e($s['name']) ?></span>
          <?php if ($s['description']): ?><span class="option-desc"><?= e($s['description']) ?></span><?php endif; ?>
          <span class="option-meta">
            <?php if ($s['is_food_only']): ?>Price depends on food coupons
            <?php elseif ($s['price_paise'] > 0): ?><span class="price"><?= e(money($s['price_paise'])) ?></span>
            <?php else: ?>Charges confirmed by the temple office<?php endif; ?>
            <?php if ($s['requires_approval']): ?><span class="tag">Needs temple approval</span><?php endif; ?>
          </span>
        </span>
      </label>
      <?php endforeach; ?>
    </div>
    <div class="step-nav"><button type="button" class="btn btn-primary btn-lg" data-next>Next: your details</button></div>
  </fieldset>

  <!-- STEP 2: details -->
  <fieldset class="step card" data-step="2">
    <legend><h2>2. Your details</h2></legend>
    <div class="grid-2">
      <div class="field<?= $err('name') ? ' has-error' : '' ?>">
        <label for="name">Full name <span class="req">(required)</span></label>
        <input id="name" name="name" required maxlength="100" autocomplete="name" value="<?= e($val('name')) ?>" <?= $err('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
        <?php if ($err('name')): ?><p class="field-error" id="name-error"><?= e($err('name')) ?></p><?php endif; ?>
      </div>
      <div class="field<?= $err('mobile') ? ' has-error' : '' ?>">
        <label for="mobile">Mobile number (WhatsApp) <span class="req">(required)</span></label>
        <input id="mobile" name="mobile" type="tel" inputmode="tel" required maxlength="20" autocomplete="tel" placeholder="98765 43210" value="<?= e($val('mobile')) ?>" aria-describedby="mobile-hint<?= $err('mobile') ? ' mobile-error' : '' ?>" <?= $err('mobile') ? 'aria-invalid="true"' : '' ?>>
        <p class="hint" id="mobile-hint">Booking updates are sent to this number on WhatsApp.</p>
        <?php if ($err('mobile')): ?><p class="field-error" id="mobile-error"><?= e($err('mobile')) ?></p><?php endif; ?>
      </div>
      <div class="field<?= $err('email') ? ' has-error' : '' ?>">
        <label for="email">Email <span class="opt">(optional)</span></label>
        <input id="email" name="email" type="email" inputmode="email" maxlength="150" autocomplete="email" value="<?= e($val('email')) ?>" <?= $err('email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
        <?php if ($err('email')): ?><p class="field-error" id="email-error"><?= e($err('email')) ?></p><?php endif; ?>
      </div>
      <div class="field<?= $err('service_date') ? ' has-error' : '' ?>">
        <label for="service_date">Preferred date <span class="req">(required)</span></label>
        <input id="service_date" name="service_date" type="date" required min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>" value="<?= e($val('service_date')) ?>" aria-describedby="date-hint<?= $err('service_date') ? ' service_date-error' : '' ?>" <?= $err('service_date') ? 'aria-invalid="true"' : '' ?>>
        <p class="hint" id="date-hint">Bookings open from <?= e(fmt_date($minDate)) ?> to <?= e(fmt_date($maxDate)) ?>.</p>
        <p class="field-error" id="service_date-error" data-date-message <?= $err('service_date') ? '' : 'hidden' ?>><?= e($err('service_date') ?? '') ?></p>
      </div>
    </div>

    <?php foreach ($services as $s): $sid = $s['id']; $on = in_array((string) $sid, $sel, true); ?>
    <div class="service-panel" data-panel="<?= $sid ?>" <?= $on ? '' : 'data-inactive' ?>>
      <h3><?= e($s['name']) ?></h3>
      <?php if ($s['requires_slot']): $k = "slot.$sid"; ?>
      <div class="field<?= $err($k) ? ' has-error' : '' ?>">
        <label for="slot-<?= $sid ?>"><?= $s['is_food_only'] ? 'Collection / meal time' : 'Preferred time' ?> <span class="req">(required)</span></label>
        <select id="slot-<?= $sid ?>" name="slot[<?= $sid ?>]" data-slot-select="<?= $sid ?>" <?= $err($k) ? 'aria-invalid="true" aria-describedby="slot-' . $sid . '-error"' : '' ?>>
          <option value="">— Choose a time —</option>
          <?php foreach ($slots[$sid] ?? [] as $sl): ?>
            <option value="<?= $sl['id'] ?>" <?= $nested('slot', $sid) === (string) $sl['id'] ? 'selected' : '' ?>><?= e($sl['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($err($k)): ?><p class="field-error" id="slot-<?= $sid ?>-error"><?= e($err($k)) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($s['collect_participants']): $k = "participants.$sid"; ?>
      <div class="field field-narrow<?= $err($k) ? ' has-error' : '' ?>">
        <label for="participants-<?= $sid ?>">Number of participants / family members <span class="req">(required)</span></label>
        <input id="participants-<?= $sid ?>" name="participants[<?= $sid ?>]" type="number" inputmode="numeric" min="1" max="<?= (int) $s['max_participants'] ?>" value="<?= e($nested('participants', $sid) ?: '1') ?>" <?= $err($k) ? 'aria-invalid="true" aria-describedby="participants-' . $sid . '-error"' : '' ?>>
        <?php if ($err($k)): ?><p class="field-error" id="participants-<?= $sid ?>-error"><?= e($err($k)) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>

      <?php foreach ($s['fields'] as $fd): $k = "f.$sid.{$fd['key']}"; $id = "f-$sid-{$fd['key']}"; $v = $nested('f', $sid, $fd['key']); ?>
      <div class="field<?= $err($k) ? ' has-error' : '' ?>">
        <label for="<?= e($id) ?>"><?= e($fd['label']) ?> <?= !empty($fd['required']) ? '<span class="req">(required)</span>' : '<span class="opt">(optional)</span>' ?></label>
        <?php $attrs = 'id="' . e($id) . '" name="f[' . $sid . '][' . e($fd['key']) . ']"' . ($err($k) ? ' aria-invalid="true" aria-describedby="' . e($id) . '-error"' : '') . (!empty($fd['required']) ? ' data-required' : ''); ?>
        <?php if ($fd['type'] === 'textarea'): ?>
          <textarea <?= $attrs ?> rows="3" maxlength="1000"><?= e($v) ?></textarea>
        <?php elseif ($fd['type'] === 'select'): ?>
          <select <?= $attrs ?>>
            <option value="">— Choose —</option>
            <?php foreach ($fd['options'] ?? [] as $o): ?><option <?= $v === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
          </select>
        <?php elseif ($fd['type'] === 'number'): ?>
          <input <?= $attrs ?> type="number" inputmode="numeric" min="0" value="<?= e($v) ?>">
        <?php elseif ($fd['type'] === 'date'): ?>
          <input <?= $attrs ?> type="date" value="<?= e($v) ?>">
        <?php else: ?>
          <input <?= $attrs ?> maxlength="150" value="<?= e($v) ?>">
        <?php endif; ?>
        <?php if ($err($k)): ?><p class="field-error" id="<?= e($id) ?>-error"><?= e($err($k)) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div class="field">
      <label for="instructions">Special instructions or additional information <span class="opt">(optional)</span></label>
      <textarea id="instructions" name="instructions" rows="3" maxlength="1000"><?= e($val('instructions')) ?></textarea>
    </div>
    <div class="step-nav">
      <button type="button" class="btn btn-secondary" data-prev>Back</button>
      <button type="button" class="btn btn-primary btn-lg" data-next>Next: prasadam</button>
    </div>
  </fieldset>

  <!-- STEP 3: food -->
  <fieldset class="step card" data-step="3" id="food-section">
    <legend><h2>3. Food / Prasadam coupons</h2></legend>
    <p class="hint" data-food-only-note hidden>Food-Only Booking: choose at least one coupon category and enter the number of persons.</p>
    <label class="check-row" data-add-food-row>
      <input type="checkbox" name="add_food" value="1" id="add_food" <?= $addFood ? 'checked' : '' ?>>
      <span><strong>Add Food / Prasadam Coupons</strong> <span class="opt">(optional)</span></span>
    </label>
    <?php if ($err('food')): ?><p class="field-error" id="food-error"><?= e($err('food')) ?></p><?php endif; ?>
    <div class="coupons" data-coupons>
      <?php foreach ($coupons as $c): $cid = $c['id']; $k = "food.$cid"; $qty = (string) (($in['food'] ?? [])[$cid] ?? ''); $catOn = !empty(($in['food_cat'] ?? [])[$cid]); ?>
      <div class="coupon<?= $err($k) ? ' has-error' : '' ?>" data-coupon="<?= $cid ?>">
        <label class="coupon-head">
          <input type="checkbox" name="food_cat[<?= $cid ?>]" value="1" data-coupon-check="<?= $cid ?>" <?= $catOn ? 'checked' : '' ?>>
          <span><strong><?= e($c['name']) ?></strong> — <?= e(money($c['price_paise'])) ?> per person<?php if ($c['description']): ?><span class="sub"><?= e($c['description']) ?></span><?php endif; ?></span>
        </label>
        <div class="coupon-qty">
          <label for="food-<?= $cid ?>">Number of persons</label>
          <div class="stepper">
            <button type="button" class="btn btn-icon" data-dec="<?= $cid ?>" aria-label="Fewer persons for <?= e($c['name']) ?>">−</button>
            <input id="food-<?= $cid ?>" name="food[<?= $cid ?>]" type="number" inputmode="numeric" min="0" max="<?= (int) $c['max_per_booking'] ?>" value="<?= e($qty) ?>" data-qty="<?= $cid ?>" <?= $err($k) ? 'aria-invalid="true" aria-describedby="food-' . $cid . '-error"' : '' ?>>
            <button type="button" class="btn btn-icon" data-inc="<?= $cid ?>" aria-label="More persons for <?= e($c['name']) ?>">+</button>
          </div>
          <output class="line-total" data-line-total="<?= $cid ?>" aria-live="polite"></output>
        </div>
        <?php if ($err($k)): ?><p class="field-error" id="food-<?= $cid ?>-error"><?= e($err($k)) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="estimate" data-estimate hidden aria-live="polite">
      <h3>Price estimate</h3>
      <dl data-estimate-lines></dl>
      <p class="estimate-total">Estimated total: <strong data-estimate-total>₹0</strong></p>
      <p class="hint">The final amount is calculated by the temple's system on the next page.</p>
    </div>

    <div class="step-nav">
      <button type="button" class="btn btn-secondary" data-prev>Back</button>
      <button type="submit" class="btn btn-primary btn-lg">Review booking</button>
    </div>
  </fieldset>
</form>
