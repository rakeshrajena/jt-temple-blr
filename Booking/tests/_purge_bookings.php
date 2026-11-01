<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit(1);
}
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

$db = SJT\Core\App::db();
$count = (int) $db->value('SELECT COUNT(*) FROM bookings');
$out = static fn (string $s) => fwrite(STDOUT, $s . PHP_EOL);

$db->transaction(function (SJT\Core\Database $db) {
    $db->run('DELETE FROM notifications WHERE booking_id IS NOT NULL');
    $db->run('DELETE FROM audit_log WHERE booking_id IS NOT NULL');
    $db->run('DELETE FROM booking_items');
    $db->run('DELETE FROM payments');
    $db->run('DELETE FROM bookings');
    $db->run("DELETE FROM sequences WHERE name IN ('booking', 'invoice')");
});

$left = (int) $db->value('SELECT COUNT(*) FROM bookings');
$out("Removed {$count} booking(s). Remaining: {$left}.");
