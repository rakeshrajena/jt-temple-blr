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

$noMail = array_merge(load_messaging_settings(), ['smtp_host' => '', 'smtp_from_email' => '']);
$userId = (int) db_value('SELECT id FROM users ORDER BY id LIMIT 1');
$mobile = '7' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
$pdfs = [];
$pdo = db();
$pdo->beginTransaction();
try {
    $subId = add_subscriber(subscriber_input([
        'name' => 'Receipt Tester',
        'mobile' => $mobile,
        'email' => 'receipt.tester@example.com',
        'plan_name' => 'Annadan Seva',
        'plan_amount' => '501',
        'frequency' => 'Monthly',
        'status' => 'Active',
    ])['values']);
    $invoice = create_subscription_invoice($subId);
    check($invoice['error'] === null, 'the test invoice is created');

    $early = issue_subscription_receipt($invoice['id'], $userId);
    check($early['error'] !== null && $early['number'] === '', 'an unpaid invoice gets no receipt');
    check(send_subscription_receipt($invoice['id'], $noMail)['error'] !== null, 'an unpaid invoice has no receipt to send');

    check(record_paid_invoice($invoice['id'], 'UPI', 'PAY-REF-TEST0001') === 'saved', 'the payment is recorded');
    $paid = db_one('SELECT * FROM subscription_invoices WHERE id = ?', [$invoice['id']]);
    $donationId = (int) ($paid['linked_donation_id'] ?? 0);
    $donor = db_one('SELECT don.* FROM donations d JOIN donors don ON don.id = d.donor_id WHERE d.id = ?', [$donationId]);
    check($donor !== null && $donor['email'] === 'receipt.tester@example.com', 'the paying devotee keeps the subscriber email for the receipt');

    $issued = issue_subscription_receipt($invoice['id'], $userId);
    $pdfs[] = $issued['number'];
    check($issued['error'] === null && $issued['created'] && receipt_number_is_valid($issued['number']), 'a paid invoice gets a receipt number in the donation format');
    $donation = db_one('SELECT * FROM donations WHERE id = ?', [$donationId]);
    check($donation !== null && (int) $donation['receipt_generated'] === 1 && $donation['receipt_number'] === $issued['number'], 'the receipt is saved on the linked donation');
    check(preg_match('/^[a-f0-9]{32}$/', (string) ($donation['receipt_share_token'] ?? '')) === 1, 'the receipt has a public link token like any donation receipt');
    check(receipt_file_exists($issued['number']), 'the receipt PDF is written like a donation receipt');
    check((int) db_value('SELECT COUNT(*) FROM receipts WHERE donation_id = ? AND receipt_number = ?', [$donationId, $issued['number']]) === 1, 'the receipt appears on the Receipts page');

    $again = issue_subscription_receipt($invoice['id'], $userId);
    check($again['error'] === null && !$again['created'] && $again['number'] === $issued['number'], 'generating again keeps the same receipt number');

    $send = send_subscription_receipt($invoice['id'], $noMail);
    check($send['error'] === 'Outgoing mail is not configured.' && $send['email'] === 'receipt.tester@example.com' && $send['number'] === $issued['number'], 'sending goes to the subscriber email and needs outgoing mail');

    db_exec('UPDATE subscribers SET email = NULL WHERE id = ?', [$subId]);
    db_exec('UPDATE donors SET email = NULL WHERE id = ?', [(int) $donor['id']]);
    check(send_subscription_receipt($invoice['id'], $noMail)['error'] === 'This devotee has no email address.', 'a subscriber without email cannot be sent the receipt');

    check(issue_subscription_receipt(0, $userId)['error'] !== null, 'an unknown invoice gets no receipt');
} finally {
    $pdo->rollBack();
    foreach ($pdfs as $number) {
        if ($number !== '' && receipt_file_exists($number)) {
            unlink(receipt_path($number));
        }
    }
}

echo $failed === 0 ? "passed\n" : "failed {$failed}\n";
exit($failed === 0 ? 0 : 1);
