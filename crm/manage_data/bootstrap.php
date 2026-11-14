<?php
declare(strict_types=1);

define('MANAGE_DATA_ROOT', __DIR__);
define('MANAGE_DATA_BOOT', true);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    header('Cache-Control: no-store');
}