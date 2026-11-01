<?php
declare(strict_types=1);

/**
 * Unified setup: temple ERP tables + sibling Booking/ migrations (one setup key).
 */

if (PHP_VERSION_ID < 80100) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'PHP 8.1 or newer is needed; this server runs ' . PHP_VERSION . ". Choose PHP 8.2 in the hosting control panel (cPanel, Select PHP Version), then reload this page.\n";
    exit;
}

require __DIR__ . '/includes/site_install.php';

site_install_http([
    'entry' => 'erp',
    'php_root' => __DIR__,
    'booking_root' => null,
]);
