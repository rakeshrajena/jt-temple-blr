<?php
declare(strict_types=1);

/**
 * Browser-based setup: migrations + first booking administrator.
 * Does not install or require the temple ERP (php/ folder).
 *
 * Before opening this page:
 * 1. Upload the whole booking/ folder (app/, bin/, migrations/,
 *    storage/, .env) to a web folder, e.g. public_html/Booking.
 * 2. Put INSTALL_TOKEN (16+ random chars) and APP_KEY in .env.
 *
 * After success: delete install.php and remove INSTALL_TOKEN from .env.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

if (PHP_VERSION_ID < 80100) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    exit('PHP 8.1 or newer is required. This server runs ' . PHP_VERSION);
}

require APP_ROOT . '/app/autoload.php';

use SJT\Core\App;
use SJT\Core\Database;
use SJT\Core\Env;
use SJT\Core\Migrator;
use SJT\Service\Auth;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$envFile = APP_ROOT . '/.env';
Env::load($envFile);
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Kolkata'));

$lockFile = APP_ROOT . '/storage/install/complete.lock';
$lockDir = dirname($lockFile);
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}

session_start();
if (empty($_SESSION['_install_csrf'])) {
    $_SESSION['_install_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['_install_csrf'];

function install_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function install_requirements(): array
{
    $problems = [];
    if (PHP_VERSION_ID < 80100) {
        $problems[] = 'PHP 8.1+ required.';
    }
    foreach (['pdo_mysql', 'mbstring', 'gd', 'curl', 'dom', 'openssl'] as $ext) {
        if (!extension_loaded($ext)) {
            $problems[] = "PHP extension missing: $ext";
        }
    }
    if (!is_file(APP_ROOT . '/.env')) {
        $problems[] = 'Missing .env — copy .env.example to .env on the server and edit DB settings.';
    }
    foreach (['logs', 'sessions', 'uploads', 'cache', 'backups', 'install'] as $d) {
        $p = APP_ROOT . '/storage/' . $d;
        if (!is_dir($p)) {
            @mkdir($p, 0755, true);
        }
        if (!is_dir($p) || !is_writable($p)) {
            $problems[] = "storage/$d must exist and be writable.";
        }
    }
    $token = (string) Env::get('INSTALL_TOKEN', '');
    if (strlen($token) < 16) {
        $problems[] = 'Set INSTALL_TOKEN in .env to at least 16 random characters.';
    }
    if ((string) Env::get('APP_KEY', '') === 'change-me-to-a-long-random-string' || strlen((string) Env::get('APP_KEY', '')) < 32) {
        $problems[] = 'Set APP_KEY in .env (run locally: php bin/console.php key).';
    }
    if ((string) Env::get('APP_URL', '') === '') {
        $problems[] = 'Set APP_URL in .env (e.g. https://yourdomain.org/Booking).';
    }
    if (Env::get('DB_DRIVER', 'mysql') === 'mysql') {
        foreach (['DB_NAME', 'DB_USER'] as $k) {
            if ((string) Env::get($k, '') === '') {
                $problems[] = "$k is empty in .env.";
            }
        }
    }
    return $problems;
}

function install_token_ok(string $input): bool
{
    $expected = (string) Env::get('INSTALL_TOKEN', '');
    return $expected !== '' && hash_equals($expected, $input);
}

function install_shared_erp_hint(?Database $db): ?string
{
    if (!$db || $db->driver !== 'mysql') {
        return null;
    }
    try {
        $users = $db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
        $donors = $db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'donors'");
        if ($users && $donors) {
            return 'This database already has ERP tables (users, donors). Setup will add booking tables only. You do not need to upload the php/ ERP folder for booking to work.';
        }
    } catch (Throwable) {
    }
    return null;
}

$problems = install_requirements();
$error = null;
$success = null;
$applied = [];
$db = null;
$dbError = null;
$sharedHint = null;
$hasSuper = false;

if ($problems === []) {
    try {
        $db = App::db();
        $sharedHint = install_shared_erp_hint($db);
        $hasSuper = false;
        try {
            // The admins table may not exist yet (migrations have not run),
            // so this query is separate from the connection check above.
            $hasSuper = (int) $db->value("SELECT COUNT(*) FROM admins WHERE role = 'super' AND is_active = 1") > 0;
        } catch (Throwable) {
            $hasSuper = false;
        }
    } catch (Throwable $e) {
        $dbError = 'Database connection failed. Check DB_HOST, DB_NAME, DB_USER, DB_PASS in .env.';
        $problems[] = $dbError;
    }
}

$locked = is_file($lockFile);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $problems === [] && $db) {
    if (!hash_equals($csrf, (string) ($_POST['_csrf'] ?? ''))) {
        $error = 'Session expired. Reload the page and try again.';
    } elseif (!install_token_ok((string) ($_POST['setup_key'] ?? ''))) {
        $error = 'Setup key does not match INSTALL_TOKEN in .env.';
    } else {
        try {
            $m = new Migrator($db, APP_ROOT . '/migrations');
            $applied = $m->migrate();
            $msg = $applied ? 'Migrations applied: ' . implode(', ', $applied) : 'Database schema is already up to date.';

            $username = strtolower(trim((string) ($_POST['admin_user'] ?? '')));
            $name = trim((string) ($_POST['admin_name'] ?? ''));
            $password = (string) ($_POST['admin_password'] ?? '');
            $role = (string) ($_POST['admin_role'] ?? 'super');
            $createAdmin = $username !== '' || $password !== '';

            if ($createAdmin) {
                if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
                    throw new RuntimeException('Admin username must be 3–50 characters (letters, digits, dot, dash, underscore).');
                }
                if (!in_array($role, ['super', 'staff'], true)) {
                    throw new RuntimeException('Admin role must be super or staff.');
                }
                if ($p = Auth::passwordProblem($password)) {
                    throw new RuntimeException($p);
                }
                if ($db->value('SELECT 1 FROM admins WHERE username = ?', [$username])) {
                    throw new RuntimeException("Administrator '$username' already exists. Leave admin fields blank to run migrations only.");
                }
                $db->insert('admins', [
                    'username' => $username,
                    'name' => $name !== '' ? $name : $username,
                    'password_hash' => Auth::hash($password),
                    'role' => $role,
                    'is_active' => 1,
                    'failed_logins' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $msg .= " Created administrator '$username' ($role).";
                $hasSuper = $role === 'super' || $hasSuper;
            }

            if (!$hasSuper && !$createAdmin) {
                $msg .= ' Warning: no senior (super) administrator exists yet — fill in the admin form or run create-admin on the server.';
            }

            file_put_contents($lockFile, date('c') . "\n");
            $success = $msg;
            $locked = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$installedAt = $locked && is_file($lockFile) ? trim((string) file_get_contents($lockFile)) : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set up temple booking</title>
  <style>
    body { margin: 0; font: 16px/1.5 system-ui, sans-serif; background: #fbf5e9; color: #2a1b12; }
    main { max-width: 40rem; margin: 6vh auto; padding: 1.5rem 1.75rem; background: #fffdf8; border: 1px solid #e3d3bb; border-radius: 12px; }
    h1 { font-family: Georgia, serif; color: #521010; font-size: 1.5rem; margin: 0 0 0.75rem; }
    p, li { margin: 0 0 0.65rem; }
    ul { margin: 0 0 1rem; padding-left: 1.25rem; }
    label { display: block; font-weight: 600; font-size: 0.9rem; margin: 0.75rem 0 0.25rem; }
    input, select { width: 100%; box-sizing: border-box; padding: 0.55rem 0.65rem; border: 2px solid #b9a68e; border-radius: 8px; font: inherit; }
    button { margin-top: 1rem; background: #7a1d1d; color: #fff; border: 0; border-radius: 8px; padding: 0.7rem 1.2rem; font: inherit; font-weight: 600; cursor: pointer; }
    .box { padding: 0.75rem 1rem; border-radius: 8px; margin: 0 0 1rem; }
    .err { background: #fbe7e5; border: 1px solid #a3221e; }
    .ok { background: #e7f4ea; border: 1px solid #1d6b38; }
    .info { background: #fff3d8; border: 1px solid #f0d38f; }
    code { font-family: ui-monospace, Consolas, monospace; font-size: 0.9em; }
  </style>
</head>
<body>
<main>
  <h1>Set up online booking</h1>
  <p>This installs <strong>booking only</strong>. You do <strong>not</strong> need to upload the ERP <code>php/</code> folder.</p>

  <?php if ($problems): ?>
    <div class="box err">
      <p><strong>Fix these before setup:</strong></p>
      <ul><?php foreach ($problems as $p): ?><li><?= install_e($p) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($sharedHint): ?>
    <div class="box info"><p><?= install_e($sharedHint) ?></p></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="box err"><p><?= install_e($error) ?></p></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="box ok">
      <p><?= install_e($success) ?></p>
      <p><strong>Next:</strong></p>
      <ul>
        <li>Open <a href="<?= install_e(rtrim((string) Env::get('APP_URL', '/'), '/') . '/') ?>">the booking site</a> and <a href="<?= install_e(rtrim((string) Env::get('APP_URL', '/'), '/') . '/admin') ?>">/admin</a>.</li>
        <li>Delete <code>public_html/…/install.php</code> on the server.</li>
        <li>Remove <code>INSTALL_TOKEN</code> from <code>.env</code>.</li>
        <li>Configure UPI and services in the admin dashboard.</li>
      </ul>
    </div>
  <?php elseif ($locked && $installedAt): ?>
    <div class="box info">
      <p>Setup was completed (<?= install_e($installedAt) ?>). You can run migrations again with the setup key if you deploy a new version.</p>
    </div>
  <?php endif; ?>

  <?php if ($problems === []): ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= install_e($csrf) ?>">
    <label for="setup_key">Setup key (same as INSTALL_TOKEN in .env)</label>
    <input id="setup_key" name="setup_key" type="password" autocomplete="off" required>

    <p style="margin-top:1.25rem"><strong>First booking administrator</strong> (optional if one already exists)</p>
    <label for="admin_user">Username</label>
    <input id="admin_user" name="admin_user" autocomplete="username" placeholder="admin">
    <label for="admin_name">Full name</label>
    <input id="admin_name" name="admin_name" autocomplete="name" placeholder="Temple Administrator">
    <label for="admin_role">Role</label>
    <select id="admin_role" name="admin_role">
      <option value="super">Senior admin (super)</option>
      <option value="staff">Staff</option>
    </select>
    <label for="admin_password">Password (min 10 chars, letters + numbers)</label>
    <input id="admin_password" name="admin_password" type="password" autocomplete="new-password">

    <button type="submit">Run database setup</button>
  </form>
  <?php else: ?>
  <div class="box info">
    <p><strong>Upload checklist</strong></p>
    <ul>
      <li>Upload the whole <code>booking/</code> folder to a web folder (e.g. <code>public_html/Booking</code>): <code>app/</code>, <code>assets/</code>, <code>bin/</code>, <code>migrations/</code>, <code>storage/</code>, <code>.env</code></li>
    </ul>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
