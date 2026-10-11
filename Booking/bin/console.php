<?php
declare(strict_types=1);

/*
 * Command-line tasks. Run from the application folder, e.g.
 *   php bin/console.php migrate
 *   php bin/console.php create-admin <username> "<Full Name>" super
 *   php bin/console.php reset-password <username>
 *   php bin/console.php notifications:retry      (cron, every 10 minutes)
 *   php bin/console.php maintenance              (cron, daily)
 *   php bin/console.php check                    (configuration check)
 *   php bin/console.php key                      (print a new APP_KEY)
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

use SJT\Core\App;
use SJT\Core\Container;
use SJT\Core\Env;
use SJT\Core\Migrator;
use SJT\Service\Auth;

$cmd = $argv[1] ?? 'help';
$out = fn (string $s) => fwrite(STDOUT, $s . PHP_EOL);
$err = function (string $s): never {
    fwrite(STDERR, $s . PHP_EOL);
    exit(1);
};
$ask = function (string $prompt, bool $hidden = false): string {
    fwrite(STDOUT, $prompt);
    if ($hidden && DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN)) {
        shell_exec('stty -echo');
        $v = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
        return $v;
    }
    return trim((string) fgets(STDIN));
};

try {
    switch ($cmd) {
        case 'migrate':
            $m = new Migrator(App::db(), APP_ROOT . '/migrations');
            $done = $m->migrate($out);
            $out($done ? 'Applied: ' . implode(', ', $done) : 'Database is up to date.');
            break;

        case 'create-admin':
            $username = strtolower(trim($argv[2] ?? $ask('Username: ')));
            $name = trim($argv[3] ?? $ask('Full name: '));
            $role = $argv[4] ?? 'staff';
            if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
                $err('Username must be 3-50 characters: letters, digits, dot, dash, underscore.');
            }
            if (!in_array($role, ['super', 'staff'], true)) {
                $err('Role must be "super" or "staff".');
            }
            $pw = getenv('SJT_ADMIN_PASSWORD') ?: $ask('Password (min 10 chars, letters + numbers): ', true);
            if ($p = Auth::passwordProblem($pw)) {
                $err($p);
            }
            $db = App::db();
            if ($db->value('SELECT 1 FROM admins WHERE username = ?', [$username])) {
                $err("User $username already exists. Use reset-password.");
            }
            $db->insert('admins', [
                'username' => $username, 'name' => $name ?: $username, 'password_hash' => Auth::hash($pw),
                'role' => $role, 'is_active' => 1, 'failed_logins' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            Container::audit()->log(['id' => null, 'name' => 'cli'], 'admin', $username, null, 'admin.created', ['role' => $role]);
            $out("Administrator '$username' ($role) created.");
            break;

        case 'reset-password':
            $username = strtolower(trim($argv[2] ?? $ask('Username: ')));
            $pw = getenv('SJT_ADMIN_PASSWORD') ?: $ask('New password: ', true);
            if ($p = Auth::passwordProblem($pw)) {
                $err($p);
            }
            $n = App::db()->update('admins', ['password_hash' => Auth::hash($pw), 'failed_logins' => 0, 'locked_until' => null, 'updated_at' => now()], 'username = :u', ['u' => $username]);
            if (!$n) {
                $err('No such user.');
            }
            Container::audit()->log(['id' => null, 'name' => 'cli'], 'admin', $username, null, 'admin.password_reset', []);
            $out('Password updated and account unlocked.');
            break;

        case 'notifications:retry':
            [$tried, $sent] = Container::notifier()->retryDue();
            if ($tried) {
                $out("Retried $tried notification(s); $sent sent.");
            }
            break;

        case 'maintenance':
            Container::limiter()->prune();
            // Remove session files older than 2 days and logs older than 180 days.
            foreach (glob(APP_ROOT . '/storage/sessions/sess_*') ?: [] as $f) {
                if (filemtime($f) < time() - 172800) {
                    @unlink($f);
                }
            }
            foreach (glob(APP_ROOT . '/storage/logs/app-*.log') ?: [] as $f) {
                if (filemtime($f) < time() - 180 * 86400) {
                    @unlink($f);
                }
            }
            $out('Maintenance complete.');
            break;

        case 'check':
            $ok = fn ($c, $m) => $out(($c ? '[ OK ] ' : '[WARN] ') . $m);
            $ok(PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION);
            foreach (['pdo_mysql', 'mbstring', 'gd', 'curl', 'dom', 'openssl'] as $ext) {
                $ok(extension_loaded($ext), "extension $ext");
            }
            $ok(strlen((string) Env::get('APP_KEY')) >= 32 && Env::get('APP_KEY') !== 'change-me-to-a-long-random-string', 'APP_KEY set');
            $ok(str_starts_with((string) Env::get('APP_URL'), 'https://'), 'APP_URL uses HTTPS (' . Env::get('APP_URL') . ')');
            $ok(!Env::bool('APP_DEBUG'), 'APP_DEBUG is off');
            foreach (['logs', 'uploads', 'cache', 'sessions', 'backups'] as $d) {
                $ok(is_writable(APP_ROOT . "/storage/$d"), "storage/$d writable");
            }
            App::db()->value('SELECT 1');
            $ok(true, 'database connection');
            $pending = (new Migrator(App::db(), APP_ROOT . '/migrations'))->pending();
            $ok(!$pending, $pending ? 'pending migrations: ' . implode(', ', array_keys($pending)) : 'migrations up to date');
            $s = App::settings();
            $ok((int) App::db()->value("SELECT COUNT(*) FROM admins WHERE role = 'super' AND is_active = 1") > 0, 'senior administrator account exists');
            $ok($s->bool('upi_verified') && Container::upi()->available(), 'UPI payment details verified and available');
            $ok(Container::whatsapp()->configured(), 'WhatsApp API credentials present');
            $ok($s->bool('whatsapp_enabled'), 'WhatsApp notifications switched on');
            $ok((bool) $s->adminNumbers(), 'administrator WhatsApp number configured');
            $ok($s->bool('prices_reviewed'), 'service prices confirmed by an administrator');
            $ok((bool) $s->get('temple_phone'), 'temple phone number set');
            break;

        case 'key':
            $out(bin2hex(random_bytes(32)));
            break;

        default:
            $out("Commands: migrate | create-admin [user] [name] [super|staff] | reset-password [user] | notifications:retry | maintenance | check | key");
    }
} catch (Throwable $e) {
    $err('Error: ' . $e->getMessage());
}
