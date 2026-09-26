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

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $server = new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        pdo_options()
    );
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        pdo_options()
    );
    $pdo->exec("SET time_zone = '+05:30'");
    ensure_schema($pdo);
    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($exists === false) {
        run_sql_file($pdo, APP_ROOT . '/schema.sql');
    }
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        Seed::run($pdo);
    }
    ensure_books_schema($pdo);
    ensure_payment_columns($pdo);
    ensure_approval_schema($pdo);
    ensure_correction_schema($pdo);
    ensure_donor_schema($pdo);
    ensure_stock_schema($pdo);
    ensure_messaging_schema($pdo);
    ensure_receipt_share_schema($pdo);
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

function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Schema file is missing.');
    }
    $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
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
