<?php
declare(strict_types=1);

/** @return list<string> */
function coupon_amount_types(): array
{
    $types = [];
    foreach (selection_pairs('donation_types') as $type) {
        $value = (string) ($type['value'] ?? '');
        if ($value !== '' && !in_array($value, ['Food', 'Vastra', 'Inventory'], true)) {
            $types[] = $value;
        }
    }
    return $types === [] ? ['Cash'] : $types;
}

/**
 * @param array<string, mixed> $input
 * @return array{error: ?string, values: array<string, ?string>}
 */
function coupon_gift_storage(array $input): array
{
    $empty = [
        'donor_name' => null,
        'donor_phone' => null,
        'donor_email' => null,
        'donor_address' => null,
        'donor_pan' => null,
        'donation_type' => null,
        'payment_mode' => null,
        'purpose' => null,
        'upi_reference' => null,
        'cheque_number' => null,
        'cheque_date' => null,
    ];
    $name = trim((string) ($input['donor_name'] ?? ''));
    $phone = trim((string) ($input['donor_phone'] ?? ''));
    $email = trim((string) ($input['donor_email'] ?? ''));
    $address = trim((string) ($input['donor_address'] ?? ''));
    $pan = strtoupper(trim((string) ($input['donor_pan'] ?? '')));
    $type = trim((string) ($input['donation_type'] ?? ''));
    $payment = trim((string) ($input['payment_mode'] ?? ''));
    $purpose = trim((string) ($input['purpose'] ?? ''));
    $upi = strtoupper((string) preg_replace('/\s+/', '', (string) ($input['upi_reference'] ?? '')));
    $cheque = strtoupper((string) preg_replace('/\s+/', '', (string) ($input['cheque_number'] ?? '')));
    $chequeDate = trim((string) ($input['cheque_date'] ?? ''));
    if ($name === '' && ($phone !== '' || $email !== '' || $address !== '' || $pan !== '')) {
        return ['error' => 'Enter the devotee name, or leave the devotee fields blank.', 'values' => $empty];
    }
    if (mb_strlen($name) > 150) {
        return ['error' => 'The devotee name is too long.', 'values' => $empty];
    }
    $digits = devotee_phone_digits($phone);
    if ($phone !== '' && (strlen($digits) < 8 || strlen($digits) > 15)) {
        return ['error' => 'Enter a phone number of 8 to 15 digits.', 'values' => $empty];
    }
    if (mb_strlen($email) > 120 || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        return ['error' => 'The email address is not valid.', 'values' => $empty];
    }
    if (mb_strlen($address) > 500) {
        return ['error' => 'The address is too long.', 'values' => $empty];
    }
    if ($pan !== '' && preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan) !== 1) {
        return ['error' => 'PAN should look like ABCDE1234F.', 'values' => $empty];
    }
    if ($type !== '' && !in_array($type, coupon_amount_types(), true)) {
        return ['error' => 'Choose a donation type from the list, or leave it blank.', 'values' => $empty];
    }
    if ($payment !== '' && !in_array($payment, money_payment_modes(), true)) {
        return ['error' => 'Choose a cash or bank payment, or leave it blank.', 'values' => $empty];
    }
    if (mb_strlen($purpose) > 200) {
        return ['error' => 'The purpose is too long.', 'values' => $empty];
    }
    if ($upi !== '' && $cheque !== '') {
        return ['error' => 'Enter either a UPI id or a cheque number.', 'values' => $empty];
    }
    if ($payment === '' && $upi !== '') {
        $payment = 'UPI';
    }
    if ($payment === '' && $cheque !== '') {
        $payment = 'Cheque';
    }
    if ($payment === 'UPI') {
        if (preg_match('/^[A-Z0-9][A-Z0-9._-]{5,63}$/', $upi) !== 1) {
            return ['error' => 'Enter the UPI transaction id, or leave payment blank.', 'values' => $empty];
        }
        $cheque = '';
        $chequeDate = '';
    } elseif ($payment === 'Cheque') {
        if (preg_match('/^[A-Z0-9]{4,30}$/', $cheque) !== 1) {
            return ['error' => 'Enter the cheque number, or leave payment blank.', 'values' => $empty];
        }
        $dated = valid_book_date($chequeDate);
        if ($dated === null) {
            return ['error' => 'Enter the cheque date, or leave the cheque number blank.', 'values' => $empty];
        }
        $chequeDate = $dated;
        $upi = '';
    } else {
        $upi = '';
        $cheque = '';
        $chequeDate = '';
    }
    return ['error' => null, 'values' => [
        'donor_name' => $name !== '' ? $name : null,
        'donor_phone' => $phone !== '' ? $phone : null,
        'donor_email' => $email !== '' ? $email : null,
        'donor_address' => $address !== '' ? $address : null,
        'donor_pan' => $pan !== '' ? $pan : null,
        'donation_type' => $type !== '' ? $type : null,
        'payment_mode' => $payment !== '' ? $payment : null,
        'purpose' => $purpose !== '' ? $purpose : null,
        'upi_reference' => $upi !== '' ? $upi : null,
        'cheque_number' => $cheque !== '' ? $cheque : null,
        'cheque_date' => $chequeDate !== '' ? $chequeDate : null,
    ]];
}

/** @param array<string, mixed> $row @param array<string, ?string> $values */
function coupon_gift_differs(array $row, array $values): bool
{
    foreach ($values as $key => $value) {
        $current = $row[$key] ?? null;
        $current = $current !== null && (string) $current !== '' ? (string) $current : null;
        if ($key === 'cheque_date' && $current !== null) {
            $current = substr($current, 0, 10);
        }
        if ($current !== $value) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{error: ?string, id: int, name: string}
 */
function coupon_resolve_donor(string $name, string $phone, string $email, string $address, string $pan): array
{
    $name = trim($name);
    if ($name === '') {
        $name = 'Coupon counter';
        $phone = '';
        $email = '';
        $address = '';
        $pan = '';
    }
    $digits = devotee_phone_digits($phone);
    if ($digits !== '') {
        foreach (db_all('SELECT id, name, phone FROM donors') as $row) {
            if (devotee_phone_digits((string) ($row['phone'] ?? '')) === $digits) {
                return ['error' => null, 'id' => (int) $row['id'], 'name' => (string) $row['name']];
            }
        }
    }
    $existing = db_one('SELECT id, name FROM donors WHERE name = ? ORDER BY id LIMIT 1', [$name]);
    if ($existing !== null) {
        return ['error' => null, 'id' => (int) $existing['id'], 'name' => (string) $existing['name']];
    }
    $saved = save_devotee(null, [
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'address' => $address,
        'pan' => $pan,
    ]);
    if ($saved['error'] !== null) {
        return ['error' => $saved['error'], 'id' => 0, 'name' => $name];
    }
    return ['error' => null, 'id' => $saved['id'], 'name' => $name];
}

/** @return array<string, string> */
function coupon_gift_from_post(): array
{
    return [
        'donor_name' => post_string('donor_name', 150),
        'donor_phone' => post_string('donor_phone', 20),
        'donor_email' => post_string('donor_email', 120),
        'donor_address' => post_string('donor_address', 500),
        'donor_pan' => post_string('donor_pan', 20),
        'donation_type' => post_string('donation_type', 30),
        'payment_mode' => post_string('payment_mode', 30),
        'purpose' => post_string('purpose', 200),
        'upi_reference' => post_string('upi_reference', 64),
        'cheque_number' => post_string('cheque_number', 30),
        'cheque_date' => post_string('cheque_date', 10),
    ];
}

function coupon_request_error(string $name, float $cost, int $quantity): ?string
{
    if (trim($name) === '') {
        return 'Enter a coupon name.';
    }
    if ($cost <= 0) {
        return 'Enter a cost above zero.';
    }
    if ($quantity < 1 || $quantity > 400) {
        return 'Enter a quantity from 1 to 400.';
    }
    return null;
}

function coupon_ranges_overlap(int $startA, int $endA, int $startB, int $endB): bool
{
    return $startA <= $endB && $startB <= $endA;
}

function coupon_pdf_path(int $batchId): string
{
    return APP_ROOT . '/storage/coupons/batch_' . $batchId . '.pdf';
}

function forget_coupon_pdf(int $batchId): void
{
    $path = coupon_pdf_path($batchId);
    if (is_file($path)) {
        unlink($path);
    }
}

/**
 * @param array<string, mixed> $gift
 * @return array{error: ?string, id: ?int, start: ?int, end: ?int, total: ?float}
 */
function create_coupon_batch(string $name, float $cost, int $quantity, int $userId, ?string $expiresAt = null, array $gift = [], bool $issueNow = false): array
{
    $failed = ['error' => null, 'id' => null, 'start' => null, 'end' => null, 'total' => null];
    $error = coupon_request_error($name, $cost, $quantity);
    if ($error === null && $issueNow && $quantity !== 1) {
        $error = 'One coupon is issued at a time. A larger quantity waits for approval.';
    }
    if ($error !== null) {
        $failed['error'] = $error;
        return $failed;
    }
    $storedGift = coupon_gift_storage($gift);
    if ($storedGift['error'] !== null) {
        $failed['error'] = $storedGift['error'];
        return $failed;
    }
    $giftValues = $storedGift['values'];
    $name = trim($name);
    $cost = round($cost, 2);
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $last = db_value('SELECT MAX(end_sl_no) FROM food_coupon_batches');
        $start = ($last === null || $last === false) ? 1 : ((int) $last) + 1;
        $end = $start + $quantity - 1;
        $total = round($cost * $quantity, 2);
        $issued = time();
        $id = db_exec(
            'INSERT INTO food_coupon_batches (
                coupon_name, cost, start_sl_no, end_sl_no, quantity, total_value, created_date, created_by, issued_unix, expires_at,
                donor_name, donor_phone, donor_email, donor_address, donor_pan, donation_type, payment_mode, purpose, upi_reference, cheque_number, cheque_date
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $name, $cost, $start, $end, $quantity, $total, date('Y-m-d'), $userId, $issued, $expiresAt,
                $giftValues['donor_name'], $giftValues['donor_phone'], $giftValues['donor_email'], $giftValues['donor_address'],
                $giftValues['donor_pan'], $giftValues['donation_type'], $giftValues['payment_mode'], $giftValues['purpose'],
                $giftValues['upi_reference'], $giftValues['cheque_number'], $giftValues['cheque_date'],
            ]
        );
        insert_coupon_rows($id, $issued, $start, $end, $expiresAt);
        record_approval('coupon', $id, $issueNow ? 'Approved' : 'Waiting', $total, $userId);
        if ($own) {
            $pdo->commit();
        }
        return ['error' => null, 'id' => $id, 'start' => $start, 'end' => $end, 'total' => $total];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function coupon_serial_clash(int $start, int $end, int $exceptId): ?string
{
    $row = db_one(
        'SELECT start_sl_no, end_sl_no FROM food_coupon_batches WHERE id <> ? AND start_sl_no <= ? AND end_sl_no >= ? LIMIT 1',
        [$exceptId, $end, $start]
    );
    if ($row === null) {
        return null;
    }
    return 'That quantity overlaps serial numbers ' . $row['start_sl_no'] . '–' . $row['end_sl_no'] . '.';
}

/**
 * @param array<string, mixed>|null $gift
 * @return array{error: ?string, changed: bool, reapproval: bool}
 */
function update_coupon_batch(
    int $batchId,
    string $name,
    float $cost,
    int $quantity,
    int $userId,
    ?string $expiresAt = null,
    bool $updateExpiry = false,
    ?array $gift = null
): array {
    $unchanged = ['error' => null, 'changed' => false, 'reapproval' => false];
    $error = coupon_request_error($name, $cost, $quantity);
    if ($error !== null) {
        return ['error' => $error, 'changed' => false, 'reapproval' => false];
    }
    $batch = db_one(
        'SELECT id, coupon_name, cost, quantity, start_sl_no, issued_unix, expires_at,
                donor_name, donor_phone, donor_email, donor_address, donor_pan, donation_type, payment_mode, purpose, upi_reference, cheque_number, cheque_date
         FROM food_coupon_batches WHERE id = ?',
        [$batchId]
    );
    if ($batch === null) {
        return ['error' => 'That coupon batch was not found.', 'changed' => false, 'reapproval' => false];
    }
    $currentExpiry = $batch['expires_at'] !== null && (string) $batch['expires_at'] !== ''
        ? (string) $batch['expires_at']
        : null;
    $expiryChanged = $updateExpiry && $expiresAt !== $currentExpiry;
    $costChanged = abs(round($cost, 2) - round((float) $batch['cost'], 2)) >= 0.001;
    $quantityChanged = $quantity !== (int) $batch['quantity'];
    $nameChanged = trim($name) !== (string) $batch['coupon_name'];
    $giftValues = null;
    $giftChanged = false;
    if ($gift !== null) {
        $storedGift = coupon_gift_storage($gift);
        if ($storedGift['error'] !== null) {
            return ['error' => $storedGift['error'], 'changed' => false, 'reapproval' => false];
        }
        $giftValues = $storedGift['values'];
        $giftChanged = coupon_gift_differs($batch, $giftValues);
    }
    if (!$expiryChanged && !$costChanged && !$quantityChanged && !$nameChanged && !$giftChanged) {
        return $unchanged;
    }
    if (($costChanged || $quantityChanged) && coupon_redeemed_count($batchId) > 0) {
        return ['error' => 'Sold coupons are already in the books. Cost and quantity stay as they are.', 'changed' => false, 'reapproval' => false];
    }
    $start = (int) $batch['start_sl_no'];
    $end = $start + $quantity - 1;
    $clash = coupon_serial_clash($start, $end, $batchId);
    if ($clash !== null) {
        return ['error' => $clash, 'changed' => false, 'reapproval' => false];
    }
    $total = round($cost * $quantity, 2);
    $expiresForRows = $updateExpiry ? $expiresAt : $currentExpiry;
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        if ($giftValues === null) {
            db_exec(
                'UPDATE food_coupon_batches
                 SET coupon_name = ?, cost = ?, end_sl_no = ?, quantity = ?, total_value = ?, expires_at = ?
                 WHERE id = ?',
                [trim($name), round($cost, 2), $end, $quantity, $total, $expiresForRows, $batchId]
            );
        } else {
            db_exec(
                'UPDATE food_coupon_batches
                 SET coupon_name = ?, cost = ?, end_sl_no = ?, quantity = ?, total_value = ?, expires_at = ?,
                     donor_name = ?, donor_phone = ?, donor_email = ?, donor_address = ?, donor_pan = ?,
                     donation_type = ?, payment_mode = ?, purpose = ?, upi_reference = ?, cheque_number = ?, cheque_date = ?
                 WHERE id = ?',
                [
                    trim($name), round($cost, 2), $end, $quantity, $total, $expiresForRows,
                    $giftValues['donor_name'], $giftValues['donor_phone'], $giftValues['donor_email'], $giftValues['donor_address'],
                    $giftValues['donor_pan'], $giftValues['donation_type'], $giftValues['payment_mode'], $giftValues['purpose'],
                    $giftValues['upi_reference'], $giftValues['cheque_number'], $giftValues['cheque_date'],
                    $batchId,
                ]
            );
        }
        $spanError = sync_coupon_rows($batchId, (int) ($batch['issued_unix'] ?? 0), $start, $end, $expiresForRows, true);
        if ($spanError !== null) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => $spanError, 'changed' => false, 'reapproval' => false];
        }
        if ($costChanged || $quantityChanged) {
            record_approval('coupon', $batchId, 'Waiting', $total, $userId);
        }
        forget_coupon_pdf($batchId);
        if ($own) {
            $pdo->commit();
        }
        return ['error' => null, 'changed' => true, 'reapproval' => $costChanged || $quantityChanged];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function remove_coupon_batch(int $batchId, string $role): ?string
{
    $row = db_one(
        "SELECT b.id, a.status FROM food_coupon_batches b
         LEFT JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
         WHERE b.id = ?",
        [$batchId]
    );
    if ($row === null) {
        return 'That coupon batch was not found.';
    }
    if ((string) ($row['status'] ?? '') === 'Approved' && $role !== 'Admin') {
        return 'Only an Admin can remove a batch that has been approved.';
    }
    if (coupon_redeemed_count($batchId) > 0) {
        return 'This batch has coupons already recorded as donations. Those stay in the books.';
    }
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        db_exec("DELETE FROM approvals WHERE subject_type = 'coupon' AND subject_id = ?", [$batchId]);
        db_exec('DELETE FROM food_coupons WHERE batch_id = ?', [$batchId]);
        db_exec('DELETE FROM food_coupon_batches WHERE id = ?', [$batchId]);
        forget_coupon_pdf($batchId);
        if ($own) {
            $pdo->commit();
        }
        return null;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function coupon_code(int $issuedUnix, int $serial): string
{
    return sprintf('CU-%d-%04d', $issuedUnix, $serial);
}

function coupon_code_from_input(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/(CU-\d{9,12}-\d{4,6})/', $raw, $match) === 1) {
        return $match[1];
    }
    return $raw;
}

function app_public_origin(): string
{
    $configured = rtrim(env_value('APP_URL'), '/');
    if (preg_match('#^https?://[A-Za-z0-9._:-]+$#', $configured) === 1) {
        return $configured;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (preg_match('/^[A-Za-z0-9._:-]+$/', $host) !== 1) {
        $host = 'localhost';
    }
    return ($https ? 'https' : 'http') . '://' . $host;
}

function app_web_script(): string
{
    if (PHP_SAPI !== 'cli') {
        return app_script();
    }
    $root = str_replace('\\', '/', APP_ROOT);
    $local = '/jt_blr/jt-temple-blr/php';
    if (str_ends_with($root, $local)) {
        return $local . '/index.php';
    }
    return '/index.php';
}

function coupon_scan_url(string $code): string
{
    return app_public_origin() . app_web_script() . '?' . http_build_query([
        'r' => 'coupons/scan',
        'code' => $code,
    ]);
}

function coupon_issued_unix(int $batchId): int
{
    $row = db_one('SELECT issued_unix, created_date FROM food_coupon_batches WHERE id = ?', [$batchId]);
    if ($row === null) {
        return time();
    }
    $issued = (int) ($row['issued_unix'] ?? 0);
    if ($issued > 0) {
        return $issued;
    }
    $fromDate = strtotime((string) $row['created_date'] . ' 00:00:00');
    $issued = $fromDate === false ? time() : $fromDate;
    db_exec(
        'UPDATE food_coupon_batches SET issued_unix = ? WHERE id = ? AND (issued_unix IS NULL OR issued_unix = 0)',
        [$issued, $batchId]
    );
    return $issued;
}

function ensure_coupon_schema(PDO $pdo): void
{
    ensure_column($pdo, 'food_coupon_batches', 'issued_unix', 'INT UNSIGNED NULL');
    ensure_column($pdo, 'food_coupon_batches', 'expires_at', 'DATETIME NULL');
    ensure_column($pdo, 'food_coupon_batches', 'donor_name', 'VARCHAR(150) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'donor_phone', 'VARCHAR(20) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'donor_email', 'VARCHAR(120) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'donor_address', 'VARCHAR(500) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'donor_pan', 'VARCHAR(10) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'donation_type', 'VARCHAR(30) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'payment_mode', 'VARCHAR(30) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'purpose', 'VARCHAR(200) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'upi_reference', 'VARCHAR(64) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'cheque_number', 'VARCHAR(30) NULL');
    ensure_column($pdo, 'food_coupon_batches', 'cheque_date', 'DATE NULL');
    $pdo->exec(
        'UPDATE food_coupon_batches
         SET issued_unix = UNIX_TIMESTAMP(created_date)
         WHERE issued_unix IS NULL OR issued_unix = 0'
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS food_coupons (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            batch_id        INT NOT NULL,
            serial_no       INT NOT NULL,
            code            VARCHAR(40) NOT NULL,
            status          ENUM('Valid','Redeemed','Expired','Invalid') NOT NULL DEFAULT 'Valid',
            expires_at      DATETIME NULL,
            redeemed_at     DATETIME NULL,
            donation_id     INT NULL,
            invalidated_at  DATETIME NULL,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_coupon_code (code),
            UNIQUE KEY uq_coupon_batch_serial (batch_id, serial_no),
            KEY idx_coupon_status (status),
            KEY idx_coupon_expires (expires_at),
            FOREIGN KEY (batch_id) REFERENCES food_coupon_batches(id) ON DELETE CASCADE,
            FOREIGN KEY (donation_id) REFERENCES donations(id)
        ) ENGINE=InnoDB"
    );
    backfill_coupon_rows($pdo);
}

function coupon_code_is_valid(string $code): bool
{
    return preg_match('/^CU-\d{9,12}-\d{4,6}$/', trim($code)) === 1;
}

/**
 * @return array{error: ?string, expires_at: ?string}
 */
function coupon_expires_at(bool $noExpiry, string $raw): array
{
    if ($noExpiry) {
        return ['error' => null, 'expires_at' => null];
    }
    $raw = str_replace('T', ' ', trim($raw));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw) === 1) {
        $raw .= ':00';
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    $invalid = $parsed === false
        || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        || $parsed->format('Y-m-d H:i:s') !== $raw;
    if ($invalid) {
        return ['error' => 'Enter an expiry date and time, or choose no expiry.', 'expires_at' => null];
    }
    if ($parsed <= new DateTimeImmutable('now')) {
        return ['error' => 'Enter an expiry in the future, or choose no expiry.', 'expires_at' => null];
    }
    return ['error' => null, 'expires_at' => $parsed->format('Y-m-d H:i:s')];
}

function coupon_expiry_label(?string $expiresAt): string
{
    if ($expiresAt === null || $expiresAt === '') {
        return '';
    }
    $stamp = strtotime($expiresAt);
    if ($stamp === false) {
        return '';
    }
    return date('j M Y, g:i A', $stamp);
}

function insert_coupon_rows(int $batchId, int $issuedUnix, int $start, int $end, ?string $expiresAt): void
{
    for ($serial = $start; $serial <= $end; $serial++) {
        db_exec(
            'INSERT INTO food_coupons (batch_id, serial_no, code, status, expires_at) VALUES (?,?,?,?,?)',
            [$batchId, $serial, coupon_code($issuedUnix, $serial), 'Valid', $expiresAt]
        );
    }
}

function sync_coupon_rows(int $batchId, int $issuedUnix, int $start, int $end, ?string $expiresAt, bool $touchExpiry): ?string
{
    if ($issuedUnix <= 0) {
        $issuedUnix = coupon_issued_unix($batchId);
    }
    $rows = db_all('SELECT serial_no, status FROM food_coupons WHERE batch_id = ?', [$batchId]);
    $bySerial = [];
    foreach ($rows as $row) {
        $bySerial[(int) $row['serial_no']] = (string) $row['status'];
    }
    foreach ($bySerial as $serial => $status) {
        if (($serial < $start || $serial > $end) && $status === 'Redeemed') {
            return 'Sold coupons are already in the books. The quantity cannot drop those serials.';
        }
    }
    foreach (array_keys($bySerial) as $serial) {
        if ($serial < $start || $serial > $end) {
            db_exec(
                'DELETE FROM food_coupons WHERE batch_id = ? AND serial_no = ? AND status <> ?',
                [$batchId, $serial, 'Redeemed']
            );
        }
    }
    for ($serial = $start; $serial <= $end; $serial++) {
        if (!isset($bySerial[$serial])) {
            db_exec(
                'INSERT INTO food_coupons (batch_id, serial_no, code, status, expires_at) VALUES (?,?,?,?,?)',
                [$batchId, $serial, coupon_code($issuedUnix, $serial), 'Valid', $expiresAt]
            );
        }
    }
    if ($touchExpiry) {
        db_exec(
            "UPDATE food_coupons SET expires_at = ? WHERE batch_id = ? AND status = 'Valid'",
            [$expiresAt, $batchId]
        );
        db_exec(
            "UPDATE food_coupons
             SET status = 'Valid', invalidated_at = NULL, expires_at = ?
             WHERE batch_id = ? AND status = 'Expired'",
            [$expiresAt, $batchId]
        );
    }
    return null;
}

function backfill_coupon_rows(PDO $pdo): void
{
    $short = $pdo->query(
        'SELECT b.id
         FROM food_coupon_batches b
         LEFT JOIN food_coupons c ON c.batch_id = b.id
         GROUP BY b.id, b.quantity
         HAVING COUNT(c.id) < b.quantity'
    )->fetchAll();
    if ($short === []) {
        return;
    }
    $ids = [];
    foreach ($short as $row) {
        if (is_array($row)) {
            $ids[] = (int) $row['id'];
        }
    }
    if ($ids === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(
        "SELECT id, start_sl_no, end_sl_no, issued_unix, expires_at
         FROM food_coupon_batches WHERE id IN ($placeholders)"
    );
    $statement->execute($ids);
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO food_coupons (batch_id, serial_no, code, status, expires_at) VALUES (?,?,?,?,?)'
    );
    foreach ($statement->fetchAll() as $batch) {
        if (!is_array($batch)) {
            continue;
        }
        $issued = (int) ($batch['issued_unix'] ?? 0);
        if ($issued <= 0) {
            continue;
        }
        $expires = $batch['expires_at'] !== null && (string) $batch['expires_at'] !== ''
            ? (string) $batch['expires_at']
            : null;
        $batchId = (int) $batch['id'];
        for ($serial = (int) $batch['start_sl_no']; $serial <= (int) $batch['end_sl_no']; $serial++) {
            $insert->execute([$batchId, $serial, coupon_code($issued, $serial), 'Valid', $expires]);
        }
    }
}

function coupon_redeemed_count(int $batchId): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM food_coupons WHERE batch_id = ? AND status = 'Redeemed'",
        [$batchId]
    );
}

function expire_due_coupons(): int
{
    $statement = db()->prepare(
        "UPDATE food_coupons
         SET status = 'Expired', invalidated_at = NOW()
         WHERE status = 'Valid' AND expires_at IS NOT NULL AND expires_at <= NOW()"
    );
    $statement->execute();
    return $statement->rowCount();
}

function coupon_already_redeemed_message(): string
{
    return 'This coupon is already scanned and redeemed. Please use a valid coupon and contact an Admin.';
}

/**
 * @return array<int, list<array{code:string,scanned_at:string,scanned_by:string}>>
 */
function coupon_batch_scans(): array
{
    $scans = [];
    $rows = db_all(
        "SELECT c.batch_id, c.code, c.redeemed_at, u.full_name AS scanned_by
         FROM food_coupons c
         LEFT JOIN donations d ON d.id = c.donation_id
         LEFT JOIN users u ON u.id = d.created_by
         WHERE c.status = 'Redeemed'
         ORDER BY c.redeemed_at DESC, c.id DESC"
    );
    foreach ($rows as $row) {
        $id = (int) $row['batch_id'];
        $when = trim((string) ($row['redeemed_at'] ?? ''));
        $stamp = $when !== '' ? strtotime($when) : false;
        $scans[$id][] = [
            'code' => (string) $row['code'],
            'scanned_at' => $stamp !== false ? date('d M Y, g:i A', $stamp) : '',
            'scanned_by' => trim((string) ($row['scanned_by'] ?? '')) !== '' ? trim((string) $row['scanned_by']) : 'Unknown',
        ];
    }
    return $scans;
}

/**
 * @return array<int, array{Valid:int,Redeemed:int,Expired:int,Invalid:int}>
 */
function coupon_batch_counts(): array
{
    $counts = [];
    foreach (db_all('SELECT batch_id, status, COUNT(*) AS total FROM food_coupons GROUP BY batch_id, status') as $row) {
        $id = (int) $row['batch_id'];
        if (!isset($counts[$id])) {
            $counts[$id] = ['Valid' => 0, 'Redeemed' => 0, 'Expired' => 0, 'Invalid' => 0];
        }
        $status = (string) $row['status'];
        if (isset($counts[$id][$status])) {
            $counts[$id][$status] = (int) $row['total'];
        }
    }
    return $counts;
}

function coupon_api_token_matches(string $given): bool
{
    $token = env_value('COUPON_API_TOKEN');
    if (strlen($token) < 16 || strlen($given) < 16 || strlen($given) > 200) {
        return false;
    }
    return hash_equals($token, $given);
}

function coupon_actor_from_bearer(): ?int
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(\S{1,200})$/', $header, $match) !== 1) {
        return null;
    }
    if (!coupon_api_token_matches($match[1])) {
        return null;
    }
    $id = db_value("SELECT id FROM users WHERE role = 'Admin' AND is_active = 1 ORDER BY id LIMIT 1");
    if ($id === false || $id === null) {
        return null;
    }
    return (int) $id;
}

/**
 * @return array{error: ?string, fields: array<string, mixed>}
 */
function coupon_api_fields(): array
{
    $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($type, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode(is_string($raw) ? $raw : '', true);
        if (!is_array($decoded)) {
            return ['error' => 'Send a JSON object.', 'fields' => []];
        }
        return ['error' => null, 'fields' => $decoded];
    }
    return ['error' => null, 'fields' => $_POST];
}

/** @param array<string, mixed> $fields */
function coupon_field(array $fields, string $key, int $max): string
{
    $value = $fields[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (mb_strlen($value) > $max) {
        return mb_substr($value, 0, $max);
    }
    return $value;
}

/**
 * @return array{
 *   error: ?string,
 *   code: ?string,
 *   amount: ?float,
 *   donation_id: ?int,
 *   purpose: ?string,
 *   donor_name: ?string,
 *   payment_mode: ?string,
 *   status: ?string
 * }
 */
function redeem_coupon(
    string $code,
    int $userId,
    string $donorName,
    string $paymentMode,
    string $upiReference = '',
    string $chequeNumber = '',
    string $chequeDate = '',
    bool $chequeCleared = false
): array {
    $failed = [
        'error' => null,
        'code' => null,
        'amount' => null,
        'donation_id' => null,
        'purpose' => null,
        'donor_name' => null,
        'payment_mode' => null,
        'status' => null,
    ];
    $code = coupon_code_from_input($code);
    if (!coupon_code_is_valid($code)) {
        $failed['error'] = 'Enter the coupon code from the QR code.';
        return $failed;
    }
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        expire_due_coupons();
        $pdo->beginTransaction();
    } else {
        expire_due_coupons();
    }
    try {
        $row = db_one(
            "SELECT c.id, c.code, c.status, c.expires_at, b.coupon_name, b.cost, a.status AS approval_status,
                    b.donor_name, b.donor_phone, b.donor_email, b.donor_address, b.donor_pan,
                    b.donation_type, b.payment_mode, b.purpose, b.upi_reference, b.cheque_number, b.cheque_date
             FROM food_coupons c
             JOIN food_coupon_batches b ON b.id = c.batch_id
             LEFT JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
             WHERE c.code = ?
             FOR UPDATE",
            [$code]
        );
        if ($row === null) {
            $failed['error'] = 'That coupon was not found.';
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $failed;
        }
        $blocked = coupon_use_error($row);
        if ($blocked !== null) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $failed['error'] = $blocked;
            $failed['code'] = $code;
            $failed['status'] = (string) $row['status'];
            return $failed;
        }
        $mode = $paymentMode !== '' ? $paymentMode : trim((string) ($row['payment_mode'] ?? ''));
        if ($mode === '') {
            $mode = 'Cash';
        }
        if (!in_array($mode, money_payment_modes(), true)) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $failed['error'] = 'Choose a cash or bank payment.';
            $failed['code'] = $code;
            return $failed;
        }
        $upi = $paymentMode === '' ? trim((string) ($row['upi_reference'] ?? '')) : $upiReference;
        $chequeNo = $paymentMode === '' ? trim((string) ($row['cheque_number'] ?? '')) : $chequeNumber;
        $chequeOn = $paymentMode === '' ? trim((string) ($row['cheque_date'] ?? '')) : $chequeDate;
        $instrument = normalize_payment_instrument($mode, $upi, $chequeNo, substr($chequeOn, 0, 10), $chequeCleared);
        if ($instrument['error'] !== null) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $failed['error'] = $instrument['error'];
            $failed['code'] = $code;
            return $failed;
        }
        $useBatchDonor = trim($donorName) === '';
        $resolved = coupon_resolve_donor(
            $useBatchDonor ? trim((string) ($row['donor_name'] ?? '')) : trim($donorName),
            $useBatchDonor ? trim((string) ($row['donor_phone'] ?? '')) : '',
            $useBatchDonor ? trim((string) ($row['donor_email'] ?? '')) : '',
            $useBatchDonor ? trim((string) ($row['donor_address'] ?? '')) : '',
            $useBatchDonor ? trim((string) ($row['donor_pan'] ?? '')) : ''
        );
        if ($resolved['error'] !== null) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $failed['error'] = $resolved['error'];
            $failed['code'] = $code;
            return $failed;
        }
        $donor = $resolved['name'];
        $donorId = $resolved['id'];
        $amount = round((float) $row['cost'], 2);
        $purpose = trim((string) ($row['purpose'] ?? ''));
        if ($purpose === '') {
            $purpose = 'Donation';
        }
        $type = trim((string) ($row['donation_type'] ?? ''));
        if (!in_array($type, coupon_amount_types(), true)) {
            $type = 'Cash';
        }
        $donationId = db_exec(
            'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, cheque_number, cheque_date, cheque_cleared, upi_reference, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $donorId,
                $type,
                $amount,
                $purpose,
                date('Y-m-d'),
                $mode,
                $instrument['cheque_number'],
                $instrument['cheque_date'],
                $instrument['cheque_cleared'],
                $instrument['upi_reference'],
                $code,
                $userId,
            ]
        );
        $marked = db()->prepare(
            "UPDATE food_coupons SET status = 'Redeemed', redeemed_at = NOW(), donation_id = ? WHERE id = ? AND status = 'Valid'"
        );
        $marked->execute([$donationId, (int) $row['id']]);
        if ($marked->rowCount() !== 1) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $failed['error'] = coupon_already_redeemed_message();
            $failed['code'] = $code;
            $failed['status'] = 'Redeemed';
            return $failed;
        }
        if ($own) {
            $pdo->commit();
        }
        return [
            'error' => null,
            'code' => $code,
            'amount' => $amount,
            'donation_id' => $donationId,
            'purpose' => $purpose,
            'donor_name' => $donor,
            'payment_mode' => $mode,
            'status' => 'Redeemed',
        ];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * @return array{error: ?string, code: ?string, status: ?string}
 */
function invalidate_coupon(string $code): array
{
    $code = coupon_code_from_input($code);
    if (!coupon_code_is_valid($code)) {
        return ['error' => 'Enter the coupon code from the QR code.', 'code' => null, 'status' => null];
    }
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        expire_due_coupons();
        $pdo->beginTransaction();
    } else {
        expire_due_coupons();
    }
    try {
        $row = db_one(
            'SELECT id, code, status, expires_at FROM food_coupons WHERE code = ? FOR UPDATE',
            [$code]
        );
        if ($row === null) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => 'That coupon was not found.', 'code' => null, 'status' => null];
        }
        $status = (string) $row['status'];
        if ($status === 'Redeemed') {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => coupon_already_redeemed_message(), 'code' => $code, 'status' => $status];
        }
        if ($status === 'Expired') {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => coupon_expired_message($row), 'code' => $code, 'status' => $status];
        }
        if ($status === 'Invalid') {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => 'This coupon was invalidated.', 'code' => $code, 'status' => $status];
        }
        db_exec(
            "UPDATE food_coupons SET status = 'Invalid', invalidated_at = NOW() WHERE id = ?",
            [(int) $row['id']]
        );
        if ($own) {
            $pdo->commit();
        }
        return ['error' => null, 'code' => $code, 'status' => 'Invalid'];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * @return array{error: ?string, coupon: ?array<string, mixed>}
 */
/**
 * Read-only check used by the scan link. It does not record a donation.
 *
 * @return array{error:?string,code:?string,amount:?float,purpose:?string,status:?string}
 */
function coupon_scan_preview(string $code): array
{
    $empty = ['error' => null, 'code' => null, 'amount' => null, 'purpose' => null, 'status' => null];
    $code = coupon_code_from_input($code);
    if (!coupon_code_is_valid($code)) {
        $empty['error'] = 'Enter the coupon code from the QR code.';
        return $empty;
    }
    expire_due_coupons();
    $row = db_one(
        "SELECT c.id, c.code, c.status, c.expires_at, b.coupon_name, b.cost, a.status AS approval_status
         FROM food_coupons c
         JOIN food_coupon_batches b ON b.id = c.batch_id
         LEFT JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
         WHERE c.code = ?",
        [$code]
    );
    if ($row === null) {
        $empty['error'] = 'That coupon was not found.';
        return $empty;
    }
    return [
        'error' => coupon_use_error($row),
        'code' => $code,
        'amount' => round((float) $row['cost'], 2),
        'purpose' => trim((string) $row['coupon_name']),
        'status' => (string) $row['status'],
    ];
}

function coupon_status(string $code): array
{
    $code = coupon_code_from_input($code);
    if (!coupon_code_is_valid($code)) {
        return ['error' => 'Enter the coupon code from the QR code.', 'coupon' => null];
    }
    expire_due_coupons();
    $row = db_one(
        "SELECT c.code, c.status, c.expires_at, c.redeemed_at, c.donation_id, b.coupon_name, b.cost, a.status AS approval_status
         FROM food_coupons c
         JOIN food_coupon_batches b ON b.id = c.batch_id
         LEFT JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
         WHERE c.code = ?",
        [$code]
    );
    if ($row === null) {
        return ['error' => 'That coupon was not found.', 'coupon' => null];
    }
    return [
        'error' => null,
        'coupon' => [
            'code' => (string) $row['code'],
            'status' => (string) $row['status'],
            'coupon_name' => (string) $row['coupon_name'],
            'amount' => round((float) $row['cost'], 2),
            'expires_at' => $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
            'approved' => (string) ($row['approval_status'] ?? '') === 'Approved',
            'redeemed_at' => $row['redeemed_at'] !== null ? (string) $row['redeemed_at'] : null,
            'donation_id' => $row['donation_id'] !== null ? (int) $row['donation_id'] : null,
        ],
    ];
}

/** @param array<string, mixed> $row */
function coupon_use_error(array $row): ?string
{
    if ((string) ($row['approval_status'] ?? '') !== 'Approved') {
        return 'This coupon is not approved yet.';
    }
    $status = (string) ($row['status'] ?? '');
    if ($status === 'Redeemed') {
        return coupon_already_redeemed_message();
    }
    if ($status === 'Invalid') {
        return 'This coupon was invalidated.';
    }
    if ($status === 'Expired') {
        return coupon_expired_message($row);
    }
    $expires = $row['expires_at'] ?? null;
    if ($expires !== null && (string) $expires !== '' && strtotime((string) $expires) !== false && strtotime((string) $expires) <= time()) {
        return coupon_expired_message($row);
    }
    if ($status !== 'Valid') {
        return 'This coupon cannot be recorded.';
    }
    return null;
}

/** @param array<string, mixed> $row */
function coupon_expired_message(array $row): string
{
    $label = coupon_expiry_label(isset($row['expires_at']) ? (string) $row['expires_at'] : null);
    if ($label === '') {
        return 'This coupon has expired.';
    }
    return 'This coupon expired on ' . $label . '.';
}

function generate_coupon_batch_pdf(
    int $batchId,
    string $couponName,
    float $cost,
    int $startSlNo,
    int $quantity,
    ?int $issuedUnix = null
): string {
    $pageW = 841.89;
    $pageH = 595.28;
    $cols = 4;
    $rows = 4;
    $marginX = 18.0;
    $marginY = 16.0;
    $cellW = ($pageW - (2 * $marginX)) / $cols;
    $cellH = ($pageH - (2 * $marginY)) / $rows;
    $perPage = $cols * $rows;

    $pdf = new PdfDocument($pageW, $pageH);
    $navy = [17 / 255, 44 / 255, 97 / 255];
    $logo = brand_logo_raster();
    $image = $logo !== null ? $pdf->addImage($logo) : null;
    $issued = $issuedUnix ?? coupon_issued_unix($batchId);
    $batchRow = db_one('SELECT expires_at FROM food_coupon_batches WHERE id = ?', [$batchId]);
    $expiresLabel = '';
    if ($batchRow !== null && $batchRow['expires_at'] !== null) {
        $expiresLabel = coupon_expiry_label((string) $batchRow['expires_at']);
    }

    for ($i = 0; $i < $quantity; $i++) {
        if ($i > 0 && $i % $perPage === 0) {
            $pdf->addPage();
        }
        $slot = $i % $perPage;
        $col = $slot % $cols;
        $row = intdiv($slot, $cols);
        $x = $marginX + ($col * $cellW) + 4;
        $y = $pageH - $marginY - (($row + 1) * $cellH) + 6;
        $w = $cellW - 8;
        $h = $cellH - 10;
        draw_coupon($pdf, $x, $y, $w, $h, $couponName, $cost, coupon_code($issued, $startSlNo + $i), $navy, $image, $expiresLabel);
    }

    $path = APP_ROOT . '/storage/coupons/batch_' . $batchId . '.pdf';
    $pdf->save($path);
    return $path;
}

/** @param array{0:float,1:float,2:float} $navy */
function draw_coupon(
    PdfDocument $pdf,
    float $x,
    float $y,
    float $w,
    float $h,
    string $couponName,
    float $cost,
    string $serialCode,
    array $navy,
    ?int $logo = null,
    string $expiresLabel = ''
): void {
    $raster = $logo === null ? null : brand_logo_raster();
    $pdf->setStroke(...$navy);
    $pdf->setFill(1, 1, 1);
    $pdf->setLineWidth(1.4);
    $pdf->setDash();
    $pdf->rect($x, $y, $w, $h, true, true);
    if ($logo !== null && $raster !== null) {
        [$markW, $markH] = brand_fit_box((int) $raster['width'], (int) $raster['height'], min($w, $h) * 0.62);
        $opacity = brand_watermark_opacity('coupon');
        if ($opacity > 0.0) {
            $pdf->drawImage($logo, $x + ($w - $markW) / 2, $y + ($h - $markH) / 2, $markW, $markH, $opacity);
        }
    }
    $pdf->setFill(...$navy);

    $perfX = $x + ($w * 0.62);
    $pdf->setDash(1.2, 2.2);
    $pdf->line($perfX, $y + 8, $perfX, $y + $h - 8);
    $pdf->setDash();

    $brandY = $y + $h - 24;
    $brandX = $x + 8;
    if ($logo !== null && $raster !== null) {
        [$logoW, $logoH] = brand_fit_box((int) $raster['width'], (int) $raster['height'], 20.0);
        $pdf->drawImage($logo, $x + 8, $brandY - 2, $logoW, $logoH, 1);
        $brandX = $x + 8 + $logoW + 4;
    }
    $temple = $pdf->fitText(app_display_name(), 8, max(20.0, $perfX - 6 - $brandX), true);
    $pdf->text($brandX, $brandY + 4, $temple, 8, 'F2');

    $title = strtoupper($couponName);
    $words = preg_split('/\s+/', $title) ?: [$title];
    $line1 = $title;
    $line2 = '';
    if (strlen($title) > 16 && count($words) > 1) {
        $mid = intdiv(count($words), 2);
        $line1 = implode(' ', array_slice($words, 0, $mid));
        $line2 = implode(' ', array_slice($words, $mid));
    }
    $pdf->text($x + 8, $y + $h - 38, $line1, 11, 'F2');
    $ruleY = $y + $h - 44;
    if ($line2 !== '') {
        $pdf->text($x + 8, $y + $h - 52, $line2, 11, 'F2');
        $ruleY = $y + $h - 58;
    }
    $pdf->setLineWidth(0.7);
    $pdf->line($x + 8, $ruleY, $perfX - 8, $ruleY);

    $amount = 'Rs. ' . number_format($cost, 0) . '/-';
    $pdf->text($x + 8, $ruleY - 18, $amount, 13, 'F2');

    $code = $pdf->fitText($serialCode, 6.5, max(20.0, $perfX - $x - 16), true);
    $pdf->setFill(...$navy);
    $pdf->text($x + 8, $y + 22, $code, 6.5, 'F2');
    if ($expiresLabel !== '') {
        $till = $pdf->fitText('Till ' . $expiresLabel, 6, max(20.0, $perfX - $x - 16), true);
        $pdf->text($x + 8, $y + 12, $till, 6, 'F1');
    }
    $symbol = qr_matrix(coupon_scan_url($serialCode));
    $modules = count($symbol);
    $available = ($x + $w - 4) - ($perfX + 4);
    if ($modules > 0 && $available > 8) {
        $module = $available / ($modules + 8);
        $symbolSize = $module * $modules;
        $pad = $module * 4;
        $box = $symbolSize + (2 * $pad);
        $qrX = $perfX + 4 + $pad;
        $qrY = $y + (($h - $box) / 2) + $pad;
        $pdf->setFill(1, 1, 1);
        $pdf->rect($qrX - $pad, $qrY - $pad, $box, $box, false, true);
        $pdf->matrix($qrX, $qrY, $symbolSize, $symbol);
    }
}
