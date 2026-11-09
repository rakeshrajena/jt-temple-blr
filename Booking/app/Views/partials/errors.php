<?php
/** Error summary with links to each field. @var array $errors */
use SJT\Service\BookingForm;

if (!$errors) {
    return;
}
$availabilityConflict = BookingForm::hasAvailabilityConflict($errors);
$fieldId = function (string $k): string {
    $p = explode('.', $k);
    return match ($p[0]) {
        'slot' => 'slot-' . ($p[1] ?? ''),
        'participants' => 'participants-' . ($p[1] ?? ''),
        'f' => 'f-' . ($p[1] ?? '') . '-' . ($p[2] ?? ''),
        'food' => isset($p[1]) ? 'food-' . $p[1] : 'food-section',
        'services' => 'services-section',
        default => $k,
    };
};
?>
<div class="error-summary" role="alert" tabindex="-1" data-error-summary>
  <h2><?= $availabilityConflict ? 'Please choose another date or service' : 'Please correct the following' ?></h2>
  <?php if ($availabilityConflict): ?>
    <p class="availability-recovery-hint"><?= e(BookingForm::availabilityHint()) ?></p>
  <?php endif; ?>
  <ul>
    <?php foreach ($errors as $k => $msg): ?>
      <li><a href="#<?= e($fieldId((string) $k)) ?>" data-error-key="<?= e($k) ?>"><?= e($msg) ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>
