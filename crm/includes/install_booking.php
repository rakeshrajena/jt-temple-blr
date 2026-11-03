<?php
declare(strict_types=1);

/** Sibling Booking/ folder (online booking module) next to crm/. */
function install_booking_root_from_erp(): ?string
{
    $phpRoot = APP_ROOT;
    $parent = dirname($phpRoot);
    foreach (['Booking', 'booking'] as $name) {
        foreach ([$parent . DIRECTORY_SEPARATOR . $name, $phpRoot . DIRECTORY_SEPARATOR . $name] as $path) {
            if (is_dir($path) && is_file($path . '/migrations/001_initial_schema.php')) {
                return $path;
            }
        }
    }
    return null;
}

/** Apply Booking/migrations on the same MySQL connection as the ERP (fresh install or verify). */
function install_booking_migrations(PDO $pdo): void
{
    $root = install_booking_root_from_erp();
    if ($root === null) {
        return;
    }
    static $autoloaded = false;
    if (!$autoloaded) {
        require_once $root . '/app/autoload.php';
        $autoloaded = true;
    }
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = 'mysql';
    }
    $db = \SJT\Core\Database::usingPdo($pdo, $driver === 'mysql' ? 'mysql' : 'sqlite');
    (new \SJT\Core\Migrator($db, $root . '/migrations'))->migrate();
}
