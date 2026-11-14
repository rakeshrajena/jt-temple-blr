<?php
declare(strict_types=1);

/** @return list<string> */
function vastra_movement_types(): array
{
    return ['Issue', 'Return', 'Retire'];
}

function vastra_normalize_status(string $status): string
{
    foreach (selection_values('vastra_statuses') as $allowed) {
        if (strcasecmp($status, $allowed) === 0) {
            return $allowed;
        }
    }

    return 'In Store';
}

/** @return array{In Store: int, In Use: int, Retired: int, total: int, lines: int} */
function vastra_status_totals(): array
{
    $out = [
        'In Store' => 0,
        'In Use' => 0,
        'Retired' => 0,
        'total' => 0,
        'lines' => 0,
    ];
    foreach (db_all(
        'SELECT status, quantity FROM vastra_items WHERE quantity > 0'
    ) as $row) {
        $key = vastra_normalize_status((string) ($row['status'] ?? 'In Store'));
        $qty = (int) $row['quantity'];
        if (array_key_exists($key, $out)) {
            $out[$key] += $qty;
        }
        $out['total'] += $qty;
        $out['lines']++;
    }

    return $out;
}

/**
 * One row per deity + item + colour (no duplicate product lines).
 *
 * @return list<array{deity_name: string, item_name: string, color: ?string, in_store: int, in_use: int, retired: int, purchased: int, donated: int, total: int}>
 */
function vastra_stock_summary(): array
{
    $rows = db_all(
        "SELECT deity_name, item_name, color,
                SUM(CASE WHEN status = 'In Store' THEN quantity ELSE 0 END) AS in_store,
                SUM(CASE WHEN status = 'In Use' THEN quantity ELSE 0 END) AS in_use,
                SUM(CASE WHEN status = 'Retired' THEN quantity ELSE 0 END) AS retired,
                SUM(CASE WHEN source = 'Purchased' THEN quantity ELSE 0 END) AS purchased,
                SUM(CASE WHEN source = 'Donated' THEN quantity ELSE 0 END) AS donated,
                SUM(quantity) AS total
         FROM vastra_items
         WHERE quantity > 0
         GROUP BY deity_name, item_name, color
         ORDER BY deity_name, item_name, color"
    );
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'deity_name' => (string) $row['deity_name'],
            'item_name' => (string) $row['item_name'],
            'color' => $row['color'] !== null && $row['color'] !== '' ? (string) $row['color'] : null,
            'in_store' => (int) $row['in_store'],
            'in_use' => (int) $row['in_use'],
            'retired' => (int) $row['retired'],
            'purchased' => (int) $row['purchased'],
            'donated' => (int) $row['donated'],
            'total' => (int) $row['total'],
        ];
    }

    return $out;
}

/** @param list<array{in_store: int, in_use: int, retired: int, purchased: int, donated: int, total: int}> $rows */
function vastra_summary_roll_up(array $rows): array
{
    $sum = [
        'in_store' => 0,
        'in_use' => 0,
        'retired' => 0,
        'purchased' => 0,
        'donated' => 0,
        'total' => 0,
    ];
    foreach ($rows as $row) {
        foreach ($sum as $key => $_) {
            $sum[$key] += (int) $row[$key];
        }
    }

    return $sum;
}

function ensure_vastra_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS vastra_movements (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            vastra_id       INT NOT NULL,
            movement_type   VARCHAR(20) NOT NULL,
            quantity        INT NOT NULL,
            note            VARCHAR(255) NULL,
            movement_date   DATE NOT NULL,
            status_before   VARCHAR(30) NULL,
            status_after    VARCHAR(30) NULL,
            related_vastra_id INT NULL,
            logged_by       INT NULL,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vastra_id) REFERENCES vastra_items(id),
            FOREIGN KEY (logged_by) REFERENCES users(id),
            KEY idx_vastra_movement_item (vastra_id),
            KEY idx_vastra_movement_date (movement_date)
        ) ENGINE=InnoDB"
    );
}

/** @param array<string, mixed> $item */
function vastra_find_store_line(array $item, ?int $excludeId = null): ?int
{
    $sql = "SELECT id FROM vastra_items
            WHERE deity_name = ? AND item_name = ? AND status = 'In Store'
              AND (color <=> ?) AND (source <=> ?) AND quantity > 0";
    $params = [$item['deity_name'], $item['item_name'], $item['color'] ?? null, $item['source'] ?? null];
    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' ORDER BY id LIMIT 1';
    $row = db_one($sql, $params);

    return $row !== null ? (int) $row['id'] : null;
}

/**
 * @param array<string, mixed> $item
 * @return int id of the line holding returned / store stock
 */
function vastra_receive_into_store(array $item, int $quantity, string $date, string $note, ?int $excludeId = null): int
{
    $storeId = vastra_find_store_line($item, $excludeId);
    if ($storeId !== null) {
        db_exec('UPDATE vastra_items SET quantity = quantity + ? WHERE id = ?', [$quantity, $storeId]);

        return $storeId;
    }

    return (int) db_exec(
        'INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, donation_id, date_added, status, notes)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            $item['deity_name'],
            $item['item_name'],
            $item['color'],
            $quantity,
            $item['source'],
            $item['donation_id'] ?? null,
            $date,
            'In Store',
            $note !== '' ? $note : null,
        ]
    );
}

/**
 * @param array<string, mixed> $item
 * @return int id of the new or updated line for the moved quantity
 */
function vastra_create_status_line(
    array $item,
    int $quantity,
    string $statusAfter,
    string $date,
    string $note
): int {
    if ($statusAfter === 'In Store') {
        return vastra_receive_into_store($item, $quantity, $date, $note, null);
    }

    return (int) db_exec(
        'INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, donation_id, date_added, status, notes)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            $item['deity_name'],
            $item['item_name'],
            $item['color'],
            $quantity,
            $item['source'],
            $item['donation_id'] ?? null,
            $date,
            $statusAfter,
            $note !== '' ? $note : null,
        ]
    );
}

/**
 * @return array{error: ?string}
 */
function record_vastra_movement(
    int $vastraId,
    string $movement,
    int $quantity,
    string $note,
    string $date,
    int $userId
): array {
    $failed = ['error' => null];
    if (!in_array($movement, vastra_movement_types(), true)) {
        $failed['error'] = 'That vastra movement is not recognised.';

        return $failed;
    }
    if ($quantity < 1) {
        $failed['error'] = 'Enter a quantity of at least 1.';

        return $failed;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $item = db_one('SELECT * FROM vastra_items WHERE id = ?', [$vastraId]);
    if ($item === null) {
        $failed['error'] = 'Vastra item not found.';

        return $failed;
    }
    $onHand = (int) $item['quantity'];
    if ($onHand < 1) {
        $failed['error'] = 'This line has no quantity left.';

        return $failed;
    }
    $status = vastra_normalize_status((string) ($item['status'] ?? 'In Store'));
    if ($quantity > $onHand) {
        $failed['error'] = 'Only ' . $onHand . ' on this line — lower the quantity.';

        return $failed;
    }

    $statusAfter = match ($movement) {
        'Issue' => 'In Use',
        'Return' => 'In Store',
        'Retire' => 'Retired',
        default => $status,
    };

    if ($movement === 'Issue' && $status !== 'In Store') {
        $failed['error'] = 'Issue only applies to vastra that is In Store.';

        return $failed;
    }
    if ($movement === 'Return' && $status !== 'In Use') {
        $failed['error'] = 'Return only applies to vastra that is In Use.';

        return $failed;
    }
    if ($movement === 'Retire' && $status === 'Retired') {
        $failed['error'] = 'This line is already retired.';

        return $failed;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $relatedId = $vastraId;
        if ($quantity < $onHand) {
            db_exec('UPDATE vastra_items SET quantity = quantity - ? WHERE id = ?', [$quantity, $vastraId]);
            $relatedId = vastra_create_status_line($item, $quantity, $statusAfter, $date, $note);
        } elseif ($movement === 'Return') {
            $relatedId = vastra_receive_into_store($item, $onHand, $date, $note, $vastraId);
            db_exec('UPDATE vastra_items SET quantity = 0 WHERE id = ?', [$vastraId]);
        } elseif ($movement === 'Issue') {
            db_exec('UPDATE vastra_items SET status = ? WHERE id = ?', ['In Use', $vastraId]);
            $relatedId = $vastraId;
        } else {
            db_exec('UPDATE vastra_items SET status = ? WHERE id = ?', [$statusAfter, $vastraId]);
            $relatedId = $vastraId;
        }

        if ($note !== '' && $relatedId === $vastraId && $quantity >= $onHand) {
            $existingNote = trim((string) ($item['notes'] ?? ''));
            if ($existingNote === '') {
                db_exec('UPDATE vastra_items SET notes = ? WHERE id = ?', [$note, $vastraId]);
            }
        }

        db_exec(
            'INSERT INTO vastra_movements (vastra_id, movement_type, quantity, note, movement_date, status_before, status_after, related_vastra_id, logged_by)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $vastraId,
                $movement,
                $quantity,
                $note !== '' ? $note : null,
                $date,
                $status,
                $statusAfter,
                $relatedId,
                $userId,
            ]
        );
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $failed['error'] = 'Could not record the movement.';

        return $failed;
    }

    return $failed;
}