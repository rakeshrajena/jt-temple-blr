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

check(coupon_issues_now(1) === true, 'a quantity of one is issued at once');
check(coupon_issues_now(2) === false, 'a quantity above one waits for approval');
check(coupon_request_error('', 10, 2) !== null, 'a coupon batch needs a name');
check(coupon_request_error('Lunch', 0, 2) !== null, 'a coupon cost must be above zero');
check(coupon_request_error('Lunch', 10, 401) !== null, 'a batch cannot exceed 400 coupons');
check(coupon_request_error('Lunch', 10, 2) === null, 'a named batch with a cost and a quantity is accepted');
check(coupon_ranges_overlap(1, 5, 5, 8) === true, 'serial ranges that share a number overlap');
check(coupon_ranges_overlap(1, 5, 6, 8) === false, 'the next serial range starts after the previous one');
check(approval_error('Treasurer', 10000, 1, 2, 'approve', 'Waiting', '') === null, 'a treasurer can approve a coupon batch of ₹10,000');
check(approval_error('Treasurer', 10001, 1, 2, 'approve', 'Waiting', '') !== null, 'a coupon batch above ₹10,000 needs an Admin');
check(approval_error('Staff', 100, 1, 2, 'approve', 'Waiting', '') !== null, 'staff cannot approve a coupon batch');

$admin = db_one("SELECT id FROM users WHERE username = 'admin'");
check($admin !== null, 'demo admin exists');

$pdo = db();
$pdo->beginTransaction();
try {
    $adminId = (int) $admin['id'];
    $before = (float) db_value(
        "SELECT COALESCE(SUM(b.total_value),0) FROM food_coupon_batches b
         JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id AND a.status = 'Approved'"
    );
    $first = create_coupon_batch('Step coupon', 25, 2, $adminId);
    $waiting = db_one(
        "SELECT a.status, a.amount, b.total_value FROM food_coupon_batches b
         JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id
         WHERE b.id = ?",
        [(int) $first['id']]
    );
    $during = (float) db_value(
        "SELECT COALESCE(SUM(b.total_value),0) FROM food_coupon_batches b
         JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id AND a.status = 'Approved'"
    );
    check(
        $first['error'] === null
        && $waiting['status'] === 'Waiting'
        && (float) $waiting['amount'] === 50.0
        && abs($during - $before) < 0.001,
        'a new batch waits and is left out of the approved total'
    );
    $one = create_coupon_batch('Satyanarayan Puja', 501, 1, $adminId, null, ['purpose' => 'Satyanarayan Puja'], true);
    $oneStatus = (string) db_value(
        "SELECT a.status FROM approvals a WHERE a.subject_type = 'coupon' AND a.subject_id = ?",
        [(int) $one['id']]
    );
    $afterOne = (float) db_value(
        "SELECT COALESCE(SUM(b.total_value),0) FROM food_coupon_batches b
         JOIN approvals a ON a.subject_type = 'coupon' AND a.subject_id = b.id AND a.status = 'Approved'"
    );
    check($one['error'] === null && $oneStatus === 'Approved' && abs($afterOne - $before - 501) < 0.001, 'one coupon is issued without waiting for approval');
    check(create_coupon_batch('Two at once', 10, 2, $adminId, null, [], true)['error'] !== null, 'more than one coupon still waits for approval');
    db_exec('DELETE FROM food_coupons WHERE batch_id = ?', [(int) $one['id']]);
    db_exec("DELETE FROM approvals WHERE subject_type = 'coupon' AND subject_id = ?", [(int) $one['id']]);
    db_exec('DELETE FROM food_coupon_batches WHERE id = ?', [(int) $one['id']]);
    $issued = (int) db_value('SELECT issued_unix FROM food_coupon_batches WHERE id = ?', [(int) $first['id']]);
    $now = time();
    check(
        $issued >= $now - 5 && $issued <= $now + 5
        && coupon_code($issued, (int) $first['start']) === sprintf('CU-%d-%04d', $issued, (int) $first['start']),
        'a coupon serial is CU, the POSIX time, and a 4-digit number'
    );

    $grown = update_coupon_batch((int) $first['id'], 'Step coupon', 25, 3, $adminId);
    $afterEdit = db_one('SELECT quantity, total_value, end_sl_no, start_sl_no FROM food_coupon_batches WHERE id = ?', [(int) $first['id']]);
    $storedAfterEdit = (int) db_value('SELECT COUNT(*) FROM food_coupons WHERE batch_id = ?', [(int) $first['id']]);
    check(
        $grown['error'] === null
        && (int) $afterEdit['quantity'] === 3
        && (float) $afterEdit['total_value'] === 75.0
        && (int) $afterEdit['end_sl_no'] === (int) $afterEdit['start_sl_no'] + 2
        && $storedAfterEdit === 3,
        'editing the quantity updates the face value, the serial range, and the stored coupons'
    );

    $second = create_coupon_batch('Next coupon', 10, 4, $adminId);
    $overlap = update_coupon_batch((int) $first['id'], 'Step coupon', 25, 8, $adminId);
    check($second['error'] === null && $overlap['error'] !== null, 'a larger quantity cannot overlap the next batch');

    $removed = remove_coupon_batch((int) $first['id'], 'Staff');
    $gone = db_one('SELECT id FROM food_coupon_batches WHERE id = ?', [(int) $first['id']]);
    $couponsGone = (int) db_value('SELECT COUNT(*) FROM food_coupons WHERE batch_id = ?', [(int) $first['id']]);
    check($removed === null && $gone === null && $couponsGone === 0, 'removing a batch also removes its coupons');

    db_exec(
        "UPDATE approvals SET status = 'Approved' WHERE subject_type = 'coupon' AND subject_id = ?",
        [(int) $second['id']]
    );
    $staffRemove = remove_coupon_batch((int) $second['id'], 'Staff');
    $stillThere = db_one('SELECT id FROM food_coupon_batches WHERE id = ?', [(int) $second['id']]);
    $adminRemove = remove_coupon_batch((int) $second['id'], 'Admin');
    $nowGone = db_one('SELECT id FROM food_coupon_batches WHERE id = ?', [(int) $second['id']]);
    check(
        $staffRemove !== null && $stillThere !== null && $adminRemove === null && $nowGone === null,
        'only an Admin can remove a batch after it is approved'
    );

    $tomorrow = coupon_expires_at(false, date('Y-m-d H:i:s', time() + 86400));
    $pastExpiry = coupon_expires_at(false, date('Y-m-d H:i:s', time() - 120));
    $openEnded = coupon_expires_at(true, '');
    check(
        $tomorrow['error'] === null
        && $pastExpiry['error'] !== null
        && $openEnded['error'] === null
        && $openEnded['expires_at'] === null,
        'expiry must be in the future, and no expiry is allowed'
    );
    $scanLink = coupon_scan_url('CU-1758920820-0007');
    check(
        str_contains($scanLink, 'r=coupons%2Fscan')
        && str_contains($scanLink, 'code=CU-1758920820-0007')
        && str_starts_with($scanLink, 'http'),
        'the QR target is a link that carries the coupon code'
    );
    $linkedPreview = coupon_scan_preview($scanLink);
    check($linkedPreview['error'] !== null && $linkedPreview['code'] === null, 'a scan link is checked before anything is recorded');
    check(coupon_api_token_matches('too-short') === false, 'a short coupon API token never matches');
    check(coupon_api_token_matches('this-token-is-long-enough') === false, 'a coupon API token matches only the configured value');

    $sale = create_coupon_batch('Prasad sale', 40, 2, $adminId, $tomorrow['expires_at']);
    $saleId = (int) $sale['id'];
    $saleIssued = (int) db_value('SELECT issued_unix FROM food_coupon_batches WHERE id = ?', [$saleId]);
    $saleStart = (int) $sale['start'];
    $storedSale = (int) db_value(
        "SELECT COUNT(*) FROM food_coupons WHERE batch_id = ? AND status = 'Valid' AND expires_at IS NOT NULL",
        [$saleId]
    );
    $firstCode = coupon_code($saleIssued, $saleStart);
    $secondCode = coupon_code($saleIssued, $saleStart + 1);
    $waitingSale = redeem_coupon($firstCode, $adminId, 'Walk-in devotee', 'Cash');
    check(
        $sale['error'] === null
        && $storedSale === 2
        && $waitingSale['error'] !== null
        && $waitingSale['donation_id'] === null,
        'each coupon is stored, and a sale waits until the batch is approved'
    );

    db_exec(
        "UPDATE approvals SET status = 'Approved' WHERE subject_type = 'coupon' AND subject_id = ?",
        [$saleId]
    );
    $ready = coupon_scan_preview(coupon_scan_url($firstCode));
    $beforeScan = (int) db_value('SELECT COUNT(*) FROM donations WHERE notes = ?', [$firstCode]);
    check(
        $ready['error'] === null && $ready['code'] === $firstCode && $beforeScan === 0,
        'a scan link for a valid coupon is accepted and does not add income until it is recorded'
    );
    $sold = redeem_coupon($firstCode, $adminId, 'Walk-in devotee', 'Cash');
    $donation = db_one(
        'SELECT d.id, d.donation_date, d.amount, d.payment_mode, d.purpose, d.notes, d.donation_type,
                don.name AS donor_name, u.full_name AS entered_by_name
         FROM donations d
         JOIN donors don ON don.id = d.donor_id
         LEFT JOIN users u ON u.id = d.created_by
         WHERE d.id = ?',
        [(int) $sold['donation_id']]
    );
    $movement = is_array($donation) ? donation_movement($donation) : null;
    $ledger = is_array($donation) ? donation_ledger_line($donation) : null;
    $again = redeem_coupon($firstCode, $adminId, 'Walk-in devotee', 'Cash');
    check(
        $sold['error'] === null
        && is_array($donation)
        && (float) $donation['amount'] === 40.0
        && (string) $donation['purpose'] === 'Donation'
        && (string) $donation['donor_name'] === 'Walk-in devotee'
        && (string) $donation['notes'] === $firstCode
        && is_array($movement)
        && (float) $movement['receipt_cash'] === 40.0
        && str_contains((string) $movement['particulars'], $firstCode)
        && is_array($ledger)
        && (string) $ledger['head'] === 'Donation'
        && (float) $ledger['received'] === 40.0
        && $again['error'] !== null
        && str_contains((string) $again['error'], 'already scanned and redeemed'),
        'a sold coupon is donation income in the cash book and the ledger, and cannot be sold twice'
    );
    $adminName = (string) db_value('SELECT full_name FROM users WHERE id = ?', [$adminId]);
    $sawScan = false;
    foreach (coupon_batch_scans()[$saleId] ?? [] as $scan) {
        if ($scan['code'] === $firstCode && $scan['scanned_by'] === $adminName && $scan['scanned_at'] !== '') {
            $sawScan = true;
        }
    }
    check($sawScan, 'a scanned coupon is listed on its batch with the time and the person');

    $blankGift = coupon_gift_storage([]);
    $badPan = coupon_gift_storage(['donor_name' => 'A', 'donor_pan' => 'not-a-pan']);
    $phoneOnly = coupon_gift_storage(['donor_phone' => '9000007711']);
    check(
        $blankGift['error'] === null && $blankGift['values']['donor_name'] === null,
        'coupon devotee details can be left blank'
    );
    check($badPan['error'] !== null, 'a coupon PAN must look like a PAN');
    check($phoneOnly['error'] !== null, 'a coupon phone needs a devotee name');

    $named = create_coupon_batch('Named coupon', 30, 1, $adminId, null, [
        'donor_name' => 'Coupon Devotee Test',
        'donor_phone' => '9000007711',
        'donor_email' => 'coupon.dev@example.com',
        'donor_address' => 'Lane 1',
        'donor_pan' => 'ABCDE1234F',
        'donation_type' => 'Other',
        'payment_mode' => 'UPI',
        'purpose' => 'Annadaan',
        'upi_reference' => 'UPI123456',
    ]);
    $namedId = (int) $named['id'];
    db_exec("UPDATE approvals SET status = 'Approved' WHERE subject_type = 'coupon' AND subject_id = ?", [$namedId]);
    $namedIssued = (int) db_value('SELECT issued_unix FROM food_coupon_batches WHERE id = ?', [$namedId]);
    $namedCode = coupon_code($namedIssued, (int) $named['start']);
    $namedSale = redeem_coupon($namedCode, $adminId, '', '');
    $namedGift = db_one(
        'SELECT d.donation_type, d.payment_mode, d.purpose, d.upi_reference, don.name AS donor_name, don.phone, don.email, don.address, don.pan_number
         FROM donations d JOIN donors don ON don.id = d.donor_id WHERE d.id = ?',
        [(int) $namedSale['donation_id']]
    );
    $giftEdit = update_coupon_batch($namedId, 'Named coupon', 30, 1, $adminId, null, false, ['purpose' => 'Seva']);
    check(
        $named['error'] === null
        && $namedSale['error'] === null
        && is_array($namedGift)
        && (string) $namedGift['donor_name'] === 'Coupon Devotee Test'
        && (string) $namedGift['phone'] === '9000007711'
        && (string) $namedGift['email'] === 'coupon.dev@example.com'
        && (string) $namedGift['pan_number'] === 'ABCDE1234F'
        && (string) $namedGift['donation_type'] === 'Other'
        && (string) $namedGift['payment_mode'] === 'UPI'
        && (string) $namedGift['purpose'] === 'Annadaan'
        && (string) $namedGift['upi_reference'] === 'UPI123456'
        && $giftEdit['changed'] === true
        && $giftEdit['reapproval'] === false,
        'optional batch details become the donation, and editing them does not restart approval'
    );

    $plain = create_coupon_batch('Counter sale', 25, 1, $adminId, null);
    $plainId = (int) $plain['id'];
    db_exec("UPDATE approvals SET status = 'Approved' WHERE subject_type = 'coupon' AND subject_id = ?", [$plainId]);
    $plainIssued = (int) db_value('SELECT issued_unix FROM food_coupon_batches WHERE id = ?', [$plainId]);
    $plainCode = coupon_code($plainIssued, (int) $plain['start']);
    $plainSale = redeem_coupon($plainCode, $adminId, '', 'Cash');
    $plainGift = db_one(
        'SELECT d.donation_type, d.payment_mode, d.purpose, don.name AS donor_name
         FROM donations d JOIN donors don ON don.id = d.donor_id WHERE d.id = ?',
        [(int) $plainSale['donation_id']]
    );
    check(
        $plainSale['error'] === null
        && is_array($plainGift)
        && (string) $plainGift['donation_type'] === 'Cash'
        && (string) $plainGift['payment_mode'] === 'Cash'
        && (string) $plainGift['donor_name'] === 'Coupon counter'
        && (string) $plainGift['purpose'] === 'Donation',
        'a scan with no extra choices is a cash donation under Coupon counter'
    );

    db_exec('UPDATE food_coupons SET expires_at = ? WHERE code = ?', [date('Y-m-d H:i:s', time() - 120), $secondCode]);
    expire_due_coupons();
    $expiredStatus = (string) db_value('SELECT status FROM food_coupons WHERE code = ?', [$secondCode]);
    $expiredSale = redeem_coupon($secondCode, $adminId, '', 'Cash');
    check(
        $expiredStatus === 'Expired' && $expiredSale['error'] !== null && $expiredSale['donation_id'] === null,
        'a coupon past its expiry is invalidated and is not income'
    );

    $kept = remove_coupon_batch($saleId, 'Admin');
    $saleStillThere = db_one('SELECT id FROM food_coupon_batches WHERE id = ?', [$saleId]);
    $donationStillThere = db_one('SELECT id FROM donations WHERE id = ?', [(int) $sold['donation_id']]);
    check(
        $kept !== null && $saleStillThere !== null && $donationStillThere !== null,
        'a batch with a sold coupon stays, and the donation stays in the books'
    );

    $loose = create_coupon_batch('Open coupon', 15, 1, $adminId, null);
    $looseId = (int) $loose['id'];
    db_exec(
        "UPDATE approvals SET status = 'Approved' WHERE subject_type = 'coupon' AND subject_id = ?",
        [$looseId]
    );
    $looseIssued = (int) db_value('SELECT issued_unix FROM food_coupon_batches WHERE id = ?', [$looseId]);
    $looseCode = coupon_code($looseIssued, (int) $loose['start']);
    $invalidated = invalidate_coupon($looseCode);
    $invalidStatus = (string) db_value('SELECT status FROM food_coupons WHERE code = ?', [$looseCode]);
    $invalidSale = redeem_coupon($looseCode, $adminId, '', 'Cash');
    $noDonation = (int) db_value('SELECT COUNT(*) FROM donations WHERE notes = ?', [$looseCode]);
    check(
        $invalidated['error'] === null
        && $invalidStatus === 'Invalid'
        && $invalidSale['error'] !== null
        && $noDonation === 0,
        'invalidating a coupon adds no donation'
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
echo "all coupon tests passed\n";
