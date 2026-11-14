<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

try {
    db();
    dispatch_request();
} catch (PDOException $e) {
    error_log('[jt_blr] ' . $e->getMessage());
    http_response_code(500);
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $local = $host === '' || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
    $msg = $e->getMessage();
    $connectFailed = preg_match('/(access denied|unknown database|connection refused|can\'t connect)/i', $msg) === 1;
    if ($connectFailed) {
        $hint = $local
            ? ' On AMPPS: start MySQL, put local DB_USER and DB_PASSWORD in crm/.env.local (copy from crm/env.local.example), then open crm/install_db_probe.php to verify.'
            : '';
        echo 'Database connection failed. Check the database name, user, and password in the server configuration.' . $hint;
    } elseif ($local) {
        echo 'Database error: ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    } else {
        echo 'A database error occurred. Check the server error log.';
    }
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'install.php')) {
        http_response_code(503);
        echo 'The tables are not created yet. Open <a href="install.php">install.php</a> and enter the setup key.';
        return;
    }
    $detail = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    error_log('[jt_blr] ' . $detail);
    $logDir = __DIR__ . '/storage/logs';
    if (is_dir($logDir)) {
        file_put_contents($logDir . '/app.log', '[' . date('c') . '] ' . $detail . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    http_response_code(500);
    echo 'A server error occurred.';
} catch (Throwable $e) {
    $detail = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    error_log('[jt_blr] ' . $detail);
    $logDir = __DIR__ . '/storage/logs';
    if (is_dir($logDir)) {
        file_put_contents($logDir . '/app.log', '[' . date('c') . '] ' . $detail . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    http_response_code(500);
    echo 'A server error occurred.';
}
