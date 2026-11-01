<?php
declare(strict_types=1);

/**
 * Shared prasadam menu for online booking — managed in ERP (Settings › Online booking › Prasadam menu),
 * same rows the devotee sees when adding food to a booking.
 */

use SJT\Core\Database;

return function (Database $db): void {
    $my = $db->driver === 'mysql';
    $bool = $my ? 'TINYINT(1)' : 'INTEGER';
    $dt = $my ? 'DATETIME' : 'TEXT';

    $exists = $my
        ? (bool) $db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'food_coupon_catalog'")
        : (bool) $db->value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'food_coupon_catalog'");

    if (!$exists) {
        $db->pdo->exec(
            "CREATE TABLE food_coupon_catalog (
                id " . ($my ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
                name VARCHAR(100) NOT NULL,
                description VARCHAR(255) NULL,
                price_paise INT NOT NULL,
                max_per_booking INT NOT NULL DEFAULT 200,
                is_active {$bool} NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at {$dt} NOT NULL,
                updated_at {$dt} NOT NULL
            )" . ($my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '')
        );
    }

    $legacy = $my
        ? (bool) $db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'booking_coupons'")
        : (bool) $db->value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'booking_coupons'");

    $count = (int) $db->value('SELECT COUNT(*) FROM food_coupon_catalog');
    if ($count > 0) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    if ($legacy) {
        $rows = $db->all('SELECT * FROM booking_coupons ORDER BY id');
        foreach ($rows as $r) {
            $db->insert('food_coupon_catalog', [
                'id' => (int) $r['id'],
                'name' => $r['name'],
                'description' => $r['description'],
                'price_paise' => (int) $r['price_paise'],
                'max_per_booking' => (int) $r['max_per_booking'],
                'is_active' => (int) $r['is_active'],
                'sort_order' => (int) $r['sort_order'],
                'created_at' => $r['created_at'],
                'updated_at' => $r['updated_at'],
            ]);
        }
        return;
    }

    foreach ([
        ['Niyamita Bhoga Thali', 'Anna, Daali, Mixed Veg, Khatta', 15100],
        ['Special Bhoga Thali', 'Anna, Daali, Mixed Veg, Khatta, ek Bhajji, Kheeri', 19100],
        ['Khichuri Bhoga Thali', 'Khichuri, Bhaja, Khatta', 15100],
        ['Extra Kheeri', 'Additional serving of Kheeri', 5000],
    ] as $i => [$n, $d, $p]) {
        $db->insert('food_coupon_catalog', [
            'name' => $n,
            'description' => $d,
            'price_paise' => $p,
            'max_per_booking' => 200,
            'is_active' => 1,
            'sort_order' => $i,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
