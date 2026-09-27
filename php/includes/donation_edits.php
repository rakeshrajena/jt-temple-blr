<?php
declare(strict_types=1);

class DonationEditException extends RuntimeException
{
}

/** @return list<string> */
function donation_amount_types(): array
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

function donation_type_is_locked(array $donation): bool
{
    if (in_array((string) ($donation['donation_type'] ?? ''), ['Food', 'Vastra', 'Inventory'], true)) {
        return true;
    }
    return !empty($donation['linked_food_id'])
        || !empty($donation['linked_vastra_id'])
        || !empty($donation['linked_inventory_id']);
}

function ensure_donation_edit_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS donation_edits (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            donation_id     INT NOT NULL,
            donor_name      VARCHAR(150) NOT NULL,
            donor_phone     VARCHAR(20) NULL,
            donor_email     VARCHAR(120) NULL,
            donor_address   VARCHAR(500) NULL,
            donor_pan       VARCHAR(20) NULL,
            donation_type   VARCHAR(30) NOT NULL,
            amount          DECIMAL(12,2) NULL,
            purpose         VARCHAR(200) NULL,
            donation_date   DATE NOT NULL,
            payment_mode    VARCHAR(30) NOT NULL,
            cheque_number   VARCHAR(30) NULL,
            cheque_date     DATE NULL,
            cheque_cleared  TINYINT(1) NOT NULL DEFAULT 0,
            upi_reference   VARCHAR(64) NULL,
            pledge_id       INT NULL,
            reason          VARCHAR(500) NOT NULL,
            prepared_by     INT NOT NULL,
            applied         TINYINT(1) NOT NULL DEFAULT 0,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (donation_id) REFERENCES donations(id),
            FOREIGN KEY (pledge_id) REFERENCES pledges(id),
            FOREIGN KEY (prepared_by) REFERENCES users(id),
            KEY idx_donation_edit (donation_id, applied)
        ) ENGINE=InnoDB"
    );
    ensure_column($pdo, 'donation_edits', 'treasurer_approved_by', 'INT NULL');
    ensure_column($pdo, 'donation_edits', 'admin_approved_by', 'INT NULL');
}

function donation_edit_needs_both(string $createdAt): bool
{
    $createdAt = substr(trim($createdAt), 0, 19);
    $added = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $createdAt);
    if (!$added instanceof DateTimeImmutable || $added->format('Y-m-d H:i:s') !== $createdAt) {
        return true;
    }
    return (new DateTimeImmutable('now'))->getTimestamp() - $added->getTimestamp() > 86400;
}

function donation_edit_both_error(string $role, int $preparerId, int $actorId, string $status): ?string
{
    if ($status !== 'Waiting') {
        return 'Only a waiting item can be decided.';
    }
    if ($actorId === $preparerId) {
        return 'You cannot decide an item you prepared.';
    }
    if ($role !== 'Treasurer' && $role !== 'Admin') {
        return 'An edit older than 24 hours needs both a Treasurer and an Admin.';
    }
    return null;
}

function donation_edit_sign_message(string $sign): string
{
    return match ($sign) {
        'waiting_admin' => 'A Treasurer has approved. An Admin must also approve before the gift or the receipt changes.',
        'waiting_treasurer' => 'An Admin has approved. A Treasurer must also approve before the gift or the receipt changes.',
        'already' => 'That role has already approved this edit. The other role must still approve it.',
        default => 'Both approvals are recorded.',
    };
}

function donation_edit_record_signature(int $editId, string $role, int $userId): string
{
    if ($role !== 'Treasurer' && $role !== 'Admin') {
        throw new DonationEditException('An edit older than 24 hours needs both a Treasurer and an Admin.');
    }
    $column = $role === 'Treasurer' ? 'treasurer_approved_by' : 'admin_approved_by';
    $edit = db_one('SELECT * FROM donation_edits WHERE id = ? FOR UPDATE', [$editId]);
    if ($edit === null) {
        throw new DonationEditException('That edit was not found.');
    }
    if ((int) $edit['applied'] === 1) {
        return 'complete';
    }
    $mine = $edit[$column] !== null ? (int) $edit[$column] : 0;
    if ($mine === 0) {
        $marked = db()->prepare(
            "UPDATE donation_edits SET {$column} = ? WHERE id = ? AND {$column} IS NULL AND applied = 0"
        );
        $marked->execute([$userId, $editId]);
        if ($marked->rowCount() !== 1) {
            return 'already';
        }
        $edit[$column] = $userId;
    } elseif ($mine !== $userId) {
        return 'already';
    }
    if ($edit['treasurer_approved_by'] !== null && $edit['admin_approved_by'] !== null) {
        return 'complete';
    }
    return $role === 'Treasurer' ? 'waiting_admin' : 'waiting_treasurer';
}

function donation_edit_clear_signatures(int $editId): void
{
    db_exec(
        'UPDATE donation_edits SET treasurer_approved_by = NULL, admin_approved_by = NULL WHERE id = ? AND applied = 0',
        [$editId]
    );
}

/** @return array<string, mixed>|null */
function donation_edit_row(int $donationId): ?array
{
    return db_one(
        'SELECT d.*, don.name AS donor_name, don.phone AS donor_phone, don.email AS donor_email,
                don.address AS donor_address, don.pan_number AS donor_pan
         FROM donations d
         JOIN donors don ON don.id = d.donor_id
         WHERE d.id = ?',
        [$donationId]
    );
}

function donation_edit_is_open(int $donationId): bool
{
    return (int) db_value(
        "SELECT COUNT(*)
         FROM donation_edits e
         JOIN approvals a ON a.subject_type = 'donation_edit' AND a.subject_id = e.id
         WHERE e.donation_id = ? AND a.status IN ('Draft', 'Waiting', 'Sent back')",
        [$donationId]
    ) > 0;
}

/** @return array<string, mixed>|null */
function open_donation_edit(int $donationId): ?array
{
    return db_one(
        "SELECT e.*, a.status
         FROM donation_edits e
         JOIN approvals a ON a.subject_type = 'donation_edit' AND a.subject_id = e.id
         WHERE e.donation_id = ? AND a.status IN ('Draft', 'Waiting', 'Sent back')
         ORDER BY e.id DESC
         LIMIT 1",
        [$donationId]
    );
}

/** @return array<string, mixed> */
function donation_edit_from_post(): array
{
    return [
        'donor_name' => post_string('donor_name', 150),
        'donor_phone' => post_string('donor_phone', 20),
        'donor_email' => post_string('donor_email', 120),
        'donor_address' => post_string('donor_address', 500),
        'donor_pan' => strtoupper(post_string('pan_number', 20)),
        'donation_type' => post_string('donation_type', 30),
        'amount' => trim((string) ($_POST['amount'] ?? '')),
        'purpose' => post_string('purpose', 200),
        'donation_date' => post_string('donation_date', 10),
        'payment_mode' => post_string('payment_mode', 30),
        'upi_reference' => post_string('upi_reference', 64),
        'cheque_number' => post_string('cheque_number', 30),
        'cheque_date' => post_string('cheque_date', 10),
        'cheque_cleared' => isset($_POST['cheque_cleared']),
        'pledge_id' => (int) ($_POST['pledge_id'] ?? 0),
        'reason' => post_string('reason', 500),
    ];
}

/**
 * @param array<string, mixed> $donation
 * @param array<string, mixed> $input
 * @return array{error: ?string, values: array<string, mixed>, approval_amount: float}
 */
function donation_edit_normalize(array $donation, array $input): array
{
    $failed = ['error' => null, 'values' => [], 'approval_amount' => 0.0];
    $reason = trim((string) ($input['reason'] ?? ''));
    if (mb_strlen($reason) < 3) {
        $failed['error'] = 'Write a short reason for the edit.';
        return $failed;
    }
    $fields = devotee_fields([
        'name' => (string) ($input['donor_name'] ?? ''),
        'phone' => (string) ($input['donor_phone'] ?? ''),
        'email' => (string) ($input['donor_email'] ?? ''),
        'address' => (string) ($input['donor_address'] ?? ''),
        'pan' => (string) ($input['donor_pan'] ?? ''),
    ]);
    $target = donation_edit_target_donor($donation, $fields);
    if ($target['error'] !== null) {
        $failed['error'] = $target['error'];
        return $failed;
    }
    $profileError = devotee_profile_error($fields, $target['ignore_id'], $target['kept_email']);
    if ($profileError !== null) {
        $failed['error'] = $profileError;
        return $failed;
    }
    $currentType = (string) $donation['donation_type'];
    $postedType = trim((string) ($input['donation_type'] ?? ''));
    if (donation_type_is_locked($donation)) {
        if ($postedType !== $currentType) {
            $failed['error'] = 'This gift is linked to stock, so its type stays.';
            return $failed;
        }
        $type = $currentType;
    } elseif (!in_array($postedType, donation_amount_types(), true)) {
        $failed['error'] = 'Choose a donation type from the list.';
        return $failed;
    } else {
        $type = $postedType;
    }
    $modes = money_payment_modes();
    $currentMode = (string) $donation['payment_mode'];
    if (!in_array($currentMode, $modes, true)) {
        $modes[] = $currentMode;
    }
    $postedMode = trim((string) ($input['payment_mode'] ?? ''));
    if (!in_array($postedMode, $modes, true)) {
        $failed['error'] = 'Choose a payment from the list.';
        return $failed;
    }
    $date = valid_book_date(trim((string) ($input['donation_date'] ?? '')));
    if ($date === null) {
        $failed['error'] = 'Enter a valid date.';
        return $failed;
    }
    $instrument = normalize_payment_instrument(
        $postedMode,
        (string) ($input['upi_reference'] ?? ''),
        (string) ($input['cheque_number'] ?? ''),
        (string) ($input['cheque_date'] ?? ''),
        !empty($input['cheque_cleared'])
    );
    if ($instrument['error'] !== null) {
        $failed['error'] = $instrument['error'];
        return $failed;
    }
    $purposes = selection_values('purposes');
    $currentPurpose = trim((string) ($donation['purpose'] ?? ''));
    if ($currentPurpose !== '' && !in_array($currentPurpose, $purposes, true)) {
        $purposes[] = $currentPurpose;
    }
    $purpose = one_of(trim((string) ($input['purpose'] ?? '')), $purposes, $currentPurpose !== '' ? $currentPurpose : 'General');
    $rawAmount = trim((string) ($input['amount'] ?? ''));
    $amount = null;
    if (book_account($postedMode) !== null) {
        if ($rawAmount === '' || !is_numeric($rawAmount) || round((float) $rawAmount, 2) <= 0) {
            $failed['error'] = 'Enter an amount greater than zero.';
            return $failed;
        }
        $amount = round((float) $rawAmount, 2);
        if ($amount > 99999999.99) {
            $failed['error'] = 'That amount is too large.';
            return $failed;
        }
    } elseif ($rawAmount !== '' && is_numeric($rawAmount)) {
        $amount = round((float) $rawAmount, 2);
        if ($amount < 0 || $amount > 99999999.99) {
            $failed['error'] = 'That amount is not valid.';
            return $failed;
        }
    }
    $pledgeId = (int) ($input['pledge_id'] ?? 0);
    if ($pledgeId > 0) {
        $pledge = db_one('SELECT id, donor_id FROM pledges WHERE id = ?', [$pledgeId]);
        if ($pledge === null) {
            $failed['error'] = 'That pledge was not found.';
            return $failed;
        }
        if ($target['donor_id'] !== null && (int) $pledge['donor_id'] !== $target['donor_id']) {
            $failed['error'] = 'This pledge belongs to a different devotee.';
            return $failed;
        }
        if ($target['donor_id'] === null) {
            $failed['error'] = 'A new devotee has no pledge yet. Clear the pledge, or keep this devotee.';
            return $failed;
        }
    }
    $values = [
        'donor_name' => $fields['name'],
        'donor_phone' => $fields['phone'] !== '' ? $fields['phone'] : null,
        'donor_email' => $fields['email'] !== '' ? $fields['email'] : null,
        'donor_address' => $fields['address'] !== '' ? $fields['address'] : null,
        'donor_pan' => $fields['pan'] !== '' ? $fields['pan'] : null,
        'donation_type' => $type,
        'amount' => $amount,
        'purpose' => $purpose,
        'donation_date' => $date,
        'payment_mode' => $postedMode,
        'cheque_number' => $instrument['cheque_number'],
        'cheque_date' => $instrument['cheque_date'],
        'cheque_cleared' => (int) $instrument['cheque_cleared'],
        'upi_reference' => $instrument['upi_reference'],
        'pledge_id' => $pledgeId > 0 ? $pledgeId : null,
        'reason' => $reason,
    ];
    if (donation_edit_same($donation, $values)) {
        $failed['error'] = 'Nothing on this gift has changed.';
        return $failed;
    }
    $currentAmount = round((float) ($donation['amount'] ?? 0), 2);
    $bookAmount = corrected_book_amount('donation', (int) $donation['id'], $currentAmount);
    $proposed = $amount ?? 0.0;
    if (!empty($donation['reconciled_bank_txn_id'])) {
        $amountMoved = abs($currentAmount - $proposed) >= 0.01;
        $modeMoved = $postedMode !== $currentMode;
        $dateMoved = $date !== (string) $donation['donation_date'];
        if ($amountMoved || $modeMoved || $dateMoved) {
            $failed['error'] = 'This gift is matched to a bank line. Clear that match before changing the amount, date, or payment.';
            return $failed;
        }
    }
    if (correction_is_open('donation', (int) $donation['id'])) {
        $failed['error'] = 'A correction for this gift is already waiting.';
        return $failed;
    }
    $amountMoved = abs($currentAmount - $proposed) >= 0.01;
    $modeMoved = $postedMode !== $currentMode;
    if (($amountMoved || $modeMoved) && donation_has_approved_correction((int) $donation['id'])) {
        $failed['error'] = 'This gift has an approved correction. Change the amount from Corrections.';
        return $failed;
    }
    return [
        'error' => null,
        'values' => $values,
        'approval_amount' => round(max($bookAmount, $currentAmount, $proposed), 2),
    ];
}

function donation_has_approved_correction(int $donationId): bool
{
    return (int) db_value(
        "SELECT COUNT(*)
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id AND a.status = 'Approved'
         WHERE c.subject_type = 'donation' AND c.subject_id = ?",
        [$donationId]
    ) > 0;
}

/**
 * @param array<string, mixed> $donation
 * @param array{name: string, phone: string, email: string, address: string, pan: string} $fields
 * @return array{error: ?string, donor_id: ?int, ignore_id: ?int, kept_email: ?string}
 */
function donation_edit_target_donor(array $donation, array $fields): array
{
    $empty = ['error' => null, 'donor_id' => null, 'ignore_id' => null, 'kept_email' => null];
    $digits = devotee_phone_digits($fields['phone']);
    if ($digits === '') {
        $empty['donor_id'] = (int) $donation['donor_id'];
        $empty['ignore_id'] = (int) $donation['donor_id'];
        $empty['kept_email'] = (string) ($donation['donor_email'] ?? '');
        return $empty;
    }
    foreach (db_all('SELECT id, phone, email FROM donors') as $row) {
        if (devotee_phone_digits((string) ($row['phone'] ?? '')) !== $digits) {
            continue;
        }
        $empty['donor_id'] = (int) $row['id'];
        $empty['ignore_id'] = (int) $row['id'];
        $empty['kept_email'] = (string) ($row['email'] ?? '');
        return $empty;
    }
    return $empty;
}

/** @param array<string, mixed> $values */
function donation_edit_same(array $donation, array $values): bool
{
    $currentAmount = $donation['amount'] === null || $donation['amount'] === ''
        ? null
        : round((float) $donation['amount'], 2);
    $newAmount = $values['amount'];
    $amountSame = $currentAmount === null && $newAmount === null
        || ($currentAmount !== null && $newAmount !== null && abs($currentAmount - (float) $newAmount) < 0.001);
    $currentPledge = $donation['pledge_id'] === null || $donation['pledge_id'] === ''
        ? null
        : (int) $donation['pledge_id'];
    return $amountSame
        && trim((string) ($donation['donor_name'] ?? '')) === (string) $values['donor_name']
        && devotee_phone_digits((string) ($donation['donor_phone'] ?? '')) === devotee_phone_digits((string) ($values['donor_phone'] ?? ''))
        && strtolower(trim((string) ($donation['donor_email'] ?? ''))) === strtolower(trim((string) ($values['donor_email'] ?? '')))
        && trim((string) ($donation['donor_address'] ?? '')) === trim((string) ($values['donor_address'] ?? ''))
        && strtoupper(trim((string) ($donation['donor_pan'] ?? ''))) === strtoupper(trim((string) ($values['donor_pan'] ?? '')))
        && (string) $donation['donation_type'] === (string) $values['donation_type']
        && trim((string) ($donation['purpose'] ?? '')) === trim((string) ($values['purpose'] ?? ''))
        && (string) $donation['donation_date'] === (string) $values['donation_date']
        && (string) $donation['payment_mode'] === (string) $values['payment_mode']
        && trim((string) ($donation['cheque_number'] ?? '')) === trim((string) ($values['cheque_number'] ?? ''))
        && substr((string) ($donation['cheque_date'] ?? ''), 0, 10) === substr((string) ($values['cheque_date'] ?? ''), 0, 10)
        && (int) ($donation['cheque_cleared'] ?? 0) === (int) $values['cheque_cleared']
        && strtoupper(trim((string) ($donation['upi_reference'] ?? ''))) === strtoupper(trim((string) ($values['upi_reference'] ?? '')))
        && $currentPledge === $values['pledge_id'];
}

/** @param array<string, mixed> $input */
function save_donation_edit(int $donationId, array $input, int $userId): ?string
{
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        db_mutex($pdo, 'donationedit' . $donationId);
        $donation = donation_edit_locked($donationId);
        if ($donation === null) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return 'Donation not found.';
        }
        if (donation_edit_is_open($donationId)) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return 'An edit for this gift is already waiting.';
        }
        $proposal = donation_edit_normalize($donation, $input);
        if ($proposal['error'] !== null) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $proposal['error'];
        }
        $values = $proposal['values'];
        $editId = db_exec(
            'INSERT INTO donation_edits (
                donation_id, donor_name, donor_phone, donor_email, donor_address, donor_pan,
                donation_type, amount, purpose, donation_date, payment_mode,
                cheque_number, cheque_date, cheque_cleared, upi_reference, pledge_id, reason, prepared_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $donationId,
                $values['donor_name'],
                $values['donor_phone'],
                $values['donor_email'],
                $values['donor_address'],
                $values['donor_pan'],
                $values['donation_type'],
                $values['amount'],
                $values['purpose'],
                $values['donation_date'],
                $values['payment_mode'],
                $values['cheque_number'],
                $values['cheque_date'],
                $values['cheque_cleared'],
                $values['upi_reference'],
                $values['pledge_id'],
                $values['reason'],
                $userId,
            ]
        );
        record_approval('donation_edit', $editId, 'Waiting', $proposal['approval_amount'], $userId);
        if ($own) {
            $pdo->commit();
        }
        return null;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!$own) {
            throw $e;
        }
        error_log('[jt_blr] donation edit: ' . $e->getMessage());
        return 'Could not save the edit.';
    }
}

function apply_donation_edit(int $editId): void
{
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $edit = db_one('SELECT * FROM donation_edits WHERE id = ? FOR UPDATE', [$editId]);
        if ($edit === null) {
            throw new DonationEditException('That edit was not found.');
        }
        if ((int) $edit['applied'] === 1) {
            if ($own) {
                $pdo->commit();
            }
            return;
        }
        db_mutex($pdo, 'donationedit' . (int) $edit['donation_id']);
        $donation = donation_edit_locked((int) $edit['donation_id']);
        if ($donation === null) {
            throw new DonationEditException('Donation not found.');
        }
        $proposal = donation_edit_normalize($donation, [
            'donor_name' => (string) $edit['donor_name'],
            'donor_phone' => (string) ($edit['donor_phone'] ?? ''),
            'donor_email' => (string) ($edit['donor_email'] ?? ''),
            'donor_address' => (string) ($edit['donor_address'] ?? ''),
            'donor_pan' => (string) ($edit['donor_pan'] ?? ''),
            'donation_type' => (string) $edit['donation_type'],
            'amount' => $edit['amount'] === null ? '' : (string) $edit['amount'],
            'purpose' => (string) ($edit['purpose'] ?? ''),
            'donation_date' => (string) $edit['donation_date'],
            'payment_mode' => (string) $edit['payment_mode'],
            'upi_reference' => (string) ($edit['upi_reference'] ?? ''),
            'cheque_number' => (string) ($edit['cheque_number'] ?? ''),
            'cheque_date' => substr((string) ($edit['cheque_date'] ?? ''), 0, 10),
            'cheque_cleared' => (int) $edit['cheque_cleared'] === 1,
            'pledge_id' => (int) ($edit['pledge_id'] ?? 0),
            'reason' => (string) $edit['reason'],
        ]);
        if ($proposal['error'] !== null) {
            throw new DonationEditException($proposal['error']);
        }
        $values = $proposal['values'];
        $donorId = donation_edit_write_donor($donation, $values);
        db_exec(
            'UPDATE donations
             SET donor_id = ?, donation_type = ?, amount = ?, purpose = ?, donation_date = ?, payment_mode = ?,
                 cheque_number = ?, cheque_date = ?, cheque_cleared = ?, upi_reference = ?, pledge_id = ?
             WHERE id = ?',
            [
                $donorId,
                $values['donation_type'],
                $values['amount'],
                $values['purpose'],
                $values['donation_date'],
                $values['payment_mode'],
                $values['cheque_number'],
                $values['cheque_date'],
                $values['cheque_cleared'],
                $values['upi_reference'],
                $values['pledge_id'],
                (int) $donation['id'],
            ]
        );
        $marked = db()->prepare('UPDATE donation_edits SET applied = 1 WHERE id = ? AND applied = 0');
        $marked->execute([$editId]);
        if ($marked->rowCount() !== 1) {
            throw new DonationEditException('This edit was already saved.');
        }
        donation_edit_refresh_receipt((int) $donation['id']);
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** @return array<string, mixed>|null */
function donation_edit_locked(int $donationId): ?array
{
    $donation = db_one('SELECT * FROM donations WHERE id = ? FOR UPDATE', [$donationId]);
    if ($donation === null) {
        return null;
    }
    $donor = db_one(
        'SELECT name, phone, email, address, pan_number FROM donors WHERE id = ?',
        [(int) $donation['donor_id']]
    );
    if ($donor === null) {
        return null;
    }
    $donation['donor_name'] = (string) $donor['name'];
    $donation['donor_phone'] = (string) ($donor['phone'] ?? '');
    $donation['donor_email'] = (string) ($donor['email'] ?? '');
    $donation['donor_address'] = (string) ($donor['address'] ?? '');
    $donation['donor_pan'] = (string) ($donor['pan_number'] ?? '');
    return $donation;
}

/** @param array<string, mixed> $values */
function donation_edit_write_donor(array $donation, array $values): int
{
    $fields = devotee_fields([
        'name' => (string) $values['donor_name'],
        'phone' => (string) ($values['donor_phone'] ?? ''),
        'email' => (string) ($values['donor_email'] ?? ''),
        'address' => (string) ($values['donor_address'] ?? ''),
        'pan' => (string) ($values['donor_pan'] ?? ''),
    ]);
    $target = donation_edit_target_donor($donation, $fields);
    if ($target['error'] !== null) {
        throw new DonationEditException($target['error']);
    }
    $saved = save_devotee($target['donor_id'], $fields);
    if ($saved['error'] !== null || (int) $saved['id'] < 1) {
        throw new DonationEditException($saved['error'] ?? 'The devotee could not be saved.');
    }
    return (int) $saved['id'];
}

function donation_edit_refresh_receipt(int $donationId): void
{
    $donation = db_one('SELECT * FROM donations WHERE id = ?', [$donationId]);
    if ($donation === null || (int) ($donation['receipt_generated'] ?? 0) !== 1 || (int) ($donation['receipt_cancelled'] ?? 0) === 1) {
        return;
    }
    $number = trim((string) ($donation['receipt_number'] ?? ''));
    if (!receipt_number_is_valid($number)) {
        return;
    }
    $donor = db_one('SELECT * FROM donors WHERE id = ?', [(int) $donation['donor_id']]);
    if ($donor === null) {
        throw new DonationEditException('The devotee for this receipt was not found.');
    }
    $donation['receipt_share_token'] = receipt_ensure_share_token($donationId);
    generate_receipt_pdf($donation, $donor, $number);
}
