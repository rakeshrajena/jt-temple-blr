<?php
declare(strict_types=1);

$base = 'http://localhost/jt_blr/jt-temple-blr/php/index.php';
$cookie = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt_review_cookies.txt';
@unlink($cookie);

function http(string $url, string $cookie, ?array $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $raw = is_string($raw) ? $raw : '';
    $parts = explode("\r\n\r\n", $raw, 2);
    $body = $parts[count($parts) - 1] ?? '';
    return [$code, $body];
}

[$code, $login] = http($base . '?r=login', $cookie);
if (!preg_match('/name="csrf" value="([^"]+)"/', $login, $m)) {
    fwrite(STDERR, "csrf missing\n");
    exit(1);
}
http($base . '?r=login', $cookie, [
    'csrf' => $m[1],
    'username' => 'admin',
    'password' => 'temple@123',
]);

require dirname(__DIR__) . '/includes/bootstrap.php';
$token = (string) db()->query("SELECT payment_token FROM subscription_invoices WHERE status <> 'Paid' LIMIT 1")->fetchColumn();

$routes = [
    '' => 'Dashboard',
    'demo' => 'Demo',
    'inventory' => 'Inventory',
    'food' => 'Food',
    'food/coupons' => 'Coupons',
    'vastra' => 'Vastra',
    'donations' => 'Donations',
    'expenses' => 'Expenses',
    'bank' => 'Bank',
    'reports' => 'Reports',
    'reports/donations' => 'Report donations',
    'reports/expenses' => 'Report expenses',
    'reports/inventory' => 'Report inventory',
    'reports/food' => 'Report food',
    'reports/vastra' => 'Report vastra',
    'reports/reconciliation' => 'Report bank',
    'subscriptions' => 'Subscriptions',
    'users' => 'Users',
    'api/donors/search&q=Bi' => 'Donor search',
    'pay/' . $token => 'Pay page',
    'pay/not-a-real-token' => 'Invalid pay',
];

$failed = 0;
foreach ($routes as $route => $label) {
    if ($route === '') {
        $url = $base;
    } elseif (str_starts_with($route, 'api/')) {
        $url = $base . '?r=api/donors/search&q=Bi';
    } else {
        $url = $base . '?r=' . rawurlencode($route);
        $url = str_replace('%2F', '/', $url);
    }
    [$status, $body] = http($url, $cookie);
    $bad = $status >= 500
        || str_contains($body, 'Fatal error')
        || str_contains($body, 'Warning:')
        || str_contains($body, 'A server error');
    if ($route === 'pay/not-a-real-token' && $status !== 404) {
        $bad = true;
    }
    if ($route === 'api/donors/search&q=Bi' && !str_contains($body, 'Bikash')) {
        $bad = true;
    }
    if ($bad) {
        $failed++;
    }
    echo sprintf("%-22s %3d %s\n", $label, $status, $bad ? 'FAIL' : 'ok');
}

exit($failed === 0 ? 0 : 1);
