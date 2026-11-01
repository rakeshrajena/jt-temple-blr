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

$live = [
    'donors' => ['columns' => ['id', 'name', 'phone'], 'rows' => 12],
    'users' => ['columns' => ['id', 'username'], 'rows' => 3],
    'write_claims' => ['columns' => ['token', 'created_at'], 'rows' => 40],
];
check(compare_database_shapes($live, $live) === [], 'identical databases have no differences');

$fewerClaims = $live;
$fewerClaims['write_claims']['rows'] = 0;
check(compare_database_shapes($live, $fewerClaims) === [], 'one-time save tokens are not compared by row count');

$missingTable = $live;
unset($missingTable['users']);
check(compare_database_shapes($live, $missingTable) === ['Table users is missing after install.'], 'a missing table is reported');

$missingColumn = $live;
$missingColumn['donors']['columns'] = ['id', 'name'];
check(compare_database_shapes($live, $missingColumn) === ['Table donors is missing column phone after install.'], 'a missing column is reported');

$fewerRows = $live;
$fewerRows['donors']['rows'] = 10;
check(compare_database_shapes($live, $fewerRows) === ['Table donors has 10 rows after install; the live books have 12.'], 'a row count difference is reported');

$extraTable = $live;
$extraTable['old_notes'] = ['columns' => ['id'], 'rows' => 0];
check(compare_database_shapes($live, $extraTable) === [], 'an extra empty table after install is not a problem');

check(install_check_database_name('sjt_temple_blr') === 'sjt_temple_blr_install_check', 'the scratch database is named after the live one');
check(install_check_database_name('bad-name') === null, 'an unsafe database name gets no scratch database');

$ready = [
    'php' => '8.2.27',
    'extensions' => ['pdo_mysql', 'mbstring', 'zlib', 'json', 'openssl', 'simplexml', 'dom', 'gd'],
    'writable' => ['storage/receipts' => true, 'storage/logs' => true],
];
check(install_requirement_problems($ready) === [], 'a server with PHP 8.2, the needed extensions, and writable storage is ready');
check(install_requirement_problems(['php' => '8.0.30'] + $ready) === ['PHP 8.1 or newer is needed; this server runs 8.0.30. Choose a newer PHP version in the hosting control panel.'], 'an old PHP version is reported with what to do');
$missing = $ready;
$missing['extensions'] = ['mbstring', 'json', 'openssl', 'simplexml', 'dom', 'zlib'];
check(install_requirement_problems($missing) === ['The PHP extension pdo_mysql is missing. Turn it on in the hosting control panel under PHP extensions.'], 'a missing required extension is reported');
$noGd = $ready;
$noGd['extensions'] = array_values(array_diff($ready['extensions'], ['gd']));
check(install_requirement_problems($noGd) === [], 'the optional image extension is not required');
$locked = $ready;
$locked['writable']['storage/receipts'] = false;
check(install_requirement_problems($locked) === ['The folder storage/receipts cannot be written. Set its permission to 755 in the hosting file manager.'], 'a folder the server cannot write is reported');
$facts = install_server_facts();
check(isset($facts['writable']['storage/receipts'], $facts['writable']['storage/install']) && in_array('pdo_mysql', $facts['extensions'], true), 'the real server facts list the storage folders and extensions');
check(install_requirement_problems($facts) === [], 'this computer meets the server requirements');

$sample = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt_install_summary.jsonl';
file_put_contents($sample, implode("\n", [
    json_encode(['table' => 'donors', 'rows' => [['id' => 1], ['id' => 2]]]),
    json_encode(['table' => 'donors', 'rows' => [['id' => 3]]]),
    json_encode(['table' => 'users', 'rows' => [['id' => 1]]]),
    json_encode(['table' => 'donations', 'rows' => [['id' => 1, 'receipt_generated' => 1], ['id' => 2, 'receipt_generated' => 0], ['id' => 3, 'receipt_generated' => '1']]]),
    '',
]));
touch($sample, 1790000000);
$summary = books_snapshot_summary($sample);
$expectedSummary = ['rows' => 7, 'tables' => 3, 'receipts' => 2, 'saved_at' => 1790000000];
check($summary === $expectedSummary, 'the books copy summary counts rows, tables, and generated receipts' . ($summary === $expectedSummary ? '' : ': ' . json_encode($summary)));
file_put_contents($sample, "not json\n");
check(books_snapshot_summary($sample) === null, 'an unreadable books copy has no summary');
unlink($sample);
check(books_snapshot_summary($sample) === null, 'a missing books copy has no summary');

$snapshot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt_install_check.jsonl';
try {
    write_books_snapshot(db(), $snapshot);
    $differences = verify_fresh_install(db(), $snapshot);
    check($differences === [], 'a fresh install from schema.sql and the books copy matches the live books' . ($differences === [] ? '' : ': ' . implode(' ', array_slice($differences, 0, 3))));
    $scratch = (string) install_check_database_name(DB_NAME);
    $left = db()->query('SHOW DATABASES LIKE ' . db()->quote($scratch))->fetchColumn();
    check($left === false, 'the scratch database is removed after the check');
    check((int) db_value('SELECT COUNT(*) FROM donors') > 0, 'the live books are untouched by the check');
} finally {
    if (is_file($snapshot)) {
        unlink($snapshot);
    }
}

echo $failed === 0 ? "passed\n" : "failed {$failed}\n";
exit($failed === 0 ? 0 : 1);
