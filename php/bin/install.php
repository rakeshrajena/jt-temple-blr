<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

try {
    $pdo = db();
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Database " . DB_NAME . " is ready.\n";
    echo 'Tables: ' . count($tables) . "\n";
    echo 'Users: ' . $users . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
