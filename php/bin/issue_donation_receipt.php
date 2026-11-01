<?php
declare(strict_types=1);

/** CLI: issue (or refresh) ERP receipt PDF for one donation. Used by online booking ledger sync. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/config.php';
require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/PdfDocument.php';
require APP_ROOT . '/includes/barcode.php';
require APP_ROOT . '/includes/qrcode.php';
require APP_ROOT . '/includes/brand_mark.php';
require APP_ROOT . '/includes/settings.php';
require APP_ROOT . '/includes/receipt.php';

$donationId = (int) ($argv[1] ?? 0);
$userId = isset($argv[2]) ? (int) $argv[2] : null;
if ($donationId <= 0) {
    fwrite(STDERR, "Usage: php bin/issue_donation_receipt.php DONATION_ID [USER_ID]\n");
    exit(1);
}

try {
    issue_donation_receipt($donationId, $userId);
    fwrite(STDOUT, "Receipt issued for donation #{$donationId}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
