<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = 0;

function check(bool $ok, string $name): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n";
}

check(next_financial_year('2026-2027') === '2027-2028', 'the next financial year starts the following April');
$badYear = false;
try {
    next_financial_year('2026-2028');
} catch (InvalidArgumentException) {
    $badYear = true;
}
check($badYear, 'a label that skips a year is rejected');

$admin = db_one("SELECT id FROM users WHERE username = 'admin'");
$treasurer = db_one("SELECT id FROM users WHERE username = 'treasurer'");
check($admin !== null && $treasurer !== null, 'demo admin and treasurer exist');

$pdo = db();
$pdo->beginTransaction();
try {
    $adminId = (int) $admin['id'];
    $treasurerId = (int) $treasurer['id'];

    $sourceId = db_exec(
        'INSERT INTO opening_balances (financial_year, cash_amount, bank_amount, note, set_by) VALUES (?,?,?,?,?)',
        ['2098-2099', 100, 40, 'test opening', $adminId]
    );
    record_approval('opening', $sourceId, 'Approved', 100, $adminId);
    $contraId = db_exec(
        'INSERT INTO contra_entries (entry_date, direction, amount, note, entered_by) VALUES (?,?,?,?,?)',
        ['2098-06-01', 'Deposit', 20, 'test deposit', $adminId]
    );
    record_approval('contra', $contraId, 'Approved', 20, $adminId);

    $closing = year_closing_balance('2098-2099');
    check($closing['cash'] === 80.0 && $closing['bank'] === 60.0, 'a full-year closing includes the opening and later movements');

    $carried = submit_carried_opening('2098-2099', $adminId);
    $nextOpening = load_opening_balance('2099-2100');
    $pending = db_one('SELECT pending_cash, pending_bank, pending_note, cash_amount, bank_amount FROM opening_balances WHERE financial_year = ?', ['2099-2100']);
    $approval = db_one("SELECT status, amount FROM approvals WHERE subject_type = 'opening' AND subject_id = (SELECT id FROM opening_balances WHERE financial_year = '2099-2100')");
    check(
        $carried['error'] === null
        && $carried['next_year'] === '2099-2100'
        && $carried['cash'] === 80.0
        && $carried['bank'] === 60.0
        && $nextOpening['cash'] === 0.0
        && $nextOpening['bank'] === 0.0
        && (float) $pending['pending_cash'] === 80.0
        && (float) $pending['pending_bank'] === 60.0
        && $pending['pending_note'] === 'Brought forward from 2098-2099'
        && $approval['status'] === 'Waiting'
        && (float) $approval['amount'] === 80.0,
        'a carried opening waits, and the next year still opens at zero'
    );

    $nextId = (int) db_value('SELECT id FROM opening_balances WHERE financial_year = ?', ['2099-2100']);
    db_exec(
        "UPDATE approvals SET status = 'Approved', decided_by = ? WHERE subject_type = 'opening' AND subject_id = ?",
        [$treasurerId, $nextId]
    );
    db_exec(
        'UPDATE opening_balances
         SET cash_amount = COALESCE(pending_cash, cash_amount),
             bank_amount = COALESCE(pending_bank, bank_amount),
             note = COALESCE(pending_note, note),
             pending_cash = NULL, pending_bank = NULL, pending_note = NULL
         WHERE id = ?',
        [$nextId]
    );
    $approved = load_opening_balance('2099-2100');
    check(
        $approved['cash'] === 80.0 && $approved['bank'] === 60.0 && $approved['note'] === 'Brought forward from 2098-2099',
        'after approval the next year opens with the previous closing'
    );

    $withdrawId = db_exec(
        'INSERT INTO contra_entries (entry_date, direction, amount, note, entered_by) VALUES (?,?,?,?,?)',
        ['2097-06-01', 'Withdraw', 5, 'test withdraw', $adminId]
    );
    record_approval('contra', $withdrawId, 'Approved', 5, $adminId);
    $negative = submit_carried_opening('2097-2098', $adminId);
    $sourceAfter = db_one('SELECT cash_amount, pending_cash FROM opening_balances WHERE id = ?', [$sourceId]);
    check(
        $negative['error'] !== null
        && $sourceAfter['pending_cash'] === null
        && (float) $sourceAfter['cash_amount'] === 100.0,
        'a negative closing is not copied into the next opening'
    );
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all carry tests passed\n";
