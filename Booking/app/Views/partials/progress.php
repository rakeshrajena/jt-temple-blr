<?php
/** @var int $step 1..6 */
$steps = ['Choose service', 'Your details', 'Prasadam', 'Review', 'Pay', 'Done'];
?>
<nav class="progress" aria-label="Booking progress">
  <ol>
    <?php foreach ($steps as $i => $label): $n = $i + 1; ?>
      <li class="<?= $n < $step ? 'done' : ($n === $step ? 'current' : '') ?>" data-progress="<?= $n ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>>
        <span class="dot" aria-hidden="true"><?= $n < $step ? '✓' : $n ?></span>
        <span class="lbl"><?= e($label) ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
  <p class="progress-text" data-progress-text>Step <?= (int) $step ?> of <?= count($steps) ?>: <?= e($steps[$step - 1]) ?></p>
</nav>
