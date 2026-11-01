<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = 0;

function check(bool $ok, string $name): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n";
}

check(password_rule_error('short', 'short') !== null, 'a password shorter than 6 characters is refused');
check(password_rule_error('temple@123', 'temple@124') !== null, 'a confirmation that differs is refused');
check(password_rule_error('temple@123', 'temple@123') === null, 'a matching password of 6 or more characters is accepted');
check(account_access_error(4, 4, 'Staff', true, 2) !== null, 'a person cannot turn their own access off from the user list');
check(account_access_error(1, 2, 'Admin', true, 1) !== null, 'the last active Admin cannot be deactivated');
check(account_access_error(1, 2, 'Admin', true, 2) === null, 'an Admin can deactivate another Admin when one remains');
check(account_access_error(1, 3, 'Staff', true, 2) === null, 'an Admin can deactivate a staff account');
check(account_access_error(1, 3, 'Staff', false, 2) === null, 'an Admin can turn a deactivated account back on');

$pdo = db();
$pdo->beginTransaction();
try {
    $username = 'pw_' . bin2hex(random_bytes(4));
    db_exec(
        'INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?,?,?,?,1)',
        [$username, password_hash('staff@123', PASSWORD_DEFAULT), 'Password Test', 'Staff']
    );
    $staffId = (int) $pdo->lastInsertId();
    $adminId = (int) db_value("SELECT id FROM users WHERE username = 'admin'");
    $adminHashBefore = (string) db_value('SELECT password_hash FROM users WHERE id = ?', [$adminId]);

    $wrong = change_own_password($staffId, 'not-the-password', 'newer@123', 'newer@123');
    $stillOld = db_value('SELECT password_hash FROM users WHERE id = ?', [$staffId]);
    check(
        $wrong !== null && is_string($stillOld) && password_verify('staff@123', $stillOld),
        'the wrong current password leaves the saved password unchanged'
    );

    $mismatch = change_own_password($staffId, 'staff@123', 'newer@123', 'other@123');
    check($mismatch !== null && password_verify('staff@123', (string) db_value('SELECT password_hash FROM users WHERE id = ?', [$staffId])), 'a mismatched confirmation does not save');

    $changed = change_own_password($staffId, 'staff@123', 'newer@123', 'newer@123');
    $updated = db_value('SELECT password_hash FROM users WHERE id = ?', [$staffId]);
    check(
        $changed === null && is_string($updated) && password_verify('newer@123', $updated) && !password_verify('staff@123', $updated),
        'staff, treasurer, and admin can replace their own password'
    );

    $staffAttempt = admin_set_user_password('Staff', $staffId, $adminId, 'taken@123', 'taken@123');
    check(
        $staffAttempt !== null && db_value('SELECT password_hash FROM users WHERE id = ?', [$adminId]) === $adminHashBefore,
        'staff cannot set another person\'s password'
    );

    $selfAttempt = admin_set_user_password('Admin', $adminId, $adminId, 'self@123', 'self@123');
    check(
        $selfAttempt !== null && db_value('SELECT password_hash FROM users WHERE id = ?', [$adminId]) === $adminHashBefore,
        'an Admin changes their own password from the password page'
    );

    $set = admin_set_user_password('Admin', $adminId, $staffId, 'reset@123', 'reset@123');
    $reset = db_value('SELECT password_hash FROM users WHERE id = ?', [$staffId]);
    check(
        $set === null && is_string($reset) && password_verify('reset@123', $reset),
        'an Admin can set another person\'s password without the old one'
    );

    db_exec('UPDATE users SET is_active = 0 WHERE id = ?', [$staffId]);
    $inactive = db_one('SELECT id FROM users WHERE username = ? AND is_active = 1', [$username]);
    check($inactive === null, 'a deactivated account cannot sign in');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$adminStill = db_value("SELECT password_hash FROM users WHERE username = 'admin'");
check(is_string($adminStill) && $adminStill === $adminHashBefore, 'the live admin password is unchanged after the test');

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all password tests passed\n";
