<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/config.php';
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/locale.php';
require APP_ROOT . '/includes/help_tip.php';
require APP_ROOT . '/includes/PdfDocument.php';
require APP_ROOT . '/includes/barcode.php';
require APP_ROOT . '/includes/qrcode.php';
require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/install_booking.php';
require APP_ROOT . '/includes/staff_session.php';
require APP_ROOT . '/includes/booking_link.php';
require APP_ROOT . '/includes/booking_settings.php';
require APP_ROOT . '/includes/booking_catalog.php';
require APP_ROOT . '/includes/booking_ledger_sync.php';
require APP_ROOT . '/includes/seed.php';
require APP_ROOT . '/includes/install_check.php';
require APP_ROOT . '/includes/reports.php';
require APP_ROOT . '/includes/reconciliation.php';
require APP_ROOT . '/includes/receipt.php';
require APP_ROOT . '/includes/coupons.php';
require APP_ROOT . '/includes/coupon_catalog.php';
require APP_ROOT . '/includes/online_booking_migrate.php';
require APP_ROOT . '/includes/vouchers.php';
require APP_ROOT . '/includes/approval.php';
require APP_ROOT . '/includes/corrections.php';
require APP_ROOT . '/includes/donation_edits.php';
require APP_ROOT . '/includes/books.php';
require APP_ROOT . '/includes/donors.php';
require APP_ROOT . '/includes/stock.php';
require APP_ROOT . '/includes/settings.php';
require APP_ROOT . '/includes/brand_mark.php';
require APP_ROOT . '/includes/selections.php';
require APP_ROOT . '/includes/subscribers.php';
require APP_ROOT . '/includes/suggest.php';
require APP_ROOT . '/includes/accounts.php';
require APP_ROOT . '/includes/invitations.php';
require APP_ROOT . '/includes/contributors.php';
require APP_ROOT . '/includes/actions.php';

date_default_timezone_set(TIMEZONE);
ensure_storage();

if (defined('JT_INSTALL_MINIMAL')) {
    if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
        $sessDir = APP_ROOT . '/storage/sessions';
        if (!is_dir($sessDir)) {
            @mkdir($sessDir, 0755, true);
        }
        session_name(SESSION_NAME);
        if (is_dir($sessDir) && is_writable($sessDir)) {
            session_save_path($sessDir);
        }
        session_start();
    }
    return;
}

food_coupon_catalog_ensure_ready();
online_booking_migrate_puja_purposes_from_choices();
online_booking_normalize_service_food_rules();
online_booking_normalize_service_slot_rules();

if (PHP_SAPI !== 'cli') {
    session_name(SESSION_NAME);
    staff_session_bootstrap();
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
