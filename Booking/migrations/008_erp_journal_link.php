<?php
declare(strict_types=1);

/** Link bookings to ERP donations and track cancellation approval. */

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
        $cols = array_column($db->pdo->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
        if (in_array($col, $cols, true)) {
            return;
        }
        $db->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$def}");
    };

    $add('bookings', 'erp_donation_id', 'INT NULL');
    $add('bookings', 'erp_receipt_cancel_id', 'INT NULL');
    $add('bookings', 'cancellation_status', 'VARCHAR(30) NULL');

    $hasDonations = $my
        ? (bool) $db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'donations' LIMIT 1")
        : (bool) $db->value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'donations' LIMIT 1");
    if (!$hasDonations) {
        return;
    }

    $add('donations', 'online_booking_id', 'INT NULL');
    $add('donations', 'online_booking_no', 'VARCHAR(30) NULL');

    if ($my) {
        $idx = $db->value(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'donations' AND index_name = 'uq_donations_online_booking_no' LIMIT 1"
        );
        if (!$idx) {
            $db->pdo->exec('CREATE UNIQUE INDEX uq_donations_online_booking_no ON donations (online_booking_no)');
        }
    }
};
