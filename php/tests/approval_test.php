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

check(approval_limit('Staff') === 0.0, 'staff have no approval limit they can use');
check(approval_limit('Treasurer') === 10000.0, 'a treasurer can approve up to 10000');
check(approval_limit('Admin') === null, 'an admin has no amount ceiling');
check(counts_in_books('Approved') === true && counts_in_books('Waiting') === false, 'only an approved line changes a balance');

check(approval_error('Staff', 100, 2, 1, 'approve', 'Waiting', '') !== null, 'staff cannot approve');
check(approval_error('Admin', 50000, 1, 1, 'approve', 'Waiting', '') !== null, 'the preparer cannot approve their own item');
check(approval_error('Treasurer', 10000, 2, 3, 'approve', 'Waiting', '') === null, 'a treasurer can approve 10000 prepared by someone else');
check(approval_error('Treasurer', 10000.01, 2, 3, 'approve', 'Waiting', '') !== null, 'above 10000 the treasurer must leave it for an admin');
check(approval_error('Admin', 80000, 2, 1, 'approve', 'Waiting', '') === null, 'an admin can approve a large amount they did not prepare');
check(approval_error('Admin', 100, 2, 1, 'approve', 'Draft', '') !== null, 'a draft is not ready to approve');
check(approval_error('Treasurer', 50000, 2, 3, 'send_back', 'Waiting', '') !== null, 'sending back needs a note');
check(approval_error('Treasurer', 50000, 2, 3, 'send_back', 'Waiting', 'Missing bill') === null, 'a treasurer can send back an amount they cannot approve');
check(approval_error('Staff', 100, 2, 3, 'reject', 'Waiting', 'No') !== null, 'staff cannot reject');
check(approval_error('Admin', 100, 2, 2, 'resubmit', 'Sent back', '') === null, 'the preparer can submit again after it is sent back');
check(approval_error('Admin', 100, 2, 1, 'resubmit', 'Sent back', '') !== null, 'someone else cannot submit it again');
check(approval_next_status('approve') === 'Approved' && approval_next_status('reject') === 'Rejected', 'decisions map to the next status');

$pdo = db();
$pdo->beginTransaction();
try {
    $expenseId = db_exec(
        'INSERT INTO expenses (category, description, amount, paid_to, expense_date, payment_mode, voucher_number, added_by)
         VALUES (?,?,?,?,?,?,?,?)',
        ['Maintenance', 'waiting paint', 250, 'Waiting Painter Test', '2026-09-02', 'Cash', 'VCH-2026-9999', 1]
    );
    record_approval('expense', $expenseId, 'Waiting', 250, 1);
    $hidden = load_book_movements('2026-09-02', '2026-09-02');
    $seen = false;
    foreach ($hidden as $line) {
        if (str_contains((string) $line['particulars'], 'Waiting Painter Test')) {
            $seen = true;
        }
    }
    check($seen === false, 'a waiting expense stays out of the cash book');
    db_exec("UPDATE approvals SET status = 'Approved' WHERE subject_type = 'expense' AND subject_id = ?", [$expenseId]);
    $shown = load_book_movements('2026-09-02', '2026-09-02');
    $seen = false;
    foreach ($shown as $line) {
        if (str_contains((string) $line['particulars'], 'Waiting Painter Test')) {
            $seen = true;
        }
    }
    check($seen === true, 'the same expense enters the cash book once it is approved');
} finally {
    $pdo->rollBack();
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all approval tests passed\n";
