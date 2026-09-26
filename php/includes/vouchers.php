<?php
declare(strict_types=1);

/**
 * @return array{
 *   error: ?string,
 *   cheque_number: ?string,
 *   cheque_date: ?string,
 *   cheque_cleared: int,
 *   upi_reference: ?string
 * }
 */
function normalize_payment_instrument(
    string $mode,
    string $upi,
    string $chequeNumber,
    string $chequeDate,
    bool $cleared
): array {
    $empty = [
        'error' => null,
        'cheque_number' => null,
        'cheque_date' => null,
        'cheque_cleared' => 0,
        'upi_reference' => null,
    ];
    $upi = strtoupper((string) preg_replace('/\s+/', '', $upi));
    $chequeNumber = strtoupper((string) preg_replace('/\s+/', '', $chequeNumber));
    if ($mode === 'UPI') {
        if (preg_match('/^[A-Z0-9][A-Z0-9._-]{5,63}$/', $upi) !== 1) {
            $empty['error'] = 'Enter the UPI transaction id, at least 6 characters, with no spaces.';
            return $empty;
        }
        $empty['upi_reference'] = $upi;
        return $empty;
    }
    if ($mode === 'Cheque') {
        if (preg_match('/^[A-Z0-9]{4,30}$/', $chequeNumber) !== 1) {
            $empty['error'] = 'Enter the cheque number, 4 to 30 letters or digits.';
            return $empty;
        }
        $date = valid_book_date($chequeDate);
        if ($date === null) {
            $empty['error'] = 'Enter the cheque date.';
            return $empty;
        }
        $empty['cheque_number'] = $chequeNumber;
        $empty['cheque_date'] = $date;
        $empty['cheque_cleared'] = $cleared ? 1 : 0;
        return $empty;
    }
    return $empty;
}

function payment_trail(array $row): string
{
    $parts = [];
    $voucher = trim((string) ($row['voucher_number'] ?? ''));
    if ($voucher !== '') {
        $parts[] = $voucher;
    }
    $cheque = trim((string) ($row['cheque_number'] ?? ''));
    if ($cheque !== '') {
        $parts[] = 'Chq ' . $cheque . ((int) ($row['cheque_cleared'] ?? 0) === 1 ? ' cleared' : '');
    }
    $upi = trim((string) ($row['upi_reference'] ?? ''));
    if ($upi !== '') {
        $parts[] = 'UPI ' . $upi;
    }
    return implode(' · ', $parts);
}

function posted_bill_error(mixed $file): ?string
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return 'The bill upload did not complete.';
    }
    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return 'The bill is larger than 2 MB.';
    }
    if (bill_extension((string) ($file['name'] ?? '')) === null) {
        return 'Attach the bill as a PDF, JPEG, or PNG.';
    }
    return null;
}

function store_bill_upload(mixed $file, string $voucherNumber): ?string
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $error = posted_bill_error($file);
    if ($error !== null) {
        throw new RuntimeException($error);
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('The bill upload did not complete.');
    }
    $ext = bill_extension((string) ($file['name'] ?? ''));
    $name = $voucherNumber . '.' . $ext;
    $dest = APP_ROOT . '/storage/vouchers/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('The bill could not be saved.');
    }
    return $name;
}

function bill_extension(string $filename): ?string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) ? $ext : null;
}

function reference_is_mentioned(string $description, string $reference): bool
{
    $reference = strtoupper((string) preg_replace('/\s+/', '', $reference));
    if (strlen($reference) < 6) {
        return false;
    }
    $haystack = strtoupper((string) preg_replace('/\s+/', '', $description));
    return $haystack !== '' && str_contains($haystack, $reference);
}

/**
 * Prefer a cheque number or UPI id written in the narration, within 90 days.
 * Otherwise use the same amount inside 3 days.
 *
 * @param list<array<string, mixed>> $candidates
 */
function choose_reconcile_match(array $candidates, string $description, string $txnDate): ?int
{
    if (valid_book_date($txnDate) === null) {
        return null;
    }
    $txn = new DateTimeImmutable($txnDate);
    $referenced = [];
    $window = [];
    foreach ($candidates as $candidate) {
        $entryDate = valid_book_date((string) ($candidate['entry_date'] ?? ''));
        if ($entryDate === null) {
            continue;
        }
        $days = (int) $txn->diff(new DateTimeImmutable($entryDate))->days;
        $hit = [
            'id' => (int) $candidate['id'],
            'days' => $days,
            'date' => $entryDate,
        ];
        $mentioned = reference_is_mentioned($description, (string) ($candidate['upi_reference'] ?? ''))
            || reference_is_mentioned($description, (string) ($candidate['cheque_number'] ?? ''));
        if ($mentioned && $days <= 90) {
            $referenced[] = $hit;
        }
        if ($days <= 3) {
            $window[] = $hit;
        }
    }
    $pool = $referenced !== [] ? $referenced : $window;
    if ($pool === []) {
        return null;
    }
    usort($pool, static function (array $a, array $b): int {
        return [$a['days'], $a['date'], $a['id']] <=> [$b['days'], $b['date'], $b['id']];
    });
    return $pool[0]['id'];
}

function next_voucher_number(PDO $pdo, string $expenseDate): string
{
    $year = substr(financial_year_start(financial_year_label($expenseDate)), 0, 4);
    $stmt = $pdo->prepare(
        'SELECT voucher_number FROM expenses WHERE voucher_number LIKE ? ORDER BY voucher_number DESC LIMIT 1'
    );
    $stmt->execute(["VCH-{$year}-%"]);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if (is_string($last) && preg_match('/(\d+)$/', $last, $m) === 1) {
        $seq = (int) $m[1] + 1;
    }
    return sprintf('VCH-%s-%04d', $year, $seq);
}

function backfill_voucher_numbers(PDO $pdo): void
{
    $rows = $pdo->query(
        'SELECT id, expense_date FROM expenses WHERE voucher_number IS NULL OR voucher_number = \'\' ORDER BY expense_date, id'
    )->fetchAll();
    $update = $pdo->prepare('UPDATE expenses SET voucher_number = ? WHERE id = ?');
    foreach ($rows as $row) {
        $update->execute([
            next_voucher_number($pdo, (string) $row['expense_date']),
            (int) $row['id'],
        ]);
    }
}

function ensure_payment_columns(PDO $pdo): void
{
    ensure_column($pdo, 'donations', 'cheque_number', 'VARCHAR(30) NULL');
    ensure_column($pdo, 'donations', 'cheque_date', 'DATE NULL');
    ensure_column($pdo, 'donations', 'cheque_cleared', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'donations', 'upi_reference', 'VARCHAR(64) NULL');
    ensure_column($pdo, 'expenses', 'voucher_number', 'VARCHAR(30) NULL');
    ensure_column($pdo, 'expenses', 'cheque_number', 'VARCHAR(30) NULL');
    ensure_column($pdo, 'expenses', 'cheque_date', 'DATE NULL');
    ensure_column($pdo, 'expenses', 'cheque_cleared', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'expenses', 'upi_reference', 'VARCHAR(64) NULL');
    ensure_column($pdo, 'expenses', 'bill_filename', 'VARCHAR(255) NULL');
    $index = $pdo->query("SHOW INDEX FROM expenses WHERE Key_name = 'uq_expense_voucher'")->fetch();
    if ($index === false) {
        $pdo->exec('ALTER TABLE expenses ADD UNIQUE KEY uq_expense_voucher (voucher_number)');
    }
    backfill_voucher_numbers($pdo);
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }
}
