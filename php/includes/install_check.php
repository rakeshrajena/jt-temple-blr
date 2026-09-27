<?php
declare(strict_types=1);

/** Tables whose row count changes with every page load and is not part of the books. */
const INSTALL_CHECK_VOLATILE_TABLES = ['write_claims'];

/** @return array<string, array{columns: list<string>, rows: int}> */
function database_shape(PDO $pdo): array
{
    $shape = [];
    foreach (books_table_names($pdo) as $table) {
        $shape[$table] = [
            'columns' => books_column_names($pdo, $table),
            'rows' => (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn(),
        ];
    }
    ksort($shape);
    return $shape;
}

/**
 * Lists what a fresh install is missing compared with the live books.
 *
 * @param array<string, array{columns: list<string>, rows: int}> $expected
 * @param array<string, array{columns: list<string>, rows: int}> $actual
 * @return list<string>
 */
function compare_database_shapes(array $expected, array $actual): array
{
    $differences = [];
    foreach ($expected as $table => $want) {
        $got = $actual[$table] ?? null;
        if ($got === null) {
            $differences[] = "Table {$table} is missing after install.";
            continue;
        }
        foreach (array_diff($want['columns'], $got['columns']) as $column) {
            $differences[] = "Table {$table} is missing column {$column} after install.";
        }
        if (!in_array($table, INSTALL_CHECK_VOLATILE_TABLES, true) && $want['rows'] !== $got['rows']) {
            $differences[] = "Table {$table} has {$got['rows']} rows after install; the live books have {$want['rows']}.";
        }
    }
    return $differences;
}

/**
 * Counts what a books copy holds without loading it.
 *
 * @return array{rows: int, tables: int, saved_at: int}|null Null when the copy is missing or unreadable.
 */
function books_snapshot_summary(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }
    $rows = 0;
    $tables = [];
    try {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $payload = json_decode($line, true);
            if (!is_array($payload) || !is_string($payload['table'] ?? null) || !is_array($payload['rows'] ?? null)) {
                return null;
            }
            $tables[$payload['table']] = true;
            $rows += count($payload['rows']);
        }
    } finally {
        fclose($handle);
    }
    clearstatcache(true, $path);
    return ['rows' => $rows, 'tables' => count($tables), 'saved_at' => (int) filemtime($path)];
}

function install_check_database_name(string $live): ?string
{
    if (preg_match('/^[A-Za-z0-9_]{1,40}$/', $live) !== 1) {
        return null;
    }
    return $live . '_install_check';
}

/**
 * Installs schema.sql, the migrations, and a books copy into a scratch database,
 * compares it with the live books, and always removes the scratch database.
 *
 * @return list<string> Differences; empty when the install reproduces the live books.
 */
function verify_fresh_install(PDO $live, string $snapshotPath): array
{
    $scratch = install_check_database_name(DB_NAME);
    if ($scratch === null || $scratch === DB_NAME) {
        throw new RuntimeException('The live database name cannot be checked safely.');
    }
    if (!is_file($snapshotPath)) {
        throw new RuntimeException('The books copy to check was not found.');
    }
    $quoted = '`' . $scratch . '`';
    $live->exec('DROP DATABASE IF EXISTS ' . $quoted);
    $live->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . $scratch . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            pdo_options()
        );
        $pdo->exec("SET time_zone = '+05:30'");
        $result = install_into($pdo, false, $snapshotPath);
        if (!$result['imported']) {
            return ['The books copy was not loaded into the fresh database.'];
        }
        $differences = compare_database_shapes(database_shape($live), database_shape($pdo));
        $pdo = null;
        return $differences;
    } finally {
        $live->exec('DROP DATABASE IF EXISTS ' . $quoted);
    }
}
