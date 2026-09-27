<?php
declare(strict_types=1);

function approval_limit(string $role): ?float
{
    return match ($role) {
        'Treasurer' => stored_approval_limit('approval_limit_treasurer', TREASURER_APPROVAL_LIMIT),
        'Staff' => stored_approval_limit('approval_limit_staff', 0.0),
        'Admin' => null,
        default => 0.0,
    };
}

function stored_approval_limit(string $key, float $fallback): float
{
    $row = db_one('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$key]);
    if ($row === null) {
        return $fallback;
    }
    $value = trim((string) ($row['setting_value'] ?? ''));
    if ($value === '' || !is_numeric($value) || (float) $value < 0) {
        return $fallback;
    }
    return round((float) $value, 2);
}

function approval_limit_error(string $treasurerRaw, string $staffRaw): ?string
{
    if (preg_match('/^\d+(\.\d{1,2})?$/', $treasurerRaw) !== 1 || preg_match('/^\d+(\.\d{1,2})?$/', $staffRaw) !== 1) {
        return 'Enter each approval limit as an amount in rupees.';
    }
    $treasurer = round((float) $treasurerRaw, 2);
    $staff = round((float) $staffRaw, 2);
    if ($treasurer > 100000000 || $staff > 100000000) {
        return 'An approval limit cannot be more than ₹10,00,00,000.';
    }
    if ($staff > $treasurer) {
        return 'The Staff limit cannot be higher than the Treasurer limit.';
    }
    return null;
}

function save_approval_limits(string $treasurerRaw, string $staffRaw): ?string
{
    $error = approval_limit_error($treasurerRaw, $staffRaw);
    if ($error !== null) {
        return $error;
    }
    brand_upsert('approval_limit_treasurer', number_format((float) $treasurerRaw, 2, '.', ''));
    brand_upsert('approval_limit_staff', number_format((float) $staffRaw, 2, '.', ''));
    return null;
}

function counts_in_books(string $status): bool
{
    return $status === 'Approved';
}

function approval_error(
    string $role,
    float $amount,
    int $preparerId,
    int $actorId,
    string $decision,
    string $status,
    string $note
): ?string {
    $note = trim($note);
    if ($decision === 'submit') {
        if ($status !== 'Draft') {
            return 'Only a draft can be submitted.';
        }
        if ($actorId !== $preparerId) {
            return 'Only the person who prepared this can submit it.';
        }
        return null;
    }
    if ($decision === 'resubmit') {
        if ($status !== 'Sent back') {
            return 'Only an item sent back can be submitted again.';
        }
        if ($actorId !== $preparerId) {
            return 'Only the person who prepared this can submit it again.';
        }
        return null;
    }
    if (!in_array($decision, ['approve', 'send_back', 'reject'], true)) {
        return 'That decision is not recognised.';
    }
    if ($status !== 'Waiting') {
        return 'Only a waiting item can be decided.';
    }
    if ($actorId === $preparerId) {
        return 'You cannot decide an item you prepared.';
    }
    if ($role !== 'Treasurer' && $role !== 'Admin' && $role !== 'Staff') {
        return 'Staff cannot decide an approval.';
    }
    if ($role === 'Staff' && approval_limit('Staff') <= 0.0) {
        return 'Staff cannot decide an approval.';
    }
    if ($decision === 'approve') {
        $limit = approval_limit($role);
        if ($limit !== null && $amount > $limit) {
            return 'This amount is above your approval limit.';
        }
        return null;
    }
    if ($note === '') {
        return 'Write a note explaining the decision.';
    }
    return null;
}

function approval_next_status(string $decision): string
{
    return match ($decision) {
        'submit', 'resubmit' => 'Waiting',
        'approve' => 'Approved',
        'send_back' => 'Sent back',
        'reject' => 'Rejected',
        default => throw new InvalidArgumentException('That decision is not recognised.'),
    };
}

function ensure_approval_schema(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    $type = is_array($column) ? (string) ($column['Type'] ?? '') : '';
    if (!str_contains($type, 'Treasurer')) {
        $pdo->exec(
            "ALTER TABLE users MODIFY role ENUM('Admin','Treasurer','Staff') NOT NULL DEFAULT 'Staff'"
        );
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS approvals (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            subject_type    ENUM('expense','contra','opening','purchase','correction','receipt','stock','coupon','donation_edit') NOT NULL,
            subject_id      INT NOT NULL,
            status          ENUM('Draft','Waiting','Approved','Sent back','Rejected') NOT NULL DEFAULT 'Draft',
            amount          DECIMAL(14,2) NOT NULL DEFAULT 0,
            prepared_by     INT NOT NULL,
            decided_by      INT NULL,
            decision_note   VARCHAR(500) NULL,
            updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_approval_subject (subject_type, subject_id),
            FOREIGN KEY (prepared_by) REFERENCES users(id),
            FOREIGN KEY (decided_by) REFERENCES users(id)
        ) ENGINE=InnoDB"
    );
    $subject = $pdo->query("SHOW COLUMNS FROM approvals LIKE 'subject_type'")->fetch();
    $subjectType = is_array($subject) ? (string) ($subject['Type'] ?? '') : '';
    if (!str_contains($subjectType, 'donation_edit')) {
        $pdo->exec(
            "ALTER TABLE approvals MODIFY subject_type ENUM('expense','contra','opening','purchase','correction','receipt','stock','coupon','donation_edit') NOT NULL"
        );
    }
    ensure_column($pdo, 'opening_balances', 'pending_cash', 'DECIMAL(12,2) NULL');
    ensure_column($pdo, 'opening_balances', 'pending_bank', 'DECIMAL(12,2) NULL');
    ensure_column($pdo, 'opening_balances', 'pending_note', 'VARCHAR(255) NULL');
    backfill_approvals($pdo);
    ensure_demo_treasurer($pdo);
}

function backfill_approvals(PDO $pdo): void
{
    $pdo->exec(
        "INSERT INTO approvals (subject_type, subject_id, status, amount, prepared_by)
         SELECT 'expense', e.id, 'Approved', e.amount, COALESCE(e.added_by, 1)
         FROM expenses e
         LEFT JOIN approvals a ON a.subject_type = 'expense' AND a.subject_id = e.id
         WHERE a.id IS NULL"
    );
    $pdo->exec(
        "INSERT INTO approvals (subject_type, subject_id, status, amount, prepared_by)
         SELECT 'contra', c.id, 'Approved', c.amount, COALESCE(c.entered_by, 1)
         FROM contra_entries c
         LEFT JOIN approvals a ON a.subject_type = 'contra' AND a.subject_id = c.id
         WHERE a.id IS NULL"
    );
    $pdo->exec(
        "INSERT INTO approvals (subject_type, subject_id, status, amount, prepared_by)
         SELECT 'opening', o.id, 'Approved', GREATEST(o.cash_amount, o.bank_amount), COALESCE(o.set_by, 1)
         FROM opening_balances o
         LEFT JOIN approvals a ON a.subject_type = 'opening' AND a.subject_id = o.id
         WHERE a.id IS NULL"
    );
    $pdo->exec(
        "INSERT INTO approvals (subject_type, subject_id, status, amount, prepared_by)
         SELECT 'coupon', b.id, 'Approved', b.total_value, COALESCE(b.created_by, 1)
         FROM food_coupon_batches b
         LEFT JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
         WHERE a.id IS NULL"
    );
}

function ensure_demo_treasurer(PDO $pdo): void
{
    $exists = $pdo->query("SELECT id FROM users WHERE username = 'treasurer'")->fetch();
    if ($exists !== false) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)'
    );
    $stmt->execute(['treasurer', password_hash('treasurer@123', PASSWORD_DEFAULT), 'Lakshmi (Treasurer)', 'Treasurer']);
}

function approval_claim_decision(int $id, string $fromStatus, string $next, ?int $decidedBy, ?string $note): bool
{
    $stmt = db()->prepare(
        'UPDATE approvals SET status = ?, decided_by = ?, decision_note = ? WHERE id = ? AND status = ?'
    );
    $stmt->execute([$next, $decidedBy, $note, $id, $fromStatus]);
    return $stmt->rowCount() === 1;
}

function record_approval(string $type, int $subjectId, string $status, float $amount, int $userId): void
{
    db_exec(
        'INSERT INTO approvals (subject_type, subject_id, status, amount, prepared_by, decided_by, decision_note)
         VALUES (?,?,?,?,?,NULL,NULL)
         ON DUPLICATE KEY UPDATE status = VALUES(status), amount = VALUES(amount), prepared_by = VALUES(prepared_by),
             decided_by = NULL, decision_note = NULL',
        [$type, $subjectId, $status, round($amount, 2), $userId]
    );
}
