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

check(pledge_request_error(0, '2026-09-01', 'Annadaan') !== null, 'a pledge needs an amount');
check(pledge_request_error(1000, '2026-09-01', 'Annadaan') === null, 'a dated promise with an amount is accepted');
check(pledge_receipt_error(100, '2026-09-02', 'In-Kind') !== null, 'an in-kind gift does not settle a pledge');
check(pledge_outstanding(1000, 400) === 600.0, 'still to come is promised minus received');
check(pledge_is_visible('2025-05-01', 100, '2026-04-01', '2026-09-26') === true, 'an older promise still shows while money is due');
check(pledge_is_visible('2025-05-01', 0, '2026-04-01', '2026-09-26') === false, 'a finished promise from last year stays off this statement');

$donor = [
    'id' => 1,
    'name' => 'Meera',
    'phone' => '9000000000',
    'email' => '',
    'address' => 'Sarjapura',
    'pan_number' => 'ABCDE1234F',
];
$statement = build_donor_statement(
    $donor,
    '2026-04-01',
    '2026-09-26',
    [
        ['id' => 1, 'donation_date' => '2026-09-01', 'amount' => 400, 'purpose' => 'Annadaan', 'payment_mode' => 'Cash', 'receipt_number' => 'RCPT-2026-0100', 'receipt_cancelled' => 0, 'pledge_id' => 7],
        ['id' => 2, 'donation_date' => '2026-09-02', 'amount' => 10, 'purpose' => 'Rice', 'payment_mode' => 'In-Kind', 'receipt_number' => '', 'receipt_cancelled' => 0, 'pledge_id' => null],
    ],
    [
        ['id' => 3, 'subject_id' => 1, 'entry_date' => '2026-09-05', 'original_amount' => 400, 'corrected_amount' => 300, 'reason' => 'counted extra', 'payment_mode' => 'Cash', 'receipt_number' => 'RCPT-2026-0100'],
    ],
    [
        ['id' => 7, 'purpose' => 'Annadaan', 'pledged_amount' => 1000, 'pledge_date' => '2026-08-01', 'note' => ''],
    ],
    [
        ['id' => 1, 'pledge_id' => 7, 'amount' => 400, 'payment_mode' => 'Cash'],
    ]
);
check($statement['pan'] === 'ABCDE1234F', 'the statement keeps the devotee PAN');
check($statement['received'] === 300.0, 'the yearly total is money received after the correction, without the in-kind gift');
check(count($statement['lines']) === 3, 'the gift, the in-kind line, and the correction are all on the statement');
check($statement['pledges'][0]['outstanding'] === 700.0, 'the promise falls by the net amount received');
$threw = false;
try {
    build_donor_statement($donor, '2026-03-01', '2026-04-10', [], [], [], []);
} catch (InvalidArgumentException) {
    $threw = true;
}
check($threw, 'a donor statement cannot cross two financial years');

$pdo = db();
$pdo->beginTransaction();
try {
    $donorId = db_exec(
        'INSERT INTO donors (name, phone, pan_number) VALUES (?,?,?)',
        ['Step6 Ledger Donor', '9999900006', 'PQRST1234Z']
    );
    $pledgeId = db_exec(
        'INSERT INTO pledges (donor_id, purpose, pledged_amount, pledge_date, note, created_by) VALUES (?,?,?,?,?,?)',
        [$donorId, 'Annadaan', 1000, '2026-09-01', 'step6 promise', 1]
    );
    $before = load_book_movements('2026-09-01', '2026-09-01');
    $donationId = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, pledge_id, created_by)
         VALUES (?,?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 400, 'Annadaan', '2026-09-01', 'Cash', $pledgeId, 1]
    );
    $after = load_book_movements('2026-09-01', '2026-09-01');
    $pledgeInBook = false;
    $giftInBook = false;
    foreach ($after as $line) {
        if (str_contains((string) $line['particulars'], 'step6 promise')) {
            $pledgeInBook = true;
        }
        if (str_contains((string) $line['particulars'], 'Step6 Ledger Donor') && abs((float) $line['receipt_cash'] - 400) < 0.001) {
            $giftInBook = true;
        }
    }
    check($pledgeInBook === false && count($after) === count($before) + 1 && $giftInBook, 'only the money received enters the cash book');
    $loaded = load_donor_statement($donorId, '2026-04-01', '2026-09-26');
    check($loaded['received'] === 400.0 && $loaded['pledges'][0]['outstanding'] === 600.0, 'the saved statement shows 400 received and 600 still promised');
    check($loaded['lines'][0]['receipt_number'] === '' && (int) $donationId > 0, 'the gift is on the devotee page before a receipt is generated');
} finally {
    $pdo->rollBack();
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all donor tests passed\n";
