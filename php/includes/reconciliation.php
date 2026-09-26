<?php
declare(strict_types=1);

/**
 * Bank statement parsing (CSV, and Excel via the Windows tar unzip) plus
 * amount/date auto-matching within a 3-day window.
 */
function parse_statement_file(string $filepath): array
{
    $lower = strtolower($filepath);
    if (str_ends_with($lower, '.csv')) {
        $table = read_csv_table($filepath);
    } elseif (str_ends_with($lower, '.xlsx')) {
        $table = read_xlsx_table($filepath);
    } else {
        throw new RuntimeException('Upload a .csv or .xlsx file.');
    }
    if ($table === []) {
        throw new RuntimeException('The file has no rows.');
    }
    return rows_from_table($table);
}

/** @param list<list<string>> $table */
function rows_from_table(array $table): array
{
    $header = array_map(static fn (string $c): string => strtolower(trim($c)), array_shift($table) ?? []);
    $colMap = [];
    foreach ($header as $index => $name) {
        $mapped = match (true) {
            in_array($name, ['date', 'txn date', 'transaction date', 'value date'], true) => 'date',
            in_array($name, ['description', 'narration', 'particulars', 'details'], true) => 'description',
            in_array($name, ['amount', 'txn amount', 'transaction amount'], true) => 'amount',
            in_array($name, ['credit', 'credit amount', 'deposit'], true) => 'credit',
            in_array($name, ['debit', 'debit amount', 'withdrawal'], true) => 'debit',
            in_array($name, ['type', 'txn type', 'dr/cr'], true) => 'type',
            in_array($name, ['balance', 'closing balance'], true) => 'balance',
            default => null,
        };
        if ($mapped !== null) {
            $colMap[$mapped] = $index;
        }
    }
    if (!isset($colMap['date'])) {
        throw new RuntimeException('A Date column is required.');
    }

    $rows = [];
    foreach ($table as $cells) {
        $txnDate = parse_statement_date($cells[$colMap['date']] ?? '');
        if ($txnDate === null) {
            continue;
        }
        $description = trim((string) ($cells[$colMap['description'] ?? -1] ?? ''));
        $balance = null;
        if (isset($colMap['balance'])) {
            $rawBalance = normalize_amount($cells[$colMap['balance']] ?? '');
            $balance = $rawBalance;
        }

        if (isset($colMap['credit']) || isset($colMap['debit'])) {
            $credit = isset($colMap['credit']) ? normalize_amount($cells[$colMap['credit']] ?? '') : null;
            $debit = isset($colMap['debit']) ? normalize_amount($cells[$colMap['debit']] ?? '') : null;
            if ($credit !== null && $credit > 0) {
                $amount = $credit;
                $txnType = 'Credit';
            } elseif ($debit !== null && $debit > 0) {
                $amount = $debit;
                $txnType = 'Debit';
            } else {
                continue;
            }
        } else {
            if (!isset($colMap['amount'])) {
                continue;
            }
            $amount = normalize_amount($cells[$colMap['amount']] ?? '');
            if ($amount === null) {
                continue;
            }
            if (isset($colMap['type']) && trim((string) ($cells[$colMap['type']] ?? '')) !== '') {
                $typeStr = strtolower(trim((string) $cells[$colMap['type']]));
                $txnType = str_starts_with($typeStr, 'cr') || str_starts_with($typeStr, 'credit') || str_starts_with($typeStr, 'deposit')
                    ? 'Credit'
                    : 'Debit';
                $amount = abs($amount);
            } else {
                $txnType = $amount >= 0 ? 'Credit' : 'Debit';
                $amount = abs($amount);
            }
        }

        $rows[] = [
            'txn_date' => $txnDate,
            'description' => mb_substr($description, 0, 255),
            'amount' => round($amount, 2),
            'txn_type' => $txnType,
            'balance' => $balance === null ? null : round($balance, 2),
        ];
    }
    if ($rows === []) {
        throw new RuntimeException('No transaction rows could be read.');
    }
    return $rows;
}

/** @return list<list<string>> */
function read_csv_table(string $filepath): array
{
    $handle = fopen($filepath, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not open the CSV file.');
    }
    $first = fgets($handle);
    if ($first === false) {
        fclose($handle);
        return [];
    }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
    $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    $rows = [];
    $rows[] = str_getcsv($first, $delimiter);
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
            continue;
        }
        $rows[] = array_map(static fn ($cell): string => trim((string) $cell), $row);
    }
    fclose($handle);
    return $rows;
}

/** @return list<list<string>> */
function read_xlsx_table(string $filepath): array
{
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt_xlsx_' . bin2hex(random_bytes(4));
    if (!mkdir($tmp) && !is_dir($tmp)) {
        throw new RuntimeException('Could not prepare a temp folder for the Excel file.');
    }
    try {
        $command = 'tar -xf ' . escapeshellarg($filepath) . ' -C ' . escapeshellarg($tmp);
        exec($command, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('Could not read the Excel file. Save it as CSV and upload that.');
        }
        $shared = [];
        $sharedPath = $tmp . '/xl/sharedStrings.xml';
        if (is_file($sharedPath)) {
            $xml = simplexml_load_file($sharedPath);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    }
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                    $shared[] = $text;
                }
            }
        }
        $sheetPath = $tmp . '/xl/worksheets/sheet1.xml';
        if (!is_file($sheetPath)) {
            throw new RuntimeException('The workbook has no first worksheet.');
        }
        $sheet = simplexml_load_file($sheetPath);
        if ($sheet === false) {
            throw new RuntimeException('The worksheet could not be read.');
        }
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            $maxCol = 0;
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $col = column_index($ref);
                $maxCol = max($maxCol, $col);
                $type = (string) $cell['t'];
                $value = '';
                if ($type === 's') {
                    $index = (int) $cell->v;
                    $value = $shared[$index] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) $cell->is->t;
                } else {
                    $value = (string) $cell->v;
                }
                $cells[$col] = $value;
            }
            $line = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
        }
        return $rows;
    } finally {
        remove_tree($tmp);
    }
}

function column_index(string $cellRef): int
{
    if (preg_match('/^([A-Z]+)/', $cellRef, $m) !== 1) {
        return 0;
    }
    $index = 0;
    foreach (str_split($m[1]) as $letter) {
        $index = $index * 26 + (ord($letter) - 64);
    }
    return $index - 1;
}

function remove_tree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            remove_tree($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function normalize_amount(string $raw): ?float
{
    $raw = trim($raw);
    if ($raw === '' || strcasecmp($raw, 'null') === 0) {
        return null;
    }
    $negative = str_contains($raw, '(') && str_contains($raw, ')');
    $raw = str_replace([',', '₹', 'Rs.', 'Rs', ' '], '', $raw);
    $raw = str_replace(['(', ')'], '', $raw);
    if (!is_numeric($raw)) {
        return null;
    }
    $value = (float) $raw;
    return $negative ? -abs($value) : $value;
}

function parse_statement_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (is_numeric($value)) {
        $serial = (float) $value;
        if ($serial > 20000 && $serial < 80000) {
            $base = new DateTimeImmutable('1899-12-30');
            return $base->modify('+' . (int) $serial . ' days')->format('Y-m-d');
        }
    }
    foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'd M Y', 'd-M-Y', 'd-M-y'] as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        $clean = $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
        if ($dt instanceof DateTimeImmutable && $clean) {
            return $dt->format('Y-m-d');
        }
    }
    return null;
}

/** @param list<int> $transactionIds */
function reconcile_transactions(PDO $pdo, array $transactionIds): int
{
    $matched = 0;
    $findTxn = $pdo->prepare('SELECT * FROM bank_transactions WHERE id = ?');
    $findDonation = $pdo->prepare(
        "SELECT id FROM donations
         WHERE reconciled_bank_txn_id IS NULL
           AND amount IS NOT NULL
           AND ABS(amount - ?) < 0.01
           AND donation_date BETWEEN ? AND ?
         ORDER BY donation_date LIMIT 1"
    );
    $findExpense = $pdo->prepare(
        "SELECT id FROM expenses
         WHERE reconciled_bank_txn_id IS NULL
           AND ABS(amount - ?) < 0.01
           AND expense_date BETWEEN ? AND ?
         ORDER BY expense_date LIMIT 1"
    );
    $markDonation = $pdo->prepare(
        "UPDATE bank_transactions SET reconciled_status='Matched', matched_donation_id=? WHERE id=?"
    );
    $linkDonation = $pdo->prepare('UPDATE donations SET reconciled_bank_txn_id=? WHERE id=?');
    $markExpense = $pdo->prepare(
        "UPDATE bank_transactions SET reconciled_status='Matched', matched_expense_id=? WHERE id=?"
    );
    $linkExpense = $pdo->prepare('UPDATE expenses SET reconciled_bank_txn_id=? WHERE id=?');

    foreach ($transactionIds as $txnId) {
        $findTxn->execute([$txnId]);
        $txn = $findTxn->fetch();
        if ($txn === false) {
            continue;
        }
        $txnDate = new DateTimeImmutable((string) $txn['txn_date']);
        $windowStart = $txnDate->modify('-3 days')->format('Y-m-d');
        $windowEnd = $txnDate->modify('+3 days')->format('Y-m-d');
        $amount = (float) $txn['amount'];

        if ($txn['txn_type'] === 'Credit') {
            $findDonation->execute([$amount, $windowStart, $windowEnd]);
            $match = $findDonation->fetch();
            if ($match !== false) {
                $markDonation->execute([(int) $match['id'], $txnId]);
                $linkDonation->execute([$txnId, (int) $match['id']]);
                $matched++;
            }
            continue;
        }

        $findExpense->execute([$amount, $windowStart, $windowEnd]);
        $match = $findExpense->fetch();
        if ($match !== false) {
            $markExpense->execute([(int) $match['id'], $txnId]);
            $linkExpense->execute([$txnId, (int) $match['id']]);
            $matched++;
        }
    }
    return $matched;
}
