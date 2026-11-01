<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "Booking install probe OK\n";
echo 'PHP ' . PHP_VERSION . "\n";
echo 'Folder: ' . __DIR__ . "\n";
echo 'helpers.php: ' . (is_file(__DIR__ . '/app/helpers.php') ? 'yes' : 'NO') . "\n";
echo 'autoload.php: ' . (is_file(__DIR__ . '/app/autoload.php') ? 'yes' : 'NO') . "\n";
echo 'install_bootstrap.php: ' . (is_file(__DIR__ . '/app/install_bootstrap.php') ? 'yes' : 'NO') . "\n";
echo '.env: ' . (is_file(__DIR__ . '/.env') ? 'yes' : 'NO') . "\n";
require __DIR__ . '/install_erp_locator.php';
$erp = booking_install_erp_root();
echo 'ERP sibling: ' . ($erp ?? 'NOT FOUND') . "\n";
echo 'site_install: ' . ($erp && is_file($erp . '/includes/site_install.php') ? 'yes' : 'NO') . "\n";
