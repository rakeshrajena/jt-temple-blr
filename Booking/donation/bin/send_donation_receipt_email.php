<?php
declare(strict_types=1);

/** CLI: email donation receipt PDF (same as ERP). Invoked from Booking/donation after online payment. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$donationId = (int) ($argv[1] ?? 0);
$to = isset($argv[2]) ? trim((string) $argv[2]) : null;
if ($donationId <= 0) {
    fwrite(STDERR, "Usage: php send_donation_receipt_email.php DONATION_ID [EMAIL]\n");
    exit(1);
}

$bookingRoot = dirname(__DIR__, 2);
$erp = $bookingRoot . DIRECTORY_SEPARATOR . 'crm';
if (!is_dir($erp)) {
    $erp = dirname($bookingRoot) . DIRECTORY_SEPARATOR . 'crm';
}
if (!is_dir($erp)) {
    $erp = dirname($bookingRoot) . DIRECTORY_SEPARATOR . 'php';
}
if (!is_dir($erp)) {
    fwrite(STDOUT, "ERROR: ERP folder not found.\n");
    exit(2);
}

require dirname(__DIR__) . '/worker/erp_bootstrap.php';
donation_erp_bootstrap($erp, 'email');

try {
    $result = send_donation_receipt($donationId, load_messaging_settings(), $to !== '' ? $to : null);
} catch (Throwable $e) {
    fwrite(STDOUT, 'ERROR: ' . $e->getMessage() . "\n");
    exit(3);
}

if (($result['error'] ?? null) !== null) {
    fwrite(STDOUT, 'ERROR: ' . (string) $result['error'] . "\n");
    fwrite(STDOUT, 'EMAIL: ' . (string) ($result['email'] ?? '') . "\n");
    fwrite(STDOUT, 'NUMBER: ' . (string) ($result['number'] ?? '') . "\n");
    exit(4);
}

fwrite(STDOUT, "OK\n");
fwrite(STDOUT, 'EMAIL: ' . (string) ($result['email'] ?? '') . "\n");
fwrite(STDOUT, 'NUMBER: ' . (string) ($result['number'] ?? '') . "\n");
exit(0);
