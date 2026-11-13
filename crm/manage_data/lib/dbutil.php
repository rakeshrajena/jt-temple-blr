<?php
declare(strict_types=1);

/** PDO connection without running full CRM migrate_schema (manage_data loads minimal includes only). */
function manage_data_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = db_connect();
    if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() === false) {
        throw new RuntimeException('Database tables are not created yet.');
    }

    return $pdo;
}

/** @return list<array{id:int,full_name:string,username:string,role:string}> */
function manage_data_staff_users(): array
{
    if (!manage_data_table_exists(manage_data_pdo(), 'users')) {
        return [];
    }
    $rows = manage_data_pdo()->query(
        "SELECT id, full_name, username, role FROM users WHERE is_active = 1 ORDER BY full_name, username"
    )->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static function (array $r): array {
        return [
            'id' => (int) $r['id'],
            'full_name' => (string) $r['full_name'],
            'username' => (string) $r['username'],
            'role' => (string) $r['role'],
        ];
    }, $rows);
}

function manage_data_normalize_mobile(?string $value): string
{
    $digits = preg_replace('/\D+/', '', (string) $value);

    return is_string($digits) ? $digits : '';
}

function manage_data_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);

    return $stmt->fetchColumn() !== false;
}

function manage_data_valid_date(?string $value): ?string
{
    $value = trim((string) $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $value) {
        return null;
    }

    return $value;
}
