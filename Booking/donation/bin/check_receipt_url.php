<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$token = (string) ($argv[1] ?? '');
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__) . '/includes/receipt_service.php';
echo donation_crm_receipt_public_url($token);
