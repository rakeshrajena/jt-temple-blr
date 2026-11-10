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

$pdo = db();
$pdo->beginTransaction();
try {
    $mobile = '8' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
    $subId = add_subscriber(subscriber_input([
        'name' => 'E2E Subscriber',
        'mobile' => $mobile,
        'email' => 'e2e.sub@example.com',
        'plan_name' => 'Annadan Seva',
        'plan_amount' => '500',
        'frequency' => 'Monthly',
        'status' => 'Active',
    ])['values']);

    check(subscriber_can_generate_invoice($subId), 'new active subscriber can get first invoice');
    $inv = create_subscription_invoice($subId);
    check($inv['error'] === null && !$inv['reused'], 'first invoice is created for current period');
    check(!subscriber_can_generate_invoice($subId), 'no second invoice while first is unpaid');

    $invId = (int) $inv['id'];
    db_exec(
        "UPDATE subscription_invoices SET due_date = ? WHERE id = ?",
        [date('Y-m-d', strtotime('-2 days')), $invId]
    );
    subscription_mark_overdue_invoices();
    check(db_value('SELECT status FROM subscription_invoices WHERE id = ?', [$invId]) === 'Overdue', 'past due date becomes Overdue');

    check(record_paid_invoice($invId, 'UPI', 'pay_e2e_001') === 'saved', 'first payment posts donation and marks Paid');
    $paid = db_one('SELECT * FROM subscription_invoices WHERE id = ?', [$invId]);
    check($paid['status'] === 'Paid' && (int) ($paid['linked_donation_id'] ?? 0) > 0, 'invoice links to ledger donation');
    $donation = db_one('SELECT * FROM donations WHERE id = ?', [(int) $paid['linked_donation_id']]);
    check(
        $donation !== null
        && str_contains((string) $donation['purpose'], 'Subscription')
        && (float) $donation['amount'] === 500.0,
        'donation journal row matches subscription amount and purpose'
    );

    check(record_paid_invoice($invId, 'UPI', 'pay_e2e_002') === 'paid', 'duplicate payment does not create another donation');
    $donCount = (int) db_value('SELECT COUNT(*) FROM donations WHERE id = ?', [(int) $paid['linked_donation_id']]);
    check($donCount === 1, 'linked donation row stays single');

    $r1 = issue_subscription_receipt($invId, null);
    $r2 = issue_subscription_receipt($invId, null);
    check($r1['created'] === true && $r2['created'] === false && $r1['number'] === $r2['number'], 'receipt is issued once automatically');

    check(subscriber_period_paid($subId), 'subscriber shows paid for this period');
    check(!subscriber_can_generate_invoice($subId), 'no new invoice after period paid');
    $blocked = create_subscription_invoice($subId);
    check($blocked['error'] !== null, 'create invoice blocked when period paid');

    check(
        (int) db_value(
            "SELECT COUNT(*) FROM subscription_invoices WHERE subscriber_id = ? AND period_label = ?",
            [$subId, subscription_current_period_label()]
        ) === 1,
        'only one invoice row for this billing period'
    );
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo $failed === 0 ? "passed\n" : "failed {$failed}\n";
exit($failed === 0 ? 0 : 1);
