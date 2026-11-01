<?php
declare(strict_types=1);

/** Whether the online booking app has been installed in this database. */
function booking_module_installed(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $pdo = db();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $cached = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'bookings'")->fetchColumn();
            return $cached;
        }
        $cached = (bool) db_value(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'bookings'"
        );
        return $cached;
    } catch (Throwable) {
        $cached = false;
        return false;
    }
}

/** Public URL path to Booking admin (no trailing slash). Empty if not configured. */
function booking_admin_url(): string
{
    $explicit = rtrim(env_value('BOOKING_ADMIN_URL', ''), '/');
    if ($explicit !== '') {
        return $explicit;
    }
    $app = rtrim(env_value('BOOKING_APP_URL', ''), '/');
    if ($app !== '') {
        return $app . '/admin';
    }
    $dir = app_dir_url();
    if ($dir === '' || $dir === '/') {
        return '/Booking/admin';
    }
    if (str_ends_with($dir, '/php')) {
        return substr($dir, 0, -4) . '/Booking/admin';
    }
    return rtrim(dirname($dir), '/') . '/Booking/admin';
}
