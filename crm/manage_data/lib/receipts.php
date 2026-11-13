<?php
declare(strict_types=1);

/**
 * @return list<array{file:string,receipt_number:string,source:string,donation_date:?string,bytes:int,mtime:int,donation_id:?int}>
 */
function manage_data_list_receipt_pdfs(?string $search, ?string $from, ?string $to): array
{
    $dir = manage_data_receipts_dir();
    if (!is_dir($dir)) {
        return [];
    }

    $dbMap = manage_data_receipt_db_map();
    $search = trim((string) $search);
    $searchLower = $search !== '' ? mb_strtolower($search) : '';
    $from = manage_data_valid_date($from);
    $to = manage_data_valid_date($to);

    $out = [];
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..' || !str_ends_with(strtolower($name), '.pdf')) {
            continue;
        }
        $receiptNumber = preg_replace('/\.pdf$/i', '', $name);
        if (!is_string($receiptNumber) || $receiptNumber === '') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (!is_file($path)) {
            continue;
        }

        $meta = $dbMap[$receiptNumber] ?? null;
        $donationDate = $meta['donation_date'] ?? null;
        if ($from !== null && $to !== null && $donationDate !== null) {
            if ($donationDate < $from || $donationDate > $to) {
                continue;
            }
        }
        if ($searchLower !== '') {
            $hay = mb_strtolower($receiptNumber . ' ' . ($meta['online_booking_no'] ?? '') . ' ' . ($meta['purpose'] ?? ''));
            if (!str_contains($hay, $searchLower)) {
                continue;
            }
        }

        $out[] = [
            'file' => $name,
            'receipt_number' => $receiptNumber,
            'source' => manage_data_receipt_source_label($meta),
            'donation_date' => $donationDate,
            'bytes' => (int) filesize($path),
            'mtime' => (int) filemtime($path),
            'donation_id' => isset($meta['id']) ? (int) $meta['id'] : null,
        ];
    }

    usort($out, static fn (array $a, array $b): int => ($b['mtime'] <=> $a['mtime']));

    return $out;
}

/** @return array<string, array{id:int,donation_date:?string,online_booking_no:?string,purpose:?string,payment_mode:?string}> */
function manage_data_receipt_db_map(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $cache = [];
    if (!manage_data_table_exists(manage_data_pdo(), 'donations')) {
        return $cache;
    }
    $rows = manage_data_pdo()->query(
        'SELECT id, receipt_number, donation_date, online_booking_no, purpose, payment_mode
         FROM donations WHERE receipt_number IS NOT NULL AND receipt_number <> \'\''
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $num = (string) $row['receipt_number'];
        $cache[$num] = [
            'id' => (int) $row['id'],
            'donation_date' => (string) ($row['donation_date'] ?? '') ?: null,
            'online_booking_no' => trim((string) ($row['online_booking_no'] ?? '')) ?: null,
            'purpose' => trim((string) ($row['purpose'] ?? '')) ?: null,
            'payment_mode' => trim((string) ($row['payment_mode'] ?? '')) ?: null,
        ];
    }

    return $cache;
}

/** @param array<string,mixed>|null $meta */
function manage_data_receipt_source_label(?array $meta): string
{
    if ($meta === null) {
        return 'Orphan file';
    }
    $obn = strtoupper((string) ($meta['online_booking_no'] ?? ''));
    if (str_starts_with($obn, 'ODN-')) {
        return 'Online donation';
    }
    if (str_starts_with($obn, 'SUB-') || str_contains(strtoupper((string) ($meta['purpose'] ?? '')), 'SUBSCRIPTION')) {
        return 'Subscription';
    }
    if ($obn !== '') {
        return 'Online booking';
    }

    return 'CRM / office';
}

/**
 * @param list<string> $files Basenames only
 * @return array{ok:bool,message:string,deleted:int}
 */
function manage_data_delete_receipt_files(array $files, bool $allInFilter, ?string $search, ?string $from, ?string $to): array
{
    if ($allInFilter) {
        $list = manage_data_list_receipt_pdfs($search, $from, $to);
        $files = array_map(static fn (array $r): string => $r['file'], $list);
    }
    $files = array_values(array_unique(array_filter($files, static fn (string $f): bool => preg_match('/^[A-Za-z0-9._-]+\.pdf$/', $f) === 1)));
    if ($files === []) {
        return ['ok' => false, 'message' => 'No receipt files selected.', 'deleted' => 0];
    }

    $deleted = 0;
    $dir = manage_data_receipts_dir();
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_file($path) && @unlink($path)) {
            ++$deleted;
            $num = preg_replace('/\.pdf$/i', '', $file);
            if (is_string($num) && manage_data_table_exists(manage_data_pdo(), 'donations')) {
                manage_data_pdo()->prepare(
                    'UPDATE donations SET receipt_generated = 0 WHERE receipt_number = ?'
                )->execute([$num]);
            }
        }
    }

    return ['ok' => true, 'message' => "Deleted {$deleted} PDF file(s). Database rows kept; use Data tab to remove ledger rows.", 'deleted' => $deleted];
}

/** @param list<string> $files */
function manage_data_stream_receipt_zip(array $files): void
{
    $files = array_values(array_unique(array_filter($files, static fn (string $f): bool => preg_match('/^[A-Za-z0-9._-]+\.pdf$/', $f) === 1)));
    if ($files === [] || !class_exists(ZipArchive::class)) {
        http_response_code(400);
        echo 'No files or ZIP not supported on server.';
        exit;
    }

    $dir = manage_data_receipts_dir();
    $tmp = sys_get_temp_dir() . '/md_receipts_' . bin2hex(random_bytes(8)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'Could not create ZIP.';
        exit;
    }
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_file($path)) {
            $zip->addFile($path, $file);
        }
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="receipts-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . (string) filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}
