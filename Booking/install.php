<?php
declare(strict_types=1);

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
    exit('PHP 8.1 or newer is required. This server runs ' . PHP_VERSION);
}

require __DIR__ . '/install_erp_locator.php';

try {
    $erpRoot = booking_install_erp_root();
    $unified = $erpRoot !== null
        ? $erpRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'site_install.php'
        : null;

    if ($unified !== null && is_file($unified)) {
        require $unified;
        site_install_http([
            'entry' => 'booking',
            'php_root' => $erpRoot,
            'booking_root' => __DIR__,
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log('[jt_blr] Booking/install.php: ' . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8', true, 500);
    }
    exit(
        "Setup failed: " . $e->getMessage() . "\n\n"
        . "Check crm/.env and Booking/.env (same database), INSTALL_TOKEN in crm/.env, and storage/ permissions.\n"
        . "Try Booking/install_probe.php for a quick path check.\n"
    );
}

require __DIR__ . '/install_booking_only.php';
