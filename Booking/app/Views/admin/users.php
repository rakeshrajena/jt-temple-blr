<?php /** @var array $users @var bool $shared_erp */ ?>
<h1>Administrators</h1>
<?php if ($shared_erp): ?>
<p class="muted">These accounts are <strong>shared with the temple ERP</strong> (<code>users</code> table). Sign in to booking admin with the same username and password as ERP. To add, deactivate, or reset another user’s password, use <strong>ERP › Users</strong>.</p>
<p class="muted">In booking: <strong>Admin</strong> and <strong>Treasurer</strong> can change services, settings and prices; <strong>Staff</strong> can manage bookings and payments.</p>
<?php else: ?>
<p class="muted"><strong>Senior admins</strong> can change prices, services, settings and users. <strong>Staff</strong> can manage bookings, payments, availability, notifications and reports.</p>
<?php endif; ?>
<?php foreach ($shared_erp ? $users : array_merge($users, [['id' => 0, 'username' => '', 'name' => '', 'role' => 'staff', 'is_active' => 1, 'last_login_at' => null, 'locked_until' => null]]) as $u): ?>
<?php if ($shared_erp): ?>
<div class="panel user-form">
  <h2 class="h3"><?= e($u['username']) ?>
    <small class="muted">· ERP role <?= e($u['erp_role'] ?? 'Staff') ?> · booking access <?= e($u['role'] === 'super' ? 'full' : 'bookings only') ?>
    · last sign-in <?= e(fmt_datetime($u['last_login_at'])) ?><?= !empty($u['locked_until']) && $u['locked_until'] > now() ? ' · <strong>locked</strong>' : '' ?></small></h2>
  <p><?= e($u['name']) ?><?= !(int) $u['is_active'] ? ' · <strong>inactive</strong>' : '' ?></p>
</div>
<?php else: ?>
<form method="post" action="<?= e(url('/admin/users/save')) ?>" class="panel form user-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
  <h2 class="h3"><?= $u['id'] ? e($u['username']) : 'Add an administrator' ?>
    <?php if ($u['id']): ?><small class="muted">· last sign-in <?= e(fmt_datetime($u['last_login_at'])) ?><?= $u['locked_until'] && $u['locked_until'] > now() ? ' · <strong>locked</strong>' : '' ?></small><?php endif; ?></h2>
  <div class="grid-3">
    <?php if (!$u['id']): ?><label>Username <input name="username" required pattern="[a-z0-9._\-]{3,50}" autocapitalize="none"></label><?php endif; ?>
    <label>Full name <input name="name" value="<?= e($u['name']) ?>" maxlength="100"></label>
    <label>Role <select name="role"><option value="staff"<?= $u['role'] === 'staff' ? ' selected' : '' ?>>Staff</option><option value="super"<?= $u['role'] === 'super' ? ' selected' : '' ?>>Senior admin</option></select></label>
    <label><?= $u['id'] ? 'New password (leave empty to keep)' : 'Password' ?> <input type="password" name="password" autocomplete="new-password"<?= $u['id'] ? '' : ' required' ?> minlength="10"></label>
    <?php if ($u['id']): ?><label class="inline"><input type="checkbox" name="is_active" value="1"<?= (int) $u['is_active'] ? ' checked' : '' ?>> Active</label><?php endif; ?>
  </div>
  <button class="btn<?= $u['id'] ? '' : ' btn-primary' ?>" type="submit"><?= $u['id'] ? 'Save' : 'Create administrator' ?></button>
</form>
<?php endif; ?>
<?php endforeach; ?>
