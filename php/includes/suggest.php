<?php
declare(strict_types=1);

function suggest_like(string $value): string
{
    $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    return '%' . $value . '%';
}

/** @return list<array{id: int, name: string, phone: string, email: string, address: string, pan_number: string}> */
function suggest_donors(string $query): array
{
    $query = mb_substr(trim($query), 0, 80);
    if ($query === '') {
        return [];
    }
    $like = suggest_like($query);
    $rows = db_all(
        'SELECT id, name, phone, email, address, pan_number
         FROM donors
         WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?
         ORDER BY name
         LIMIT 12',
        [$like, $like, $like]
    );
    $matches = [];
    foreach ($rows as $row) {
        $matches[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'pan_number' => (string) ($row['pan_number'] ?? ''),
        ];
    }
    return $matches;
}

/** @return list<array{name: string, unit: string, stock: string, minimum_threshold: string}> */
function suggest_food_catalog(): array
{
    $rows = [];
    foreach (db_all('SELECT name, unit, current_stock, minimum_threshold FROM food_items ORDER BY name') as $row) {
        $rows[] = [
            'name' => (string) $row['name'],
            'unit' => (string) $row['unit'],
            'stock' => (string) $row['current_stock'],
            'minimum_threshold' => (string) $row['minimum_threshold'],
        ];
    }
    return $rows;
}

/** @return list<array{name: string, color: string, colors: list<string>}> */
function suggest_vastra_names(): array
{
    $grouped = [];
    foreach (db_all('SELECT item_name, color FROM vastra_items ORDER BY item_name, id') as $row) {
        $name = trim((string) $row['item_name']);
        if ($name === '') {
            continue;
        }
        if (!isset($grouped[$name])) {
            $grouped[$name] = [];
        }
        $color = trim((string) ($row['color'] ?? ''));
        if ($color !== '') {
            $grouped[$name][$color] = $color;
        }
    }
    $rows = [];
    foreach ($grouped as $name => $colors) {
        $list = array_values($colors);
        $rows[] = [
            'name' => $name,
            'color' => count($list) === 1 ? $list[0] : '',
            'colors' => $list,
        ];
    }
    return $rows;
}

/** @return list<array{name: string, item: string}> */
function suggest_vastra_colors(): array
{
    $rows = [];
    $seen = [];
    foreach (db_all('SELECT item_name, color FROM vastra_items ORDER BY color, item_name') as $row) {
        $color = trim((string) ($row['color'] ?? ''));
        $item = trim((string) $row['item_name']);
        if ($color === '') {
            continue;
        }
        $key = $item . "\n" . $color;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $rows[] = ['name' => $color, 'item' => $item];
    }
    return $rows;
}

/** @return list<array{id: int, name: string, category: string, unit: string, unit_cost: string, condition: string, location: string, description: string}> */
function suggest_inventory_catalog(): array
{
    $rows = [];
    foreach (db_all('SELECT id, name, category, unit, unit_cost, item_condition, location, description FROM inventory_items ORDER BY category, name') as $row) {
        $rows[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'category' => (string) $row['category'],
            'unit' => (string) $row['unit'],
            'unit_cost' => (string) $row['unit_cost'],
            'condition' => (string) $row['item_condition'],
            'location' => (string) ($row['location'] ?? ''),
            'description' => mb_substr((string) ($row['description'] ?? ''), 0, 500),
        ];
    }
    return $rows;
}

/** @return list<array{name: string}> */
function suggest_location_catalog(): array
{
    $rows = [];
    foreach (db_all("SELECT DISTINCT location FROM inventory_items WHERE location IS NOT NULL AND location <> '' ORDER BY location") as $row) {
        $name = trim((string) $row['location']);
        if ($name !== '') {
            $rows[] = ['name' => $name];
        }
    }
    return $rows;
}
