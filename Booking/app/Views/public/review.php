<?php
/** @var array $d draft @var array $q quote */
$step = 4; include __DIR__ . '/../partials/progress.php';
$needsApproval = (bool) array_filter($d['services'], fn ($l) => $l['service']['requires_approval']);
$items = array_map(fn ($i) => $i, $q['items']);
?>
<section class="card">
  <h1>4. Review your booking</h1>
  <?php if ($notice): ?><div class="alert alert-warn" role="alert"><?= e($notice) ?></div><?php endif; ?>
  <p class="lead">Please check everything carefully. Prices below are calculated by the temple's booking system.</p>

  <div class="summary-grid">
    <div>
      <h2 class="h3">Devotee</h2>
      <dl class="kv">
        <dt>Name</dt><dd><?= e($d['name']) ?></dd>
        <dt>Mobile</dt><dd><?= e(fmt_mobile($d['mobile'])) ?></dd>
        <?php if ($d['email']): ?><dt>Email</dt><dd><?= e($d['email']) ?></dd><?php endif; ?>
      </dl>
    </div>
    <div>
      <h2 class="h3">Date</h2>
      <dl class="kv">
        <dt>Date</dt><dd><?= e(fmt_date($d['service_date'])) ?></dd>
        <?php if ($d['instructions'] !== ''): ?><dt>Instructions</dt><dd class="pre"><?= e($d['instructions']) ?></dd><?php endif; ?>
      </dl>
    </div>
  </div>

  <h2 class="h3">Price summary</h2>
  <?php $sub = $q['subtotal_paise']; $extra = $q['additional_paise']; $extraLabel = $q['additional_label']; $total = $q['total_paise'];
  include __DIR__ . '/../partials/items_table.php'; ?>

  <?php if ($q['food_paise'] > 0): ?>
    <p class="hint">Food charges: <?= e(money($q['food_paise'])) ?><?php if ($q['services_paise'] > 0): ?> · Service charges: <?= e(money($q['services_paise'])) ?><?php endif; ?></p>
  <?php endif; ?>

  <div class="callout">
    <p><strong>What happens next:</strong> after you submit, you get a booking number<?= $total > 0 ? ' and a UPI QR code to pay ' . e(money($total)) : '' ?>.
    Your booking is <strong>confirmed only after</strong> <?= $total > 0 ? 'the temple verifies your payment' : 'the temple office reviews it' ?><?= $needsApproval && $total > 0 ? ' and approves the ceremony' : '' ?>.</p>
    <?php if ($total === 0 && $needsApproval): ?><p>The temple office will tell you the charges, if any, before you pay.</p><?php endif; ?>
  </div>

  <form method="post" action="<?= e(url('/book/confirm')) ?>" class="stack" data-submit-once>
    <?= csrf_field() ?>
    <input type="hidden" name="form_id" value="<?= e($formId) ?>">
    <label class="check-row<?= !empty($errors['agree']) ? ' has-error' : '' ?>" id="agree-row">
      <input type="checkbox" name="agree" value="1" required>
      <span>I confirm the details above are correct.<?php if ($terms): ?> <span class="sub"><?= e($terms) ?></span><?php endif; ?></span>
    </label>
    <?php if (!empty($errors['agree'])): ?><p class="field-error" role="alert"><?= e($errors['agree']) ?></p><?php endif; ?>
    <div class="step-nav">
      <a class="btn btn-secondary" href="<?= e(url('/book', ['edit' => 1])) ?>">Edit booking</a>
      <button type="submit" class="btn btn-primary btn-lg" data-busy-text="Submitting…">Submit booking</button>
    </div>
  </form>
</section>
