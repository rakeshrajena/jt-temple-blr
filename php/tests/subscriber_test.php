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

/** @return array<string, string> */
function subscriber_post(array $overrides = []): array
{
    return array_merge([
        'name' => 'Seva Tester',
        'mobile' => '9000000000',
        'email' => '',
        'family_members' => 'Lata, Arun',
        'gotra' => 'Kasyapa',
        'seva_date' => '2026-10-02',
        'plan_name' => 'annadan seva',
        'plan_amount' => '501',
        'frequency' => 'quarterly',
        'status' => 'active',
    ], $overrides);
}

$parsed = subscriber_input(subscriber_post());
check($parsed['error'] === null, 'a complete subscriber form is accepted');
check($parsed['values']['plan_name'] === 'Annadan Seva' && $parsed['values']['frequency'] === 'Quarterly' && $parsed['values']['status'] === 'Active', 'plan, cycle, and status take the Settings spelling');
check($parsed['values']['email'] === null && $parsed['values']['seva_date'] === '2026-10-02', 'a blank email is stored as empty and the seva date is kept');
check(subscriber_input(subscriber_post(['status' => '']))['values']['status'] === 'Active', 'a blank status starts as Active');
check(subscriber_input(subscriber_post(['name' => '']))['error'] !== null, 'a name is required');
check(subscriber_input(subscriber_post(['plan_amount' => '0']))['error'] !== null, 'the amount must be above zero');
check(subscriber_input(subscriber_post(['email' => 'not-an-email']))['error'] !== null, 'a wrong email is refused');
check(subscriber_input(subscriber_post(['seva_date' => '2026-02-30']))['error'] !== null, 'an impossible seva date is refused');
check(subscriber_input(subscriber_post(['plan_name' => 'Not a plan']))['error'] !== null, 'a plan outside Settings is refused');
check(subscriber_input(subscriber_post(['frequency' => 'Weekly']))['error'] !== null, 'a billing cycle outside Settings is refused');
check(subscriber_input(subscriber_post(['status' => 'Gone']))['error'] !== null, 'a status outside Settings is refused');

check(subscriber_can_invoice('Active'), 'an active subscriber can get an invoice');
foreach (['Paused', 'Inactive', 'Cancelled'] as $stopped) {
    check(!subscriber_can_invoice($stopped), "a {$stopped} subscriber cannot get an invoice");
}

$invoice = [
    'name' => 'Seva <Tester>',
    'invoice_number' => 'INV-2026-0042',
    'amount' => '1100.00',
    'period_label' => 'September 2026',
    'due_date' => '2026-09-27',
    'plan_name' => 'Annadan Seva',
    'frequency' => 'Monthly',
];
$payUrl = 'https://temple.example/pay/abc123';
$mail = subscription_request_email($invoice, $payUrl);
check(str_contains($mail['subject'], 'INV-2026-0042') && str_contains($mail['subject'], 'September 2026'), 'the request subject names the invoice and period');
foreach (['Namaskar Seva <Tester>', 'Annadan Seva', 'Monthly', 'September 2026', '₹1,100', '27 Sep 2026', 'INV-2026-0042', $payUrl] as $part) {
    check(str_contains($mail['text'], $part), "the request text includes {$part}");
}
check(str_contains($mail['html'], 'href="' . $payUrl . '"') && str_contains($mail['html'], '₹1,100'), 'the request email has a pay button and the amount');
check(str_contains($mail['html'], 'Seva &lt;Tester&gt;') && !str_contains($mail['html'], '<Tester>'), 'the name is escaped in the email');
check(subscription_request_block_reason('devotee@example.com', true) === null, 'a subscriber with an email can be sent the request');
check(subscription_request_block_reason(null, true) !== null, 'a subscriber without an email is not emailed');
check(subscription_request_block_reason('devotee@example.com', false) !== null, 'nothing is emailed until outgoing mail is set up');

$mobile = '9' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
$otherMobile = '8' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
$userId = (int) db_value('SELECT id FROM users ORDER BY id LIMIT 1');
$pdo = db();
$pdo->beginTransaction();
try {
    $values = subscriber_input(subscriber_post(['mobile' => $mobile, 'status' => 'Inactive']))['values'];
    $id = add_subscriber($values);
    $row = db_one('SELECT * FROM subscribers WHERE id = ?', [$id]);
    check($row !== null && $row['family_members'] === 'Lata, Arun' && $row['gotra'] === 'Kasyapa', 'family members and gotra are stored');
    check($row !== null && $row['status'] === 'Inactive' && $row['frequency'] === 'Quarterly', 'the chosen status and cycle are stored');

    $changed = subscriber_input(subscriber_post(['mobile' => $mobile, 'plan_amount' => '1100', 'gotra' => '', 'status' => 'Active']))['values'];
    check(update_subscriber($id, $changed, $userId) === null, 'a subscriber can be updated');
    $row = db_one('SELECT * FROM subscribers WHERE id = ?', [$id]);
    check($row !== null && (float) $row['plan_amount'] === 1100.0 && $row['gotra'] === null && $row['status'] === 'Active', 'the update changes amount, gotra, and status');
    $log = db_all('SELECT * FROM subscriber_status_log WHERE subscriber_id = ? ORDER BY id', [$id]);
    check(count($log) === 1 && $log[0]['from_status'] === 'Inactive' && $log[0]['to_status'] === 'Active' && (int) $log[0]['changed_by'] === $userId, 'a status change records who changed it and from what');

    $unchecked = $changed;
    $unchecked['status'] = '';
    check(update_subscriber($id, $unchecked, $userId) !== null, 'values that failed the form checks are not saved');

    $sameStatus = subscriber_input(subscriber_post(['mobile' => $mobile, 'plan_amount' => '1200', 'status' => 'Active']))['values'];
    update_subscriber($id, $sameStatus, $userId);
    check((int) db_value('SELECT COUNT(*) FROM subscriber_status_log WHERE subscriber_id = ?', [$id]) === 1, 'an update that keeps the status adds no status line');

    add_subscriber(subscriber_input(subscriber_post(['mobile' => $otherMobile]))['values']);
    $clash = subscriber_input(subscriber_post(['mobile' => $otherMobile]))['values'];
    check(update_subscriber($id, $clash, $userId) !== null, 'a mobile number used by another subscriber is refused');
    check(update_subscriber(0, $sameStatus, $userId) !== null, 'an unknown subscriber cannot be updated');

    $latest = latest_subscriber_status_changes([$id]);
    check(isset($latest[$id]) && $latest[$id]['to_status'] === 'Active' && $latest[$id]['changed_by_name'] !== '', 'the list can show the latest status change and who made it');

    $first = create_subscription_invoice($id);
    check($first['error'] === null && !$first['reused'] && preg_match('/^INV-\d{4}-\d{4}$/', $first['number']) === 1, 'an active subscriber gets a new invoice');
    $stored = db_one('SELECT * FROM subscription_invoices WHERE id = ?', [$first['id']]);
    check($stored !== null && (float) $stored['amount'] === 1200.0 && $stored['status'] === 'Pending' && $stored['due_date'] === date('Y-m-d'), 'the invoice takes the plan amount and is due today');
    $again = create_subscription_invoice($id);
    check($again['error'] === null && $again['reused'] && $again['id'] === $first['id'], 'an unpaid invoice for the same period is sent again, not duplicated');
    db_exec("UPDATE subscription_invoices SET status = 'Paid' WHERE id = ?", [$first['id']]);
    $afterPaid = create_subscription_invoice($id);
    check($afterPaid['error'] === null && !$afterPaid['reused'] && $afterPaid['id'] !== $first['id'], 'a paid invoice does not block the next one');
    $paused = subscriber_input(subscriber_post(['mobile' => $mobile, 'plan_amount' => '1200', 'status' => 'Inactive']))['values'];
    update_subscriber($id, $paused, $userId);
    check(create_subscription_invoice($id)['error'] !== null, 'a subscriber who is not Active gets no invoice');
    check(create_subscription_invoice(0)['error'] !== null, 'an unknown subscriber gets no invoice');
} finally {
    $pdo->rollBack();
}

echo $failed === 0 ? "passed\n" : "failed {$failed}\n";
exit($failed === 0 ? 0 : 1);
