<?php
declare(strict_types=1);

if (!defined('BOOKING_ROOT')) {
    define('BOOKING_ROOT', dirname(__DIR__));
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', BOOKING_ROOT);
}
if (!defined('DONATION_ROOT')) {
    define('DONATION_ROOT', __DIR__);
}

require_once BOOKING_ROOT . '/app/bootstrap.php';

use SJT\Core\App;

if (!function_exists('donation_url')) {
    function donation_url(string $path = '', array $query = []): string
    {
        $base = rtrim(App::basePath(), '/') . '/donation';
        $path = ltrim($path, '/');
        $u = $base . ($path !== '' ? '/' . $path : '/');
        if ($query !== []) {
            $u .= '?' . http_build_query($query);
        }
        return $u;
    }
}

if (!function_exists('donation_asset')) {
    function donation_asset(string $file): string
    {
        return donation_url('assets/' . ltrim($file, '/'));
    }
}
