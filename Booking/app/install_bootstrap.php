<?php
declare(strict_types=1);

/**
 * Load the Booking app after crm/bootstrap.php (APP_ROOT = crm).
 * Always pass the absolute path to the Booking folder (public_html/Booking).
 */
function sjt_booking_install_bootstrap(string $bookingRoot): void
{
    $bookingRoot = rtrim($bookingRoot, '/\\');
    $helpers = $bookingRoot . '/app/helpers.php';
    $autoload = $bookingRoot . '/app/autoload.php';

    if (!is_file($helpers)) {
        $crmApp = dirname($bookingRoot) . '/crm/app/helpers.php';
        $hint = is_file(dirname($bookingRoot) . '/crm/app/autoload.php')
            ? ' It looks like Booking was copied into crm/app/ — use public_html/Booking/ as its own folder beside crm/.'
            : '';
        throw new RuntimeException(
            'Booking app/helpers.php not found at ' . $helpers . '.' . $hint
            . ' Re-upload the full Booking folder from your release (including app/helpers.php and the updated app/autoload.php).'
        );
    }
    if (!is_file($autoload)) {
        throw new RuntimeException('Missing ' . $autoload);
    }

    if (!defined('BOOKING_ROOT')) {
        define('BOOKING_ROOT', $bookingRoot);
    }

    require_once $autoload;
}
