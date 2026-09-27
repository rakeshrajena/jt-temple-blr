<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/config.php';
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/locale.php';
require APP_ROOT . '/includes/PdfDocument.php';
require APP_ROOT . '/includes/barcode.php';
require APP_ROOT . '/includes/qrcode.php';
require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/seed.php';
require APP_ROOT . '/includes/reports.php';
require APP_ROOT . '/includes/reconciliation.php';
require APP_ROOT . '/includes/receipt.php';
require APP_ROOT . '/includes/coupons.php';
require APP_ROOT . '/includes/vouchers.php';
require APP_ROOT . '/includes/approval.php';
require APP_ROOT . '/includes/corrections.php';
require APP_ROOT . '/includes/books.php';
require APP_ROOT . '/includes/donors.php';
require APP_ROOT . '/includes/stock.php';
require APP_ROOT . '/includes/settings.php';
require APP_ROOT . '/includes/brand_mark.php';
require APP_ROOT . '/includes/selections.php';
require APP_ROOT . '/includes/suggest.php';
require APP_ROOT . '/includes/accounts.php';
require APP_ROOT . '/includes/actions.php';

date_default_timezone_set(TIMEZONE);
ensure_storage();

if (PHP_SAPI !== 'cli') {
    session_name(SESSION_NAME);
    $cookiePath = app_dir_url();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => ($cookiePath === '' || $cookiePath === '.') ? '/' : $cookiePath . '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
