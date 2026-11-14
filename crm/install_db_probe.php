<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$app = __DIR__;
$parentEnv = dirname($app) . DIRECTORY_SEPARATOR . '.env';
$crmEnv = $app . DIRECTORY_SEPARATOR . '.env';
$localEnv = $app . DIRECTORY_SEPARATOR . '.env.local';

require $app . '/includes/env.php';

echo "app: crm\n";
echo "open_basedir: " . (ini_get('open_basedir') ?: '(none)') . "\n";
echo "crm/.env readable: " . (is_readable($crmEnv) ? 'yes' : 'no') . "\n";
echo "parent .env is_file: " . (is_file($parentEnv) ? 'yes' : 'no') . "\n";
echo "crm/.env.local is_file: " . (is_file($localEnv) ? 'yes' : 'no') . "\n";
echo "local dev overrides active: " . (env_local_dev_host() ? 'yes' : 'no') . "\n";

require $app . '/config.php';

echo "effective DB_USER=" . DB_USER . "\n";
echo "effective DB_NAME=" . DB_NAME . "\n";

try {
    new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "pdo: connected\n";
} catch (Throwable $e) {
    echo "pdo: FAIL — " . $e->getMessage() . "\n";
    if (strtolower(DB_USER) === 'root') {
        echo "hint: Delete public_html/.env and crm/.env.local on the server; upload crm/.env with the cPanel MySQL user.\n";
    }
}