<section class="panel narrow">
  <h1>Administrator sign in</h1>
  <?php if (!empty($error)): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/login')) ?>" class="form">
    <?= csrf_field() ?>
    <label>Username <input name="username" autocomplete="username" required value="<?= e($username) ?>" autocapitalize="none"></label>
    <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
    <button class="btn btn-primary" type="submit">Sign in</button>
  </form>
  <p class="muted small">Accounts lock for 15 minutes after 5 wrong passwords. Ask a senior administrator to reset a forgotten password.</p>
</section>
