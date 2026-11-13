<?php
declare(strict_types=1);

define('MANAGE_DATA_ROOT', __DIR__);
define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/config.php';
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/settings.php';
require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/staff_session.php';

if (PHP_SAPI !== 'cli') {
    session_name(SESSION_NAME);
    staff_session_bootstrap();
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}
