<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;

/**
 * Staff logins for Booking admin — same {@see users} table as ERP when present,
 * otherwise legacy {@see admins} (booking-only SQLite installs before migrate).
 */
final class StaffAccounts
{
    public const ERP_ROLES = ['Admin', 'Treasurer', 'Staff'];

    public function __construct(private Database $db)
    {
    }

    public function usesErpUsers(): bool
    {
        if (!$this->tableExists('users')) {
            return false;
        }
        return $this->columnExists('users', 'full_name');
    }

    /** @return 'users'|'admins' */
    public function table(): string
    {
        return $this->usesErpUsers() ? 'users' : 'admins';
    }

    /** True when ERP donors (or similar) share this database — user CRUD stays in ERP. */
    public function sharedWithErp(): bool
    {
        return $this->usesErpUsers() && $this->tableExists('donors');
    }

    public static function bookingRoleFromErp(string $erpRole): string
    {
        return match ($erpRole) {
            'Admin', 'Treasurer' => 'super',
            default => 'staff',
        };
    }

    public static function erpRoleFromBookingSuper(bool $super): string
    {
        return $super ? 'Admin' : 'Staff';
    }

    /** Normalised row for Auth / Kernel (keys: id, username, name, role super|staff, is_active, erp_role?). */
    public function normalizeRow(array $row): array
    {
        if ($this->usesErpUsers()) {
            $erp = (string) ($row['role'] ?? 'Staff');
            return [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'name' => (string) ($row['full_name'] ?? $row['username']),
                'role' => self::bookingRoleFromErp($erp),
                'erp_role' => $erp,
                'is_active' => (int) ($row['is_active'] ?? 0),
            ];
        }
        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'name' => (string) ($row['name'] ?? $row['username']),
            'role' => (string) ($row['role'] ?? 'staff'),
            'is_active' => (int) ($row['is_active'] ?? 0),
        ];
    }

    public function findByUsername(string $username): ?array
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }
        if ($this->usesErpUsers()) {
            $row = $this->db->one('SELECT * FROM users WHERE username = ?', [$username])
                ?? $this->db->one('SELECT * FROM users WHERE LOWER(username) = LOWER(?)', [$username]);
        } else {
            $row = $this->db->one('SELECT * FROM admins WHERE username = ?', [strtolower($username)]);
        }
        return $row ? $this->normalizeRow($row) : null;
    }

    public function findById(int $id): ?array
    {
        $t = $this->table();
        $row = $this->db->one("SELECT * FROM {$t} WHERE id = ?", [$id]);
        return $row ? $this->normalizeRow($row) : null;
    }

    public function findAuthRowByUsername(string $username): ?array
    {
        $username = trim($username);
        if ($this->usesErpUsers()) {
            return $this->db->one('SELECT * FROM users WHERE username = ?', [$username])
                ?? $this->db->one('SELECT * FROM users WHERE LOWER(username) = LOWER(?)', [$username]);
        }
        return $this->db->one('SELECT * FROM admins WHERE username = ?', [strtolower($username)]);
    }

    public function findAuthRowById(int $id): ?array
    {
        $t = $this->table();
        return $this->db->one("SELECT * FROM {$t} WHERE id = ?", [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function listForAdminUi(): array
    {
        if ($this->usesErpUsers()) {
            $cols = 'id, username, full_name AS name, role AS erp_role, is_active';
            if ($this->columnExists('users', 'last_login_at')) {
                $cols .= ', last_login_at, locked_until';
            } else {
                $cols .= ', NULL AS last_login_at, NULL AS locked_until';
            }
            $rows = $this->db->all("SELECT {$cols} FROM users ORDER BY username");
            foreach ($rows as &$r) {
                $r['role'] = self::bookingRoleFromErp((string) $r['erp_role']);
            }
            unset($r);
            return $rows;
        }
        return $this->db->all('SELECT id, username, name, role, is_active, last_login_at, locked_until FROM admins ORDER BY username');
    }

    public function seniorAdminExists(): bool
    {
        if ($this->usesErpUsers()) {
            return (int) $this->db->value(
                "SELECT COUNT(*) FROM users WHERE role IN ('Admin','Treasurer') AND is_active = 1"
            ) > 0;
        }
        return (int) $this->db->value("SELECT COUNT(*) FROM admins WHERE role = 'super' AND is_active = 1") > 0;
    }

    public function recordFailedLogin(int $id, int $failed, ?string $lockedUntil): void
    {
        $t = $this->table();
        $row = ['failed_logins' => $failed, 'locked_until' => $lockedUntil];
        if ($this->columnExists($t, 'updated_at')) {
            $row['updated_at'] = now();
        }
        if (!$this->columnExists($t, 'failed_logins')) {
            return;
        }
        $this->db->update($t, $row, 'id = :id', ['id' => $id]);
    }

    public function recordSuccessfulLogin(int $id, ?string $passwordHash, string $password): void
    {
        $t = $this->table();
        $row = [];
        if ($passwordHash !== null && password_needs_rehash($passwordHash, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            $row['password_hash'] = Auth::hash($password);
        }
        if ($this->columnExists($t, 'failed_logins')) {
            $row['failed_logins'] = 0;
            $row['locked_until'] = null;
        }
        if ($this->columnExists($t, 'last_login_at')) {
            $row['last_login_at'] = now();
        }
        if ($this->columnExists($t, 'updated_at')) {
            $row['updated_at'] = now();
        }
        if ($row !== []) {
            $this->db->update($t, $row, 'id = :id', ['id' => $id]);
        }
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $t = $this->table();
        $row = ['password_hash' => $hash];
        if ($this->columnExists($t, 'updated_at')) {
            $row['updated_at'] = now();
        }
        $this->db->update($t, $row, 'id = :id', ['id' => $id]);
    }

    private function tableExists(string $name): bool
    {
        try {
            if ($this->db->driver === 'mysql') {
                return (bool) $this->db->value(
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                    [$name]
                );
            }
            return (bool) $this->db->value(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$name]
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            if ($this->db->driver === 'mysql') {
                return (bool) $this->db->value(
                    'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                    [$table, $column]
                );
            }
            $cols = $this->db->all("PRAGMA table_info({$table})");
            foreach ($cols as $c) {
                if (($c['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable) {
            return false;
        }
    }
}
