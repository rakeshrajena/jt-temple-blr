<?php
declare(strict_types=1);

final class StockApplyException extends RuntimeException
{
}

function inventory_conditions(): array
{
    return ['New', 'Good', 'Fair', 'Needs Repair', 'Damaged', 'Retired'];
}

function inventory_movements(): array
{
    return ['Added', 'Issued', 'Returned', 'Damaged', 'Lost', 'Retired'];
}

function stock_direction(string $movement): int
{
    return match ($movement) {
        'Added', 'Returned' => 1,
        'Issued', 'Used', 'Damaged', 'Lost', 'Retired' => -1,
        default => 0,
    };
}

function stock_needs_approval(string $movement, float $quantity): bool
{
    if (!in_array($movement, ['Used', 'Damaged', 'Lost', 'Retired'], true)) {
        return false;
    }
    return $quantity > STOCK_WRITE_OFF_LIMIT;
}

function quantity_after_movement(float $current, string $movement, float $quantity): ?float
{
    $direction = stock_direction($movement);
    if ($direction === 0 || $quantity <= 0) {
        return null;
    }
    $next = round($current + ($direction * $quantity), 2);
    if ($next < -0.001) {
        return null;
    }
    return $next;
}

function stock_value(float $quantity, float $unitCost): float
{
    return round(max(0, $quantity) * max(0, $unitCost), 2);
}

function weighted_unit_cost(float $oldQty, float $oldCost, float $addQty, float $addRate): float
{
    if ($addQty <= 0 || $oldQty <= 0) {
        return round($addQty > 0 ? $addRate : $oldCost, 2);
    }
    $total = $oldQty + $addQty;
    return round((($oldQty * $oldCost) + ($addQty * $addRate)) / $total, 2);
}

function ensure_stock_schema(PDO $pdo): void
{
    $cost = $pdo->query("SHOW COLUMNS FROM inventory_items LIKE 'unit_cost'")->fetch();
    if ($cost === false) {
        $pdo->exec(
            'ALTER TABLE inventory_items ADD COLUMN unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity'
        );
    }
    $condition = $pdo->query("SHOW COLUMNS FROM inventory_items LIKE 'item_condition'")->fetch();
    if ($condition !== false && !str_contains((string) $condition['Type'], 'Retired')) {
        $pdo->exec(
            "ALTER TABLE inventory_items
             MODIFY item_condition ENUM('New','Good','Fair','Needs Repair','Damaged','Retired') DEFAULT 'Good'"
        );
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS inventory_movements (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            item_id         INT NOT NULL,
            movement_type   ENUM('Added','Issued','Returned','Damaged','Lost','Retired') NOT NULL,
            quantity        INT NOT NULL,
            note            VARCHAR(255) NULL,
            movement_date   DATE NOT NULL,
            logged_by       INT NULL,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES inventory_items(id),
            FOREIGN KEY (logged_by) REFERENCES users(id),
            KEY idx_inventory_movement_item (item_id),
            KEY idx_inventory_movement_date (movement_date)
        ) ENGINE=InnoDB"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS stock_requests (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            store_name      ENUM('food','inventory') NOT NULL,
            item_id         INT NOT NULL,
            item_name       VARCHAR(150) NOT NULL,
            movement_type   VARCHAR(20) NOT NULL,
            quantity        DECIMAL(12,2) NOT NULL,
            note            VARCHAR(255) NULL,
            movement_date   DATE NOT NULL,
            prepared_by     INT NOT NULL,
            applied         TINYINT(1) NOT NULL DEFAULT 0,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (prepared_by) REFERENCES users(id),
            KEY idx_stock_request_item (store_name, item_id)
        ) ENGINE=InnoDB"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS purchases (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            item_id         INT NULL,
            item_name       VARCHAR(150) NOT NULL,
            category        VARCHAR(50) NOT NULL,
            unit            VARCHAR(30) NOT NULL DEFAULT 'pcs',
            quantity        INT NOT NULL,
            unit_cost       DECIMAL(12,2) NOT NULL,
            amount          DECIMAL(12,2) NOT NULL,
            location        VARCHAR(100) NULL,
            paid_to         VARCHAR(150) NULL,
            purchase_date   DATE NOT NULL,
            payment_mode    ENUM('Cash','Bank Transfer','UPI','Cheque') NOT NULL,
            cheque_number   VARCHAR(30) NULL,
            cheque_date     DATE NULL,
            cheque_cleared  TINYINT(1) NOT NULL DEFAULT 0,
            upi_reference   VARCHAR(64) NULL,
            expense_id      INT NULL,
            prepared_by     INT NOT NULL,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES inventory_items(id),
            FOREIGN KEY (prepared_by) REFERENCES users(id),
            KEY idx_purchase_date (purchase_date)
        ) ENGINE=InnoDB"
    );
}

function stock_request_is_open(string $store, int $itemId): bool
{
    $count = db_value(
        "SELECT COUNT(*) FROM stock_requests r
         JOIN approvals a ON a.subject_type = 'stock' AND a.subject_id = r.id
         WHERE r.store_name = ? AND r.item_id = ? AND a.status IN ('Draft', 'Waiting', 'Sent back')",
        [$store, $itemId]
    );
    return (int) $count > 0;
}

/**
 * @return array{error: ?string, outcome: ?string}
 */
function record_stock_movement(
    string $store,
    int $itemId,
    string $movement,
    float $quantity,
    string $note,
    string $date,
    int $userId
): array {
    $failed = ['error' => null, 'outcome' => null];
    $allowed = $store === 'food' ? ['Added', 'Used'] : inventory_movements();
    if (!in_array($store, ['food', 'inventory'], true) || !in_array($movement, $allowed, true)) {
        $failed['error'] = 'That stock movement is not recognised.';
        return $failed;
    }
    if ($store === 'inventory' && abs($quantity - round($quantity)) > 0.001) {
        $failed['error'] = 'Enter a whole number of pieces.';
        return $failed;
    }
    $quantity = $store === 'inventory' ? (float) (int) round($quantity) : round($quantity, 2);
    $bookDate = valid_book_date($date);
    if ($itemId < 1 || $quantity <= 0 || $bookDate === null) {
        $failed['error'] = 'Choose an item, a date, and a quantity greater than zero.';
        return $failed;
    }
    $note = trim($note);
    if (strlen($note) > 255) {
        $note = substr($note, 0, 255);
    }

    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $item = lock_stock_item($store, $itemId);
        if ($item === null) {
            $failed['error'] = 'That item is not on the list.';
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $failed;
        }
        if (quantity_after_movement((float) $item['quantity'], $movement, $quantity) === null) {
            $failed['error'] = 'There is not enough stock for that quantity.';
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $failed;
        }
        if (stock_needs_approval($movement, $quantity)) {
            if (stock_request_is_open($store, $itemId)) {
                $failed['error'] = 'A write-off for this item is already waiting for approval.';
                if ($own && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return $failed;
            }
            $requestId = db_exec(
                'INSERT INTO stock_requests (store_name, item_id, item_name, movement_type, quantity, note, movement_date, prepared_by)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$store, $itemId, (string) $item['name'], $movement, $quantity, $note !== '' ? $note : null, $bookDate, $userId]
            );
            record_approval('stock', $requestId, 'Waiting', 0.0, $userId);
            if ($own) {
                $pdo->commit();
            }
            return ['error' => null, 'outcome' => 'waiting'];
        }
        apply_stock_change($store, $item, $movement, $quantity, $note, $bookDate, $userId);
        if ($own) {
            $pdo->commit();
        }
        return ['error' => null, 'outcome' => 'posted'];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * @param array<string, mixed> $item
 */
function apply_stock_change(
    string $store,
    array $item,
    string $movement,
    float $quantity,
    string $note,
    string $date,
    int $userId
): void {
    $next = quantity_after_movement((float) $item['quantity'], $movement, $quantity);
    if ($next === null) {
        throw new StockApplyException('There is not enough stock left for this write-off.');
    }
    $itemId = (int) $item['id'];
    if ($store === 'food') {
        db_exec(
            'UPDATE food_items SET current_stock = ?, last_updated = ? WHERE id = ?',
            [$next, date('Y-m-d H:i:s'), $itemId]
        );
        db_exec(
            'INSERT INTO food_usage_log (food_item_id, txn_type, quantity, purpose, txn_date, logged_by) VALUES (?,?,?,?,?,?)',
            [$itemId, $movement, $quantity, $note !== '' ? $note : null, $date, $userId]
        );
        return;
    }
    $condition = (string) ($item['item_condition'] ?? 'Good');
    if ($movement === 'Retired' && $next <= 0) {
        $condition = 'Retired';
    }
    db_exec(
        'UPDATE inventory_items SET quantity = ?, item_condition = ? WHERE id = ?',
        [(int) round($next), $condition, $itemId]
    );
    db_exec(
        'INSERT INTO inventory_movements (item_id, movement_type, quantity, note, movement_date, logged_by) VALUES (?,?,?,?,?,?)',
        [$itemId, $movement, (int) round($quantity), $note !== '' ? $note : null, $date, $userId]
    );
}

function apply_approved_stock(int $requestId): void
{
    $request = db_one('SELECT * FROM stock_requests WHERE id = ? FOR UPDATE', [$requestId]);
    if ($request === null || (int) $request['applied'] === 1) {
        return;
    }
    $store = (string) $request['store_name'];
    $item = lock_stock_item($store, (int) $request['item_id']);
    if ($item === null) {
        throw new StockApplyException('That item is no longer on the list.');
    }
    apply_stock_change(
        $store,
        $item,
        (string) $request['movement_type'],
        (float) $request['quantity'],
        (string) ($request['note'] ?? ''),
        (string) $request['movement_date'],
        (int) $request['prepared_by']
    );
    db_exec('UPDATE stock_requests SET applied = 1 WHERE id = ?', [$requestId]);
}

/**
 * @return array<string, mixed>|null
 */
function lock_stock_item(string $store, int $itemId): ?array
{
    if ($store === 'food') {
        return db_one(
            'SELECT id, name, current_stock AS quantity, unit FROM food_items WHERE id = ? FOR UPDATE',
            [$itemId]
        );
    }
    return db_one(
        'SELECT id, name, quantity, unit, unit_cost, item_condition, category, location FROM inventory_items WHERE id = ? FOR UPDATE',
        [$itemId]
    );
}

/**
 * @param array{
 *   item_id: int,
 *   name: string,
 *   category: string,
 *   unit: string,
 *   quantity: int,
 *   unit_cost: float,
 *   location: ?string,
 *   paid_to: ?string,
 *   purchase_date: string,
 *   payment_mode: string,
 *   cheque_number: ?string,
 *   cheque_date: ?string,
 *   cheque_cleared: int,
 *   upi_reference: ?string
 * } $purchase
 * @return array{error: ?string, id: ?int}
 */
function record_purchase(array $purchase, int $userId): array
{
    $failed = ['error' => null, 'id' => null];
    $date = valid_book_date($purchase['purchase_date']);
    $mode = $purchase['payment_mode'];
    $quantity = $purchase['quantity'];
    $rate = round($purchase['unit_cost'], 2);
    if ($date === null || $quantity < 1 || $rate <= 0) {
        $failed['error'] = 'Enter a date, a quantity of at least 1, and a rate above zero.';
        return $failed;
    }
    if (!in_array($mode, ['Cash', 'Bank Transfer', 'UPI', 'Cheque'], true)) {
        $failed['error'] = 'Choose cash, bank transfer, UPI, or cheque.';
        return $failed;
    }
    $name = trim($purchase['name']);
    $category = $purchase['category'];
    $unit = trim($purchase['unit']) !== '' ? trim($purchase['unit']) : 'pcs';
    $itemId = $purchase['item_id'] > 0 ? $purchase['item_id'] : null;
    if ($itemId !== null) {
        $item = db_one('SELECT id, name, category, unit FROM inventory_items WHERE id = ?', [$itemId]);
        if ($item === null) {
            $failed['error'] = 'That item is not on the list.';
            return $failed;
        }
        $name = (string) $item['name'];
        $category = (string) $item['category'];
        $unit = (string) $item['unit'];
    } elseif ($name === '') {
        $failed['error'] = 'Enter the item name.';
        return $failed;
    }
    if (!in_array($category, INVENTORY_CATEGORIES, true)) {
        $category = 'Other';
    }
    $amount = round($quantity * $rate, 2);
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $id = db_exec(
            'INSERT INTO purchases (item_id, item_name, category, unit, quantity, unit_cost, amount, location, paid_to, purchase_date, payment_mode, cheque_number, cheque_date, cheque_cleared, upi_reference, prepared_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $itemId,
                $name,
                $category,
                $unit,
                $quantity,
                $rate,
                $amount,
                $purchase['location'],
                $purchase['paid_to'],
                $date,
                $mode,
                $purchase['cheque_number'],
                $purchase['cheque_date'],
                $purchase['cheque_cleared'],
                $purchase['upi_reference'],
                $userId,
            ]
        );
        record_approval('purchase', $id, 'Waiting', $amount, $userId);
        if ($own) {
            $pdo->commit();
        }
        return ['error' => null, 'id' => $id];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function apply_approved_purchase(int $purchaseId): void
{
    $purchase = db_one('SELECT * FROM purchases WHERE id = ? FOR UPDATE', [$purchaseId]);
    if ($purchase === null || $purchase['expense_id'] !== null) {
        return;
    }
    $quantity = (int) $purchase['quantity'];
    $rate = (float) $purchase['unit_cost'];
    $itemId = $purchase['item_id'] !== null ? (int) $purchase['item_id'] : 0;
    if ($itemId > 0) {
        $item = lock_stock_item('inventory', $itemId);
        if ($item === null) {
            throw new StockApplyException('That item is no longer on the list.');
        }
        $newCost = weighted_unit_cost((float) $item['quantity'], (float) $item['unit_cost'], (float) $quantity, $rate);
        db_exec(
            'UPDATE inventory_items SET quantity = quantity + ?, unit_cost = ? WHERE id = ?',
            [$quantity, $newCost, $itemId]
        );
    } else {
        $itemId = db_exec(
            'INSERT INTO inventory_items (category, name, quantity, unit_cost, unit, item_condition, location, source, added_date, added_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                (string) $purchase['category'],
                (string) $purchase['item_name'],
                $quantity,
                $rate,
                (string) $purchase['unit'],
                'New',
                $purchase['location'],
                'Purchased',
                (string) $purchase['purchase_date'],
                (int) $purchase['prepared_by'],
            ]
        );
        db_exec('UPDATE purchases SET item_id = ? WHERE id = ?', [$itemId, $purchaseId]);
    }
    db_exec(
        'INSERT INTO inventory_movements (item_id, movement_type, quantity, note, movement_date, logged_by) VALUES (?,?,?,?,?,?)',
        [$itemId, 'Added', $quantity, 'Purchase', (string) $purchase['purchase_date'], (int) $purchase['prepared_by']]
    );
    $voucher = next_voucher_number(db(), (string) $purchase['purchase_date']);
    $expenseId = db_exec(
        'INSERT INTO expenses (category, description, amount, paid_to, expense_date, payment_mode, voucher_number, cheque_number, cheque_date, cheque_cleared, upi_reference, receipt_ref, added_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            'Other',
            'Stock purchase: ' . (string) $purchase['item_name'],
            (float) $purchase['amount'],
            $purchase['paid_to'],
            (string) $purchase['purchase_date'],
            (string) $purchase['payment_mode'],
            $voucher,
            $purchase['cheque_number'],
            $purchase['cheque_date'],
            (int) $purchase['cheque_cleared'],
            $purchase['upi_reference'],
            null,
            (int) $purchase['prepared_by'],
        ]
    );
    record_approval('expense', $expenseId, 'Approved', (float) $purchase['amount'], (int) $purchase['prepared_by']);
    db_exec('UPDATE purchases SET expense_id = ? WHERE id = ?', [$expenseId, $purchaseId]);
}

/**
 * @return array{error: ?string, outcome: ?string}
 */
function save_item_place(int $itemId, string $condition, string $location, int $userId, string $date): array
{
    $failed = ['error' => null, 'outcome' => null];
    if (!in_array($condition, inventory_conditions(), true)) {
        $failed['error'] = 'Choose a condition from the list.';
        return $failed;
    }
    $item = db_one('SELECT id, quantity FROM inventory_items WHERE id = ?', [$itemId]);
    if ($item === null) {
        $failed['error'] = 'That item is not on the list.';
        return $failed;
    }
    $location = trim($location);
    if (strlen($location) > 100) {
        $location = substr($location, 0, 100);
    }
    if ($condition === 'Retired' && (int) $item['quantity'] > 0) {
        db_exec(
            'UPDATE inventory_items SET location = ? WHERE id = ?',
            [$location !== '' ? $location : null, $itemId]
        );
        return record_stock_movement(
            'inventory',
            $itemId,
            'Retired',
            (float) $item['quantity'],
            'Retired',
            $date,
            $userId
        );
    }
    db_exec(
        'UPDATE inventory_items SET item_condition = ?, location = ? WHERE id = ?',
        [$condition, $location !== '' ? $location : null, $itemId]
    );
    return ['error' => null, 'outcome' => 'posted'];
}
