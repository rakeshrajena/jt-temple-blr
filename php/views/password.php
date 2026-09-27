<div class="panel">
  <h3><?= e(t('page.password')) ?><?= help_tip(t('password.intro')) ?></h3>
  <form method="POST" action="<?= e(url('account/password')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group">
        <label for="current_password"><?= e(t('password.current')) ?></label>
        <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label for="new_password"><?= e(t('password.new')) ?></label>
        <input id="new_password" type="password" name="new_password" required minlength="6" maxlength="200" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label for="confirm_password"><?= e(t('password.confirm')) ?></label>
        <input id="confirm_password" type="password" name="confirm_password" required minlength="6" maxlength="200" autocomplete="new-password">
      </div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('password.save')) ?></button></div>
  </form>
</div>
