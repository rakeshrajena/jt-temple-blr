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

    $grown = update_coupon_batch((int) $first['id'], 'Step coupon', 25, 3, $adminId);
    $afterEdit = db_one('SELECT quantity, total_value, end_sl_no, start_sl_no FROM food_coupon_batches WHERE id = ?', [(int) $first['id']]);
    check(
        $grown['error'] === null
        && (int) $afterEdit['quantity'] === 3
        && (float) $afterEdit['total_value'] === 75.0
        && (int) $afterEdit['end_sl_no'] === (int) $afterEdit['start_sl_no'] + 2,
        'editing the quantity updates the face value and the serial range'
    );

    $second = create_coupon_batch('Next coupon', 10, 4, $adminId);
    $overlap = update_coupon_batch((int) $first['id'], 'Step coupon', 25, 8, $adminId);
    check($second['error'] === null && $overlap['error'] !== null, 'a larger quantity cannot overlap the next batch');

    $removed = remove_coupon_batch((int) $first['id'], 'Staff');
    $gone = db_one('SELECT id FROM food_coupon_batches WHERE id = ?', [(int) $first['id']]);
    check($removed === null && $gone === null, 'a waiting batch can be removed without an Admin');

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
