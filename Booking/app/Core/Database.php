<?php
declare(strict_types=1);

namespace SJT\Core;

use PDO;

/**
 * Thin PDO wrapper. All queries use prepared statements.
 * Production: MySQL/MariaDB (InnoDB). Tests/local: SQLite.
 */
final class Database
{
    public readonly PDO $pdo;
    public readonly string $driver;
    private int $depth = 0;

    public function __construct(array $cfg)
    {
        $this->driver = $cfg['driver'] ?? 'mysql';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($this->driver === 'sqlite') {
            $this->pdo = new PDO('sqlite:' . $cfg['path'], null, null, $opts);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA busy_timeout = 10000');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], (int) $cfg['port'], $cfg['name']);
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $opts);
            $this->pdo->exec("SET time_zone = '" . date('P') . "', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        }
    }

    public static function fromEnv(): self
    {
        $driver = Env::get('DB_DRIVER', 'mysql');
        $path = Env::get('DB_PATH', 'storage/database.sqlite');
        if ($driver === 'sqlite' && !str_starts_with($path, '/')) {
            $path = APP_ROOT . '/' . $path;
        }
        return new self([
            'driver' => $driver,
            'host' => Env::get('DB_HOST', 'localhost'),
            'port' => Env::get('DB_PORT', '3306'),
            'name' => Env::get('DB_NAME', ''),
            'user' => Env::get('DB_USER', ''),
            'pass' => Env::get('DB_PASS', ''),
            'path' => $path,
        ]);
    }

    public function run(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        foreach (array_values(array_is_list($params) ? $params : []) as $i => $v) {
            $st->bindValue($i + 1, $v, self::type($v));
        }
        if (!array_is_list($params)) {
            foreach ($params as $k => $v) {
                $st->bindValue(':' . ltrim((string) $k, ':'), $v, self::type($v));
            }
        }
        $st->execute();
        return $st;
    }

    private static function type(mixed $v): int
    {
        return match (true) {
            is_int($v) => PDO::PARAM_INT,
            is_bool($v) => PDO::PARAM_BOOL,
            $v === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    public function one(string $sql, array $params = []): ?array
    {
        $r = $this->run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $table, array $row): int
    {
        $cols = array_keys($row);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(fn ($c) => ':' . $c, $cols))
        );
        $this->run($sql, $row);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $row, string $where, array $whereParams): int
    {
        $set = [];
        $params = [];
        foreach ($row as $c => $v) {
            $set[] = "$c = :set_$c";
            $params["set_$c"] = $v;
        }
        foreach ($whereParams as $k => $v) {
            $params[$k] = $v;
        }
        return $this->run("UPDATE $table SET " . implode(', ', $set) . " WHERE $where", $params)->rowCount();
    }

    /**
     * Run $fn inside a transaction. On SQLite we take the write lock immediately
     * (BEGIN IMMEDIATE) so concurrent bookings are serialised; on MySQL the
     * callback uses SELECT ... FOR UPDATE for the rows it needs to lock.
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->depth > 0) {
            $this->depth++;
            try {
                return $fn($this);
            } finally {
                $this->depth--;
            }
        }
        // Deadlocks / serialization failures are normal under concurrent load on
        // MySQL: the whole transaction is safely retried (callbacks only touch the DB).
        for ($attempt = 1; ; $attempt++) {
            if ($this->driver === 'sqlite') {
                $this->pdo->exec('BEGIN IMMEDIATE');
            } else {
                $this->pdo->beginTransaction();
            }
            $this->depth = 1;
            try {
                $result = $fn($this);
                $this->driver === 'sqlite' ? $this->pdo->exec('COMMIT') : $this->pdo->commit();
                $this->depth = 0;
                return $result;
            } catch (\Throwable $e) {
                try {
                    if ($this->driver === 'sqlite') {
                        $this->pdo->exec('ROLLBACK');
                    } elseif ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                } catch (\Throwable) {
                }
                $this->depth = 0;
                if ($attempt < 5 && self::isRetryable($e)) {
                    usleep(random_int(20_000, 120_000) * $attempt);
                    continue;
                }
                throw $e;
            }
        }
    }

    public static function isRetryable(\Throwable $e): bool
    {
        if (!$e instanceof \PDOException) {
            return false;
        }
        $code = (string) $e->getCode();
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        return $code === '40001'                       // serialization failure
            || in_array($driverCode, [1213, 1205], true) // deadlock, lock wait timeout
            || str_contains($e->getMessage(), 'transaction rolled back')
            || str_contains($e->getMessage(), 'database is locked');
    }

    /** "FOR UPDATE" suffix on MySQL, nothing on SQLite (already write-locked). */
    public function forUpdate(): string
    {
        return $this->driver === 'mysql' ? ' FOR UPDATE' : '';
    }

    public function insertIgnore(): string
    {
        return $this->driver === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
    }

    /** Case-insensitive LIKE operator for both engines. */
    public function like(): string
    {
        return 'LIKE';
    }
}
