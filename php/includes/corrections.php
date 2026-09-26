<?php
declare(strict_types=1);

/**
 * How a correction moves cash or bank. The original line is left as it was.
 * A smaller donation, or a larger expense, is a payment. The opposite is a receipt.
 *
 * @return array{receipt_cash: float, receipt_bank: float, payment_cash: float, payment_bank: float}|null
 */
function correction_effect(string $subjectType, string $paymentMode, float $currentAmount, float $correctedAmount): ?array
{
    $account = book_account($paymentMode);
    $delta = round($correctedAmount - $currentAmount, 2);
    if ($account === null || abs($delta) < 0.01) {
        return null;
    }
    $size = abs($delta);
    $isReceipt = $subjectType === 'donation' ? $delta > 0 : $delta < 0;
    return [
        'receipt_cash' => $isReceipt && $account === 'cash' ? $size : 0.0,
        'receipt_bank' => $isReceipt && $account === 'bank' ? $size : 0.0,
        'payment_cash' => !$isReceipt && $account === 'cash' ? $size : 0.0,
        'payment_bank' => !$isReceipt && $account === 'bank' ? $size : 0.0,
    ];
}

function correction_request_error(
    string $kind,
    float $currentAmount,
    float $correctedAmount,
    string $reason,
    string $date,
    ?string $account,
    bool $blocked
): ?string {
    if ($kind !== 'void' && $kind !== 'adjust') {
        return 'Choose void or a new amount.';
    }
    if ($account === null) {
        return 'This line is not in the cash book.';
    }
    if ($blocked) {
        return 'A correction for this line is already waiting.';
    }
    if (mb_strlen(trim($reason)) < 3) {
        return 'Write a short reason for the correction.';
    }
    if (valid_book_date($date) === null) {
        return 'Enter a valid date.';
    }
    if ($correctedAmount < 0 || $correctedAmount > 99999999.99) {
        return 'The corrected amount is not valid.';
    }
    if ($kind === 'void' && $correctedAmount > 0.001) {
        return 'A void sets the amount to zero.';
    }
    if ($currentAmount <= 0) {
        return 'This line is already at zero.';
    }
    if (abs($correctedAmount - $currentAmount) < 0.01) {
        return 'The corrected amount is the same as the amount in the books.';
    }
    return null;
}

function receipt_cancel_request_error(bool $generated, bool $cancelled, bool $pending, string $reason): ?string
{
    if (!$generated) {
        return 'That donation has no receipt.';
    }
    if ($cancelled) {
        return 'That receipt is already cancelled.';
    }
    if ($pending) {
        return 'A cancellation is already waiting.';
    }
    if (mb_strlen(trim($reason)) < 3) {
        return 'Write a short reason for the cancellation.';
    }
    return null;
}

function receipt_cancel_badge(mixed $cancelled, string $reason = ''): string
{
    if ((int) $cancelled !== 1) {
        return '';
    }
    $html = ' <span class="badge badge-red">Cancelled</span>';
    $reason = trim($reason);
    if ($reason !== '') {
        $html .= '<br><span style="color:var(--ink-soft);font-size:12px;">' . e($reason) . '</span>';
    }
    return $html;
}

function ensure_correction_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS corrections (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            subject_type      ENUM('donation','expense') NOT NULL,
            subject_id        INT NOT NULL,
            original_amount   DECIMAL(12,2) NOT NULL,
            corrected_amount  DECIMAL(12,2) NOT NULL,
            reason            VARCHAR(500) NOT NULL,
            entry_date        DATE NOT NULL,
            prepared_by       INT NOT NULL,
            created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (prepared_by) REFERENCES users(id),
            KEY idx_correction_subject (subject_type, subject_id),
            KEY idx_correction_date (entry_date)
        ) ENGINE=InnoDB"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS receipt_cancellations (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            donation_id   INT NOT NULL,
            reason        VARCHAR(500) NOT NULL,
            prepared_by   INT NOT NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (donation_id) REFERENCES donations(id),
            FOREIGN KEY (prepared_by) REFERENCES users(id),
            KEY idx_receipt_cancel_donation (donation_id)
        ) ENGINE=InnoDB"
    );
    ensure_column($pdo, 'donations', 'receipt_cancelled', 'TINYINT(1) NOT NULL DEFAULT 0');
}

function corrected_book_amount(string $subjectType, int $subjectId, float $postedAmount): float
{
    $delta = (float) db_value(
        "SELECT COALESCE(SUM(c.corrected_amount - c.original_amount), 0)
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id AND a.status = 'Approved'
         WHERE c.subject_type = ? AND c.subject_id = ?",
        [$subjectType, $subjectId]
    );
    return round($postedAmount + $delta, 2);
}

function correction_is_open(string $subjectType, int $subjectId): bool
{
    return (int) db_value(
        "SELECT COUNT(*)
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id
         WHERE c.subject_type = ? AND c.subject_id = ? AND a.status IN ('Draft', 'Waiting', 'Sent back')",
        [$subjectType, $subjectId]
    ) > 0;
}

function receipt_cancel_is_open(int $donationId): bool
{
    return (int) db_value(
        "SELECT COUNT(*)
         FROM receipt_cancellations rc
         JOIN approvals a ON a.subject_type = 'receipt' AND a.subject_id = rc.id
         WHERE rc.donation_id = ? AND a.status IN ('Draft', 'Waiting', 'Sent back')",
        [$donationId]
    ) > 0;
}

/** @return array{id: int, amount: float, payment_mode: string}|null */
function correction_posted(string $subjectType, int $subjectId): ?array
{
    if ($subjectType === 'donation') {
        $row = db_one('SELECT id, amount, payment_mode FROM donations WHERE id = ?', [$subjectId]);
    } elseif ($subjectType === 'expense') {
        $row = db_one(
            "SELECT e.id, e.amount, e.payment_mode
             FROM expenses e
             JOIN approvals a ON a.subject_type = 'expense' AND a.subject_id = e.id AND a.status = 'Approved'
             WHERE e.id = ?",
            [$subjectId]
        );
    } else {
        return null;
    }
    if ($row === null) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'amount' => round((float) $row['amount'], 2),
        'payment_mode' => (string) $row['payment_mode'],
    ];
}

/** @return list<array{value: string, label: string}> */
function correction_targets(): array
{
    $deltas = [];
    foreach (db_all(
        "SELECT c.subject_type, c.subject_id, SUM(c.corrected_amount - c.original_amount) AS delta
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id AND a.status = 'Approved'
         GROUP BY c.subject_type, c.subject_id"
    ) as $row) {
        $deltas[(string) $row['subject_type'] . ':' . (string) $row['subject_id']] = (float) $row['delta'];
    }
    $targets = [];
    $donations = db_all(
        'SELECT d.id, d.amount, d.donation_date, d.payment_mode, d.receipt_number, don.name AS donor_name
         FROM donations d
         JOIN donors don ON don.id = d.donor_id
         ORDER BY d.donation_date DESC, d.id DESC
         LIMIT 200'
    );
    foreach ($donations as $row) {
        if (book_account((string) $row['payment_mode']) === null) {
            continue;
        }
        $net = round((float) $row['amount'] + ($deltas['donation:' . $row['id']] ?? 0.0), 2);
        if ($net <= 0) {
            continue;
        }
        $label = (string) $row['donation_date'] . ' · ' . (string) $row['donor_name'] . ' · ' . money($net);
        if (!empty($row['receipt_number'])) {
            $label .= ' · ' . (string) $row['receipt_number'];
        }
        $targets[] = ['value' => 'donation:' . $row['id'], 'label' => $label];
    }
    $expenses = db_all(
        "SELECT e.id, e.amount, e.expense_date, e.payment_mode, e.category, e.paid_to, e.voucher_number
         FROM expenses e
         JOIN approvals a ON a.subject_type = 'expense' AND a.subject_id = e.id AND a.status = 'Approved'
         ORDER BY e.expense_date DESC, e.id DESC
         LIMIT 200"
    );
    foreach ($expenses as $row) {
        if (book_account((string) $row['payment_mode']) === null) {
            continue;
        }
        $net = round((float) $row['amount'] + ($deltas['expense:' . $row['id']] ?? 0.0), 2);
        if ($net <= 0) {
            continue;
        }
        $who = trim((string) ($row['paid_to'] ?? ''));
        $label = ((string) ($row['voucher_number'] ?? '') !== '' ? (string) $row['voucher_number'] : 'Expense')
            . ' · ' . (string) $row['expense_date'] . ' · ' . (string) $row['category'];
        if ($who !== '') {
            $label .= ' · ' . $who;
        }
        $label .= ' · ' . money($net);
        $targets[] = ['value' => 'expense:' . $row['id'], 'label' => $label];
    }
    return $targets;
}

/** @return list<array<string, mixed>> */
function correction_history(): array
{
    return db_all(
        "SELECT c.id, c.subject_type, c.original_amount, c.corrected_amount, c.reason, c.entry_date,
                a.status, p.full_name AS prepared_name, don.name AS donor_name, e.voucher_number, e.paid_to
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id
         JOIN users p ON p.id = c.prepared_by
         LEFT JOIN donations d ON c.subject_type = 'donation' AND d.id = c.subject_id
         LEFT JOIN donors don ON don.id = d.donor_id
         LEFT JOIN expenses e ON c.subject_type = 'expense' AND e.id = c.subject_id
         ORDER BY c.id DESC
         LIMIT 50"
    );
}

function apply_receipt_cancellation(int $cancellationId): void
{
    $row = db_one('SELECT donation_id FROM receipt_cancellations WHERE id = ?', [$cancellationId]);
    if ($row === null) {
        throw new RuntimeException('That receipt cancellation was not found.');
    }
    db_exec('UPDATE donations SET receipt_cancelled = 1 WHERE id = ?', [(int) $row['donation_id']]);
}

/** @return list<array<string, mixed>> */
function approved_correction_rows(string $from, string $to): array
{
    return db_all(
        "SELECT c.id, c.subject_type, c.original_amount, c.corrected_amount, c.reason, c.entry_date,
                u.full_name AS entered_by_name,
                d.payment_mode AS donation_mode, d.purpose, don.name AS donor_name,
                e.payment_mode AS expense_mode, e.category, e.paid_to
         FROM corrections c
         JOIN approvals a ON a.subject_type = 'correction' AND a.subject_id = c.id AND a.status = 'Approved'
         LEFT JOIN users u ON u.id = c.prepared_by
         LEFT JOIN donations d ON c.subject_type = 'donation' AND d.id = c.subject_id
         LEFT JOIN donors don ON don.id = d.donor_id
         LEFT JOIN expenses e ON c.subject_type = 'expense' AND e.id = c.subject_id
         WHERE c.entry_date BETWEEN ? AND ?",
        [$from, $to]
    );
}

/** @return array<string, mixed>|null */
function correction_movement(array $row): ?array
{
    $type = (string) $row['subject_type'];
    $mode = $type === 'expense' ? (string) ($row['expense_mode'] ?? '') : (string) ($row['donation_mode'] ?? '');
    $effect = correction_effect($type, $mode, (float) $row['original_amount'], (float) $row['corrected_amount']);
    if ($effect === null) {
        return null;
    }
    $who = $type === 'expense' ? trim((string) ($row['paid_to'] ?? '')) : trim((string) ($row['donor_name'] ?? ''));
    $particulars = 'Correction — ' . ($who !== '' ? $who : 'Entry');
    $particulars .= ' — was ' . money((float) $row['original_amount']) . ' now ' . money((float) $row['corrected_amount']);
    $reason = trim((string) ($row['reason'] ?? ''));
    if ($reason !== '') {
        $particulars .= ' — ' . $reason;
    }
    return book_movement(
        (string) $row['entry_date'],
        3,
        (int) $row['id'],
        $particulars,
        'correction',
        (string) ($row['entered_by_name'] ?? ''),
        $effect['receipt_cash'],
        $effect['receipt_bank'],
        $effect['payment_cash'],
        $effect['payment_bank']
    );
}

/** @return array<string, mixed>|null */
function correction_ledger_line(array $row): ?array
{
    $type = (string) $row['subject_type'];
    $mode = $type === 'expense' ? (string) ($row['expense_mode'] ?? '') : (string) ($row['donation_mode'] ?? '');
    if (book_account($mode) === null) {
        return null;
    }
    $delta = round((float) $row['corrected_amount'] - (float) $row['original_amount'], 2);
    if (abs($delta) < 0.01) {
        return null;
    }
    $head = $type === 'expense'
        ? ledger_head_name((string) ($row['category'] ?? ''), 'Other')
        : ledger_head_name((string) ($row['purpose'] ?? ''), 'General');
    return [
        'head' => $head,
        'date' => (string) $row['entry_date'],
        'sort' => 300000000 + (int) $row['id'],
        'particulars' => 'Correction — ' . trim((string) ($row['reason'] ?? '')),
        'entered_by' => (string) ($row['entered_by_name'] ?? ''),
        'received' => $type === 'donation' ? $delta : 0.0,
        'spent' => $type === 'expense' ? $delta : 0.0,
    ];
}
