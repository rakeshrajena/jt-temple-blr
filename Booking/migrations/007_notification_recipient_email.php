<?php
declare(strict_types=1);

/** Allow email addresses in notifications.recipient (was VARCHAR(30) for mobile only). */

use SJT\Core\Database;

return function (Database $db): void {
    if ($db->driver === 'mysql') {
        $db->pdo->exec('ALTER TABLE notifications MODIFY recipient VARCHAR(255) NOT NULL');
        return;
    }
    // SQLite: recreate column via new table if needed — tests use fresh schema from 001; alter for existing DBs.
    $cols = $db->all('PRAGMA table_info(notifications)');
    $recipient = null;
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'recipient') {
            $recipient = $c;
            break;
        }
    }
    if ($recipient && (int) ($recipient['length'] ?? 0) >= 255) {
        return;
    }
    $db->pdo->exec('ALTER TABLE notifications RENAME TO notifications_old');
    $db->pdo->exec(
        "CREATE TABLE notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            booking_id INTEGER NULL,
            channel VARCHAR(20) NOT NULL,
            audience VARCHAR(20) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            event VARCHAR(50) NOT NULL,
            template VARCHAR(100) NULL,
            params_json TEXT NULL,
            status VARCHAR(20) NOT NULL,
            provider_message_id VARCHAR(150) NULL,
            error TEXT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            next_attempt_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            sent_at TEXT NULL,
            FOREIGN KEY (booking_id) REFERENCES bookings(id)
        )"
    );
    $db->pdo->exec(
        'INSERT INTO notifications SELECT id, booking_id, channel, audience, recipient, event, template, params_json,
         status, provider_message_id, error, attempts, next_attempt_at, created_at, updated_at, sent_at FROM notifications_old'
    );
    $db->pdo->exec('DROP TABLE notifications_old');
};
