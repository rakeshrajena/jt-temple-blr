<?php
declare(strict_types=1);

use SJT\Core\Database;

function donation_ensure_schema(Database $db): void
{
    if ($db->driver === 'mysql') {
        $db->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS online_donation_intents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(30) NOT NULL,
    donor_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(120) NULL,
    pan_number VARCHAR(20) NULL,
    purpose VARCHAR(200) NULL,
    amount_paise INT NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'awaiting_payment',
    gateway_order_id VARCHAR(64) NULL,
    gateway_payment_id VARCHAR(64) NULL,
    donation_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    UNIQUE KEY uq_online_donation_ref (ref_no),
    KEY idx_odn_status (status),
    KEY idx_odn_gateway_order (gateway_order_id)
) ENGINE=InnoDB
SQL);
        return;
    }

    $db->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS online_donation_intents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ref_no TEXT NOT NULL UNIQUE,
    donor_name TEXT NOT NULL,
    phone TEXT NULL,
    email TEXT NULL,
    pan_number TEXT NULL,
    purpose TEXT NULL,
    amount_paise INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'awaiting_payment',
    gateway_order_id TEXT NULL,
    gateway_payment_id TEXT NULL,
    donation_id INTEGER NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    paid_at TEXT NULL
)
SQL);
}
