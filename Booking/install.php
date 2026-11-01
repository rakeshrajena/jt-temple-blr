<?php
declare(strict_types=1);

/**
 * Unified setup when php/ is deployed beside Booking/ (recommended).
 * Falls back to booking-only setup if php/ is not on the server.
 */

if (PHP_VERSION_ID < 80100) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    exit('PHP 8.1 or newer is required. This server runs ' . PHP_VERSION);
}

$phpRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'php';
$unified = $phpRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'site_install.php';

if (is_file($unified)) {
    require $unified;
    site_install_http([
        'entry' => 'booking',
        'php_root' => $phpRoot,
        'booking_root' => __DIR__,
    ]);
    exit;
}

require __DIR__ . '/install_booking_only.php';
