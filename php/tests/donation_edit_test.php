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

/** @return array<string, mixed> */
function edit_input(string $phone, string $amount, string $purpose, string $reason, string $type = 'Cash', string $mode = 'Cash'): array
{
    return [
        'donor_name' => 'Edit Once Devotee',
        'donor_phone' => $phone,
        'donor_email' => '',
        'donor_address' => '',
        'donor_pan' => '',
        'donation_type' => $type,
        'amount' => $amount,
        'purpose' => $purpose,
        'donation_date' => '2026-09-20',
        'payment_mode' => $mode,
        'upi_reference' => '',
        'cheque_number' => '',
        'cheque_date' => '',
        'cheque_cleared' => false,
        'pledge_id' => 0,
        'reason' => $reason,
    ];
}

function cash_for(string $needle): float
{
    $total = 0.0;
    foreach (load_book_movements('2026-09-20', '2026-09-20') as $line) {
        if (str_contains((string) $line['particulars'], $needle)) {
            $total += (float) $line['receipt_cash'];
        }
    }
    return round($total, 2);
}

$same = donation_edit_normalize([
    'id' => 0,
    'donor_id' => 1,
    'donor_name' => 'Edit Once Devotee',
    'donor_phone' => '9000007721',
    'donor_email' => '',
    'donor_address' => '',
    'donor_pan' => '',
    'donation_type' => 'Cash',
    'amount' => 250,
    'purpose' => 'General',
    'donation_date' => '2026-09-20',
    'payment_mode' => 'Cash',
    'cheque_number' => null,
    'cheque_date' => null,
    'cheque_cleared' => 0,
    'upi_reference' => null,
    'pledge_id' => null,
    'linked_food_id' => null,
    'linked_vastra_id' => null,
    'linked_inventory_id' => null,
    'reconciled_bank_txn_id' => null,
], edit_input('9000007721', '250', 'General', 'No'));
check(($same['error'] ?? '') === 'Write a short reason for the edit.', 'an edit needs a reason');
check(donation_edit_needs_both(date('Y-m-d H:i:s')) === false, 'a gift from this moment needs one approval');
check(donation_edit_needs_both('2020-01-01 00:00:00') === true, 'a gift older than 24 hours needs both approvals');
check(donation_edit_both_error('Staff', 1, 2, 'Waiting') !== null, 'staff cannot approve an older edit');
check(donation_edit_both_error('Treasurer', 1, 1, 'Waiting') !== null, 'the preparer cannot be one of the two approvals');
check(donation_edit_both_error('Admin', 1, 3, 'Waiting') === null, 'an admin can give one of the two approvals above any amount');

$pdo = db();
$pdo->beginTransaction();
$receiptFile = dirname(__DIR__) . '/storage/receipts/RCPT-2099-0888.pdf';
try {
    $phone = '9' . (string) random_int(100000000, 999999999);
    $donorId = db_exec(
        'INSERT INTO donors (name, phone) VALUES (?,?)',
        ['Edit Once Devotee', $phone]
    );
    $donationId = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, created_by)
         VALUES (?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 250, 'EditOnceHead', '2026-09-20', 'Cash', 1]
    );
    $unchanged = donation_edit_normalize(
        donation_edit_row($donationId),
        edit_input($phone, '250', 'EditOnceHead', 'Same gift details')
    );
    check(($unchanged['error'] ?? '') === 'Nothing on this gift has changed.', 'an unchanged gift is not submitted');
    check(cash_for('Edit Once Devotee (EditOnceHead)') === 250.0, 'the gift is in the cash book before an edit');
    $saved = save_donation_edit($donationId, edit_input($phone, '400', 'Annadaan', 'Counted short'), 1);
    check($saved === null, 'an edit can be submitted');
    $still = db_one('SELECT amount, purpose FROM donations WHERE id = ?', [$donationId]);
    check(
        $still !== null && (float) $still['amount'] === 250.0 && $still['purpose'] === 'EditOnceHead',
        'a waiting edit does not change the gift'
    );
    check(cash_for('Edit Once Devotee (EditOnceHead)') === 250.0, 'a waiting edit stays out of the cash book');
    $editId = (int) db_value(
        "SELECT e.id FROM donation_edits e
         JOIN approvals a ON a.subject_type = 'donation_edit' AND a.subject_id = e.id
         WHERE e.donation_id = ?",
        [$donationId]
    );
    $approvalAmount = (float) db_value(
        "SELECT amount FROM approvals WHERE subject_type = 'donation_edit' AND subject_id = ?",
        [$editId]
    );
    check(abs($approvalAmount - 400) < 0.001, 'the approval amount is the larger of the old and new amounts');
    check(approval_error('Treasurer', $approvalAmount, 1, 2, 'approve', 'Waiting', '') === null, 'a treasurer can approve this edit');
    check(approval_error('Staff', $approvalAmount, 1, 2, 'approve', 'Waiting', '') !== null, 'staff cannot approve this edit');
    check(approval_error('Admin', $approvalAmount, 1, 1, 'approve', 'Waiting', '') !== null, 'the person who submitted the edit cannot approve it');
    check(save_donation_edit($donationId, edit_input($phone, '500', 'Annadaan', 'Another change'), 1) !== null, 'a second edit waits until the first is decided');

    $largeId = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, created_by)
         VALUES (?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 15000, 'General', '2026-09-20', 'Cash', 1]
    );
    check(save_donation_edit($largeId, edit_input($phone, '15000', 'Construction', 'Wrong purpose'), 1) === null, 'a large edit can be submitted');
    $largeAmount = (float) db_value(
        "SELECT a.amount FROM approvals a
         JOIN donation_edits e ON a.subject_type = 'donation_edit' AND a.subject_id = e.id
         WHERE e.donation_id = ?",
        [$largeId]
    );
    check(abs($largeAmount - 15000) < 0.001, 'a purpose edit still uses the gift amount');
    check(approval_error('Treasurer', $largeAmount, 1, 2, 'approve', 'Waiting', '') !== null, 'a treasurer cannot approve above the limit');
    check(approval_error('Admin', $largeAmount, 1, 2, 'approve', 'Waiting', '') === null, 'an admin can approve above the treasurer limit');
    check((float) db_value('SELECT amount FROM donations WHERE id = ?', [$largeId]) === 15000.0, 'the large gift is unchanged while it waits');

    apply_donation_edit($editId);
    $applied = db_one('SELECT amount, purpose FROM donations WHERE id = ?', [$donationId]);
    check(
        $applied !== null && (float) $applied['amount'] === 400.0 && $applied['purpose'] === 'Annadaan',
        'approval writes the new amount and purpose'
    );
    check(
        cash_for('Edit Once Devotee (EditOnceHead)') === 0.0 && cash_for('Edit Once Devotee (Annadaan)') === 400.0,
        'the cash book changes only after approval'
    );
    apply_donation_edit($editId);
    check((float) db_value('SELECT amount FROM donations WHERE id = ?', [$donationId]) === 400.0, 'approving the same edit again does not write it twice');

    $foodId = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, created_by)
         VALUES (?,?,?,?,?,?,?)',
        [$donorId, 'Food', null, 'General', '2026-09-20', 'In-Kind', 1]
    );
    $food = save_donation_edit($foodId, edit_input($phone, '10', 'General', 'Change the type', 'Cash', 'Cash'), 1);
    check($food !== null && str_contains((string) $food, 'type stays'), 'a stock gift keeps its type');
    check(db_value('SELECT donation_type FROM donations WHERE id = ?', [$foodId]) === 'Food', 'the food gift is unchanged');

    $correctedId = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, created_by)
         VALUES (?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 100, 'General', '2026-09-21', 'Cash', 1]
    );
    $correctionId = db_exec(
        'INSERT INTO corrections (subject_type, subject_id, original_amount, corrected_amount, reason, entry_date, prepared_by)
         VALUES (?,?,?,?,?,?,?)',
        ['donation', $correctedId, 100, 80, 'counted twice', '2026-09-21', 1]
    );
    record_approval('correction', $correctionId, 'Approved', 20, 1);
    $blocked = save_donation_edit(
        $correctedId,
        [
            'donor_name' => 'Edit Once Devotee',
            'donor_phone' => $phone,
            'donor_email' => '',
            'donor_address' => '',
            'donor_pan' => '',
            'donation_type' => 'Cash',
            'amount' => '80',
            'purpose' => 'General',
            'donation_date' => '2026-09-21',
            'payment_mode' => 'Cash',
            'upi_reference' => '',
            'cheque_number' => '',
            'cheque_date' => '',
            'cheque_cleared' => false,
            'pledge_id' => 0,
            'reason' => 'Use the edit form',
        ],
        1
    );
    check($blocked !== null && str_contains((string) $blocked, 'Corrections'), 'an amount edit stops when a correction is already approved');

    $oldId = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, created_by)
         VALUES (?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 50000, 'General', '2026-09-01', 'Cash', 1]
    );
    db_exec('UPDATE donations SET created_at = ? WHERE id = ?', ['2026-09-01 08:00:00', $oldId]);
    $oldRow = donation_edit_row($oldId);
    check($oldRow !== null && donation_edit_needs_both((string) $oldRow['created_at']), 'the stored gift is older than 24 hours');
    $oldInput = edit_input($phone, '50000', 'Construction', 'Old gift purpose');
    $oldInput['donation_date'] = '2026-09-01';
    check(save_donation_edit($oldId, $oldInput, 1) === null, 'an older edit can be submitted');
    $oldEditId = (int) db_value('SELECT id FROM donation_edits WHERE donation_id = ?', [$oldId]);
    check(donation_edit_record_signature($oldEditId, 'Treasurer', 2) === 'waiting_admin', 'a treasurer alone does not finish an older edit');
    check((string) db_value('SELECT purpose FROM donations WHERE id = ?', [$oldId]) === 'General', 'one approval leaves the older gift unchanged');
    check(donation_edit_record_signature($oldEditId, 'Treasurer', 9) === 'already', 'a second treasurer does not replace the first');
    check(donation_edit_record_signature($oldEditId, 'Admin', 3) === 'complete', 'the admin signature completes the pair');
    check((string) db_value('SELECT purpose FROM donations WHERE id = ?', [$oldId]) === 'General', 'both signatures still wait for the edit to be applied');
    apply_donation_edit($oldEditId);
    check((string) db_value('SELECT purpose FROM donations WHERE id = ?', [$oldId]) === 'Construction', 'the older gift changes after both approvals are applied');

    $receiptDonationId = db_exec(
        'INSERT INTO donations (donor_id, donation_type, amount, purpose, donation_date, payment_mode, receipt_number, receipt_generated, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$donorId, 'Cash', 40, 'General', '2026-09-22', 'Cash', 'RCPT-2099-0888', 1, 1]
    );
    $receiptInput = edit_input($phone, '40', 'Annadaan', 'Rewrite the receipt');
    $receiptInput['donation_date'] = '2026-09-22';
    check(save_donation_edit($receiptDonationId, $receiptInput, 1) === null, 'an edit of a receipt gift can be submitted');
    $receiptEditId = (int) db_value('SELECT id FROM donation_edits WHERE donation_id = ?', [$receiptDonationId]);
    apply_donation_edit($receiptEditId);
    check(is_file($receiptFile), 'approving the edit rewrites the existing receipt');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_file($receiptFile)) {
        unlink($receiptFile);
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all donation edit tests passed\n";
