<?php
declare(strict_types=1);

use SJT\Core\Database;
use SJT\Core\Logger;
use SJT\Service\TempleLogo;

/** @return array{donation_id: int, receipt_number: ?string} */
function donation_post_to_erp(
    Database $db,
    array $intent,
    string $paymentId,
): array {
    $refNo = (string) $intent['ref_no'];
    $existingId = $db->one(
        'SELECT id, receipt_number, receipt_generated FROM donations WHERE online_booking_no = ? LIMIT 1',
        [$refNo]
    );
    if ($existingId) {
        $donationId = (int) $existingId['id'];
        $issued = donation_issue_full_receipt($db, $donationId);
        $fresh = $db->one(
            'SELECT receipt_number, receipt_generated FROM donations WHERE id = ?',
            [$donationId]
        ) ?: $existingId;

        return [
            'donation_id' => $donationId,
            'receipt_number' => $issued['number'] ?? donation_receipt_label($fresh),
        ];
    }

    $donorId = donation_ensure_donor($db, $intent);
    $amount = round((int) $intent['amount_paise'] / 100, 2);
    $purpose = trim((string) ($intent['purpose'] ?? ''));
    if ($purpose === '') {
        $purpose = 'General temple donation';
    }

    $row = [
        'donor_id' => $donorId,
        'donation_type' => 'Cash',
        'amount' => $amount,
        'purpose' => mb_substr($purpose, 0, 200),
        'donation_date' => date('Y-m-d'),
        'payment_mode' => 'UPI',
        'upi_reference' => mb_substr($paymentId, 0, 64),
        'notes' => 'Online donation ' . $refNo,
        'created_by' => null,
        'receipt_generated' => 0,
        'receipt_cancelled' => 0,
        'cheque_cleared' => 0,
        'online_booking_id' => null,
        'online_booking_no' => $refNo,
    ];

    $donationId = (int) $db->insert('donations', $row);
    $receipt = donation_finalize_receipt($db, $donationId);

    return ['donation_id' => $donationId, 'receipt_number' => $receipt];
}

function donation_ensure_donor(Database $db, array $intent): int
{
    $phone = donation_normalise_mobile((string) ($intent['phone'] ?? ''));
    if ($phone !== '') {
        $row = $db->one('SELECT id FROM donors WHERE phone = ? LIMIT 1', [$phone]);
        if ($row) {
            return (int) $row['id'];
        }
    }
    $pan = trim((string) ($intent['pan_number'] ?? ''));
    return (int) $db->insert('donors', [
        'name' => mb_substr(trim((string) $intent['donor_name']), 0, 150),
        'phone' => $phone !== '' ? $phone : null,
        'email' => trim((string) ($intent['email'] ?? '')) ?: null,
        'pan_number' => $pan !== '' ? mb_substr(strtoupper($pan), 0, 20) : null,
    ]);
}

function donation_normalise_mobile(string $raw): string
{
    $digits = preg_replace('/\D/', '', $raw) ?? '';
    if (strlen($digits) === 10) {
        return '91' . $digits;
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        return $digits;
    }
    return $digits;
}

function donation_finalize_receipt(Database $db, int $donationId): ?string
{
    $issued = donation_issue_full_receipt($db, $donationId);

    return $issued['number'];
}

/** @param array<string, mixed> $row */
function donation_receipt_label(array $row): ?string
{
    $n = trim((string) ($row['receipt_number'] ?? ''));
    if ($n !== '' && (int) ($row['receipt_generated'] ?? 0) === 1) {
        return $n;
    }
    return $n !== '' ? $n : null;
}

function donation_new_ref_no(): string
{
    return sprintf('ODN-%s-%s', date('Y'), strtoupper(bin2hex(random_bytes(4))));
}
