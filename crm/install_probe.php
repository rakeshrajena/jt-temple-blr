<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "CRM install probe OK\n";
echo 'PHP ' . PHP_VERSION . "\n";
echo 'APP folder: ' . __DIR__ . "\n";
echo 'bootstrap: ' . (is_file(__DIR__ . '/includes/bootstrap.php') ? 'yes' : 'NO') . "\n";
echo 'site_install: ' . (is_file(__DIR__ . '/includes/site_install.php') ? 'yes' : 'NO') . "\n";
echo '.env: ' . (is_file(__DIR__ . '/.env') ? 'yes' : 'NO') . "\n";
$booking = dirname(__DIR__) . '/Booking';
echo 'Booking sibling: ' . (is_dir($booking) ? $booking : 'NOT FOUND') . "\n";
echo 'Booking/helpers: ' . (is_file($booking . '/app/helpers.php') ? 'yes' : 'NO') . "\n";
