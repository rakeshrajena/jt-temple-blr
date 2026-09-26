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

$void = correction_effect('donation', 'Cash', 1000, 0);
check($void !== null && $void['payment_cash'] === 1000.0 && $void['receipt_cash'] === 0.0, 'voiding a cash donation reverses it as a cash payment');
$cut = correction_effect('expense', 'UPI', 500, 200);
check($cut !== null && $cut['receipt_bank'] === 300.0 && $cut['payment_bank'] === 0.0, 'reducing a bank expense comes back as a bank receipt');
$raise = correction_effect('donation', 'Cheque', 100, 150);
check($raise !== null && $raise['receipt_bank'] === 50.0, 'raising a donation adds a bank receipt');
check(correction_effect('donation', 'In-Kind', 100, 0) === null, 'an in-kind gift is not corrected in the cash book');
check(correction_request_error('adjust', 100, 100, 'Wrong figure', '2026-09-03', 'cash', false) !== null, 'the same amount is not a correction');
check(correction_request_error('void', 100, 0, 'No', '2026-09-03', 'cash', false) !== null, 'a correction needs a reason');
check(correction_request_error('void', 100, 0, 'Entered twice', '2026-09-03', 'cash', true) !== null, 'a waiting correction blocks another');
check(correction_request_error('adjust', 100, 80, 'Entered twice', '2026-09-03', 'cash', false) === null, 'a smaller amount with a reason can be submitted');
check(receipt_cancel_request_error(true, true, false, 'Wrong name') !== null, 'a cancelled receipt cannot be cancelled again');
check(receipt_cancel_request_error(true, false, false, 'Wrong donor name') === null, 'an active receipt can be submitted for cancellation');

$pdo = db();
$pdo->beginTransaction();
try {
    $donorId = (int) db_value('SELECT id FROM donors ORDER BY id LIMIT 1');
    $donationId = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_generated, created_by)
         VALUES (?,?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 1000, 'Correction Head', '2026-09-03', 'Cash', 0, 1]
    );
    $correctionId = db_exec(
        'INSERT INTO corrections (subject_type, subject_id, original_amount, corrected_amount, reason, entry_date, prepared_by)
         VALUES (?,?,?,?,?,?,?)',
        ['donation', $donationId, 1000, 800, 'counted twice', '2026-09-03', 1]
    );
    record_approval('correction', $correctionId, 'Waiting', 200, 1);
    $hidden = false;
    foreach (load_book_movements('2026-09-03', '2026-09-03') as $line) {
        if (str_contains((string) $line['particulars'], 'counted twice')) {
            $hidden = true;
        }
    }
    check($hidden === false, 'a waiting correction stays out of the cash book');
    db_exec("UPDATE approvals SET status = 'Approved' WHERE subject_type = 'correction' AND subject_id = ?", [$correctionId]);
    $seenDonation = false;
    $seenCorrection = false;
    foreach (load_book_movements('2026-09-03', '2026-09-03') as $line) {
        if ((string) $line['kind'] === 'donation' && (int) $line['sort'] % 100000000 === $donationId) {
            $seenDonation = abs((float) $line['receipt_cash'] - 1000) < 0.001;
        }
        if (str_contains((string) $line['particulars'], 'counted twice')) {
            $seenCorrection = abs((float) $line['payment_cash'] - 200) < 0.001;
        }
    }
    check($seenDonation && $seenCorrection, 'the original donation stays and the approved correction reverses the difference');
    check(corrected_book_amount('donation', $donationId, 1000) === 800.0, 'the amount in the books is the original plus approved corrections');
    $heads = [];
    foreach (build_ledgers(load_ledger_lines('2026-04-01', '2026-09-26'), '2026-04-01', '2026-09-26') as $head) {
        $heads[$head['head']] = $head;
    }
    check(isset($heads['Correction Head']) && abs($heads['Correction Head']['balance'] - 800) < 0.001, 'the fund balance is the original gift minus the correction');

    $receiptId = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_number, receipt_generated, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 40, 'General', '2026-09-04', 'Cash', 'RCPT-2099-0099', 1, 1]
    );
    $cancelId = db_exec(
        'INSERT INTO receipt_cancellations (donation_id, reason, prepared_by) VALUES (?,?,?)',
        [$receiptId, 'Wrong donor name', 1]
    );
    apply_receipt_cancellation($cancelId);
    $kept = db_one('SELECT receipt_number, receipt_generated, receipt_cancelled FROM donations WHERE id = ?', [$receiptId]);
    check(
        $kept !== null
        && $kept['receipt_number'] === 'RCPT-2099-0099'
        && (int) $kept['receipt_generated'] === 1
        && (int) $kept['receipt_cancelled'] === 1,
        'cancelling a receipt keeps the number and marks it cancelled'
    );
} finally {
    $pdo->rollBack();
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all correction tests passed\n";
