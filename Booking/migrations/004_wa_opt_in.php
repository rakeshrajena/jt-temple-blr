<?php
declare(strict_types=1);

/**
 * WhatsApp opt-in for devotees. Defaults to opt-in (1) so existing bookings
 * and any form that does not send the field keep the current behaviour.
 */

use SJT\Core\Database;

return function (Database $db): void {
    if ($db->driver === 'mysql') {
        $exists = $db->value(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'bookings' AND column_name = 'wa_opt_in'"
        );
        if (!$exists) {
            $db->pdo->exec('ALTER TABLE bookings ADD COLUMN wa_opt_in TINYINT(1) NOT NULL DEFAULT 1');
        }
        return;
    }
    $cols = $db->all('PRAGMA table_info(bookings)');
    $exists = (bool) array_filter($cols, fn (array $c) => ($c['name'] ?? '') === 'wa_opt_in');
    if (!$exists) {
        $db->pdo->exec('ALTER TABLE bookings ADD COLUMN wa_opt_in INTEGER NOT NULL DEFAULT 1');
    }
};
