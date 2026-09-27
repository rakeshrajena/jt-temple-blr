<?php
declare(strict_types=1);

function password_rule_error(string $password, string $confirm): ?string
{
    if (strlen($password) < 6 || strlen($password) > 200) {
        return 'Use a password of 6 to 200 characters.';
    }
    if (!hash_equals($password, $confirm)) {
        return 'The new password and the confirmation do not match.';
    }
    return null;
}

function change_own_password(int $userId, string $current, string $newPassword, string $confirm): ?string
{
    $rule = password_rule_error($newPassword, $confirm);
    if ($rule !== null) {
        return $rule;
    }
    $user = db_one('SELECT id, password_hash, is_active FROM users WHERE id = ?', [$userId]);
    if ($user === null || (int) $user['is_active'] !== 1) {
        return 'That account cannot be changed.';
    }
    if (!password_verify($current, (string) $user['password_hash'])) {
        return 'The current password is not correct.';
    }
    db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
    return null;
}

function admin_set_user_password(string $actorRole, int $actorId, int $targetId, string $newPassword, string $confirm): ?string
{
    if ($actorRole !== 'Admin') {
        return 'Only an Admin can set another person\'s password.';
    }
    if ($actorId === $targetId) {
        return 'Change your own password from the password page. That page asks for your current password.';
    }
    $rule = password_rule_error($newPassword, $confirm);
    if ($rule !== null) {
        return $rule;
    }
    $user = db_one('SELECT id FROM users WHERE id = ?', [$targetId]);
    if ($user === null) {
        return 'That account was not found.';
    }
    db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
    return null;
}

function account_access_error(int $actorId, int $targetId, string $role, bool $active, int $activeAdmins): ?string
{
    if ($actorId === $targetId) {
        return 'You cannot change your own access from this screen.';
    }
    if ($active && $role === 'Admin' && $activeAdmins <= 1) {
        return 'Cannot deactivate the last active Admin.';
    }
    return null;
}
