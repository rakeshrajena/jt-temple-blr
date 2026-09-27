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
        && (string) $donation['purpose'] === 'Prasad sale'
        && (string) $donation['donor_name'] === 'Walk-in devotee'
        && (string) $donation['notes'] === $firstCode
        && is_array($movement)
        && (float) $movement['receipt_cash'] === 40.0
        && str_contains((string) $movement['particulars'], $firstCode)
        && is_array($ledger)
        && (string) $ledger['head'] === 'Prasad sale'
        && (float) $ledger['received'] === 40.0
        && $again['error'] !== null,
        'a sold coupon is donation income in the cash book and the ledger, and cannot be sold twice'
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
