<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/app/bootstrap.php';

use SJT\Core\Database;
use SJT\Core\Env;

$root = sjt_booking_root();
$parentEnv = dirname($root) . '/.env';
$localEnv = $root . '/.env.local';

echo "app: Booking (donation uses the same .env)\n";
echo "Booking/.env readable: " . (is_readable($root . '/.env') ? 'yes' : 'no') . "\n";
echo "parent .env is_file: " . (is_file($parentEnv) ? 'yes' : 'no') . "\n";
echo "Booking/.env.local is_file: " . (is_file($localEnv) ? 'yes' : 'no') . "\n";
echo "local dev overrides active: " . (sjt_booking_local_dev_host() ? 'yes' : 'no') . "\n";
echo "effective DB_USER=" . (string) Env::get('DB_USER', '') . "\n";
echo "effective DB_NAME=" . (string) Env::get('DB_NAME', '') . "\n";

try {
    Database::fromEnv();
    echo "pdo: connected\n";
} catch (Throwable $e) {
    echo 'pdo: FAIL — ' . $e->getMessage() . "\n";
    if (strtolower((string) Env::get('DB_USER', '')) === 'root') {
        echo "hint: Remove public_html/.env and Booking/.env.local on the server, or upload Booking/.env with the cPanel MySQL user.\n";
    }
}