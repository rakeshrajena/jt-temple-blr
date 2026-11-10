<?php
declare(strict_types=1);

/**
 * Generate donation receipt PDF in ERP context (APP_ROOT = crm).
 * Fallback when PHP CLI exec is disabled or misconfigured on Windows/AMPPS.
 */
require __DIR__ . '/_auth.php';
donation_worker_verify();

$donationId = (int) ($_POST['id'] ?? 0);
if ($donationId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: Invalid donation id.\n";
    exit;
}

require __DIR__ . '/erp_bootstrap.php';
donation_erp_bootstrap(donation_worker_erp_root(), 'issue');

header('Content-Type: text/plain; charset=utf-8');

try {
    $issued = issue_donation_receipt($donationId, null);
    echo "OK\n";
    echo 'NUMBER: ' . $issued['number'] . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
