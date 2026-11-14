<?php
declare(strict_types=1);

/*
 * Minimal PSR-4 autoloader for the SJT\ namespace (app/), plus the small
 * dependency-free libraries. Replaces Composer's vendor/autoload.php.
 *
 * BOOKING_ROOT is always this app folder. During unified install, crm/bootstrap.php
 * may define APP_ROOT to crm/ first — never use APP_ROOT alone for Booking paths.
 */

if (!defined('BOOKING_ROOT')) {
    define('BOOKING_ROOT', dirname(__DIR__));
}

$bookingRoot = BOOKING_ROOT;

if (!is_file($bookingRoot . '/app/helpers.php')) {
    throw new RuntimeException(
        'Booking autoload loaded from the wrong folder (expected ' . $bookingRoot . '/app/helpers.php). '
        . 'Deploy Booking as public_html/Booking/ next to crm/, not inside crm/app/.'
    );
}

if (!function_exists('sjt_booking_root')) {
    function sjt_booking_root(): string
    {
        return BOOKING_ROOT;
    }
}

/** True on AMPPS/localhost — allows .env.local and parent public_html/.env DB overrides. */
if (!function_exists('sjt_booking_local_dev_host')) {
    function sjt_booking_local_dev_host(): bool
    {
        if (PHP_SAPI === 'cli') {
            $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($host !== '' && !preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host)) {
                return false;
            }
            $root = sjt_booking_root();
            return str_contains(strtolower($root), 'ampps')
                || str_contains(strtolower($root), 'xampp')
                || is_file($root . '/.env.local');
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return $host === '' || preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) === 1;
    }
}

/** Load Booking/.env; parent and .env.local only on AMPPS/localhost (shared by bootstrap, donation workers). */
if (!function_exists('sjt_booking_load_env')) {
    function sjt_booking_load_env(): void
    {
        require_once sjt_booking_root() . '/app/Core/Env.php';
        $bookingRoot = sjt_booking_root();
        \SJT\Core\Env::load($bookingRoot . '/.env');
        $localEnv = dirname($bookingRoot) . '/.env';
        $skipRepoEnv = in_array(getenv('SJT_TEST') ?: '', ['1', 'true', 'yes'], true)
            || in_array(getenv('APP_ENV') ?: '', ['testing', 'test'], true);
        if (!$skipRepoEnv && sjt_booking_local_dev_host() && is_file($localEnv)) {
            \SJT\Core\Env::load($localEnv, [
                'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PASSWORD',
                'MYSQL_USER', 'MYSQL_PASSWORD', 'MYSQL_DATABASE',
                'APP_ENV', 'APP_DEBUG', 'APP_URL', 'ERP_APP_URL', 'GATEWAY_SSL_VERIFY', 'INSTALL_TOKEN',
                'SMTP_ONLY_LOCAL_MTA', 'SMTP_PREFER_LOCAL_MTA', 'SMTP_SKIP_LOCAL_MTA', 'SMTP_DISABLE_PHP_MAIL',
            ]);
        }
        $bookingLocal = $bookingRoot . '/.env.local';
        if (!$skipRepoEnv && sjt_booking_local_dev_host() && is_file($bookingLocal)) {
            \SJT\Core\Env::load($bookingLocal, [
                'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PASSWORD',
                'MYSQL_USER', 'MYSQL_PASSWORD', 'MYSQL_DATABASE',
            ]);
        }
    }
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', BOOKING_ROOT);
}

spl_autoload_register(static function (string $class) use ($bookingRoot): void {
    $prefix = 'SJT\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    $candidates = [$bookingRoot . '/app/' . $rel];
    $op = 'OnlinePay/';
    if (str_starts_with($rel, $op)) {
        $candidates[] = $bookingRoot . '/online_pay/' . substr($rel, strlen($op));
    }
    foreach ($candidates as $file) {
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require_once $bookingRoot . '/app/helpers.php';

// Unified install loads crm/bootstrap first (same qr + PDF libs). Skip duplicates.
if (!function_exists('qr_matrix')) {
    require $bookingRoot . '/app/Lib/qrcode.php';
}
if (!class_exists('PdfDocument', false)) {
    require $bookingRoot . '/app/Lib/PdfDocument.php';
}
