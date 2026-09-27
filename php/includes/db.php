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

/**
 * Creates any missing tables and, when the sample devotees are not there yet, loads every demo table.
 * A database that already has devotees is left in place. One existing login does not block the demo data.
 *
 * @return array{tables:int,users:int,seeded:bool}
 */
function install_database(): array
{
    $pdo = db_connect();
    $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($exists === false) {
        run_sql_file($pdo, APP_ROOT . '/schema.sql', true);
    }
    migrate_schema($pdo);
    $seeded = false;
    if (demo_data_is_pending($pdo)) {
        Seed::run($pdo);
        $seeded = true;
    }
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    return [
        'tables' => count($tables),
        'users' => $users,
        'seeded' => $seeded,
    ];
}

function migrate_schema(PDO $pdo): void
{
    ensure_books_schema($pdo);
    ensure_payment_columns($pdo);
    ensure_approval_schema($pdo);
    ensure_correction_schema($pdo);
    ensure_donor_schema($pdo);
    ensure_stock_schema($pdo);
    ensure_messaging_schema($pdo);
    ensure_receipt_share_schema($pdo);
    ensure_coupon_schema($pdo);
    ensure_selection_columns($pdo);
    backfill_receipt_pdfs();
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
