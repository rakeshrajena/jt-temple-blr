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

check(stock_needs_approval('Used', 5) === false, 'kitchen use at the limit is a normal log');
check(stock_needs_approval('Used', 6) === true, 'kitchen use above the limit waits');
check(stock_needs_approval('Issued', 20) === false, 'an issue does not wait');
check(stock_needs_approval('Damaged', 6) === true, 'damage above the limit waits');
check(stock_needs_approval('Returned', 8) === false, 'a return does not wait');
check(quantity_after_movement(3, 'Issued', 4) === null, 'a movement cannot take stock below zero');
check(quantity_after_movement(10, 'Issued', 4) === 6.0, 'an issue reduces the quantity on hand');
check(weighted_unit_cost(10, 20, 10, 40) === 30.0, 'a purchase updates the rate as a weighted average');
check(stock_value(4, 12.5) === 50.0, 'stock value is quantity times rate');
check(approval_error('Treasurer', 0, 1, 2, 'approve', 'Waiting', '') === null, 'a treasurer can approve a quantity write-off');
check(approval_error('Staff', 0, 1, 2, 'approve', 'Waiting', '') !== null, 'staff cannot approve a write-off');
check(approval_error('Admin', 0, 5, 5, 'approve', 'Waiting', '') !== null, 'the preparer cannot approve their own write-off');

$admin = db_one("SELECT id FROM users WHERE username = 'admin'");
$treasurer = db_one("SELECT id FROM users WHERE username = 'treasurer'");
check($admin !== null && $treasurer !== null, 'demo admin and treasurer exist');

$pdo = db();
$pdo->beginTransaction();
try {
    $adminId = (int) $admin['id'];
    $treasurerId = (int) $treasurer['id'];
    $foodId = db_exec(
        'INSERT INTO food_items (name, unit, current_stock, minimum_threshold) VALUES (?,?,?,?)',
        ['Step7 Rice', 'kg', 20, 1]
    );
    $small = record_stock_movement('food', $foodId, 'Used', 5, 'kitchen', '2026-09-26', $adminId);
    $foodAfterSmall = db_one('SELECT current_stock FROM food_items WHERE id = ?', [$foodId]);
    check($small['outcome'] === 'posted' && (float) $foodAfterSmall['current_stock'] === 15.0, 'food use at the limit reduces stock now');

    $large = record_stock_movement('food', $foodId, 'Used', 6, 'festival', '2026-09-26', $adminId);
    $foodWhileWaiting = db_one('SELECT current_stock FROM food_items WHERE id = ?', [$foodId]);
    check($large['outcome'] === 'waiting' && (float) $foodWhileWaiting['current_stock'] === 15.0, 'food use above the limit leaves stock unchanged');
    $request = db_one(
        "SELECT r.id FROM stock_requests r JOIN approvals a ON a.subject_type = 'stock' AND a.subject_id = r.id
         WHERE r.item_id = ? AND a.status = 'Waiting' ORDER BY r.id DESC LIMIT 1",
        [$foodId]
    );
    db_exec(
        "UPDATE approvals SET status = 'Approved', decided_by = ? WHERE subject_type = 'stock' AND subject_id = ?",
        [$treasurerId, (int) $request['id']]
    );
    apply_approved_stock((int) $request['id']);
    $foodAfter = db_one('SELECT current_stock FROM food_items WHERE id = ?', [$foodId]);
    check((float) $foodAfter['current_stock'] === 9.0, 'approved food use reduces stock');

    $lampId = db_exec(
        'INSERT INTO inventory_items (category, name, quantity, unit_cost, unit, item_condition, location, source, added_date, added_by)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        ['Puja Items', 'Step7 Lamp', 10, 20, 'pcs', 'Good', 'Store', 'Purchased', '2026-09-26', $adminId]
    );
    $beforeBook = load_book_movements('2026-09-26', '2026-09-26');
    $purchase = record_purchase([
        'item_id' => $lampId,
        'name' => '',
        'category' => 'Puja Items',
        'unit' => 'pcs',
        'quantity' => 10,
        'unit_cost' => 40,
        'location' => null,
        'paid_to' => 'Step7 Supplier',
        'purchase_date' => '2026-09-26',
        'payment_mode' => 'Cash',
        'cheque_number' => null,
        'cheque_date' => null,
        'cheque_cleared' => 0,
        'upi_reference' => null,
    ], $adminId);
    $lampWaiting = db_one('SELECT quantity, unit_cost FROM inventory_items WHERE id = ?', [$lampId]);
    $bookWaiting = load_book_movements('2026-09-26', '2026-09-26');
    $paidWhileWaiting = false;
    foreach ($bookWaiting as $line) {
        if (str_contains((string) $line['particulars'], 'Step7 Supplier')) {
            $paidWhileWaiting = true;
        }
    }
    check(
        $purchase['error'] === null
        && (int) $lampWaiting['quantity'] === 10
        && (float) $lampWaiting['unit_cost'] === 20.0
        && $paidWhileWaiting === false
        && count($bookWaiting) === count($beforeBook),
        'a waiting purchase changes neither stock nor the cash book'
    );
    db_exec(
        "UPDATE approvals SET status = 'Approved', decided_by = ? WHERE subject_type = 'purchase' AND subject_id = ?",
        [$treasurerId, (int) $purchase['id']]
    );
    apply_approved_purchase((int) $purchase['id']);
    $lampBought = db_one('SELECT quantity, unit_cost FROM inventory_items WHERE id = ?', [$lampId]);
    $bookBought = load_book_movements('2026-09-26', '2026-09-26');
    $paid = false;
    foreach ($bookBought as $line) {
        if (str_contains((string) $line['particulars'], 'Step7 Supplier') && abs((float) $line['payment_cash'] - 400) < 0.001) {
            $paid = true;
        }
    }
    check(
        (int) $lampBought['quantity'] === 20
        && (float) $lampBought['unit_cost'] === 30.0
        && stock_value(20, 30) === 600.0
        && $paid,
        'an approved purchase raises stock, averages the rate, and posts the payment'
    );

    $short = record_stock_movement('inventory', $lampId, 'Issued', 25, 'too many', '2026-09-26', $adminId);
    check($short['error'] !== null, 'an issue cannot exceed the quantity on hand');
    $issued = record_stock_movement('inventory', $lampId, 'Issued', 1, 'hall', '2026-09-26', $adminId);
    $afterIssue = db_one('SELECT quantity FROM inventory_items WHERE id = ?', [$lampId]);
    check($issued['outcome'] === 'posted' && (int) $afterIssue['quantity'] === 19, 'an issue is recorded immediately');

    $damaged = record_stock_movement('inventory', $lampId, 'Damaged', 6, 'broken', '2026-09-26', $adminId);
    $whileDamage = db_one('SELECT quantity FROM inventory_items WHERE id = ?', [$lampId]);
    check($damaged['outcome'] === 'waiting' && (int) $whileDamage['quantity'] === 19, 'damage above the limit waits and stock stays');

    $place = save_item_place($lampId, 'Needs Repair', 'Workshop', $adminId, '2026-09-26');
    $placed = db_one('SELECT item_condition, location, quantity FROM inventory_items WHERE id = ?', [$lampId]);
    check(
        $place['outcome'] === 'posted'
        && $placed['item_condition'] === 'Needs Repair'
        && $placed['location'] === 'Workshop'
        && (int) $placed['quantity'] === 19,
        'repair and a new room are saved without changing quantity'
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
echo "all stock tests passed\n";
