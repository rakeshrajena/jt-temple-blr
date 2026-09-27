<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

try {
    db();
    dispatch_request();
} catch (PDOException $e) {
    error_log('[jt_blr] ' . $e->getMessage());
    http_response_code(500);
    echo 'Database connection failed. Check the database name, user, and password in the server configuration.';
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
