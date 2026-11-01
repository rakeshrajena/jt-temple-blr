<?php
declare(strict_types=1);

/** Columns linking bookings ↔ ERP donations (shared MySQL). Safe to call repeatedly. */
function booking_erp_ensure_link_schema(): void
{
    if (!booking_erp_bookings_ready()) {
        return;
    }
    try {
        $pdo = db();
    } catch (Throwable) {
        return;
    }
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        return;
    }
    if (!function_exists('ensure_column')) {
        require_once APP_ROOT . '/includes/vouchers.php';
    }
    ensure_column($pdo, 'bookings', 'erp_donation_id', 'INT NULL');
    ensure_column($pdo, 'bookings', 'erp_receipt_cancel_id', 'INT NULL');
    ensure_column($pdo, 'bookings', 'cancellation_status', 'VARCHAR(30) NULL');
    $donations = $pdo->query("SHOW TABLES LIKE 'donations'")->fetchColumn();
    if ($donations === false) {
        return;
    }
    ensure_column($pdo, 'donations', 'online_booking_id', 'INT NULL');
    ensure_column($pdo, 'donations', 'online_booking_no', 'VARCHAR(30) NULL');
    $idx = $pdo->query(
        "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'donations' AND index_name = 'uq_donations_online_booking_no' LIMIT 1"
    )->fetchColumn();
    if ($idx === false) {
        $pdo->exec('CREATE UNIQUE INDEX uq_donations_online_booking_no ON donations (online_booking_no)');
    }
}

booking_erp_ensure_link_schema();

/** After ERP approves a receipt cancellation tied to an online booking, mark the booking cancelled. */
function booking_erp_after_receipt_cancelled(int $donationId): void
{
    if (!booking_erp_bookings_ready()) {
        return;
    }
    $row = db_one(
        'SELECT id, booking_no FROM bookings WHERE erp_donation_id = ? AND booking_status <> ? LIMIT 1',
        [$donationId, 'cancelled']
    );
    if ($row === null) {
        return;
    }
    db_exec(
        'UPDATE bookings SET booking_status = ?, cancellation_status = NULL, updated_at = ? WHERE id = ?',
        ['cancelled', date('Y-m-d H:i:s'), (int) $row['id']]
    );
}

/** Receipt cancellation was rejected or sent back — booking stays confirmed. */
function booking_erp_after_receipt_cancel_denied(int $donationId): void
{
    if (!booking_erp_bookings_ready()) {
        return;
    }
    db_exec(
        'UPDATE bookings SET cancellation_status = NULL, erp_receipt_cancel_id = NULL, updated_at = ? WHERE erp_donation_id = ?',
        [date('Y-m-d H:i:s'), $donationId]
    );
}

function booking_erp_bookings_ready(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $ok = db_one("SHOW TABLES LIKE 'bookings'") !== null;
    } catch (Throwable) {
        $ok = false;
    }
    return $ok;
}
