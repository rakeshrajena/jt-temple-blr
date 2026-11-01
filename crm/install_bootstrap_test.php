<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

try {
    if (!defined('JT_INSTALL_MINIMAL')) {
        define('JT_INSTALL_MINIMAL', true);
    }
    require __DIR__ . '/includes/bootstrap.php';
    echo "CRM bootstrap (install mode) OK\n";
    echo 'DB_NAME from config: ' . (DB_NAME !== '' ? DB_NAME : '(empty)') . "\n";
    echo 'INSTALL_TOKEN length: ' . strlen(env_value('INSTALL_TOKEN')) . "\n";
    echo 'session: ' . (session_status() === PHP_SESSION_ACTIVE ? 'active' : 'off') . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Bootstrap failed: ' . $e->getMessage() . "\n";
}
