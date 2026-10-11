<section class="panel narrow">
  <h1>Change password</h1>
  <form method="post" action="<?= e(url('/admin/password')) ?>" class="form">
    <?= csrf_field() ?>
    <label>Current password <input name="current" type="password" autocomplete="current-password" required></label>
    <?php if (!empty($errors['current'])): ?><p class="field-error"><?= e($errors['current']) ?></p><?php endif; ?>
    <label>New password <input name="new" type="password" autocomplete="new-password" required minlength="10"></label>
    <?php if (!empty($errors['new'])): ?><p class="field-error"><?= e($errors['new']) ?></p><?php endif; ?>
    <label>Repeat new password <input name="confirm" type="password" autocomplete="new-password" required></label>
    <?php if (!empty($errors['confirm'])): ?><p class="field-error"><?= e($errors['confirm']) ?></p><?php endif; ?>
    <p class="muted small">At least 10 characters, with letters and numbers.</p>
    <button class="btn btn-primary" type="submit">Change password</button>
  </form>
</section>
