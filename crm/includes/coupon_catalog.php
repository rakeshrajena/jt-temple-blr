<?php
declare(strict_types=1);

/** Default online prasadam menu (matches Booking migration seed). */
function food_coupon_catalog_default_menu(): array
{
    return [
        ['Niyamita Bhoga Thali', 'Anna, Daali, Mixed Veg, Khatta', 15100],
        ['Special Bhoga Thali', 'Anna, Daali, Mixed Veg, Khatta, ek Bhajji, Kheeri', 19100],
        ['Khichuri Bhoga Thali', 'Khichuri, Bhaja, Khatta', 15100],
        ['Extra Kheeri', 'Additional serving of Kheeri', 5000],
    ];
}

function food_coupon_catalog_table_exists_on(PDO $pdo): bool
{
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'food_coupon_catalog'");
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute(['food_coupon_catalog']);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function food_coupon_catalog_booking_coupons_exists(PDO $pdo): bool
{
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'booking_coupons'");
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute(['booking_coupons']);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

/** Create food_coupon_catalog and seed defaults when empty (ERP shared DB). */
function ensure_food_coupon_catalog_schema(PDO $pdo): void
{
    $my = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if (!food_coupon_catalog_table_exists_on($pdo)) {
        if ($my) {
            $pdo->exec(
                'CREATE TABLE food_coupon_catalog (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description VARCHAR(255) NULL,
                    price_paise INT NOT NULL,
                    max_per_booking INT NOT NULL DEFAULT 200,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE food_coupon_catalog (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(100) NOT NULL,
                    description VARCHAR(255) NULL,
                    price_paise INT NOT NULL,
                    max_per_booking INT NOT NULL DEFAULT 200,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
            );
        }
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM food_coupon_catalog')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    if (food_coupon_catalog_booking_coupons_exists($pdo)) {
        $rows = $pdo->query('SELECT * FROM booking_coupons ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare(
            'INSERT INTO food_coupon_catalog (id, name, description, price_paise, max_per_booking, is_active, sort_order, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        foreach ($rows as $r) {
            $insert->execute([
                (int) $r['id'],
                $r['name'],
                $r['description'],
                (int) $r['price_paise'],
                (int) $r['max_per_booking'],
                (int) $r['is_active'],
                (int) $r['sort_order'],
                $r['created_at'],
                $r['updated_at'],
            ]);
        }
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO food_coupon_catalog (name, description, price_paise, max_per_booking, is_active, sort_order, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    foreach (food_coupon_catalog_default_menu() as $i => [$name, $desc, $paise]) {
        $insert->execute([$name, $desc, $paise, 200, 1, $i, $now, $now]);
    }
}

function food_coupon_catalog_ensure_ready(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        ensure_food_coupon_catalog_schema(db());
    } catch (Throwable $e) {
        error_log('[jt_blr] food_coupon_catalog: ' . $e->getMessage());
    }
}

function food_coupon_catalog_table_exists(): bool
{
    try {
        $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return (bool) db_value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'food_coupon_catalog'");
        }
        return (bool) db_value(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'food_coupon_catalog'"
        );
    } catch (Throwable) {
        return false;
    }
}

/** @return list<array<string,mixed>> */
function food_coupon_catalog_list(bool $activeOnly = false): array
{
    if (!food_coupon_catalog_table_exists()) {
        return [];
    }
    $sql = 'SELECT * FROM food_coupon_catalog' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id';
    $rows = db_all($sql);
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['price_paise'] = (int) $row['price_paise'];
        $row['max_per_booking'] = (int) $row['max_per_booking'];
        $row['is_active'] = (int) $row['is_active'] === 1;
        $row['sort_order'] = (int) $row['sort_order'];
    }
    unset($row);
    return $rows;
}

/** Existing rows plus one blank row for adding a menu item. */
function food_coupon_catalog_editor_rows(): array
{
    $rows = food_coupon_catalog_list(false);
    $rows[] = [
        'id' => 0,
        'name' => '',
        'description' => '',
        'price_paise' => 0,
        'max_per_booking' => 200,
        'is_active' => true,
        'sort_order' => count($rows),
    ];
    return $rows;
}

function food_coupon_catalog_can_edit(string $role): bool
{
    return in_array($role, ['Admin', 'Treasurer'], true);
}

/** @return array{error:?string} */
function food_coupon_catalog_save_from_post(): array
{
    if (!food_coupon_catalog_table_exists()) {
        return ['error' => 'Online booking menu is not set up yet. Run Booking migrations on this database.'];
    }
    $id = (int) ($_POST['catalog_id'] ?? 0);
    $name = trim(post_string('catalog_name', 100));
    $priceRaw = trim((string) ($_POST['catalog_price'] ?? ''));
    $price = is_numeric(str_replace(',', '', $priceRaw)) ? (int) round((float) str_replace(',', '', $priceRaw) * 100) : 0;
    if (mb_strlen($name) < 3 || $price <= 0) {
        return ['error' => 'Enter a name (3+ characters) and a price above zero.'];
    }
    $row = [
        'name' => $name,
        'description' => trim(post_string('catalog_description', 255)) ?: null,
        'price_paise' => $price,
        'max_per_booking' => max(1, min(5000, (int) ($_POST['catalog_max'] ?? 200))),
        'is_active' => isset($_POST['catalog_active']) ? 1 : 0,
        'sort_order' => (int) ($_POST['catalog_sort'] ?? 0),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($id > 0) {
        db_exec(
            'UPDATE food_coupon_catalog SET name = ?, description = ?, price_paise = ?, max_per_booking = ?, is_active = ?, sort_order = ?, updated_at = ? WHERE id = ?',
            [$row['name'], $row['description'], $row['price_paise'], $row['max_per_booking'], $row['is_active'], $row['sort_order'], $row['updated_at'], $id]
        );
    } else {
        db_exec(
            'INSERT INTO food_coupon_catalog (name, description, price_paise, max_per_booking, is_active, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
            [$row['name'], $row['description'], $row['price_paise'], $row['max_per_booking'], $row['is_active'], $row['sort_order'], $row['updated_at'], $row['updated_at']]
        );
    }
    return ['error' => null];
}

/**
 * Names and prices for printed coupons and legacy puja-purpose suggests.
 * Source of truth: Settings › Online booking (Prasadam menu + Services).
 *
 * @return list<array{name: string, amount: string, tag: string}>
 */
function online_booking_offer_suggestions(): array
{
    $out = [];
    $seen = [];
    $add = static function (string $name, int $pricePaise, string $tag) use (&$out, &$seen): void {
        $name = trim($name);
        if ($name === '' || $pricePaise < 1) {
            return;
        }
        $key = mb_strtolower($name);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $out[] = [
            'name' => $name,
            'amount' => number_format($pricePaise / 100, 2, '.', ''),
            'tag' => $tag,
        ];
    };
    foreach (food_coupon_catalog_list(true) as $row) {
        $add((string) ($row['name'] ?? ''), (int) ($row['price_paise'] ?? 0), 'prasadam');
    }
    if (function_exists('booking_catalog_services') && booking_module_installed()) {
        foreach (booking_catalog_services(true) as $row) {
            $add((string) ($row['name'] ?? ''), (int) ($row['price_paise'] ?? 0), 'service');
        }
    }
    return $out;
}
