<?php
declare(strict_types=1);

/*
 * Minimal PSR-4 autoloader for the SJT\ namespace (app/), plus the small
 * dependency-free libraries. Replaces Composer's vendor/autoload.php.
 *
 * BOOKING_ROOT is always this app folder. During unified install, crm/bootstrap.php
 * may define APP_ROOT to crm/ first — never use APP_ROOT alone for Booking paths.
 */

if (!defined('BOOKING_ROOT')) {
    define('BOOKING_ROOT', dirname(__DIR__));
}

$bookingRoot = BOOKING_ROOT;

if (!is_file($bookingRoot . '/app/helpers.php')) {
    throw new RuntimeException(
        'Booking autoload loaded from the wrong folder (expected ' . $bookingRoot . '/app/helpers.php). '
        . 'Deploy Booking as public_html/Booking/ next to crm/, not inside crm/app/.'
    );
}

if (!function_exists('sjt_booking_root')) {
    function sjt_booking_root(): string
    {
        return BOOKING_ROOT;
    }
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', BOOKING_ROOT);
}

spl_autoload_register(static function (string $class) use ($bookingRoot): void {
    $prefix = 'SJT\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    $candidates = [$bookingRoot . '/app/' . $rel];
    $op = 'OnlinePay/';
    if (str_starts_with($rel, $op)) {
        $candidates[] = $bookingRoot . '/online_pay/' . substr($rel, strlen($op));
    }
    foreach ($candidates as $file) {
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require $bookingRoot . '/app/helpers.php';

// Unified install loads crm/bootstrap first (same qr + PDF libs). Skip duplicates.
if (!function_exists('qr_matrix')) {
    require $bookingRoot . '/app/Lib/qrcode.php';
}
if (!class_exists('PdfDocument', false)) {
    require $bookingRoot . '/app/Lib/PdfDocument.php';
}
