<?php
declare(strict_types=1);

namespace SJT\Core;

/**
 * Applies migrations/NNN_name.php in order, once each, recording them in
 * schema_migrations. Migrations are additive only — nothing here drops or
 * rewrites existing data.
 *
 * A migration file returns either an array of SQL strings (with dialect
 * tokens) or a callable(Database): void.
 */
final class Migrator
{
    public function __construct(private Database $db, private string $dir)
    {
    }

    public function tokens(): array
    {
        $my = $this->db->driver === 'mysql';
        return [
            '{PK}' => $my ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
            '{FK}' => $my ? 'INT UNSIGNED' : 'INTEGER',
            '{BOOL}' => $my ? 'TINYINT(1)' : 'INTEGER',
            '{DATETIME}' => $my ? 'DATETIME' : 'TEXT',
            '{DATE}' => $my ? 'DATE' : 'TEXT',
            '{TEXT}' => $my ? 'TEXT' : 'TEXT',
            '{LONGTEXT}' => $my ? 'MEDIUMTEXT' : 'TEXT',
            '{TABLE_OPTS}' => $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '',
        ];
    }

    public function pending(): array
    {
        $this->ensureTable();
        $done = array_column($this->db->all('SELECT version FROM schema_migrations'), 'version');
        $files = glob($this->dir . '/*.php') ?: [];
        sort($files);
        $out = [];
        foreach ($files as $f) {
            $v = basename($f, '.php');
            if (!in_array($v, $done, true)) {
                $out[$v] = $f;
            }
        }
        return $out;
    }

    /** @return string[] applied versions */
    public function migrate(?callable $log = null): array
    {
        $applied = [];
        foreach ($this->pending() as $version => $file) {
            $log && $log("Applying $version ...");
            $m = require $file;
            // MySQL DDL (CREATE/ALTER/RENAME) performs an implicit commit, so it
            // cannot run inside a transaction. Callable migrations therefore run
            // untransactional; one that needs atomic DML wraps its own
            // $db->transaction() internally. SQL statements use IF NOT EXISTS so
            // a re-run after a failure is safe.
            if (is_callable($m)) {
                $m($this->db);
            } else {
                foreach ($m as $sql) {
                    $this->db->pdo->exec(strtr($sql, $this->tokens()));
                }
            }
            $this->db->insert('schema_migrations', ['version' => $version, 'applied_at' => date('Y-m-d H:i:s')]);
            $applied[] = $version;
        }
        return $applied;
    }

    private function ensureTable(): void
    {
        $opts = $this->tokens()['{TABLE_OPTS}'];
        $this->db->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) NOT NULL PRIMARY KEY, applied_at VARCHAR(20) NOT NULL)$opts");
    }
}
