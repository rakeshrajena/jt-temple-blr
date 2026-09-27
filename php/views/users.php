<?php
/** @var list<array<string,mixed>> $users */
/** @var int $activeCount */
/** @var int $maxUsers */
/** @var array $currentUser */
?>
<div class="kpi-grid">
  <div class="kpi-card <?= $activeCount >= $maxUsers ? 'warn' : 'good' ?>">
    <div class="value"><?= e((string) $activeCount) ?> / <?= e((string) $maxUsers) ?></div>
    <div class="label"><?= e(t('ui.users_active')) ?></div>
  </div>
</div>
<div class="reveal-group">
<div class="action-bar">
  <button class="btn btn-outline" type="button" data-reveal="reveal-user"><?= e(t('ui.add_user')) ?></button>
</div>
<div class="panel reveal-panel" id="reveal-user" hidden>
  <h3><?= e(t('ui.add_user')) ?></h3>
  <?php if ($activeCount >= $maxUsers): ?>
  <div class="flash flash-error"><?= e(t('ui.user_limit', ['max' => (string) $maxUsers])) ?></div>
  <?php else: ?>
  <form method="POST" action="<?= e(url('users')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label><?= e(t('ui.full_name')) ?></label><input type="text" name="full_name" required></div>
      <div class="form-group"><label><?= e(t('login.username')) ?></label><input type="text" name="username" required></div>
      <div class="form-group"><label><?= e(t('login.password')) ?></label><input type="password" name="password" required minlength="6"></div>
      <div class="form-group">
        <label><?= e(t('common.role')) ?></label>
        <select name="role"><option value="Staff"><?= e(t_fixed('role', 'Staff')) ?></option><option value="Treasurer"><?= e(t_fixed('role', 'Treasurer')) ?></option><option value="Admin"><?= e(t_fixed('role', 'Admin')) ?></option></select>
      </div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= e(t('ui.add_user')) ?></button></div>
  </form>
  <?php endif; ?>
</div>
</div>
<div class="panel">
  <h3><?= e(t('ui.all_users')) ?><?= help_tip(t('password.own_hint')) ?></h3>
  <table class="data-table">
    <tr><th><?= e(t('common.name')) ?></th><th><?= e(t('login.username')) ?></th><th><?= e(t('common.role')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('ui.created')) ?></th><th><?= e(t('password.set')) ?></th><th><?= e(t('ui.access')) ?></th></tr>
    <?php foreach ($users as $u): ?>
    <?php $isSelf = (int) $u['id'] === (int) ($currentUser['id'] ?? 0); ?>
    <tr>
      <td><?= e($u['full_name']) ?></td>
      <td><?= e($u['username']) ?></td>
      <td><?= e(t_fixed('role', (string) $u['role'])) ?></td>
      <td><?php if ((int) $u['is_active'] === 1): ?><span class="badge badge-green"><?= e(t('status.active')) ?></span><?php else: ?><span class="badge badge-grey"><?= e(t('status.inactive')) ?></span><?php endif; ?></td>
      <td><?= e($u['created_at']) ?></td>
      <td>
        <?php if ($isSelf): ?>
        <a class="btn btn-sm btn-outline" href="<?= e(url('account/password')) ?>"><?= e(t('page.password')) ?></a>
        <?php else: ?>
        <details class="row-fold">
          <summary class="btn btn-sm btn-outline"><?= e(t('password.set')) ?></summary>
          <form class="cell-form" method="POST" action="<?= e(url('users/' . $u['id'] . '/password')) ?>">
            <?= csrf_field() ?>
            <input type="password" name="new_password" required minlength="6" maxlength="200" autocomplete="new-password" placeholder="<?= e(t('password.new')) ?>" aria-label="<?= e(t('password.new')) ?>">
            <input type="password" name="confirm_password" required minlength="6" maxlength="200" autocomplete="new-password" placeholder="<?= e(t('password.confirm')) ?>" aria-label="<?= e(t('password.confirm')) ?>">
            <button class="btn btn-sm btn-primary" type="submit"><?= e(t('password.set')) ?></button>
          </form>
        </details>
        <?php endif; ?>
      </td>
      <td>
        <?php if (!$isSelf): ?>
        <form method="POST" action="<?= e(url('users/' . $u['id'] . '/toggle')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-sm <?= (int) $u['is_active'] === 1 ? 'btn-danger' : 'btn-outline' ?>" type="submit">
            <?= e((int) $u['is_active'] === 1 ? t('ui.deactivate') : t('ui.reactivate')) ?>
          </button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
