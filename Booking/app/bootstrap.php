<?php
declare(strict_types=1);

/*
 * Application bootstrap. Loaded by index.php and the bin/ scripts.
 */
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('This application needs PHP 8.1 or newer. Select it in cPanel > MultiPHP Manager.');
}
require APP_ROOT . '/app/autoload.php';

$bookingRoot = sjt_booking_root();
SJT\Core\Env::load($bookingRoot . '/.env');
$localEnv = dirname($bookingRoot) . '/.env';
$skipRepoEnv = in_array(getenv('SJT_TEST') ?: '', ['1', 'true', 'yes'], true)
    || in_array(getenv('APP_ENV') ?: '', ['testing', 'test'], true);
if (!$skipRepoEnv && is_file($localEnv)) {
    SJT\Core\Env::load($localEnv, [
        'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PASSWORD',
        'MYSQL_USER', 'MYSQL_PASSWORD', 'MYSQL_DATABASE',
        'APP_ENV', 'APP_DEBUG', 'APP_URL', 'ERP_APP_URL', 'GATEWAY_SSL_VERIFY', 'INSTALL_TOKEN',
        'SMTP_ONLY_LOCAL_MTA', 'SMTP_PREFER_LOCAL_MTA', 'SMTP_SKIP_LOCAL_MTA', 'SMTP_DISABLE_PHP_MAIL',
    ]);
}
date_default_timezone_set(SJT\Core\Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');
SJT\Core\Logger::init($bookingRoot . '/storage/logs');

ini_set('display_errors', SJT\Core\App::debug() ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $bookingRoot . '/storage/logs/php-errors.log');
error_reporting(E_ALL);
set_error_handler(function (int $no, string $msg, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    if ($no & (E_DEPRECATED | E_USER_DEPRECATED)) {
        SJT\Core\Logger::warning('Deprecated: ' . $msg, ['at' => $file . ':' . $line]);
        return true;
    }
    throw new ErrorException($msg, 0, $no, $file, $line);
});

// Fatal errors (out of memory, parse errors in edited files): log them and show a plain message.
register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        SJT\Core\Logger::error('Fatal: ' . $e['message'], ['at' => $e['file'] . ':' . $e['line']]);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code(500);
            echo '<!doctype html><meta charset="utf-8"><title>Temporarily unavailable</title><p style="font:18px system-ui;max-width:600px;margin:40px auto">Sorry, the booking service is temporarily unavailable. Please try again in a few minutes or contact the temple office.</p>';
        }
    }
});
