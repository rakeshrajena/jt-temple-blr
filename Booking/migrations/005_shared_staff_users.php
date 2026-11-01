<?php
declare(strict_types=1);

/**
 * Unify Booking admin logins with ERP `users` (same database, same passwords).
 * Copies legacy `admins` rows into `users` when needed; adds optional lockout columns on `users`.
 */

use SJT\Core\Database;

return function (Database $db): void {
    $my = $db->driver === 'mysql';
    $bool = $my ? 'TINYINT(1)' : 'INTEGER';
    $dt = $my ? 'DATETIME' : 'TEXT';

    $usersExists = $my
        ? (bool) $db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'")
        : (bool) $db->value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'");

    if (!$usersExists) {
        if ($my) {
            $db->pdo->exec(
                "CREATE TABLE users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    full_name VARCHAR(100) NOT NULL,
                    role ENUM('Admin','Treasurer','Staff') NOT NULL DEFAULT 'Staff',
                    is_active {$bool} NOT NULL DEFAULT 1,
                    failed_logins INT NOT NULL DEFAULT 0,
                    locked_until {$dt} NULL,
                    last_login_at {$dt} NULL,
                    created_at {$dt} NULL,
                    updated_at {$dt} NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } else {
            $db->pdo->exec(
                "CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    full_name TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'Staff',
                    is_active INTEGER NOT NULL DEFAULT 1,
                    failed_logins INTEGER NOT NULL DEFAULT 0,
                    locked_until TEXT NULL,
                    last_login_at TEXT NULL,
                    created_at TEXT NULL,
                    updated_at TEXT NULL
                )"
            );
        }
        $usersExists = true;
    }

    foreach ([
        'failed_logins' => 'INT NOT NULL DEFAULT 0',
        'locked_until' => "{$dt} NULL",
        'last_login_at' => "{$dt} NULL",
        'updated_at' => "{$dt} NULL",
    ] as $col => $def) {
        $has = $my
            ? (bool) $db->value(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                ['users', $col]
            )
            : (bool) array_filter(
                $db->all('PRAGMA table_info(users)'),
                fn ($c) => ($c['name'] ?? '') === $col
            );
        if (!$has) {
            $db->pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
        }
    }

    $adminsExists = $my
        ? (bool) $db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'admins'")
        : (bool) $db->value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'admins'");

    if (!$adminsExists) {
        return;
    }

    $admins = $db->all('SELECT * FROM admins');
    foreach ($admins as $a) {
        $username = (string) $a['username'];
        if ($db->value('SELECT 1 FROM users WHERE username = ?', [$username])) {
            continue;
        }
        $erpRole = ($a['role'] ?? '') === 'super' ? 'Admin' : 'Staff';
        $ts = $a['created_at'] ?? date('Y-m-d H:i:s');
        $db->insert('users', [
            'username' => $username,
            'password_hash' => $a['password_hash'],
            'full_name' => $a['name'] ?: $username,
            'role' => $erpRole,
            'is_active' => (int) ($a['is_active'] ?? 1),
            'failed_logins' => (int) ($a['failed_logins'] ?? 0),
            'locked_until' => $a['locked_until'] ?? null,
            'last_login_at' => $a['last_login_at'] ?? null,
            'created_at' => $ts,
            'updated_at' => $a['updated_at'] ?? $ts,
        ]);
    }
};
