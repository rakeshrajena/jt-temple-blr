<?php
declare(strict_types=1);

/**
 * Shared-database safety:
 * - ERP food_coupons = serial codes (batch_id, code, …).
 * - Booking catalog was briefly named food_coupons; rename to booking_coupons when detected.
 */

use SJT\Core\Database;

return function (Database $db): void {
    if ($db->driver !== 'mysql') {
        return;
    }
    if ($db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'booking_coupons'")) {
        return;
    }
    $legacy = $db->one(
        "SELECT COLUMN_NAME FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'food_coupons' AND column_name = 'price_paise'"
    );
    $erp = $db->one(
        "SELECT COLUMN_NAME FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'food_coupons' AND column_name = 'batch_id'"
    );
    if ($legacy && !$erp) {
        $db->pdo->exec('RENAME TABLE food_coupons TO booking_coupons');
    }
};
