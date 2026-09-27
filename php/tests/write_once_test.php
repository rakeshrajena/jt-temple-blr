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

$busy = (string) file_get_contents(dirname(__DIR__) . '/static/js/busy.js');
check(
    str_contains($busy, "if (shown) {\n      event.preventDefault();"),
    'a second click does not submit again'
);

$pdo = db();
$pdo->beginTransaction();
try {
    $token = bin2hex(random_bytes(16));
    check(claim_write($token) === true, 'the first save claims the token');
    check(claim_write($token) === false, 'the same token cannot save a second time');

    $expenseId = db_exec(
        'INSERT INTO expenses (category, description, amount, paid_to, expense_date, payment_mode, voucher_number, added_by)
         VALUES (?,?,?,?,?,?,?,?)',
        ['Maintenance', 'write once paint', 10, 'Write Once Painter', '2026-09-03', 'Cash', 'VCH-2026-9988', 1]
    );
    record_approval('expense', $expenseId, 'Waiting', 10, 1);
    $approvalId = (int) db_value(
        "SELECT id FROM approvals WHERE subject_type = 'expense' AND subject_id = ?",
        [$expenseId]
    );
    check(approval_claim_decision($approvalId, 'Waiting', 'Approved', 1, null) === true, 'the first decision is saved');
    check(approval_claim_decision($approvalId, 'Waiting', 'Approved', 1, null) === false, 'a second decision does not change the books');
    check(
        db_value('SELECT status FROM approvals WHERE id = ?', [$approvalId]) === 'Approved',
        'the approval stays approved after the second decision'
    );

    $mobile = '9' . (string) random_int(100000000, 999999999);
    $subscriberId = db_exec(
        'INSERT INTO subscribers (name, mobile, email, plan_name, plan_amount, frequency, status, start_date)
         VALUES (?,?,?,?,?,?,?,?)',
        ['Write Once Devotee', $mobile, null, 'Test Seva', 25, 'Monthly', 'Active', '2026-09-01']
    );
    $invoiceId = db_exec(
        'INSERT INTO subscription_invoices (subscriber_id, invoice_number, amount, period_label, due_date, status, payment_token)
         VALUES (?,?,?,?,?,?,?)',
        [$subscriberId, 'INV-ONCE-' . $mobile, 25, 'Sep 2026', '2026-09-30', 'Sent', bin2hex(random_bytes(16))]
    );
    check(record_paid_invoice($invoiceId, 'UPI', 'PAY-ONCE') === 'saved', 'the first invoice payment is saved');
    check(record_paid_invoice($invoiceId, 'UPI', 'PAY-TWICE') === 'paid', 'a second payment does not create another donation');
    $gifts = (int) db_value(
        "SELECT COUNT(*) FROM donations WHERE purpose LIKE 'Subscription — Test Seva%' AND upi_reference IN ('PAY-ONCE', 'PAY-TWICE')"
    );
    check($gifts === 1, 'the invoice has one donation in the books');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all write-once tests passed\n";
