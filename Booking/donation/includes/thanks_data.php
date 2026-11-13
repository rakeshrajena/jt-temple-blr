<?php
declare(strict_types=1);

use SJT\Core\Database;

/** @return array<string, mixed>|null */
function donation_paid_summary(Database $db, string $refNo): ?array
{
    $intent = $db->one('SELECT * FROM online_donation_intents WHERE ref_no = ? AND status = ? LIMIT 1', [$refNo, 'paid']);
    if (!$intent) {
        return null;
    }

    $receipt = null;
    $links = null;
    if (!empty($intent['donation_id'])) {
        $donationId = (int) $intent['donation_id'];
        $issued = donation_issue_full_receipt($db, $donationId);
        $receipt = $issued['number'];
        $links = donation_receipt_links($db, $donationId);
    }

    return [
        'ref_no' => (string) $intent['ref_no'],
        'amount_paise' => (int) $intent['amount_paise'],
        'amount_rupees' => (int) round((int) $intent['amount_paise'] / 100),
        'receipt_number' => $receipt,
        'name' => (string) $intent['donor_name'],
        'email' => trim((string) ($intent['email'] ?? '')),
        'purpose' => (string) ($intent['purpose'] ?? ''),
        'paid_at' => (string) ($intent['paid_at'] ?? ''),
        'links' => $links,
        'donation_id' => (int) ($intent['donation_id'] ?? 0),
    ];
}

function donation_serve_receipt_by_ref(string $refNo, bool $download): void
{
    $db = \SJT\Core\App::db();
    $summary = donation_paid_summary($db, $refNo);
    if (!$summary || empty($summary['links']['share_token'])) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Receipt not found.';
        exit;
    }
    donation_serve_receipt_pdf_by_token((string) $summary['links']['share_token'], $download);
}
