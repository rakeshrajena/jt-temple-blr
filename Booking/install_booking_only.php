<?php
declare(strict_types=1);

/**
 * Booking-only web setup (no crm/ folder on server). Prefer Booking/install.php when crm/ exists.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

if (PHP_VERSION_ID < 80100) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    exit('PHP 8.1 or newer is required. This server runs ' . PHP_VERSION);
}

try {
    require APP_ROOT . '/app/autoload.php';
} catch (Throwable $e) {
    error_log('[jt_blr] install_booking_only autoload: ' . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    exit('Could not load the booking application. Check that app/ was uploaded completely.');
}

use SJT\Core\App;
use SJT\Core\Database;
use SJT\Core\Env;
use SJT\Core\Migrator;
use SJT\Service\Auth;
use SJT\Service\StaffAccounts;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$envFile = APP_ROOT . '/.env';
if (!is_file($envFile)) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    exit('Missing Booking/.env — copy .env.example to .env on the server.');
}

Env::load($envFile);
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Kolkata'));

$lockFile = APP_ROOT . '/storage/install/complete.lock';
$lockDir = dirname($lockFile);
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
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
    foreach (['pdo_mysql', 'mbstring', 'gd', 'curl', 'dom', 'openssl'] as $ext) {
        if (!extension_loaded($ext)) {
            $problems[] = "PHP extension missing: $ext";
        }
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
        $problems[] = 'Set INSTALL_TOKEN in Booking/.env to at least 16 random characters (or use unified setup via crm/install.php).';
    }
    if ((string) Env::get('APP_KEY', '') === 'change-me-to-a-long-random-string' || strlen((string) Env::get('APP_KEY', '')) < 32) {
        $problems[] = 'Set APP_KEY in .env (run: php bin/console.php key).';
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

$problems = install_requirements();
$error = null;
$success = null;
$applied = [];
$db = null;

if ($problems === []) {
    try {
        $db = App::db();
    } catch (Throwable $e) {
        error_log('[jt_blr] install_booking_only db: ' . $e->getMessage());
        $problems[] = 'Database connection failed. Check DB_HOST, DB_NAME, DB_USER, DB_PASS in .env.';
    }
}

$pendingMigrations = [];
if ($db) {
    try {
        $pendingMigrations = array_keys((new Migrator($db, APP_ROOT . '/migrations'))->pending());
    } catch (Throwable) {
        $pendingMigrations = [];
    }
}

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
            $password = (string) ($_POST['admin_password'] ?? '');
            if ($username !== '' || $password !== '') {
                $name = trim((string) ($_POST['admin_name'] ?? ''));
                $role = (string) ($_POST['admin_role'] ?? 'super');
                if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
                    throw new RuntimeException('Admin username must be 3–50 characters.');
                }
                if ($p = Auth::passwordProblem($password)) {
                    throw new RuntimeException($p);
                }
                $accounts = new StaffAccounts($db);
                if ($accounts->findByUsername($username)) {
                    throw new RuntimeException("User '$username' already exists.");
                }
                $ts = date('Y-m-d H:i:s');
                if ($accounts->usesErpUsers()) {
                    $db->insert('users', [
                        'username' => $username,
                        'full_name' => $name !== '' ? $name : $username,
                        'password_hash' => Auth::hash($password),
                        'role' => StaffAccounts::erpRoleFromBookingSuper($role === 'super'),
                        'is_active' => 1,
                        'failed_logins' => 0,
                        'created_at' => $ts,
                        'updated_at' => $ts,
                    ]);
                } else {
                    $db->insert('admins', [
                        'username' => $username,
                        'name' => $name !== '' ? $name : $username,
                        'password_hash' => Auth::hash($password),
                        'role' => $role,
                        'is_active' => 1,
                        'failed_logins' => 0,
                        'created_at' => $ts,
                        'updated_at' => $ts,
                    ]);
                }
                $msg .= " Created administrator '$username'.";
            }

            file_put_contents($lockFile, date('c') . "\n");
            $success = $msg;
        } catch (Throwable $e) {
            error_log('[jt_blr] install_booking_only: ' . $e->getMessage());
            $error = $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set up online booking</title>
  <style>
    body { margin: 0; font: 16px/1.5 system-ui, sans-serif; background: #fbf5e9; color: #2a1b12; }
    main { max-width: 40rem; margin: 6vh auto; padding: 1.5rem; background: #fffdf8; border: 1px solid #e3d3bb; border-radius: 12px; }
    h1 { color: #521010; font-size: 1.4rem; }
    label { display: block; font-weight: 600; margin: 0.75rem 0 0.25rem; }
    input, select { width: 100%; box-sizing: border-box; padding: 0.55rem; border: 2px solid #b9a68e; border-radius: 8px; }
    button { margin-top: 1rem; background: #7a1d1d; color: #fff; border: 0; border-radius: 8px; padding: 0.7rem 1.2rem; font-weight: 600; cursor: pointer; }
    .box { padding: 0.75rem 1rem; border-radius: 8px; margin: 0 0 1rem; }
    .err { background: #fbe7e5; border: 1px solid #a3221e; }
    .ok { background: #e7f4ea; border: 1px solid #1d6b38; }
    .info { background: #fff3d8; border: 1px solid #f0d38f; }
  </style>
</head>
<body>
<main>
  <h1>Set up online booking</h1>
  <p>Booking-only mode. If <code>crm/</code> is on this server, use <a href="install.php">install.php</a> for ERP + booking together.</p>

  <?php if ($problems): ?>
    <div class="box err"><ul><?php foreach ($problems as $p): ?><li><?= install_e($p) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if ($pendingMigrations !== [] && !$problems): ?>
    <div class="box info"><p>Pending: <?= install_e(implode(', ', $pendingMigrations)) ?></p></div>
  <?php endif; ?>
  <?php if ($error): ?><div class="box err"><p><?= install_e($error) ?></p></div><?php endif; ?>
  <?php if ($success): ?><div class="box ok"><p><?= install_e($success) ?></p></div><?php endif; ?>

  <?php if ($problems === [] && !$success): ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= install_e($csrf) ?>">
    <label for="setup_key">Setup key (INSTALL_TOKEN in Booking/.env)</label>
    <input id="setup_key" name="setup_key" type="password" required>
    <label for="admin_user">Admin username (optional)</label>
    <input id="admin_user" name="admin_user" autocomplete="username">
    <label for="admin_password">Password</label>
    <input id="admin_password" name="admin_password" type="password">
    <button type="submit">Run migrations</button>
  </form>
  <?php endif; ?>
</main>
</body>
</html>
