<?php
declare(strict_types=1);

/**
 * Unified web setup: temple ERP (crm/ or legacy php/) + sibling Booking/ migrations.
 * Called from crm/install.php and Booking/install.php.
 */

/** @param array{entry: string, php_root: string, booking_root: ?string} $opts */
function site_install_http(array $opts): void
{
    try {
        site_install_http_inner($opts);
    } catch (Throwable $e) {
        error_log('[jt_blr] site_install: ' . $e->getMessage());
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8', true, 500);
        }
        exit(
            "Setup failed: " . $e->getMessage() . "\n\n"
            . "If this mentions app/helpers.php under crm/, re-upload Booking/app/autoload.php and app/install_bootstrap.php.\n"
        );
    }
}

/** @param array{entry: string, php_root: string, booking_root: ?string} $opts */
function site_install_http_inner(array $opts): void
{
    $phpRoot = rtrim($opts['php_root'], '/\\');
    $entry = $opts['entry'];
    $bookingRoot = $opts['booking_root'] ?? site_install_booking_dir($phpRoot);

    if (!is_file($phpRoot . '/includes/bootstrap.php')) {
        header('Content-Type: text/plain; charset=utf-8', true, 500);
        exit('ERP install files are missing (crm/includes/bootstrap.php).');
    }

    if (!defined('JT_INSTALL_MINIMAL')) {
        define('JT_INSTALL_MINIMAL', true);
    }
    require $phpRoot . '/includes/bootstrap.php';

    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');

    $bookingAvailable = $bookingRoot !== null && is_dir($bookingRoot);
    $erpAvailable = is_file($phpRoot . '/install.php') || $entry === 'erp';

    $serverProblems = install_requirement_problems(install_server_facts());
    $dbError = db_config_error();
    $ready = $serverProblems === [] && $dbError === null && strlen(env_value('INSTALL_TOKEN')) >= 16;

    if ($dbError === null && strlen(env_value('INSTALL_TOKEN')) < 16) {
        $dbError = 'Add INSTALL_TOKEN to crm/.env (or the parent .env). Use at least 16 random characters.';
        $ready = false;
    }

    $booksSummary = books_snapshot_summary(books_snapshot_path());
    $booksReady = $booksSummary !== null;
    $existingDonors = null;
    $bookingProblems = $bookingAvailable ? site_install_booking_problems($bookingRoot) : [];
    $bookingReady = $bookingAvailable && $bookingProblems === [];

    if ($dbError === null && $ready) {
        try {
            $look = db_connect();
            $hasUsers = $look->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            if ($hasUsers !== false && $look->query("SHOW TABLES LIKE 'donors'")->fetchColumn() !== false) {
                $existingDonors = (int) $look->query('SELECT COUNT(*) FROM donors')->fetchColumn();
            } else {
                $existingDonors = 0;
            }
        } catch (Throwable $e) {
            error_log('[jt_blr] site_install: ' . $e->getMessage());
            $dbError = 'Could not connect to the database. Check DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD in .env.';
            $ready = false;
        }
    }

    $pendingBooking = [];
    if ($bookingReady) {
        try {
            $pendingBooking = site_install_booking_pending($bookingRoot);
        } catch (Throwable $e) {
            error_log('[jt_blr] site_install pending: ' . $e->getMessage());
        }
    }
    $error = $dbError;
    $erpResult = null;
    $bookingApplied = [];
    $bookingAdminMsg = null;
    $bookingLockWritten = false;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $ready) {
        require_csrf();
        $attempts = (int) ($_SESSION['install_attempts'] ?? 0);
        if ($attempts >= 8) {
            $error = 'Too many setup attempts. Close the browser and try again later.';
        } elseif (!install_token_matches((string) ($_POST['setup_key'] ?? ''))) {
            $_SESSION['install_attempts'] = $attempts + 1;
            $error = 'That setup key does not match INSTALL_TOKEN in .env.';
        } else {
            $_SESSION['install_attempts'] = 0;
            try {
                $runErp = !isset($_POST['skip_erp']) && $erpAvailable;
                $runBooking = isset($_POST['run_booking']) && $bookingReady;

                if ($runErp) {
                    $erpResult = install_database(isset($_POST['replace_books']));
                    if (function_exists('online_booking_migrate_puja_purposes_from_choices')) {
                        online_booking_migrate_puja_purposes_from_choices();
                    }
                    if (function_exists('online_booking_normalize_service_food_rules')) {
                        online_booking_normalize_service_food_rules();
                        online_booking_normalize_service_slot_rules();
                    }
                }

                if ($runBooking) {
                    try {
                        $bookingApplied = site_install_booking_migrate($bookingRoot);
                        $bookingAdminMsg = site_install_booking_maybe_admin($bookingRoot, $_POST);
                        site_install_booking_write_lock($bookingRoot);
                        $bookingLockWritten = true;
                        $pendingBooking = [];
                    } catch (PDOException $e) {
                        error_log('[jt_blr] site_install booking: ' . $e->getMessage());
                        $error = 'Booking migrations failed: ' . $e->getMessage()
                            . ' — ensure Booking/.env uses the same DB_NAME, DB_USER, and DB_PASS (or DB_PASSWORD) as crm/.env.';
                    } catch (Throwable $e) {
                        error_log('[jt_blr] site_install booking: ' . $e->getMessage());
                        $error = 'Booking setup failed: ' . $e->getMessage();
                    }
                }
            } catch (PDOException $e) {
                error_log('[jt_blr] site_install erp: ' . $e->getMessage());
                $error = 'ERP database update failed: ' . $e->getMessage();
            } catch (Throwable $e) {
                error_log('[jt_blr] site_install: ' . $e->getMessage());
                $error = $e->getMessage();
            }
        }
    }

    site_install_render_page([
        'entry' => $entry,
        'erpAvailable' => $erpAvailable,
        'bookingAvailable' => $bookingAvailable,
        'bookingRoot' => $bookingRoot,
        'serverProblems' => $serverProblems,
        'ready' => $ready,
        'error' => $error,
        'booksSummary' => $booksSummary,
        'booksReady' => $booksReady,
        'existingDonors' => $existingDonors,
        'bookingProblems' => $bookingProblems,
        'bookingReady' => $bookingReady,
        'pendingBooking' => $pendingBooking,
        'erpResult' => $erpResult,
        'bookingApplied' => $bookingApplied,
        'bookingAdminMsg' => $bookingAdminMsg,
        'bookingLockWritten' => $bookingLockWritten,
        'erpRoot' => $phpRoot,
    ]);
}

function site_install_booking_dir(string $phpRoot): ?string
{
    $parent = dirname($phpRoot);
    $candidates = [];
    foreach (['Booking', 'booking'] as $name) {
        $candidates[] = $parent . DIRECTORY_SEPARATOR . $name;
        $candidates[] = $phpRoot . DIRECTORY_SEPARATOR . $name;
    }
    foreach ($candidates as $dir) {
        if (is_dir($dir) && is_file($dir . '/app/helpers.php') && is_file($dir . '/app/autoload.php')) {
            return $dir;
        }
    }
    return null;
}

function site_install_load_booking(string $bookingRoot): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    require_once $bookingRoot . '/app/install_bootstrap.php';
    sjt_booking_install_bootstrap($bookingRoot);
    $loaded = true;
}

/** @param array<string, string> $env */
function site_install_booking_env_db(array $env, string $part): string
{
    return match ($part) {
        'name' => trim($env['DB_NAME'] ?? $env['MYSQL_DATABASE'] ?? ''),
        'user' => trim($env['DB_USER'] ?? $env['MYSQL_USER'] ?? ''),
        'pass' => trim($env['DB_PASS'] ?? $env['DB_PASSWORD'] ?? $env['MYSQL_PASSWORD'] ?? ''),
        default => '',
    };
}

/** After Env::load(Booking/.env), fill missing DB_* from crm config (unified install). */
function site_install_booking_sync_db_env(string $bookingRoot): void
{
    site_install_load_booking($bookingRoot);
    \SJT\Core\Env::load($bookingRoot . '/.env');
    $pairs = [
        'DB_HOST' => defined('DB_HOST') ? DB_HOST : '',
        'DB_PORT' => defined('DB_PORT') ? DB_PORT : '',
        'DB_NAME' => defined('DB_NAME') ? DB_NAME : '',
        'DB_USER' => defined('DB_USER') ? DB_USER : '',
        'DB_PASS' => defined('DB_PASS') ? DB_PASS : '',
    ];
    foreach ($pairs as $key => $crmVal) {
        if ($crmVal === '') {
            continue;
        }
        if (trim((string) \SJT\Core\Env::get($key, '')) === '') {
            \SJT\Core\Env::set($key, $crmVal);
        }
    }
    if (trim((string) \SJT\Core\Env::get('DB_PASS', '')) === '' && defined('DB_PASS') && DB_PASS !== '') {
        \SJT\Core\Env::set('DB_PASS', DB_PASS);
    }
}

/** @return array<string, string> */
function site_install_booking_env(string $bookingRoot): array
{
    $path = $bookingRoot . '/.env';
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if (!is_string($raw)) {
        return [];
    }
    return function_exists('env_parse') ? env_parse($raw) : [];
}

/** @return list<string> */
function site_install_booking_problems(string $bookingRoot): array
{
    $problems = [];
    foreach (['gd', 'curl'] as $ext) {
        if (!extension_loaded($ext)) {
            $problems[] = "Booking needs PHP extension: $ext";
        }
    }
    if (!is_file($bookingRoot . '/.env')) {
        $problems[] = 'Missing Booking/.env — copy Booking/.env.example and use the same DB_* as crm/.env.';
    }
    foreach (['logs', 'sessions', 'uploads', 'cache', 'backups', 'install'] as $d) {
        $p = $bookingRoot . '/storage/' . $d;
        if (!is_dir($p)) {
            @mkdir($p, 0755, true);
        }
        if (!is_dir($p) || !is_writable($p)) {
            $problems[] = "Booking/storage/$d must be writable.";
        }
    }
    $env = site_install_booking_env($bookingRoot);
    if ($env !== []) {
        if (trim($env['APP_URL'] ?? '') === '') {
            $problems[] = 'Set APP_URL in Booking/.env.';
        }
        $key = trim($env['APP_KEY'] ?? '');
        if ($key === 'change-me-to-a-long-random-string' || strlen($key) < 32) {
            $problems[] = 'Set APP_KEY in Booking/.env (run: php bin/console.php key inside Booking/).';
        }
        if (($env['DB_DRIVER'] ?? 'mysql') === 'mysql') {
            if (site_install_booking_env_db($env, 'name') === '') {
                $problems[] = 'Booking/.env: DB_NAME (or MYSQL_DATABASE) is empty.';
            }
            if (site_install_booking_env_db($env, 'user') === '') {
                $problems[] = 'Booking/.env: DB_USER (or MYSQL_USER) is empty.';
            }
            if (site_install_booking_env_db($env, 'pass') === '' && !defined('DB_PASS')) {
                $problems[] = 'Booking/.env: DB_PASS or DB_PASSWORD is empty.';
            }
        }
    }
    return $problems;
}

/** @return list<string> */
function site_install_booking_pending(string $bookingRoot): array
{
    try {
        site_install_booking_sync_db_env($bookingRoot);
        $db = \SJT\Core\Database::fromEnv();
        return array_keys((new \SJT\Core\Migrator($db, $bookingRoot . '/migrations'))->pending());
    } catch (Throwable) {
        return [];
    }
}

/** @return list<string> applied migration version ids */
function site_install_booking_migrate(string $bookingRoot): array
{
    site_install_booking_sync_db_env($bookingRoot);
    $db = \SJT\Core\Database::fromEnv();
    return (new \SJT\Core\Migrator($db, $bookingRoot . '/migrations'))->migrate();
}

function site_install_booking_write_lock(string $bookingRoot): void
{
    $lock = $bookingRoot . '/storage/install/complete.lock';
    $dir = dirname($lock);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    file_put_contents($lock, date('c') . "\n");
}

/** @param array<string, mixed> $post */
function site_install_booking_maybe_admin(string $bookingRoot, array $post): ?string
{
    $username = strtolower(trim((string) ($post['admin_user'] ?? '')));
    $password = (string) ($post['admin_password'] ?? '');
    if ($username === '' && $password === '') {
        return null;
    }
    site_install_booking_sync_db_env($bookingRoot);
    $db = \SJT\Core\Database::fromEnv();
    $name = trim((string) ($post['admin_name'] ?? ''));
    $role = (string) ($post['admin_role'] ?? 'super');
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
        throw new RuntimeException('Admin username must be 3–50 characters (letters, digits, dot, dash, underscore).');
    }
    if (!in_array($role, ['super', 'staff'], true)) {
        throw new RuntimeException('Admin role must be super or staff.');
    }
    if ($p = \SJT\Service\Auth::passwordProblem($password)) {
        throw new RuntimeException($p);
    }
    $accounts = new \SJT\Service\StaffAccounts($db);
    if ($accounts->findByUsername($username)) {
        throw new RuntimeException("User '$username' already exists. Leave admin fields blank to skip.");
    }
    $ts = date('Y-m-d H:i:s');
    if ($accounts->usesErpUsers()) {
        $db->insert('users', [
            'username' => $username,
            'full_name' => $name !== '' ? $name : $username,
            'password_hash' => \SJT\Service\Auth::hash($password),
            'role' => \SJT\Service\StaffAccounts::erpRoleFromBookingSuper($role === 'super'),
            'is_active' => 1,
            'failed_logins' => 0,
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    } else {
        $db->insert('admins', [
            'username' => $username,
            'name' => $name !== '' ? $name : $username,
            'password_hash' => \SJT\Service\Auth::hash($password),
            'role' => $role,
            'is_active' => 1,
            'failed_logins' => 0,
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    }
    return "Created booking administrator '$username' ($role).";
}

/** @param array<string, mixed> $ctx */
function site_install_render_page(array $ctx): void
{
    $bookingUrl = '';
    $erpIndex = 'index.php';
    if (!empty($ctx['bookingRoot'])) {
        $bookingUrl = rtrim(site_install_booking_env((string) $ctx['bookingRoot'])['APP_URL'] ?? '', '/');
    }
    $erpFolder = basename((string) ($ctx['erpRoot'] ?? 'crm'));
    if (($ctx['entry'] ?? '') === 'booking') {
        $erpIndex = '../' . $erpFolder . '/index.php';
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set up temple ERP and online booking</title>
  <style>
    body { margin: 0; font: 16px/1.5 Georgia, serif; background: #f6f1e7; color: #2a2118; }
    main { max-width: 42rem; margin: 6vh auto; padding: 1.75rem 2rem; background: #fffdf8; border: 1px solid #e4d3b4; border-radius: 12px; }
    h1 { font-size: 1.55rem; margin: 0 0 0.5rem; color: #521010; }
    h2 { font-size: 1.1rem; margin: 1.25rem 0 0.5rem; }
    p { margin: 0 0 0.75rem; }
    label { display: block; font-size: 0.9rem; margin: 0.75rem 0 0.35rem; font-weight: 600; }
    label.check { font-weight: normal; margin: 0.5rem 0; }
    input, select { width: 100%; box-sizing: border-box; padding: 0.65rem 0.75rem; border: 1px solid #c8b48a; border-radius: 8px; font: inherit; }
    button { margin-top: 1.25rem; background: #7a1626; color: #fff; border: 0; border-radius: 8px; padding: 0.75rem 1.2rem; font: inherit; font-weight: 600; cursor: pointer; }
    .error { background: #fde8e4; border: 1px solid #e7b2a8; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .ok { background: #e7f5ea; border: 1px solid #b7d7bf; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .info { background: #fff3d8; border: 1px solid #f0d38f; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
    code { font-family: Consolas, monospace; font-size: 0.92em; }
    ul { margin: 0 0 0.75rem; padding-left: 1.25rem; }
  </style>
</head>
<body>
<main>
  <h1>Set up temple ERP and online booking</h1>
  <p>One setup key runs <strong>all</strong> database updates: ERP tables/columns (<code><?= e($erpFolder) ?>/</code>) and Booking migrations (001–008), on the <strong>same</strong> MySQL database.</p>

  <?php if ($ctx['serverProblems'] !== []): ?>
    <div class="error">
      <p><strong>Fix the server first:</strong></p>
      <ul><?php foreach ($ctx['serverProblems'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($ctx['error'] !== null): ?>
    <div class="error"><p><?= e($ctx['error']) ?></p></div>
  <?php endif; ?>

  <?php if (is_array($ctx['erpResult'])): ?>
    <div class="ok">
      <p><strong>ERP:</strong> <?= e(DB_NAME) ?> on <?= e(DB_HOST) ?> — <?= e((string) $ctx['erpResult']['tables']) ?> tables,
        <?= e((string) $ctx['erpResult']['users']) ?> users, <?= e((string) $ctx['erpResult']['donors']) ?> devotees.</p>
    </div>
  <?php endif; ?>

  <?php if ($ctx['bookingApplied'] !== [] || ($ctx['bookingLockWritten'] && $ctx['bookingApplied'] === [])): ?>
    <div class="ok">
      <p><strong>Online booking:</strong>
        <?php if ($ctx['bookingApplied'] !== []): ?>
          migrations applied: <?= e(implode(', ', $ctx['bookingApplied'])) ?>.
        <?php else: ?>
          schema already up to date.
        <?php endif; ?>
        <?= $ctx['bookingAdminMsg'] ? e($ctx['bookingAdminMsg']) : '' ?>
      </p>
    </div>
  <?php endif; ?>

  <?php if (is_array($ctx['erpResult']) || $ctx['bookingLockWritten']): ?>
    <div class="ok">
      <p><strong>Next steps</strong></p>
      <ul>
        <li>Sign in to <a href="<?= e($erpIndex) ?>">ERP</a><?php if ($bookingUrl !== ''): ?> and <a href="<?= e($bookingUrl . '/admin') ?>">online booking admin</a><?php endif; ?>.</li>
        <li>Remove <code>INSTALL_TOKEN</code> from <code><?= e($erpFolder) ?>/.env</code> (and Booking/.env if set there).</li>
        <li>Delete <code><?= e($erpFolder) ?>/install.php</code> and <code>Booking/install.php</code> on the server.</li>
        <?php if ($bookingUrl !== ''): ?>
        <li>In Booking: <code>php bin/console.php check</code> and configure Razorpay/WhatsApp in <code>.env</code>.</li>
        <?php endif; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!$ctx['bookingAvailable']): ?>
    <div class="info"><p>No <code>Booking/</code> folder found next to ERP. Only ERP setup will run.</p></div>
  <?php elseif ($ctx['bookingProblems'] !== []): ?>
    <div class="error">
      <p><strong>Booking folder needs attention:</strong></p>
      <ul><?php foreach ($ctx['bookingProblems'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
    </div>
  <?php elseif ($ctx['pendingBooking'] !== []): ?>
    <div class="info">
      <p><strong>Pending booking migrations (<?= count($ctx['pendingBooking']) ?>):</strong> <?= e(implode(', ', $ctx['pendingBooking'])) ?></p>
    </div>
  <?php endif; ?>

  <?php if ($ctx['ready'] && !is_array($ctx['erpResult']) && !$ctx['bookingLockWritten']): ?>
    <p>Database: <strong><?= e(DB_NAME) ?></strong> on <?= e(DB_HOST) ?>.</p>
    <?php if ($ctx['booksReady']): ?>
      <p class="info">Saved books snapshot available<?= $ctx['existingDonors'] === 0 ? ' — will import when the database has no devotees yet.' : '.' ?></p>
    <?php endif; ?>

    <form method="post" action="">
      <?= csrf_field() ?>
      <h2>Temple books (ERP)</h2>
      <?php if ($ctx['booksReady'] && $ctx['existingDonors'] !== null && $ctx['existingDonors'] > 0): ?>
        <label class="check"><input type="checkbox" name="replace_books" value="1"> Replace existing devotees/books with the bundled snapshot.</label>
      <?php endif; ?>

      <?php if ($ctx['bookingReady']): ?>
      <h2>Online booking</h2>
      <label class="check"><input type="checkbox" name="run_booking" value="1" checked> Run Booking migrations (services, payments, ERP journal links, notifications, …).</label>
      <p style="font-size:0.9rem">Optional: first booking admin (skip if an ERP Admin already exists — same login).</p>
      <label for="admin_user">Username</label>
      <input id="admin_user" name="admin_user" autocomplete="username" placeholder="leave blank to skip">
      <label for="admin_name">Full name</label>
      <input id="admin_name" name="admin_name" autocomplete="name">
      <label for="admin_role">Role</label>
      <select id="admin_role" name="admin_role">
        <option value="super">Senior admin (super)</option>
        <option value="staff">Staff</option>
      </select>
      <label for="admin_password">Password</label>
      <input id="admin_password" name="admin_password" type="password" autocomplete="new-password">
      <?php endif; ?>

      <label for="setup_key">Setup key (INSTALL_TOKEN in .env)</label>
      <input id="setup_key" name="setup_key" type="password" autocomplete="off" required>
      <button type="submit">Run all setup</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
    <?php
}
