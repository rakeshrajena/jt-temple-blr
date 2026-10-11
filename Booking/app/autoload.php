<?php
declare(strict_types=1);

/*
 * Minimal PSR-4 autoloader for the SJT\ namespace (app/), plus the small
 * dependency-free libraries. Replaces Composer's vendor/autoload.php so the
 * booking app ships with no third-party packages — like the ERP php/ app.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'SJT\\';
    if (str_starts_with($class, $prefix)) {
        $file = APP_ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/Lib/qrcode.php';
require APP_ROOT . '/app/Lib/PdfDocument.php';
