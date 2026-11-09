<?php
declare(strict_types=1);

/**
 * Optional daily person-cap for each prasadam item, shared by every booking on that date.
 * NULL means no daily limit (max_per_booking still applies to one booking).
 */

use SJT\Core\Database;

return function (Database $db): void {
    $my = $db->driver === 'mysql';
    $add = static function (string $table, string $col, string $def) use ($db, $my): void {
        $tableExists = $my
            ? (bool) $db->value(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            )
            : (bool) $db->value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        if (!$tableExists) {
            return;
        }
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

    $add('booking_coupons', 'daily_max', 'INT NULL');
    $add('food_coupon_catalog', 'daily_max', 'INT NULL');
};
