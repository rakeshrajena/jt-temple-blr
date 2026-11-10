<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
donation_worker_verify();

$donationId = (int) ($_POST['id'] ?? 0);
$to = isset($_POST['email']) ? trim((string) $_POST['email']) : null;
if ($donationId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: Invalid donation id.\n";
    exit;
}

require __DIR__ . '/erp_bootstrap.php';
donation_erp_bootstrap(donation_worker_erp_root(), 'email');

header('Content-Type: text/plain; charset=utf-8');

try {
    $result = send_donation_receipt($donationId, load_messaging_settings(), $to !== '' ? $to : null);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}

if (($result['error'] ?? null) !== null) {
    echo 'ERROR: ' . (string) $result['error'] . "\n";
    echo 'EMAIL: ' . (string) ($result['email'] ?? '') . "\n";
    echo 'NUMBER: ' . (string) ($result['number'] ?? '') . "\n";
    exit(4);
}

echo "OK\n";
echo 'EMAIL: ' . (string) ($result['email'] ?? '') . "\n";
echo 'NUMBER: ' . (string) ($result['number'] ?? '') . "\n";
