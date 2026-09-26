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

function reconciliation_report(): array
{
    return db_all(
        "SELECT bt.*, u.filename, u.upload_date,
                d.receipt_number AS donation_receipt, don.name AS donor_name,
                e.description AS expense_description, e.category AS expense_category
         FROM bank_transactions bt
         JOIN bank_statement_uploads u ON bt.upload_batch_id = u.id
         LEFT JOIN donations d ON bt.matched_donation_id = d.id
         LEFT JOIN donors don ON d.donor_id = don.id
         LEFT JOIN expenses e ON bt.matched_expense_id = e.id
         ORDER BY bt.txn_date DESC"
    );
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
