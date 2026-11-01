<?php
declare(strict_types=1);

const ONLINE_BOOKING_PUJA_MIGRATION_FLAG = 'migration_online_booking_puja_purposes_v1';

function online_booking_services_table_exists(): bool
{
    try {
        $pdo = db();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'services'")->fetchColumn();
        }
        return (bool) db_value(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'services'"
        );
    } catch (Throwable) {
        return false;
    }
}

/** @return list<array{name: string, price_paise: int}> */
function legacy_selection_puja_purposes(): array
{
    $out = [];
    foreach (load_selections()['puja_purposes'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        $amount = round((float) ($row['amount'] ?? 0), 2);
        if ($name === '' || $amount < 0.01) {
            continue;
        }
        $out[] = ['name' => $name, 'price_paise' => (int) round($amount * 100)];
    }
    return $out;
}

/** @return array<string, true> lowercased names already in online booking tables */
function online_booking_existing_offer_name_keys(): array
{
    $keys = [];
    if (function_exists('food_coupon_catalog_list')) {
        foreach (food_coupon_catalog_list(false) as $row) {
            $n = mb_strtolower(trim((string) ($row['name'] ?? '')));
            if ($n !== '') {
                $keys[$n] = true;
            }
        }
    }
    if (function_exists('booking_catalog_services') && booking_module_installed()) {
        foreach (booking_catalog_services(false) as $row) {
            $n = mb_strtolower(trim((string) ($row['name'] ?? '')));
            if ($n !== '') {
                $keys[$n] = true;
            }
        }
    }
    return $keys;
}

function online_booking_unique_service_code(string $name): string
{
    $code = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    if ($code === '') {
        $code = 'service';
    }
    $base = mb_substr($code, 0, 45);
    $try = $base;
    $n = 0;
    while (db_value('SELECT 1 FROM services WHERE code = ?', [$try])) {
        $n++;
        $try = $base . '-' . $n;
        if ($n > 99) {
            $try = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            break;
        }
    }
    return $try;
}

function online_booking_insert_migrated_service(string $name, int $pricePaise, int $sortOrder): void
{
    $now = date('Y-m-d H:i:s');
    $code = online_booking_unique_service_code($name);
    db_exec(
        'INSERT INTO services (code, name, description, price_paise, requires_approval, requires_slot, collect_participants,
         max_participants, allow_food, is_food_only, is_combinable, daily_capacity, lead_days, fields_json, is_active, sort_order, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $code,
            $name,
            'Imported from legacy Settings › Choices › Puja purpose.',
            $pricePaise,
            0,
            0,
            0,
            50,
            1,
            0,
            0,
            null,
            null,
            '[]',
            1,
            $sortOrder,
            $now,
            $now,
        ]
    );
}

function online_booking_insert_migrated_prasadam(string $name, int $pricePaise, int $sortOrder): void
{
    if (!food_coupon_catalog_table_exists()) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    db_exec(
        'INSERT INTO food_coupon_catalog (name, description, price_paise, max_per_booking, is_active, sort_order, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?)',
        [
            mb_substr($name, 0, 100),
            'Imported from legacy Settings › Choices › Puja purpose.',
            $pricePaise,
            200,
            1,
            $sortOrder,
            $now,
            $now,
        ]
    );
}

/** One-time: copy selections.json puja_purposes into services (or prasadam catalog if booking tables missing). */
function online_booking_migrate_puja_purposes_from_choices(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $legacy = legacy_selection_puja_purposes();
    if ($legacy === []) {
        if (brand_setting(ONLINE_BOOKING_PUJA_MIGRATION_FLAG) !== '1') {
            online_booking_mark_puja_migration_done();
        }
        return;
    }

    $flagDone = brand_setting(ONLINE_BOOKING_PUJA_MIGRATION_FLAG) === '1';
    if ($flagDone && !online_booking_legacy_puja_purposes_pending($legacy)) {
        return;
    }

    try {
        $useServices = online_booking_services_table_exists();
        $useCatalog = food_coupon_catalog_table_exists();
        if (!$useServices && !$useCatalog) {
            return;
        }

        $existing = online_booking_existing_offer_name_keys();
        $sort = 200 + count($existing) * 2;
        $imported = 0;

        foreach ($legacy as $row) {
            $key = mb_strtolower($row['name']);
            if (isset($existing[$key])) {
                continue;
            }
            if ($useServices) {
                online_booking_insert_migrated_service($row['name'], $row['price_paise'], $sort);
            } else {
                online_booking_insert_migrated_prasadam($row['name'], $row['price_paise'], $sort);
            }
            $existing[$key] = true;
            $sort += 10;
            $imported++;
        }

        if ($imported > 0) {
            error_log('[jt_blr] online_booking: imported ' . $imported . ' legacy puja purpose(s) into ' . ($useServices ? 'services' : 'food_coupon_catalog'));
        }
        online_booking_mark_puja_migration_done();
    } catch (Throwable $e) {
        error_log('[jt_blr] online_booking puja_purposes migration: ' . $e->getMessage());
    }
}

/** @param list<array{name: string, price_paise: int}> $legacy */
function online_booking_legacy_puja_purposes_pending(array $legacy): bool
{
    $existing = online_booking_existing_offer_name_keys();
    foreach ($legacy as $row) {
        if (!isset($existing[mb_strtolower($row['name'])])) {
            return true;
        }
    }
    return false;
}

/**
 * Legacy imports had requires_slot=1 with no slots, which blocks all public dates.
 * Also fixes any active service that requires slots but has none configured.
 */
function online_booking_normalize_service_slot_rules(): void
{
    if (!online_booking_services_table_exists()) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    $rows = db_all('SELECT id, requires_slot, is_active FROM services WHERE requires_slot = 1');
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $activeSlots = (int) db_value(
            'SELECT COUNT(*) FROM time_slots WHERE service_id = ? AND is_active = 1',
            [$id]
        );
        if ($activeSlots > 0) {
            continue;
        }
        db_exec('UPDATE services SET requires_slot = 0, updated_at = ? WHERE id = ?', [$now, $id]);
    }
}

function online_booking_mark_puja_migration_done(): void
{
    db_exec(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [ONLINE_BOOKING_PUJA_MIGRATION_FLAG, '1']
    );
}
