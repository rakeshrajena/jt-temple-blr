<?php
declare(strict_types=1);

/** CLI: email ERP donation receipt PDF + public link (booking, donation, manual). */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$donationId = (int) ($argv[1] ?? 0);
$to = isset($argv[2]) ? trim((string) $argv[2]) : '';
if ($donationId <= 0) {
    fwrite(STDERR, "Usage: php send_donation_receipt_email.php DONATION_ID [EMAIL]\n");
    exit(1);
}

define('JT_INSTALL_MINIMAL', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

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
