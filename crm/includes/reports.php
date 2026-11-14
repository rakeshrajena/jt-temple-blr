<?php
declare(strict_types=1);

function dashboard_summary(PDO $pdo): array
{
    $totalDonations = (float) db_value('SELECT COALESCE(SUM(amount),0) FROM donations WHERE amount IS NOT NULL');
    $totalExpenses = (float) db_value(
        "SELECT COALESCE(SUM(e.amount),0) FROM expenses e
         JOIN approvals a ON a.subject_type = 'expense' AND a.subject_id = e.id AND a.status = 'Approved'"
    );
    return [
        'total_donations' => $totalDonations,
        'total_expenses' => $totalExpenses,
        'net_balance' => $totalDonations - $totalExpenses,
        'donor_count' => (int) db_value('SELECT COUNT(DISTINCT donor_id) FROM donations'),
        'pending_receipts' => (int) db_value('SELECT COUNT(*) FROM donations WHERE receipt_generated=0'),
        'low_stock' => (int) db_value('SELECT COUNT(*) FROM food_items WHERE current_stock <= minimum_threshold'),
        'unmatched_txns' => (int) db_value("SELECT COUNT(*) FROM bank_transactions WHERE reconciled_status='Unmatched'"),
        'inventory_count' => (int) db_value('SELECT COUNT(*) FROM inventory_items'),
        'vastra_count' => (float) db_value('SELECT COALESCE(SUM(quantity),0) FROM vastra_items'),
        'active_subscribers' => (int) db_value("SELECT COUNT(*) FROM subscribers WHERE status='Active'"),
        'pending_invoices' => (int) db_value("SELECT COUNT(*) FROM subscription_invoices WHERE status IN ('Sent','Pending','Overdue')"),
        'mrr' => (float) db_value("SELECT COALESCE(SUM(plan_amount),0) FROM subscribers WHERE status='Active' AND frequency='Monthly'"),
    ];
}

function donation_report(?string $start, ?string $end): array
{
    $sql = "SELECT d.id, don.name AS donor_name, d.donation_type, d.amount, d.purpose,
                   d.donation_date, d.payment_mode, d.receipt_number, d.receipt_generated
            FROM donations d JOIN donors don ON d.donor_id = don.id WHERE 1=1";
    $params = [];
    if ($start !== null) {
        $sql .= ' AND d.donation_date >= ?';
        $params[] = $start;
    }
    if ($end !== null) {
        $sql .= ' AND d.donation_date <= ?';
        $params[] = $end;
    }
    $sql .= ' ORDER BY d.donation_date DESC';
    return db_all($sql, $params);
}

function expense_report(?string $start, ?string $end): array
{
    $sql = "SELECT e.* FROM expenses e
            JOIN approvals a ON a.subject_type = 'expense' AND a.subject_id = e.id AND a.status = 'Approved'
            WHERE 1=1";
    $params = [];
    if ($start !== null) {
        $sql .= ' AND e.expense_date >= ?';
        $params[] = $start;
    }
    if ($end !== null) {
        $sql .= ' AND e.expense_date <= ?';
        $params[] = $end;
    }
    $sql .= ' ORDER BY e.expense_date DESC';
    return db_all($sql, $params);
}

function inventory_report(): array
{
    return db_all('SELECT * FROM inventory_items ORDER BY category, name');
}

function food_stock_report(): array
{
    return db_all('SELECT * FROM food_items ORDER BY name');
}

function vastra_report(): array
{
    return db_all('SELECT * FROM vastra_items ORDER BY deity_name, item_name');
}

function reconciliation_report(?string $start = null, ?string $end = null): array
{
    $sql = "SELECT bt.*, u.filename, u.upload_date,
                d.receipt_number AS donation_receipt, don.name AS donor_name,
                e.description AS expense_description, e.category AS expense_category
         FROM bank_transactions bt
         JOIN bank_statement_uploads u ON bt.upload_batch_id = u.id
         LEFT JOIN donations d ON bt.matched_donation_id = d.id
         LEFT JOIN donors don ON d.donor_id = don.id
         LEFT JOIN expenses e ON bt.matched_expense_id = e.id
         WHERE 1=1";
    $params = [];
    if ($start !== null) {
        $sql .= ' AND bt.txn_date >= ?';
        $params[] = $start;
    }
    if ($end !== null) {
        $sql .= ' AND bt.txn_date <= ?';
        $params[] = $end;
    }
    $sql .= ' ORDER BY bt.txn_date DESC';
    return db_all($sql, $params);
}

/** @return list<string> */
function report_date_filtered_types(): array
{
    return ['donations', 'expenses', 'reconciliation'];
}

function report_uses_date_filter(string $type): bool
{
    return in_array($type, report_date_filtered_types(), true);
}

/** @return array{start: ?string, end: ?string} */
function report_resolve_period(?string $startRaw, ?string $endRaw, bool $applyDefaultRange): array
{
    if ($applyDefaultRange) {
        return report_default_last_month();
    }
    return [
        'start' => optional_date($startRaw),
        'end' => optional_date($endRaw),
    ];
}

/** @return array{start: string, end: string} */
function report_default_last_month(): array
{
    $end = new DateTimeImmutable('today');
    $start = $end->modify('-1 month');

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
    ];
}

function report_period_label(?string $start, ?string $end, bool $dateFiltered): string
{
    if (!$dateFiltered) {
        return 'As of ' . date('d-M-Y');
    }
    if ($start === null && $end === null) {
        return 'All dates';
    }

    return ($start ?? 'earliest') . ' to ' . ($end ?? 'today');
}

/** @return list<array<string,mixed>> */
function report_fetch_rows(string $type, ?string $start, ?string $end): array
{
    return match ($type) {
        'donations' => donation_report($start, $end),
        'expenses' => expense_report($start, $end),
        'inventory' => inventory_report(),
        'food' => food_stock_report(),
        'vastra' => vastra_report(),
        'reconciliation' => reconciliation_report($start, $end),
        default => [],
    };
}

/** @return array{headers: list<string>, rows: list<list<string>>} */
function report_export_table(string $type, array $data): array
{
    return match ($type) {
        'donations' => report_export_donations($data),
        'expenses' => report_export_expenses($data),
        'inventory' => report_export_inventory($data),
        'food' => report_export_food($data),
        'vastra' => report_export_vastra($data),
        'reconciliation' => report_export_reconciliation($data),
        default => ['headers' => [], 'rows' => []],
    };
}

/** @param list<array<string,mixed>> $data @return array{headers: list<string>, rows: list<list<string>>} */
function report_export_donations(array $data): array
{
    $headers = ['Date', 'Donor', 'Type', 'Amount', 'Purpose', 'Payment', 'Receipt No'];
    $rows = [];
    foreach ($data as $d) {
        $rows[] = [
            (string) $d['donation_date'],
            (string) $d['donor_name'],
            (string) $d['donation_type'],
            money_or_dash($d['amount'], 2),
            dash($d['purpose']),
            (string) $d['payment_mode'],
            (string) ($d['receipt_number'] ?? ''),
        ];
    }

    return ['headers' => $headers, 'rows' => $rows];
}

/** @param list<array<string,mixed>> $data @return array{headers: list<string>, rows: list<list<string>>} */
function report_export_expenses(array $data): array
{
    $headers = ['Date', 'Category', 'Description', 'Paid to', 'Amount', 'Payment'];
    $rows = [];
    foreach ($data as $row) {
        $rows[] = [
            (string) $row['expense_date'],
            (string) $row['category'],
            dash($row['description']),
            dash($row['paid_to']),
            money($row['amount'], 2),
            (string) $row['payment_mode'],
        ];
    }

    return ['headers' => $headers, 'rows' => $rows];
}

/** @param list<array<string,mixed>> $data @return array{headers: list<string>, rows: list<list<string>>} */
function report_export_inventory(array $data): array
{
    $headers = ['Category', 'Name', 'Quantity', 'Unit', 'Condition', 'Location', 'Source', 'Added'];
    $rows = [];
    foreach ($data as $i) {
        $rows[] = [
            (string) $i['category'],
            (string) $i['name'],
            (string) $i['quantity'],
            (string) $i['unit'],
            (string) $i['item_condition'],
            dash($i['location']),
            (string) $i['source'],
            (string) $i['added_date'],
        ];
    }

    return ['headers' => $headers, 'rows' => $rows];
}

/** @param list<array<string,mixed>> $data @return array{headers: list<string>, rows: list<list<string>>} */
function report_export_food(array $data): array
{
    $headers = ['Item', 'Current stock', 'Unit', 'Low threshold', 'Status', 'Last updated'];
    $rows = [];
    foreach ($data as $f) {
        $isLow = (float) $f['current_stock'] <= (float) $f['minimum_threshold'];
        $rows[] = [
            (string) $f['name'],
            (string) $f['current_stock'],
            (string) $f['unit'],
            (string) $f['minimum_threshold'],
            $isLow ? 'Low stock' : 'OK',
            (string) $f['last_updated'],
        ];
    }

    return ['headers' => $headers, 'rows' => $rows];
}

/** @param list<array<string,mixed>> $data @return array{headers: list<string>, rows: list<list<string>>} */
function report_export_vastra(array $data): array
{
    $headers = ['Deity', 'Item', 'Color', 'Quantity', 'Source', 'Status', 'Added'];
    $rows = [];
    foreach ($data as $v) {
        $rows[] = [
            (string) $v['deity_name'],
            (string) $v['item_name'],
            dash($v['color']),
            (string) $v['quantity'],
            (string) $v['source'],
            (string) $v['status'],
            (string) $v['date_added'],
        ];
    }

    return ['headers' => $headers, 'rows' => $rows];
}

/** @param list<array<string,mixed>> $data @return array{headers: list<string>, rows: list<list<string>>} */
function report_export_reconciliation(array $data): array
{
    $headers = ['Date', 'Description', 'Amount', 'Type', 'Status', 'Matched to'];
    $rows = [];
    foreach ($data as $t) {
        $matched = '—';
        if (!empty($t['donor_name'])) {
            $matched = 'Donation — ' . $t['donor_name'];
            if (!empty($t['donation_receipt'])) {
                $matched .= ' (' . $t['donation_receipt'] . ')';
            }
        } elseif (!empty($t['expense_description'])) {
            $matched = 'Expense — ' . $t['expense_description'];
        }
        $rows[] = [
            (string) $t['txn_date'],
            (string) $t['description'],
            money($t['amount'], 2),
            (string) $t['txn_type'],
            (string) $t['reconciled_status'],
            $matched,
        ];
    }

    return ['headers' => $headers, 'rows' => $rows];
}

function report_export_filename(string $type, string $ext, ?string $start, ?string $end): string
{
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($type)) ?? $type;
    $parts = [trim($slug, '-')];
    if ($start !== null || $end !== null) {
        $parts[] = $start ?? 'start';
        $parts[] = $end ?? 'end';
    } else {
        $parts[] = date('Y-m-d');
    }

    return implode('_', $parts) . '.' . $ext;
}

/** @param list<string> $headers @param list<list<string>> $rows */
function report_build_csv(array $headers, array $rows): string
{
    $fh = fopen('php://temp', 'r+');
    if ($fh === false) {
        throw new RuntimeException('Could not build CSV.');
    }
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, $headers);
    foreach ($rows as $row) {
        fputcsv($fh, $row);
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    if ($csv === false) {
        throw new RuntimeException('Could not read CSV.');
    }

    return $csv;
}

function report_zip_available(): bool
{
    return class_exists(ZipArchive::class);
}

/** @param list<string> $headers @param list<list<string>> $rows */
function report_write_xlsx_zip(string $path, array $headers, array $rows): void
{
    if (!report_zip_available()) {
        throw new RuntimeException('ZipArchive is not available.');
    }
    $sheetRows = [$headers, ...$rows];
    $sheetXml = report_xlsx_sheet_xml($sheetRows);
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not build the Excel file.');
    }
    $zip->addFromString('[Content_Types].xml', report_xlsx_content_types());
    $zip->addFromString('_rels/.rels', report_xlsx_root_rels());
    $zip->addFromString('xl/_rels/workbook.xml.rels', report_xlsx_workbook_rels());
    $zip->addFromString('xl/workbook.xml', report_xlsx_workbook());
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();
}

/** @param list<string> $headers @param list<list<string>> $rows */
function report_build_excel_xml(array $headers, array $rows): string
{
    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<?mso-application progid="Excel.Sheet"?>' . "\n"
        . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
        . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n"
        . '<Worksheet ss:Name="Report"><Table>' . "\n";
    $out .= '<Row>';
    foreach ($headers as $header) {
        $out .= '<Cell><Data ss:Type="String">' . $esc($header) . '</Data></Cell>';
    }
    $out .= '</Row>' . "\n";
    foreach ($rows as $row) {
        $out .= '<Row>';
        foreach ($row as $cell) {
            $out .= '<Cell><Data ss:Type="String">' . $esc((string) $cell) . '</Data></Cell>';
        }
        $out .= '</Row>' . "\n";
    }
    $out .= '</Table></Worksheet></Workbook>';

    return $out;
}

/**
 * @param list<string> $headers
 * @param list<list<string>> $rows
 * @return array{path: ?string, body: ?string, filename: string, mime: string}
 */
function report_excel_download_payload(array $headers, array $rows, string $filenameXlsx): array
{
    if (report_zip_available()) {
        $tmp = APP_ROOT . '/storage/uploads/report_' . bin2hex(random_bytes(6)) . '.xlsx';
        report_write_xlsx_zip($tmp, $headers, $rows);

        return [
            'path' => $tmp,
            'body' => null,
            'filename' => $filenameXlsx,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }
    $filename = preg_replace('/\.xlsx$/i', '.xls', $filenameXlsx) ?? $filenameXlsx . '.xls';

    return [
        'path' => null,
        'body' => report_build_excel_xml($headers, $rows),
        'filename' => $filename,
        'mime' => 'application/vnd.ms-excel; charset=UTF-8',
    ];
}

/** @param list<list<string>> $rows */
function report_xlsx_sheet_xml(array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    $rowNum = 1;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $rowNum . '">';
        $colNum = 0;
        foreach ($row as $cell) {
            $ref = report_xlsx_column_name($colNum) . $rowNum;
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>'
                . report_xml_text((string) $cell)
                . '</t></is></c>';
            $colNum++;
        }
        $xml .= '</row>';
        $rowNum++;
    }
    $xml .= '</sheetData></worksheet>';

    return $xml;
}

function report_xml_text(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function report_xlsx_column_name(int $index): string
{
    $name = '';
    $n = $index + 1;
    while ($n > 0) {
        $n--;
        $name = chr(65 + ($n % 26)) . $name;
        $n = (int) floor($n / 26);
    }

    return $name;
}

function report_xlsx_content_types(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';
}

function report_xlsx_root_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function report_xlsx_workbook_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';
}

function report_xlsx_workbook(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>';
}

/** @param list<string> $headers @param list<list<string>> $rows */
function report_build_pdf(string $title, string $periodLabel, array $headers, array $rows): string
{
    $pageW = 842.0;
    $pageH = 595.0;
    $margin = 32.0;
    $pdf = new PdfDocument($pageW, $pageH);
    $lineH = 11.0;
    $fontSize = 7.5;
    $headerSize = 8.0;
    $colCount = max(1, count($headers));
    $tableW = $pageW - (2 * $margin);
    $colW = $tableW / $colCount;

    $startPage = static function () use ($pdf, $margin, $pageH, $title, $periodLabel): float {
        $y = $pageH - $margin;
        $pdf->text($margin, $y, PdfDocument::plainText(app_display_name()), 12, 'F2');
        $pdf->text($margin, $y - 16, PdfDocument::plainText($title), 10, 'F2');
        $pdf->text($margin, $y - 30, PdfDocument::plainText($periodLabel), 8, 'F1');
        return $y - 46;
    };

    $y = $startPage();
    $drawHeader = static function () use ($pdf, $headers, $margin, $colW, $headerSize, &$y): void {
        $x = $margin;
        foreach ($headers as $header) {
            $pdf->text($x + 2, $y, PdfDocument::plainText($header), $headerSize, 'F2');
            $x += $colW;
        }
        $pdf->line($margin, $y - 4, $margin + ($colW * count($headers)), $y - 4);
        $y -= 14;
    };
    $drawHeader();

    foreach ($rows as $row) {
        $linesPerCol = [];
        $maxLines = 1;
        foreach ($row as $i => $cell) {
            $maxCellW = max(24.0, $colW - 4);
            $wrapped = $pdf->wrap(PdfDocument::plainText((string) $cell), $fontSize, $maxCellW, false);
            if ($wrapped === []) {
                $wrapped = [''];
            }
            $linesPerCol[$i] = $wrapped;
            $maxLines = max($maxLines, count($wrapped));
        }
        $rowH = $maxLines * $lineH + 4;
        if ($y - $rowH < $margin + 20) {
            $pdf->addPage();
            $y = $startPage();
            $drawHeader();
        }
        $x = $margin;
        foreach ($row as $i => $cell) {
            unset($cell);
            $colLines = $linesPerCol[$i] ?? [''];
            $lineY = $y;
            foreach ($colLines as $line) {
                $pdf->text($x + 2, $lineY, PdfDocument::plainText($line), $fontSize, 'F1');
                $lineY -= $lineH;
            }
            $x += $colW;
        }
        $y -= $rowH;
    }

    return $pdf->render();
}

/** @return array<string, string> */
function report_filters_from_request(string $type): array
{
    $text = static function (string $key): string {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
    };
    $filters = ['q' => $text('q')];
    match ($type) {
        'donations' => $filters += ['dtype' => $text('dtype'), 'pay' => $text('pay')],
        'expenses' => $filters += ['cat' => $text('cat'), 'pay' => $text('pay')],
        'reconciliation' => $filters += ['status' => $text('status'), 'ttype' => $text('ttype')],
        'food' => $filters += ['stock' => $text('stock') !== '' ? $text('stock') : 'all'],
        'inventory' => $filters += ['cat' => $text('cat')],
        'vastra' => $filters += ['deity' => $text('deity'), 'vstatus' => $text('vstatus')],
        default => null,
    };

    return $filters;
}

/** @param array<string, string> $filters */
function report_filters_active(array $filters): bool
{
    foreach ($filters as $key => $value) {
        if ($key === 'stock' && $value === 'all') {
            continue;
        }
        if ($value !== '') {
            return true;
        }
    }

    return false;
}

/** @param array<string, string> $filters @return array<string, string> */
function report_filter_query(array $filters): array
{
    $query = [];
    foreach ($filters as $key => $value) {
        if ($value === '' || ($key === 'stock' && $value === 'all')) {
            continue;
        }
        $query[$key] = $value;
    }

    return $query;
}

/** @param list<array<string,mixed>> $data @return array<string, list<string>> */
function report_filter_options(string $type, array $data): array
{
    $uniq = static function (array $values): array {
        $values = array_values(array_unique(array_filter(array_map(
            static fn (mixed $v): string => trim((string) $v),
            $values
        ), static fn (string $v): bool => $v !== '')));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return $values;
    };

    return match ($type) {
        'donations' => [
            'dtype' => $uniq(array_column($data, 'donation_type')),
            'pay' => $uniq(array_column($data, 'payment_mode')),
        ],
        'expenses' => [
            'cat' => $uniq(array_column($data, 'category')),
            'pay' => $uniq(array_column($data, 'payment_mode')),
        ],
        'reconciliation' => [
            'status' => $uniq(array_column($data, 'reconciled_status')),
            'ttype' => $uniq(array_column($data, 'txn_type')),
        ],
        'inventory' => [
            'cat' => $uniq(array_column($data, 'category')),
        ],
        'vastra' => [
            'deity' => $uniq(array_column($data, 'deity_name')),
            'vstatus' => $uniq(array_column($data, 'status')),
        ],
        default => [],
    };
}

/** @param array<string, mixed> $row */
function report_row_search_blob(string $type, array $row): string
{
    $parts = match ($type) {
        'donations' => [
            $row['donor_name'] ?? '',
            $row['donation_type'] ?? '',
            $row['purpose'] ?? '',
            $row['payment_mode'] ?? '',
            $row['receipt_number'] ?? '',
        ],
        'expenses' => [
            $row['category'] ?? '',
            $row['description'] ?? '',
            $row['paid_to'] ?? '',
            $row['payment_mode'] ?? '',
        ],
        'reconciliation' => [
            $row['description'] ?? '',
            $row['txn_type'] ?? '',
            $row['reconciled_status'] ?? '',
            $row['donor_name'] ?? '',
            $row['expense_description'] ?? '',
        ],
        'inventory' => [$row['category'] ?? '', $row['name'] ?? '', $row['location'] ?? '', $row['source'] ?? ''],
        'food' => [$row['name'] ?? ''],
        'vastra' => [$row['deity_name'] ?? '', $row['item_name'] ?? '', $row['color'] ?? '', $row['status'] ?? ''],
        default => array_values($row),
    };

    return mb_strtolower(implode(' ', array_map(static fn ($v) => (string) $v, $parts)));
}

/** @param array<string, mixed> $row @param array<string, string> $filters */
function report_row_matches(string $type, array $row, array $filters): bool
{
    $q = $filters['q'] ?? '';
    if ($q !== '') {
        $words = preg_split('/\s+/', mb_strtolower($q)) ?: [];
        $blob = report_row_search_blob($type, $row);
        foreach ($words as $word) {
            if ($word !== '' && !str_contains($blob, $word)) {
                return false;
            }
        }
    }

    return match ($type) {
        'donations' => report_match_select($filters['dtype'] ?? '', (string) ($row['donation_type'] ?? ''))
            && report_match_select($filters['pay'] ?? '', (string) ($row['payment_mode'] ?? '')),
        'expenses' => report_match_select($filters['cat'] ?? '', (string) ($row['category'] ?? ''))
            && report_match_select($filters['pay'] ?? '', (string) ($row['payment_mode'] ?? '')),
        'reconciliation' => report_match_select($filters['status'] ?? '', (string) ($row['reconciled_status'] ?? ''))
            && report_match_select($filters['ttype'] ?? '', (string) ($row['txn_type'] ?? '')),
        'food' => report_match_food_stock($filters['stock'] ?? 'all', $row),
        'inventory' => report_match_select($filters['cat'] ?? '', (string) ($row['category'] ?? '')),
        'vastra' => report_match_select($filters['deity'] ?? '', (string) ($row['deity_name'] ?? ''))
            && report_match_select($filters['vstatus'] ?? '', (string) ($row['status'] ?? '')),
        default => true,
    };
}

function report_match_select(string $filter, string $value): bool
{
    return $filter === '' || strcasecmp($filter, $value) === 0;
}

/** @param array<string, mixed> $row */
function report_match_food_stock(string $filter, array $row): bool
{
    if ($filter === '' || $filter === 'all') {
        return true;
    }
    $isLow = (float) ($row['current_stock'] ?? 0) <= (float) ($row['minimum_threshold'] ?? 0);

    return match ($filter) {
        'low' => $isLow,
        'ok' => !$isLow,
        default => true,
    };
}

/** @param list<array<string,mixed>> $data @param array<string, string> $filters @return list<array<string,mixed>> */
function report_filter_data(string $type, array $data, array $filters): array
{
    if (!report_filters_active($filters)) {
        return $data;
    }
    $out = [];
    foreach ($data as $row) {
        if (report_row_matches($type, $row, $filters)) {
            $out[] = $row;
        }
    }

    return $out;
}

/** @param array<string, string> $filters */
function report_clear_filters_url(string $type, ?string $start, ?string $end, bool $dateFiltered): string
{
    $query = [];
    if ($dateFiltered) {
        if ($start !== null && $start !== '') {
            $query['start'] = $start;
        }
        if ($end !== null && $end !== '') {
            $query['end'] = $end;
        }
    }

    return url('reports/' . $type, $query);
}

function report_export_query(string $type, ?string $start, ?string $end, string $format, array $filters = []): array
{
    $format = strtolower($format);
    if (!in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
        throw new InvalidArgumentException('Choose csv, xlsx, or pdf.');
    }
    $dateFiltered = report_uses_date_filter($type);
    $raw = report_fetch_rows($type, $dateFiltered ? $start : null, $dateFiltered ? $end : null);
    $data = report_filter_data($type, $raw, $filters);
    $table = report_export_table($type, $data);
    $periodLabel = report_period_label($dateFiltered ? $start : null, $dateFiltered ? $end : null, $dateFiltered);
    $titles = [
        'donations' => 'Donation Report',
        'expenses' => 'Expense Report',
        'inventory' => 'Inventory Report',
        'food' => 'Food Stock Report',
        'vastra' => 'Deity Vastra Report',
        'reconciliation' => 'Bank Reconciliation Report',
    ];
    $title = $titles[$type] ?? 'Report';

    return [
        'format' => $format,
        'title' => $title,
        'period' => $periodLabel,
        'filename' => report_export_filename($type, $format === 'xlsx' ? 'xlsx' : $format, $dateFiltered ? $start : null, $dateFiltered ? $end : null),
        'headers' => $table['headers'],
        'rows' => $table['rows'],
    ];
}

/** @param array<string, string> $filters */
function report_download_url(string $type, string $format, ?string $start, ?string $end, array $filters = []): string
{
    $query = ['format' => $format] + report_filter_query($filters);
    if ($start !== null && $start !== '') {
        $query['start'] = $start;
    }
    if ($end !== null && $end !== '') {
        $query['end'] = $end;
    }

    return url('reports/' . $type . '/export', $query);
}

function optional_date(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $value) {
        return null;
    }
    return $value;
}
