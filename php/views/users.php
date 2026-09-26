<?php
/** @var list<array<string,mixed>> $users */
/** @var int $activeCount */
/** @var int $maxUsers */
/** @var array $currentUser */
?>
<div class="kpi-grid">
  <div class="kpi-card <?= $activeCount >= $maxUsers ? 'warn' : 'good' ?>">
    <div class="value"><?= e((string) $activeCount) ?> / <?= e((string) $maxUsers) ?></div>
    <div class="label">Active Users</div>
  </div>
</div>
<div class="panel">
  <h3>Add User</h3>
  <?php if ($activeCount >= $maxUsers): ?>
  <div class="flash flash-error">User limit reached (<?= e((string) $maxUsers) ?> active users). Deactivate someone below before adding a new one.</div>
  <?php else: ?>
  <form method="POST" action="<?= e(url('users')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
      <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
      <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="6"></div>
      <div class="form-group">
        <label>Role</label>
        <select name="role"><option value="Staff">Staff</option><option value="Treasurer">Treasurer</option><option value="Admin">Admin</option></select>
      </div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Add User</button></div>
  </form>
  <?php endif; ?>
</div>
<div class="panel">
  <h3>All Users</h3>
  <table class="data-table">
    <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['full_name']) ?></td>
      <td><?= e($u['username']) ?></td>
      <td><?= e($u['role']) ?></td>
      <td><?php if ((int) $u['is_active'] === 1): ?><span class="badge badge-green">Active</span><?php else: ?><span class="badge badge-grey">Inactive</span><?php endif; ?></td>
      <td><?= e($u['created_at']) ?></td>
      <td>
        <?php if ((int) $u['id'] !== (int) ($currentUser['id'] ?? 0)): ?>
        <form method="POST" action="<?= e(url('users/' . $u['id'] . '/toggle')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-sm <?= (int) $u['is_active'] === 1 ? 'btn-danger' : 'btn-outline' ?>" type="submit">
            <?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?>
          </button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
