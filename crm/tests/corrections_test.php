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
        [$donorId, 'Cash', 40, 'Cancel Journal Head', '2026-09-04', 'Cash', 'RCPT-2099-0099', 1, 1]
    );
    $cancelId = db_exec(
        'INSERT INTO receipt_cancellations (donation_id, reason, prepared_by) VALUES (?,?,?)',
        [$receiptId, 'Wrong donor name', 1]
    );
    $beforeApproval = false;
    foreach (load_book_movements('2026-09-04', '2026-09-04') as $line) {
        if ((string) $line['kind'] === 'receipt_cancel' && (int) $line['sort'] % 100000000 === $cancelId) {
            $beforeApproval = true;
        }
    }
    check($beforeApproval === false, 'a receipt cancellation stays out of the cash book until it is approved');
    apply_receipt_cancellation($cancelId);
    $kept = db_one('SELECT receipt_number, receipt_generated, receipt_cancelled FROM donations WHERE id = ?', [$receiptId]);
    $approved = db_one('SELECT approved_on FROM receipt_cancellations WHERE id = ?', [$cancelId]);
    $approvedOn = (string) ($approved['approved_on'] ?? '');
    check(
        $kept !== null
        && $kept['receipt_number'] === 'RCPT-2099-0099'
        && (int) $kept['receipt_generated'] === 1
        && (int) $kept['receipt_cancelled'] === 1
        && $approvedOn === date('Y-m-d'),
        'cancelling a receipt keeps the number and dates the reversal on the approval day'
    );
    $receiptStillThere = false;
    $reverseOnGiftDay = false;
    foreach (load_book_movements('2026-09-04', '2026-09-04') as $line) {
        if ((string) $line['kind'] === 'donation' && (int) $line['sort'] % 100000000 === $receiptId) {
            $receiptStillThere = abs((float) $line['receipt_cash'] - 40) < 0.001;
        }
        if ((string) $line['kind'] === 'receipt_cancel' && (int) $line['sort'] % 100000000 === $cancelId) {
            $reverseOnGiftDay = true;
        }
    }
    $seenReverse = false;
    $dayBookPayment = false;
    foreach (load_book_movements($approvedOn, $approvedOn) as $line) {
        if ((string) $line['kind'] !== 'receipt_cancel' || (int) $line['sort'] % 100000000 !== $cancelId) {
            continue;
        }
        $seenReverse = abs((float) $line['payment_cash'] - 40) < 0.001
            && abs((float) $line['receipt_cash']) < 0.001
            && str_contains((string) $line['particulars'], 'Wrong donor name')
            && str_contains((string) $line['particulars'], 'RCPT-2099-0099');
        foreach (build_day_book([$line]) as $day) {
            $dayBookPayment = (string) $day['entry'] === 'Payment · Cash' && abs((float) $day['amount'] - 40) < 0.001;
        }
    }
    check($receiptStillThere && $seenReverse, 'the original receipt stays and the approval posts a cash payment');
    check($approvedOn === '2026-09-04' || $reverseOnGiftDay === false, 'the reversal stays on the approval date');
    apply_receipt_cancellation($cancelId);
    $reverseCount = 0;
    foreach (load_book_movements($approvedOn, $approvedOn) as $line) {
        if ((string) $line['kind'] === 'receipt_cancel' && (int) $line['sort'] % 100000000 === $cancelId) {
            $reverseCount++;
        }
    }
    check($reverseCount === 1 && $dayBookPayment, 'approving the same receipt again does not post a second reversal');
    $heads = [];
    $ledgerTo = $approvedOn > '2026-09-04' ? $approvedOn : '2026-09-04';
    foreach (build_ledgers(load_ledger_lines('2026-04-01', $ledgerTo), '2026-04-01', $ledgerTo) as $head) {
        $heads[$head['head']] = $head;
    }
    check(isset($heads['Cancel Journal Head']) && abs((float) $heads['Cancel Journal Head']['balance']) < 0.001, 'the fund balance is zero after the approved cancellation');

    $waitingDonation = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_number, receipt_generated, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 25, 'Waiting Cancel Head', '2026-09-04', 'Cash', 'RCPT-2099-0100', 1, 1]
    );
    $waitingCancel = db_exec(
        'INSERT INTO receipt_cancellations (donation_id, reason, prepared_by) VALUES (?,?,?)',
        [$waitingDonation, 'Still with the treasurer', 1]
    );
    $waitingSeen = false;
    foreach (load_book_movements('2026-04-01', $ledgerTo) as $line) {
        if ((string) $line['kind'] === 'receipt_cancel' && (int) $line['sort'] % 100000000 === $waitingCancel) {
            $waitingSeen = true;
        }
    }
    check($waitingSeen === false, 'a cancellation that is still waiting stays out of the cash book');

    $bankDonation = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_number, receipt_generated, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$donorId, 'UPI', 75, 'Bank Cancel Head', '2026-09-05', 'UPI', 'RCPT-2099-0101', 1, 1]
    );
    $bankCancel = db_exec(
        'INSERT INTO receipt_cancellations (donation_id, reason, prepared_by) VALUES (?,?,?)',
        [$bankDonation, 'UPI receipt cancelled', 1]
    );
    apply_receipt_cancellation($bankCancel);
    $bankReverse = false;
    foreach (load_book_movements($approvedOn, $approvedOn) as $line) {
        if ((string) $line['kind'] === 'receipt_cancel' && (int) $line['sort'] % 100000000 === $bankCancel) {
            $bankReverse = abs((float) $line['payment_bank'] - 75) < 0.001 && abs((float) $line['payment_cash']) < 0.001;
        }
    }
    check($bankReverse, 'a cancelled UPI receipt reverses through the bank column');

    $netDonation = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_number, receipt_generated, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 100, 'Net Cancel Head', '2026-09-06', 'Cash', 'RCPT-2099-0102', 1, 1]
    );
    $netCorrection = db_exec(
        'INSERT INTO corrections (subject_type, subject_id, original_amount, corrected_amount, reason, entry_date, prepared_by)
         VALUES (?,?,?,?,?,?,?)',
        ['donation', $netDonation, 100, 60, 'counted extra before cancel', '2026-09-06', 1]
    );
    record_approval('correction', $netCorrection, 'Approved', 40, 1);
    $netCancel = db_exec(
        'INSERT INTO receipt_cancellations (donation_id, reason, prepared_by) VALUES (?,?,?)',
        [$netDonation, 'Cancel the net amount', 1]
    );
    apply_receipt_cancellation($netCancel);
    $netReverse = false;
    foreach (load_book_movements($approvedOn, $approvedOn) as $line) {
        if ((string) $line['kind'] === 'receipt_cancel' && (int) $line['sort'] % 100000000 === $netCancel) {
            $netReverse = abs((float) $line['payment_cash'] - 60) < 0.001;
        }
    }
    $netHeads = [];
    foreach (build_ledgers(load_ledger_lines('2026-04-01', $ledgerTo), '2026-04-01', $ledgerTo) as $head) {
        $netHeads[$head['head']] = $head;
    }
    check(
        $netReverse && isset($netHeads['Net Cancel Head']) && abs((float) $netHeads['Net Cancel Head']['balance']) < 0.001,
        'a cancelled receipt reverses only the amount still standing after a correction'
    );

    $journalDonor = db_exec(
        'INSERT INTO donors (name, phone) VALUES (?,?)',
        ['Journal Cancel Devotee', '9845091777']
    );
    $pledgeId = db_exec(
        'INSERT INTO pledges (donor_id, purpose, pledged_amount, pledge_date, created_by) VALUES (?,?,?,?,?)',
        [$journalDonor, 'Annadaan', 500, '2026-09-04', 1]
    );
    $pledgeGift = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_number, receipt_generated, pledge_id, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$journalDonor, 'Cash', 80, 'Annadaan', '2026-09-04', 'Cash', 'RCPT-2099-0103', 1, $pledgeId, 1]
    );
    $pledgeCancel = db_exec(
        'INSERT INTO receipt_cancellations (donation_id, reason, prepared_by) VALUES (?,?,?)',
        [$pledgeGift, 'Pledge receipt cancelled', 1]
    );
    apply_receipt_cancellation($pledgeCancel);
    $statement = load_donor_statement($journalDonor, '2026-04-01', $ledgerTo);
    $listed = null;
    foreach (load_donor_list('2026-04-01', $ledgerTo) as $devotee) {
        if ((int) $devotee['id'] === $journalDonor) {
            $listed = $devotee;
        }
    }
    $cancelLine = false;
    foreach ($statement['lines'] as $line) {
        if ((string) $line['purpose'] === 'Receipt cancelled' && abs((float) $line['amount'] + 80) < 0.001) {
            $cancelLine = true;
        }
    }
    check(
        abs((float) $statement['received']) < 0.001
        && count($statement['lines']) === 2
        && $cancelLine
        && abs((float) $statement['pledges'][0]['outstanding'] - 500) < 0.001
        && $listed !== null
        && abs((float) $listed['received']) < 0.001
        && abs((float) $listed['outstanding'] - 500) < 0.001,
        'the devotee statement and the amount still promised follow the approved reversal'
    );
} finally {
    $pdo->rollBack();
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all correction tests passed\n";
