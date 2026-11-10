<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/receipt_service.php';

use SJT\Core\App;
use SJT\Core\Request;
use SJT\Service\TempleLogo;

$req = Request::fromGlobals();
$token = trim((string) ($req->query['t'] ?? ''));
if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Receipt not found.';
    exit;
}

$db = App::db();
$row = $db->one(
    "SELECT d.id, d.receipt_number, d.receipt_generated, d.receipt_cancelled
     FROM donations d
     WHERE d.receipt_share_token = ?
       AND d.receipt_generated = 1
       AND d.receipt_number IS NOT NULL
       AND d.receipt_number <> ''",
    [$token]
);
if (!$row || (int) ($row['receipt_cancelled'] ?? 0) === 1) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Receipt not found.';
    exit;
}

$donationId = (int) $row['id'];
$number = (string) $row['receipt_number'];
if (!donation_receipt_pdf_ready($number)) {
    donation_issue_full_receipt($db, $donationId);
}

$path = TempleLogo::erpRoot() . '/storage/receipts/' . $number . '.pdf';
if (!is_file($path)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Receipt PDF is not ready yet. Please try again in a minute or contact the temple office.';
    exit;
}

$filename = $number . '.pdf';
$inline = ($req->query['download'] ?? '') !== '1';
header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, no-store');
header(
    'Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"'
);
readfile($path);
exit;
