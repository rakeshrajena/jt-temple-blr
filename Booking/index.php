<?php
declare(strict_types=1);

/*
 * Front controller for the temple booking app.
 *
 * Deploy the WHOLE booking/ folder to a web folder on the server
 * (e.g. public_html/Booking) and open https://your-site/Booking —
 * the same single-folder layout as the ERP php/ app. There is no
 * separate public/ split and no app-path.php to configure.
 */

define('APP_ROOT', __DIR__);

// Serve static assets directly under PHP's built-in server (local development only).
if (PHP_SAPI === 'cli-server') {
    $p = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $pos = strpos($p, '/assets/');
    if ($pos !== false && !str_contains($p, '..') && is_file(__DIR__ . substr($p, $pos))) {
        $ext = pathinfo($p, PATHINFO_EXTENSION);
        header('Content-Type: ' . (['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'ico' => 'image/x-icon'][$ext] ?? 'application/octet-stream'));
        readfile(__DIR__ . substr($p, $pos));
        return;
    }
}

require __DIR__ . '/app/bootstrap.php';

$router = require __DIR__ . '/app/routes.php';
(new SJT\Core\Kernel($router))->handle(SJT\Core\Request::fromGlobals())->send();
