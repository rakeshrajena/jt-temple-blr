<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

function check(bool $ok, string $label): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL  $label\n");
        exit(1);
    }
    fwrite(STDOUT, "ok  $label\n");
}

$pdo = db();
ensure_vastra_schema($pdo);

$adminId = (int) db_value("SELECT id FROM users WHERE role = 'Admin' ORDER BY id LIMIT 1");
if ($adminId < 1) {
    fwrite(STDERR, "skip (no admin user)\n");
    exit(0);
}

$storeId = (int) db_exec(
    'INSERT INTO vastra_items (deity_name, item_name, color, quantity, source, date_added, status) VALUES (?,?,?,?,?,?,?)',
    ['Test Deity', 'Probe Pata', 'Yellow', 3, 'Purchased', '2026-01-01', 'In Store']
);

$bad = record_vastra_movement($storeId, 'Return', 1, '', '2026-01-02', $adminId);
check($bad['error'] !== null, 'return is rejected when vastra is in store');

$issue = record_vastra_movement($storeId, 'Issue', 2, 'festival', '2026-01-03', $adminId);
check($issue['error'] === null, 'issue two pieces from store');

$row = db_one('SELECT quantity, status FROM vastra_items WHERE id = ?', [$storeId]);
check((int) $row['quantity'] === 1 && vastra_normalize_status((string) $row['status']) === 'In Store', 'store line keeps remaining qty and status');

$inUse = db_one("SELECT id, quantity, status FROM vastra_items WHERE deity_name = 'Test Deity' AND item_name = 'Probe Pata' AND status = 'In Use' ORDER BY id DESC LIMIT 1");
check($inUse !== null && (int) $inUse['quantity'] === 2, 'issued pieces appear as in use with correct qty');

$ret = record_vastra_movement((int) $inUse['id'], 'Return', 2, '', '2026-01-04', $adminId);
check($ret['error'] === null, 'full return merges back to store');

$storeRow = db_one('SELECT quantity, status FROM vastra_items WHERE id = ?', [$storeId]);
check((int) $storeRow['quantity'] === 3 && vastra_normalize_status((string) $storeRow['status']) === 'In Store', 'store qty is restored after return');

$ghost = db_one('SELECT quantity FROM vastra_items WHERE id = ?', [(int) $inUse['id']]);
check($ghost !== null && (int) $ghost['quantity'] === 0, 'in-use line is zeroed after full return');

$retire = record_vastra_movement($storeId, 'Retire', 1, '', '2026-01-05', $adminId);
check($retire['error'] === null, 'retire one from store');
$retired = db_one("SELECT status, quantity FROM vastra_items WHERE deity_name = 'Test Deity' AND status = 'Retired' ORDER BY id DESC LIMIT 1");
check($retired !== null && (int) $retired['quantity'] === 1, 'retired line has correct qty');

$ids = db_all("SELECT id FROM vastra_items WHERE deity_name = 'Test Deity' AND item_name = 'Probe Pata'");
$idList = array_map(static fn (array $r): int => (int) $r['id'], $ids);
if ($idList !== []) {
    $placeholders = implode(',', array_fill(0, count($idList), '?'));
    $pdo->prepare("DELETE FROM vastra_movements WHERE vastra_id IN ($placeholders) OR related_vastra_id IN ($placeholders)")
        ->execute([...$idList, ...$idList]);
    $pdo->exec("DELETE FROM vastra_items WHERE deity_name = 'Test Deity' AND item_name = 'Probe Pata'");
}

fwrite(STDOUT, "all vastra tests passed\n");