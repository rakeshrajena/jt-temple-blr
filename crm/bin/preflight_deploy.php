<?php
declare(strict_types=1);

/**
 * Pre-deploy checks: env URLs, additive schema columns, no data migration required.
 *   php bin/preflight_deploy.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('JT_INSTALL_MINIMAL', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = 0;
$warn = static function (string $msg): void {
    fwrite(STDOUT, "WARN {$msg}\n");
};
$ok = static function (string $msg): void {
    fwrite(STDOUT, "ok  {$msg}\n");
};
$fail = static function (string $msg) use (&$failed): void {
    $failed++;
    fwrite(STDOUT, "FAIL {$msg}\n");
};

migrate_schema(db());
$ok('CRM migrate_schema() ran (additive columns only — no data backfill required)');

$columns = [
    ['subscription_invoices', 'gateway_order_id'],
];
$pdo = db();
foreach ($columns as [$table, $col]) {
    $exists = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table) . " AND column_name = " . $pdo->quote($col)
    )->fetchColumn();
    if ($exists) {
        $ok("column {$table}.{$col} present");
    } else {
        $fail("column {$table}.{$col} missing after migrate");
    }
}

$appUrl = rtrim(env_value('APP_URL'), '/');
if ($appUrl !== '' && str_contains($appUrl, '/crm')) {
    $ok('crm APP_URL includes /crm');
} else {
    $fail('crm/.env APP_URL should be https://your-domain/crm');
}

$bookingEnv = dirname(APP_ROOT) . '/Booking/.env';
if (is_file($bookingEnv)) {
    $erp = '';
    foreach (env_parse((string) file_get_contents($bookingEnv)) as $k => $v) {
        if ($k === 'ERP_APP_URL') {
            $erp = rtrim($v, '/');
        }
    }
    if (str_ends_with(strtolower($erp), '/crm')) {
        $ok('Booking ERP_APP_URL points at CRM');
    } else {
        $warn('Booking/.env ERP_APP_URL should end with /crm');
    }
} else {
    $warn('Booking/.env not found beside CRM (ok if paths differ on server)');
}

$homeIndex = dirname(APP_ROOT) . '/home/index.html';
if (is_file($homeIndex) && str_contains((string) file_get_contents($homeIndex), 'SJT_DONATION_URL')) {
    $ok('home/index.html sets SJT_DONATION_URL for Donate nav');
} else {
    $fail('home/index.html missing SJT_DONATION_URL');
}

$donationIndex = dirname(APP_ROOT) . '/Booking/donation/index.php';
if (is_file($donationIndex)) {
    $ok('Booking/donation app present');
} else {
    $fail('Booking/donation/index.php missing');
}

if (subscription_gateway_enabled()) {
    $ok('Subscription Razorpay keys loaded from Booking/.env');
} else {
    $warn('Subscription gateway not configured (PAYMENT_GATEWAY / RAZORPAY_*)');
}

$smtp = load_messaging_settings();
if (trim($smtp['smtp_host'] ?? '') !== '' && trim($smtp['smtp_from_email'] ?? '') !== '') {
    $ok('CRM SMTP configured for receipts and subscription mail');
} else {
    $warn('CRM SMTP not configured — payment emails will not send until Settings › Messages');
}

fwrite(STDOUT, PHP_EOL . "Note: New installs use schema.sql. Existing production DBs only need a normal CRM/B CRM HTTP hit or this script — ensure_column adds NULL columns with no row updates." . PHP_EOL);

exit($failed > 0 ? 1 : 0);
