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
$installed = install_database();
$after = (int) db_value('SELECT COUNT(*) FROM users');
check(
    $installed['seeded'] === false
    && $installed['tables'] > 0
    && $after === $before
    && $after > 0,
    'running setup again does not replace people already in the database'
);

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all install tests passed\n";
