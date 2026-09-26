<?php
declare(strict_types=1);

function approval_limit(string $role): ?float
{
    return match ($role) {
        'Treasurer' => TREASURER_APPROVAL_LIMIT,
        'Admin' => null,
        default => 0.0,
    };
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
    if ($role !== 'Treasurer' && $role !== 'Admin') {
        return 'Staff cannot decide an approval.';
    }
    if ($decision === 'approve') {
        $limit = approval_limit($role);
        if ($limit !== null && $amount > $limit) {
            return 'This amount needs an Admin.';
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
            subject_type    ENUM('expense','contra','opening','purchase','correction','receipt','stock') NOT NULL,
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
