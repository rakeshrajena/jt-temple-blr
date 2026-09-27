<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = 0;

function check(bool $ok, string $name): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n";
}

/** @param list<array<string, mixed>> $rows */
function has_name(array $rows, string $name): bool
{
    foreach ($rows as $row) {
        if (($row['name'] ?? '') === $name) {
            return true;
        }
    }
    return false;
}

check(suggest_donors('') === [], 'an empty search returns no devotees');
check(suggest_donors('%') === [] || !has_name(suggest_donors('%'), 'Suggest Test Donor'), 'a percent sign is not a match-all search');

$pdo = db();
$pdo->beginTransaction();
try {
    db_exec(
        'INSERT INTO donors (name, phone, email, address, pan_number) VALUES (?,?,?,?,?)',
        ['Suggest Test Donor', '5550199001', 'suggest-test@example.com', 'Test Lane', 'ABCDE9999Z']
    );
    $byName = suggest_donors('Suggest Test');
    $byPhone = suggest_donors('5550199');
    $byEmail = suggest_donors('suggest-test@');
    check(has_name($byName, 'Suggest Test Donor'), 'a devotee is found by name');
    check(has_name($byPhone, 'Suggest Test Donor') && ($byPhone[0]['pan_number'] ?? '') === 'ABCDE9999Z', 'a devotee is found by phone and brings PAN');
    check(has_name($byEmail, 'Suggest Test Donor') && ($byEmail[0]['address'] ?? '') === 'Test Lane', 'a devotee is found by email and brings the address');

    db_exec(
        'INSERT INTO food_items (name, unit, current_stock, minimum_threshold) VALUES (?,?,?,?)',
        ['Suggest Rice Unique', 'kg', 12, 4]
    );
    check(has_name(suggest_food_catalog(), 'Suggest Rice Unique'), 'a food item is offered by name');

    db_exec(
        'INSERT INTO inventory_items (category, name, quantity, unit, unit_cost, item_condition, location, source, added_date)
         VALUES (?,?,?,?,?,?,?,?,?)',
        ['Hardware', 'Suggest Ladder Unique', 2, 'pcs', 10, 'Good', 'Suggest Loft', 'Purchased', '2026-09-01']
    );
    $inventory = suggest_inventory_catalog();
    $ladder = null;
    foreach ($inventory as $row) {
        if ($row['name'] === 'Suggest Ladder Unique') {
            $ladder = $row;
        }
    }
    check($ladder !== null && $ladder['category'] === 'Hardware' && $ladder['location'] === 'Suggest Loft', 'an inventory name keeps its category and location');
    check(has_name(suggest_location_catalog(), 'Suggest Loft'), 'a location already used is offered again');

    db_exec(
        'INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, date_added, status)
         VALUES (?,?,?,?,?,?,?)',
        ['Jagannath', 'Suggest Pata Unique', 'Saffron', 1, 'Donated', '2026-09-01', 'In Store']
    );
    $vastra = null;
    foreach (suggest_vastra_names() as $row) {
        if ($row['name'] === 'Suggest Pata Unique') {
            $vastra = $row;
        }
    }
    check($vastra !== null && $vastra['color'] === 'Saffron', 'a vastra name brings its color when there is only one');
    check(has_name(suggest_vastra_colors(), 'Saffron'), 'a vastra color is offered');
} finally {
    $pdo->rollBack();
}

exit($failed === 0 ? 0 : 1);
