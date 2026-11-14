<?php
declare(strict_types=1);

/**
 * Recreate receipt PDFs from existing donation rows — no new receipt numbers, no ledger changes.
 *
 * @return array{ok:bool,message:string,results:list<array{ref:string,number:string,status:string,detail:string}>}
 */
function manage_data_regenerate_receipts(string $raw, bool $onlyIfMissing): array
{
    $refs = manage_data_parse_receipt_refs($raw);
    if ($refs === []) {
        return ['ok' => false, 'message' => 'Enter at least one receipt number or reference (RCPT-…, ODN-…, booking ref, or donation ID).', 'results' => []];
    }

    $pdo = manage_data_pdo();
    $results = [];
    $okCount = 0;
    $skipCount = 0;

    foreach ($refs as $ref) {
        $row = manage_data_donation_row_for_ref($pdo, $ref);
        if ($row === null) {
            $results[] = ['ref' => $ref, 'number' => '', 'status' => 'not_found', 'detail' => 'No gift row in the database.'];
            continue;
        }
        $number = trim((string) ($row['receipt_number'] ?? ''));
        if ($number === '') {
            $results[] = [
                'ref' => $ref,
                'number' => '',
                'status' => 'no_number',
                'detail' => 'Gift exists but has no receipt number yet — issue a receipt in CRM first (do not use this tool).',
            ];
            continue;
        }
        if (!receipt_number_is_valid($number)) {
            $results[] = ['ref' => $ref, 'number' => $number, 'status' => 'bad_number', 'detail' => 'Receipt number format is not valid for PDF storage.'];
            continue;
        }
        $path = receipt_path($number);
        if ($onlyIfMissing && is_file($path)) {
            ++$skipCount;
            $results[] = ['ref' => $ref, 'number' => $number, 'status' => 'skipped', 'detail' => 'PDF already on disk.'];
            continue;
        }

        $donation = $row;
        $donor = [
            'name' => (string) ($row['donor_name'] ?? ''),
            'phone' => (string) ($row['donor_phone'] ?? ''),
            'email' => (string) ($row['donor_email'] ?? ''),
            'address' => (string) ($row['donor_address'] ?? ''),
            'pan_number' => (string) ($row['donor_pan'] ?? ''),
        ];

        try {
            generate_receipt_pdf($donation, $donor, $number);
        } catch (Throwable $e) {
            error_log('[manage_data] receipt regen: ' . $e->getMessage());
            $results[] = ['ref' => $ref, 'number' => $number, 'status' => 'error', 'detail' => $e->getMessage()];
            continue;
        }

        if (!is_file($path)) {
            $results[] = ['ref' => $ref, 'number' => $number, 'status' => 'error', 'detail' => 'PDF write failed.'];
            continue;
        }

        ++$okCount;
        $results[] = [
            'ref' => $ref,
            'number' => $number,
            'status' => 'rebuilt',
            'detail' => basename($path) . ' (' . number_format((int) filesize($path) / 1024, 1) . ' KB)',
        ];
    }

    if ($okCount === 0 && $skipCount === 0) {
        return [
            'ok' => false,
            'message' => 'No PDFs were recreated. See details below.',
            'results' => $results,
        ];
    }

    $msg = $okCount > 0 ? "Recreated {$okCount} PDF(s) with the same receipt number(s)." : 'No files written.';
    if ($skipCount > 0) {
        $msg .= " Skipped {$skipCount} (already on disk).";
    }
    $msg .= ' Database and ledger rows were not changed.';

    return ['ok' => $okCount > 0, 'message' => $msg, 'results' => $results];
}

/** @return list<string> */
function manage_data_parse_receipt_refs(string $raw): array
{
    $raw = str_replace(["\r\n", "\r"], "\n", trim($raw));
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $out[] = strtoupper($part);
    }

    return array_values(array_unique($out));
}

/** @return ?array<string,mixed> */
function manage_data_donation_row_for_ref(PDO $pdo, string $ref): ?array
{
    $ref = trim($ref);
    if ($ref === '') {
        return null;
    }

    $sql = 'SELECT d.*, don.name AS donor_name, don.phone AS donor_phone, don.email AS donor_email,
                   don.address AS donor_address, don.pan_number AS donor_pan
            FROM donations d
            INNER JOIN donors don ON don.id = d.donor_id
            WHERE ';

    if (receipt_number_is_valid($ref)) {
        $stmt = $pdo->prepare($sql . 'd.receipt_number = ? LIMIT 1');
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    if (preg_match('/^[A-Z0-9-]{4,40}$/', $ref) === 1) {
        $stmt = $pdo->prepare($sql . 'd.online_booking_no = ? LIMIT 1');
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }
        if (preg_match('/^RCPT-/i', $ref) === 1) {
            $stmt = $pdo->prepare($sql . 'd.receipt_number = ? LIMIT 1');
            $stmt->execute([$ref]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                return $row;
            }
        }
    }

    if (preg_match('/^\d{1,10}$/', $ref) === 1) {
        $stmt = $pdo->prepare($sql . 'd.id = ? LIMIT 1');
        $stmt->execute([(int) $ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    return null;
}

/** @return list<array{receipt_number:string,missing:bool,donation_date:?string,source:string}> */
function manage_data_missing_receipt_pdfs(PDO $pdo, int $limit = 50): array
{
    if (!manage_data_table_exists($pdo, 'donations')) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    $rows = $pdo->query(
        "SELECT id, receipt_number, donation_date, online_booking_no, purpose, payment_mode
         FROM donations
         WHERE receipt_number IS NOT NULL AND receipt_number <> ''
         ORDER BY donation_date DESC, id DESC
         LIMIT 500"
    )->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $num = (string) $row['receipt_number'];
        if (!receipt_number_is_valid($num)) {
            continue;
        }
        if (is_file(receipt_path($num))) {
            continue;
        }
        $out[] = [
            'receipt_number' => $num,
            'missing' => true,
            'donation_date' => (string) ($row['donation_date'] ?? '') ?: null,
            'source' => manage_data_receipt_source_label([
                'online_booking_no' => $row['online_booking_no'] ?? null,
                'purpose' => $row['purpose'] ?? null,
            ]),
        ];
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}