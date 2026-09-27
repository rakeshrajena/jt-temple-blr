<?php
declare(strict_types=1);

function pdo_options(): array
{
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
}

function db_config_error(): ?string
{
    if (DB_NAME === '' || preg_match('/^[A-Za-z0-9_]+$/', DB_NAME) !== 1) {
        return 'Set DB_NAME in the .env file to the database that already exists.';
    }
    if (DB_USER === '' || preg_match('/^[A-Za-z0-9_]+$/', DB_USER) !== 1) {
        return 'Set DB_USER or MYSQL_USER in the .env file.';
    }
    if (preg_match('/^[A-Za-z0-9._-]+$/', DB_HOST) !== 1) {
        return 'Set DB_HOST in the .env file.';
    }
    if (preg_match('/^[0-9]{1,5}$/', DB_PORT) !== 1 || (int) DB_PORT < 1 || (int) DB_PORT > 65535) {
        return 'Set DB_PORT in the .env file to a port from 1 to 65535.';
    }
    return null;
}

function db_connect(): PDO
{
    $error = db_config_error();
    if ($error !== null) {
        throw new RuntimeException($error);
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        pdo_options()
    );
    $pdo->exec("SET time_zone = '+05:30'");
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = db_connect();
    $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($exists === false) {
        throw new RuntimeException('The tables are not created yet. Open install.php.');
    }
    migrate_schema($pdo);
    return $pdo;
}

function demo_data_is_pending(PDO $pdo): bool
{
    return (int) $pdo->query('SELECT COUNT(*) FROM donors')->fetchColumn() === 0;
}

function books_snapshot_path(): string
{
    return APP_ROOT . '/storage/install/books.jsonl';
}

/** @return list<string> */
function books_table_names(PDO $pdo): array
{
    $names = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $name) {
        if (is_string($name) && preg_match('/^[A-Za-z0-9_]+$/', $name) === 1) {
            $names[] = $name;
        }
    }
    return $names;
}

/** @return list<string> */
function books_column_names(PDO $pdo, string $table): array
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
        return [];
    }
    $names = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $name = (string) ($column['Field'] ?? '');
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) === 1) {
            $names[] = $name;
        }
    }
    return $names;
}

function write_books_snapshot(PDO $pdo, ?string $path = null): int
{
    $path ??= books_snapshot_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not store the books copy.');
    }
    $tmp = $path . '.tmp';
    $handle = fopen($tmp, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Could not store the books copy.');
    }
    $count = 0;
    try {
        foreach (books_table_names($pdo) as $table) {
            $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
            foreach (array_chunk($rows, 40) as $chunk) {
                $line = json_encode(['table' => $table, 'rows' => $chunk], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
                if (fwrite($handle, $line . "\n") === false) {
                    throw new RuntimeException('Could not store the books copy.');
                }
                $count += count($chunk);
            }
        }
    } catch (Throwable $e) {
        fclose($handle);
        if (is_file($tmp)) {
            unlink($tmp);
        }
        throw $e;
    }
    fclose($handle);
    if (!rename($tmp, $path)) {
        if (is_file($tmp)) {
            unlink($tmp);
        }
        throw new RuntimeException('Could not store the books copy.');
    }
    return $count;
}

function import_books_snapshot(PDO $pdo, ?string $path = null): int
{
    $path ??= books_snapshot_path();
    if (!is_file($path)) {
        throw new RuntimeException('The saved books were not found.');
    }
    $allowed = books_table_names($pdo);
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The saved books could not be read.');
    }
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $loaded = 0;
    try {
        foreach ($allowed as $table) {
            $pdo->exec('DELETE FROM `' . $table . '`');
        }
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $payload = json_decode($line, true);
            if (!is_array($payload)) {
                throw new RuntimeException('The saved books could not be read.');
            }
            $table = (string) ($payload['table'] ?? '');
            $rows = $payload['rows'] ?? null;
            if (!in_array($table, $allowed, true) || !is_array($rows)) {
                throw new RuntimeException('The saved books could not be read.');
            }
            $columns = books_column_names($pdo, $table);
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The saved books could not be read.');
                }
                $fields = [];
                $values = [];
                foreach ($row as $field => $value) {
                    if (!is_string($field) || !in_array($field, $columns, true)) {
                        continue;
                    }
                    $fields[] = '`' . $field . '`';
                    $values[] = $value;
                }
                if ($fields === []) {
                    continue;
                }
                $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($values));
                $loaded++;
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        fclose($handle);
    }
    return $loaded;
}

/**
 * Creates any missing tables and brings an older database up to the latest columns.
 * When devotees are not there yet, loads the saved books from this copy, or the demo data if that copy is missing.
 * A database that already has devotees is left in place unless $replaceBooks is set.
 *
 * @return array{tables:int,users:int,donors:int,seeded:bool,imported:bool}
 */
function install_database(bool $replaceBooks = false): array
{
    return install_into(db_connect(), $replaceBooks);
}

/**
 * The same setup as install_database, against any connection and books copy.
 *
 * @return array{tables:int,users:int,donors:int,seeded:bool,imported:bool}
 */
function install_into(PDO $pdo, bool $replaceBooks = false, ?string $snapshotPath = null): array
{
    $snapshotPath ??= books_snapshot_path();
    $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($exists === false) {
        run_sql_file($pdo, APP_ROOT . '/schema.sql', true);
    }
    migrate_schema($pdo);
    $seeded = false;
    $imported = false;
    $snapshot = is_file($snapshotPath);
    if ($snapshot && ($replaceBooks || demo_data_is_pending($pdo))) {
        import_books_snapshot($pdo, $snapshotPath);
        backfill_receipt_pdfs($pdo);
        $imported = true;
    } elseif (demo_data_is_pending($pdo)) {
        Seed::run($pdo);
        $seeded = true;
    }
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $donors = (int) $pdo->query('SELECT COUNT(*) FROM donors')->fetchColumn();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    return [
        'tables' => count($tables),
        'users' => $users,
        'donors' => $donors,
        'seeded' => $seeded,
        'imported' => $imported,
    ];
}

function migrate_schema(PDO $pdo): void
{
    ensure_books_schema($pdo);
    ensure_payment_columns($pdo);
    ensure_approval_schema($pdo);
    ensure_correction_schema($pdo);
    ensure_donation_edit_schema($pdo);
    ensure_donor_schema($pdo);
    ensure_stock_schema($pdo);
    ensure_messaging_schema($pdo);
    ensure_receipt_share_schema($pdo);
    ensure_coupon_schema($pdo);
    ensure_invitation_schema($pdo);
    ensure_subscriber_schema($pdo);
    ensure_selection_columns($pdo);
    ensure_write_claims($pdo);
    backfill_receipt_pdfs($pdo);
}

function ensure_write_claims(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS write_claims (
            token       CHAR(32) NOT NULL PRIMARY KEY,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_write_claims_created (created_at)
        ) ENGINE=InnoDB"
    );
    $pdo->exec("DELETE FROM write_claims WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
}

function db_mutex(PDO $pdo, string $name): void
{
    $safe = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
    if ($safe === '') {
        throw new RuntimeException('Could not lock the books.');
    }
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $stmt->execute(['jt_' . $safe]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Another save is still finishing. Try again.');
    }
}

function ensure_books_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS opening_balances (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            financial_year  CHAR(9) NOT NULL,
            cash_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
            bank_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
            note            VARCHAR(255) NULL,
            set_by          INT NULL,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_opening_year (financial_year),
            FOREIGN KEY (set_by) REFERENCES users(id)
        ) ENGINE=InnoDB"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS contra_entries (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            entry_date      DATE NOT NULL,
            direction       ENUM('Deposit','Withdraw') NOT NULL,
            amount          DECIMAL(12,2) NOT NULL,
            note            VARCHAR(255) NULL,
            entered_by      INT NULL,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (entered_by) REFERENCES users(id),
            INDEX idx_contra_date (entry_date)
        ) ENGINE=InnoDB"
    );
}

function run_sql_file(PDO $pdo, string $path, bool $ignoreExisting = false): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Schema file is missing.');
    }
    $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if ($ignoreExisting && in_array($code, [1050, 1060, 1061], true)) {
                continue;
            }
            throw $e;
        }
    }
}

/** @param array<int|string, mixed> $params */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** @param array<int|string, mixed> $params */
function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** @param array<int|string, mixed> $params */
function db_value(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/** @param array<int|string, mixed> $params */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) db()->lastInsertId();
}
