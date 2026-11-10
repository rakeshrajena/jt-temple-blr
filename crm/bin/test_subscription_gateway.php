<?php
declare(strict_types=1);

define('JT_INSTALL_MINIMAL', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

echo 'enabled=' . (subscription_gateway_enabled() ? 'yes' : 'no') . PHP_EOL;
$env = subscription_gateway_env();
echo 'gateway=' . ($env['PAYMENT_GATEWAY'] ?? '') . PHP_EOL;
echo 'key_id=' . substr($env['RAZORPAY_KEY_ID'] ?? '', 0, 12) . '...' . PHP_EOL;
echo 'ssl_verify=' . ($env['GATEWAY_SSL_VERIFY'] ?? '(unset)') . PHP_EOL;
echo 'curl=' . (function_exists('curl_init') ? 'yes' : 'no') . PHP_EOL;

$inv = db_one(
    "SELECT i.*, s.name, s.email, s.mobile FROM subscription_invoices i
     JOIN subscribers s ON s.id = i.subscriber_id
     WHERE i.status IN ('Pending','Sent','Overdue') ORDER BY i.id DESC LIMIT 1"
);
if ($inv === null) {
    echo "no unpaid invoice\n";
    exit(0);
}
echo 'invoice=' . ($inv['invoice_number'] ?? '') . ' amount=' . ($inv['amount'] ?? '') . PHP_EOL;
try {
    $order = subscription_gateway_create_order($inv);
    echo 'order_id=' . $order['order_id'] . ' amount=' . $order['amount'] . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
