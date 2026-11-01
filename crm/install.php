<?php
declare(strict_types=1);

/**
 * Unified setup: temple ERP tables + sibling Booking/ migrations (one setup key).
 */

(function (): void {
    $fatal = static function (): void {
        $e = error_get_last();
        if ($e === null || !in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8', true, 500);
        }
        echo 'Install fatal: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line'] . "\n";
    };
    register_shutdown_function($fatal);
})();

if (PHP_VERSION_ID < 80100) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'PHP 8.1 or newer is needed; this server runs ' . PHP_VERSION . ". Choose PHP 8.2+ in the hosting control panel, then reload.\n";
    exit;
}

$siteInstall = __DIR__ . '/includes/site_install.php';
if (!is_file($siteInstall)) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    exit('Missing crm/includes/site_install.php — re-upload the crm folder from your release.');
}

try {
    require $siteInstall;
    site_install_http([
        'entry' => 'erp',
        'php_root' => __DIR__,
        'booking_root' => null,
    ]);
} catch (Throwable $e) {
    error_log('[jt_blr] crm/install.php: ' . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8', true, 500);
    }
    exit(
        "Setup failed: " . $e->getMessage() . "\n\n"
        . "Check crm/.env (INSTALL_TOKEN, DB_*), Booking/.env if present, and storage/ permissions.\n"
        . "Try crm/install_probe.php for a quick path check.\n"
    );
}
