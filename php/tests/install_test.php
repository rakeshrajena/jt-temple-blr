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

$parsed = env_parse("DB_NAME=temple_db\n# secret\nDB_PASSWORD=\"keep me\"\nMYSQL_USER = enk\n");
check(($parsed['DB_NAME'] ?? '') === 'temple_db', 'a database name is read from the configuration');
check(($parsed['DB_PASSWORD'] ?? '') === 'keep me', 'a quoted password is read without the quotes');
check(!isset($parsed['MYSQL_USER']) || $parsed['MYSQL_USER'] === 'enk', 'spaces around a configuration value are ignored');
check(install_token_matches('short') === false, 'a short setup key is refused');
check(install_token_matches('not-the-real-setup-key') === false, 'the wrong setup key is refused');
check(db_config_error() === null, 'the local database configuration is complete');

$config = (string) file_get_contents(dirname(__DIR__) . '/config.php');
check(!str_contains($config, "const DB_PASS"), 'the database password is not written in config.php');
check(!str_contains($config, "const DB_USER"), 'the database user is not written in config.php');

$htaccess = (string) file_get_contents(dirname(__DIR__) . '/.htaccess');
check(str_contains($htaccess, 'RewriteRule ^\.env'), 'the .env file is blocked from the web');
check(str_contains($htaccess, 'schema\\.sql'), 'the schema file is blocked from the web');

check(demo_data_is_pending(db()) === false, 'demo data stays put when devotees are already stored');

$before = (int) db_value('SELECT COUNT(*) FROM users');
$donorsBefore = (int) db_value('SELECT COUNT(*) FROM donors');
$installed = install_database();
$after = (int) db_value('SELECT COUNT(*) FROM users');
check(
    $installed['seeded'] === false
    && $installed['imported'] === false
    && $installed['tables'] > 0
    && $after === $before
    && $after > 0
    && (int) db_value('SELECT COUNT(*) FROM donors') === $donorsBefore,
    'running setup again does not replace people already in the database'
);

$snapshot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt_books_check.jsonl';
$copied = write_books_snapshot(db(), $snapshot);
$first = '';
$handle = fopen($snapshot, 'rb');
if ($handle !== false) {
    $first = (string) fgets($handle);
    fclose($handle);
}
$payload = json_decode($first, true);
check(
    $copied > 0
    && is_array($payload)
    && preg_match('/^[A-Za-z0-9_]+$/', (string) ($payload['table'] ?? '')) === 1
    && str_starts_with(str_replace('\\', '/', books_snapshot_path()), str_replace('\\', '/', APP_ROOT) . '/storage/'),
    'the books can be written as one table per line inside storage'
);
if (is_file($snapshot)) {
    unlink($snapshot);
}

$bad = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt_books_bad.jsonl';
file_put_contents($bad, "{\"table\":\"not_a_table\",\"rows\":[]}\n");
$pdo = db();
$pdo->beginTransaction();
$refused = false;
try {
    import_books_snapshot($pdo, $bad);
} catch (RuntimeException) {
    $refused = true;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_file($bad)) {
        unlink($bad);
    }
}
check($refused && (int) db_value('SELECT COUNT(*) FROM donors') === $donorsBefore, 'a books copy that names an unknown table is refused and the live books stay');

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all install tests passed\n";
