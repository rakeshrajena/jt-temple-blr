<?php
declare(strict_types=1);

/** Staff can cancel one service or prasadam line without deleting the booking. */

use SJT\Core\Database;

return function (Database $db): void {
    $my = $db->driver === 'mysql';
    $add = static function (string $table, string $col, string $def) use ($db, $my): void {
        if ($my) {
            $exists = $db->value(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $col]
            );
            if ($exists) {
                return;
            }
            $db->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$col} {$def}");
            return;
        }
        $cols = array_column($db->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name');
        if (in_array($col, $cols, true)) {
            return;
        }
        $db->pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $col . ' ' . $def);
    };

    $add('booking_items', 'cancelled_at', $my ? 'DATETIME NULL' : 'TEXT NULL');
    $add('booking_items', 'cancel_reason', 'VARCHAR(255) NULL');
};
