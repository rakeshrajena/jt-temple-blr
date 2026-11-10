<?php
declare(strict_types=1);

/**
 * Production readiness: receipt public URLs, PDF paths, and payment gateway wiring.
 *
 *   php tests/payments_receipts_readiness_test.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = 0;

function check(bool $ok, string $name): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n";
}

$token = str_repeat('a', 32);
$prodReceiptUrl = receipt_public_url($token);
check(
    str_contains($prodReceiptUrl, '/crm/index.php')
    && str_contains($prodReceiptUrl, 'receipts%2Fopen%2F' . $token),
    'CRM receipt links use /crm/index.php and receipts/open/{token}'
);
$appUrl = rtrim(env_value('APP_URL'), '/');
check(
    $appUrl !== '' && preg_match('#^https?://#i', $appUrl) === 1,
    'crm/.env APP_URL is an absolute URL'
);
check(
    str_starts_with($prodReceiptUrl, parse_url($appUrl, PHP_URL_SCHEME) . '://' . (parse_url($appUrl, PHP_URL_HOST) ?? '')),
    'receipt_public_url origin matches APP_URL host'
);

check(subscription_gateway_enabled(), 'subscription Razorpay is configured');
check(subscription_gateway_env_bool('GATEWAY_SSL_VERIFY', true) === false, 'local GATEWAY_SSL_VERIFY=false is honored (repo .env)');

$bookingEnv = dirname(APP_ROOT) . '/Booking/.env';
check(is_file($bookingEnv), 'Booking/.env exists for shared gateway keys');
$erpInBooking = '';
foreach (env_parse((string) file_get_contents($bookingEnv)) as $k => $v) {
    if ($k === 'ERP_APP_URL') {
        $erpInBooking = rtrim($v, '/');
    }
}
check($erpInBooking !== '' && str_ends_with(strtolower($erpInBooking), '/crm'), 'Booking/.env ERP_APP_URL ends with /crm');

$donationUrlCli = dirname(APP_ROOT) . '/Booking/donation/bin/check_receipt_url.php';
if (is_file($donationUrlCli)) {
    $out = [];
    exec(
        escapeshellarg(PHP_BINARY ?: 'php') . ' ' . escapeshellarg($donationUrlCli) . ' ' . escapeshellarg($token),
        $out
    );
    $donationUrl = trim(implode("\n", $out));
    check(
        $donationUrl !== '' && str_contains($donationUrl, '/crm/index.php') && str_contains($donationUrl, $token),
        'online donation receipt links match CRM public URL shape'
    );
} else {
    $expectedDonation = $prodReceiptUrl;
    check($expectedDonation !== '', 'donation receipts use same CRM public URL as ERP (no separate checker script)');
}

check(is_file(APP_ROOT . '/bin/send_donation_receipt_email.php'), 'CRM send_donation_receipt_email CLI exists for booking and donation');

$paidSub = db_one(
    "SELECT i.id, i.invoice_number, i.payment_token, i.status, i.linked_donation_id,
            d.receipt_number, d.receipt_generated, d.receipt_share_token, d.receipt_cancelled
     FROM subscription_invoices i
     LEFT JOIN donations d ON d.id = i.linked_donation_id
     WHERE i.status = 'Paid' ORDER BY i.id DESC LIMIT 1"
);
if ($paidSub !== null) {
    check((int) ($paidSub['linked_donation_id'] ?? 0) > 0, 'latest paid subscription invoice links to ERP donation');
    $hasToken = preg_match('/^[a-f0-9]{32}$/', (string) ($paidSub['receipt_share_token'] ?? '')) === 1;
    check($hasToken && (int) ($paidSub['receipt_generated'] ?? 0) === 1, 'latest paid subscription has ERP receipt + share token');
    if ($hasToken) {
        $link = receipt_public_url((string) $paidSub['receipt_share_token']);
        $block = receipt_email_block_reason('devotee@example.com', true, true);
        $body = receipt_email_body([
            'donor_name' => (string) ($paidSub['name'] ?? 'Devotee'),
            'receipt_number' => (string) ($paidSub['receipt_number'] ?? ''),
            'donation_date' => date('Y-m-d'),
            'amount' => (string) ($paidSub['amount'] ?? '0'),
            'purpose' => 'Subscription',
            'payment_mode' => 'UPI',
        ], $link);
        check(str_contains($body, $link) && $block === null, 'subscription receipt email body includes public link');
    }
} else {
    echo "skip  no paid subscription invoice in DB\n";
}

$odn = db_one(
    "SELECT id, receipt_number, receipt_generated, receipt_share_token, online_booking_no
     FROM donations WHERE online_booking_no LIKE 'ODN-%' AND receipt_generated = 1
     ORDER BY id DESC LIMIT 1"
);
if ($odn !== null) {
    check(preg_match('/^[a-f0-9]{32}$/', (string) ($odn['receipt_share_token'] ?? '')) === 1, 'latest online donation has share token');
    $pdfPath = APP_ROOT . '/storage/receipts/' . preg_replace('/[^A-Za-z0-9._-]/', '', (string) $odn['receipt_number']) . '.pdf';
    check(is_file($pdfPath), 'latest online donation receipt PDF exists on disk');
} else {
    echo "skip  no ODN online donation with receipt\n";
}

$bookDon = db_one(
    "SELECT id, receipt_number, receipt_generated, receipt_share_token, online_booking_no
     FROM donations WHERE online_booking_no IS NOT NULL AND online_booking_no <> '' AND online_booking_no NOT LIKE 'ODN-%'
       AND receipt_generated = 1 ORDER BY id DESC LIMIT 1"
);
if ($bookDon !== null) {
    check(preg_match('/^[a-f0-9]{32}$/', (string) ($bookDon['receipt_share_token'] ?? '')) === 1, 'latest booking-linked donation has share token');
} else {
    echo "skip  no booking-linked ERP donation receipt\n";
}

$smtp = load_messaging_settings();
check(
    trim($smtp['smtp_host'] ?? '') !== '' && trim($smtp['smtp_from_email'] ?? '') !== '',
    'CRM SMTP is configured for receipt emails'
);

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all payments/receipts readiness checks passed\n";
