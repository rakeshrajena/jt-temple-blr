<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$app = __DIR__;
$parentEnv = dirname($app) . DIRECTORY_SEPARATOR . '.env';
$crmEnv = $app . DIRECTORY_SEPARATOR . '.env';

echo "open_basedir: " . (ini_get('open_basedir') ?: '(none)') . "\n";
echo "crm/.env readable: " . (is_readable($crmEnv) ? 'yes' : 'no') . "\n";
echo "parent .env is_file: " . (is_file($parentEnv) ? 'yes' : 'no') . "\n";
echo "parent .env readable: " . (is_readable($parentEnv) ? 'yes' : 'no') . "\n";

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
}