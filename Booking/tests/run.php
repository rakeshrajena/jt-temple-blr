<?php
declare(strict_types=1);

/*
 * End-to-end and unit test suite.
 *
 *   php tests/run.php            (needs PHP CLI with pdo_sqlite and curl)
 *
 * It starts two local servers — the booking app (on a fresh SQLite database)
 * and a mock WhatsApp Cloud API — then drives the real HTTP flows: devotee
 * booking, payment, admin actions, notifications, security checks.
 * Results are printed and written to docs/TEST-RESULTS.md.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/sjt-test-' . getmypid();
@mkdir($tmp, 0700, true);
$appPort = 18000 + random_int(0, 900);
$mockPort = $appPort + 1000;
$base = "http://127.0.0.1:$appPort/Booking";
$env = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => $base, 'APP_KEY' => str_repeat('k', 64),
    'DB_DRIVER' => 'sqlite', 'DB_PATH' => "$tmp/test.sqlite",
    'WA_ACCESS_TOKEN' => 'test-token', 'WA_PHONE_NUMBER_ID' => '123456', 'WA_API_BASE' => "http://127.0.0.1:$mockPort",
    'WA_APP_SECRET' => 'app-secret', 'WA_WEBHOOK_VERIFY_TOKEN' => 'verify-me', 'MOCK_LOG' => "$tmp/mock.log",
    'PAYMENT_GATEWAY' => 'none', 'BOOKING_RATE_LIMIT_PER_HOUR' => '1000', 'RAZORPAY_KEY_SECRET' => 'rzp-secret', 'RAZORPAY_WEBHOOK_SECRET' => 'rzp-hook',
];
// Optional: run against MySQL/MariaDB instead of SQLite, e.g.
//   TEST_DB_DRIVER=mysql TEST_DB_HOST=127.0.0.1 TEST_DB_PORT=3306 TEST_DB_USER=root TEST_DB_PASS=secret php tests/run.php
$driver = getenv('TEST_DB_DRIVER') ?: 'sqlite';
if ($driver === 'mysql') {
    $env = array_merge($env, [
        'DB_DRIVER' => 'mysql', 'DB_HOST' => getenv('TEST_DB_HOST') ?: '127.0.0.1', 'DB_PORT' => getenv('TEST_DB_PORT') ?: '3306',
        'DB_NAME' => 'sjt_test_' . getmypid(), 'DB_USER' => getenv('TEST_DB_USER') ?: 'root', 'DB_PASS' => getenv('TEST_DB_PASS') ?: '',
    ]);
    $server = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']}", $env['DB_USER'], $env['DB_PASS']);
    $server->exec("CREATE DATABASE `{$env['DB_NAME']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
foreach ($env as $k => $v) {
    putenv("$k=$v");
}

// ------------------------------------------------------------------ servers
function sh(string $cmd): string
{
    return (string) shell_exec($cmd . ' 2>&1');
}
echo sh("php " . escapeshellarg("$root/bin/console.php") . " migrate");
putenv('SJT_ADMIN_PASSWORD=Admin-Pass-2026');
echo sh("php " . escapeshellarg("$root/bin/console.php") . " create-admin admin 'Test Admin' super");
putenv('SJT_ADMIN_PASSWORD=Staff-Pass-2026');
echo sh("php " . escapeshellarg("$root/bin/console.php") . " create-admin staff 'Test Staff' staff");

$procs = [];
$spawn = function (string $cmd) use (&$procs, $tmp) {
    $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', "$tmp/server.log", 'a'], 2 => ['file', "$tmp/server.log", 'a']], $pipes);
    $procs[] = $p;
};
$spawn('exec php -S 127.0.0.1:' . $mockPort . ' ' . escapeshellarg("$root/tests/mock_whatsapp.php"));
$spawn('exec env PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:' . $appPort . ' ' . escapeshellarg("$root/index.php"));
register_shutdown_function(function () use (&$procs, $tmp) {
    foreach ($procs as $p) {
        proc_terminate($p);
    }
    if (isset($GLOBALS['server'])) {
        $GLOBALS['pdo'] = null;
        $GLOBALS['server']->exec('DROP DATABASE `' . $GLOBALS['env']['DB_NAME'] . '`');
    }
    if (getenv('KEEP_TEST_DIR')) {
        echo "Test files kept in $tmp\n";
    } else {
        sh('rm -rf ' . escapeshellarg($tmp));
    }
});
for ($i = 0; $i < 50 && @file_get_contents("$base/health") === false; $i++) {
    usleep(100000);
}

$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
if ($driver === 'mysql') {
    $pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4", $env['DB_USER'], $env['DB_PASS'], $opts);
} else {
    $pdo = new PDO("sqlite:$tmp/test.sqlite", null, null, $opts);
    $pdo->exec('PRAGMA busy_timeout = 10000');
}
function q(string $sql, array $p = []): array
{
    global $pdo;
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return $s->fetchAll();
}
function q1(string $sql, array $p = []): mixed
{
    $r = q($sql, $p);
    return $r ? reset($r[0]) : null;
}
function setSetting(string $k, string $v): void
{
    q('UPDATE settings SET svalue = ? WHERE skey = ?', [$v, $k]);
}

// ------------------------------------------------------------------ HTTP client
final class Client
{
    public array $cookies = [];
    public function __construct(private string $base)
    {
    }

    /** @return array{status:int, body:string, location:?string, headers:array} */
    public function req(string $method, string $path, array $data = [], array $headers = [], ?string $raw = null): array
    {
        $ch = curl_init(str_starts_with($path, 'http') ? $path : $this->base . $path);
        $h = $headers;
        if ($this->cookies) {
            $h[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        }
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 60,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$respHeaders) {
                if (preg_match('/^([^:]+):\s*(.*)$/', trim($line), $m)) {
                    $respHeaders[strtolower($m[1])][] = $m[2];
                }
                return strlen($line);
            },
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $raw ?? http_build_query($data));
        }
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        foreach ($respHeaders['set-cookie'] ?? [] as $c) {
            [$kv] = explode(';', $c, 2);
            [$k, $v] = explode('=', $kv, 2);
            $this->cookies[$k] = $v;
        }
        return ['status' => $status, 'body' => $body, 'location' => $respHeaders['location'][0] ?? null, 'headers' => $respHeaders];
    }

    public function get(string $p): array { return $this->req('GET', $p); }
    public function post(string $p, array $d): array { return $this->req('POST', $p, $d); }

    public function csrf(string $path): string
    {
        $r = $this->get($path);
        preg_match('/name="_csrf" value="([^"]+)"/', $r['body'], $m);
        return $m[1] ?? '';
    }
}

/** Run the 3-step booking flow. Returns ['review' => resp, 'confirm' => resp, 'no' => ?string]. */
function book(Client $c, array $fields, bool $confirm = true): array
{
    $page = $c->get('/book');
    preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $m1);
    preg_match('/name="form_id" value="([^"]+)"/', $page['body'], $m2);
    $csrf = $m1[1] ?? '';
    $fid = $m2[1] ?? '';
    $review = $c->post('/book/review', ['_csrf' => $csrf, 'form_id' => $fid] + $fields);
    $out = ['review' => $review, 'confirm' => null, 'no' => null, 'csrf' => $csrf, 'form_id' => $fid];
    if ($confirm && $review['status'] === 200) {
        $res = $c->post('/book/confirm', ['_csrf' => $csrf, 'form_id' => $fid, 'agree' => '1']);
        $out['confirm'] = $res;
        if (preg_match('#/b/([A-Z0-9-]+)#', (string) $res['location'], $m)) {
            $out['no'] = $m[1];
        }
    }
    return $out;
}

function adminClient(string $base, string $user = 'admin', string $pass = 'Admin-Pass-2026'): Client
{
    $a = new Client($base);
    $t = $a->csrf('/admin/login');
    $a->post('/admin/login', ['_csrf' => $t, 'username' => $user, 'password' => $pass]);
    return $a;
}

function adminAction(Client $a, string $no, string $action, array $data = []): array
{
    $t = $a->csrf('/admin/bookings/' . $no);
    return $a->post('/admin/bookings/' . $no . '/action', ['_csrf' => $t, 'action' => $action] + $data);
}

function booking(string $no): array
{
    return q('SELECT * FROM bookings WHERE booking_no = ?', [$no])[0] ?? [];
}

// ------------------------------------------------------------------ assertions
$results = [];
$current = '';
function test(string $name, callable $fn): void
{
    global $results, $current;
    $current = $name;
    $t = microtime(true);
    try {
        $fn();
        $results[] = [$name, true, '', microtime(true) - $t];
        echo "  \033[32mPASS\033[0m $name\n";
    } catch (Throwable $e) {
        $results[] = [$name, false, $e->getMessage(), microtime(true) - $t];
        echo "  \033[31mFAIL\033[0m $name\n       " . $e->getMessage() . "\n";
    }
}
function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}
function eq(mixed $exp, mixed $act, string $msg): void
{
    if ($exp != $act) {
        throw new RuntimeException("$msg — expected " . var_export($exp, true) . ', got ' . var_export($act, true));
    }
}

// ids from the seed data
$svc = [];
foreach (q('SELECT id, code FROM services') as $r) {
    $svc[$r['code']] = (int) $r['id'];
}
$slot = [];
foreach (q('SELECT id, service_id FROM time_slots ORDER BY id') as $r) {
    $slot[(int) $r['service_id']] ??= (int) $r['id'];
}
q('UPDATE time_slots SET capacity = NULL WHERE service_id = ?', [$svc['special-puja']]);
$coupon = array_column(q('SELECT id, price_paise FROM booking_coupons ORDER BY id'), 'id', 'price_paise');
$c150 = (int) $coupon[15000];
$c250 = (int) $coupon[25000];
$d = fn (int $days) => date('Y-m-d', strtotime(($days >= 0 ? "+$days" : "$days") . ' days'));
$person = ['name' => 'Ananya Sahu', 'mobile' => '9845012345'];
$special = fn (array $extra = []) => array_replace($person + ['services' => [$svc['special-puja']], 'service_date' => $d(5), 'slot' => [$svc['special-puja'] => $slot[$svc['special-puja']]],
    'participants' => [$svc['special-puja'] => '3'], 'f' => [$svc['special-puja'] => ['sankalpa_names' => 'Sahu family']]], $extra);

$dev = new Client($base);
$created = [];

echo "\nUnit tests\n";
define('APP_ROOT', $root);
require $root . '/app/autoload.php';
SJT\Core\Env::load('/nonexistent');
date_default_timezone_set('Asia/Kolkata');

test('Money formatting uses Indian grouping', function () {
    eq('₹1,100', money(110000), 'money');
    eq('₹12,34,567.50', money(123456750), 'lakh grouping');
    eq('₹0', money(0), 'zero');
    eq(110050, parse_rupees('1,100.50'), 'parse');
    eq(null, parse_rupees('abc'), 'parse invalid');
    eq(null, parse_rupees('-5'), 'parse negative');
});
test('Mobile number normalisation', function () {
    $n = [SJT\Service\Validator::class, 'normaliseMobile'];
    eq('919845012345', $n('98450 12345'), 'plain');
    eq('919845012345', $n('+91-98450-12345'), '+91');
    eq('919845012345', $n('09845012345'), 'leading 0');
    eq('14155550123', $n('+1 415 555 0123'), 'international');
    eq(null, $n('12345'), 'too short');
    eq(null, $n('5845012345'), 'invalid Indian prefix');
    eq(null, $n('98450<script>'), 'garbage');
});
test('Financial year for invoice numbers', function () {
    eq('2026-27', SJT\Service\Sequence::financialYear('2026-10-10'), 'Oct');
    eq('2025-26', SJT\Service\Sequence::financialYear('2026-03-31'), 'Mar');
    eq('2026-27', SJT\Service\Sequence::financialYear('2026-04-01'), 'Apr');
});
test('Status rules: confirmed only when paid and approved', function () {
    $f = [SJT\Service\Status::class, 'derive'];
    eq('pending', $f('awaiting_verification', 'not_required', 'pending'), 'awaiting');
    eq('confirmed', $f('paid', 'not_required', 'pending'), 'paid');
    eq('pending', $f('paid', 'pending', 'pending'), 'paid but needs approval');
    eq('confirmed', $f('paid', 'approved', 'pending'), 'paid+approved');
    eq('pending', $f('pending', 'approved', 'pending'), 'approved not paid');
    eq('cancelled', $f('paid', 'approved', 'cancelled'), 'cancelled sticks');
    eq('confirmed', $f('not_required', 'approved', 'pending'), 'free + approved');
});
test('UPI reference validation', function () {
    eq('427812345678', SJT\Service\Validator::paymentReference(' 4278 1234 5678 '), 'spaces');
    eq(null, SJT\Service\Validator::paymentReference('12'), 'short');
    eq(null, SJT\Service\Validator::paymentReference("1234567'--"), 'injection');
});
test('CSV export neutralises spreadsheet formulas', function () {
    eq("'=HYPERLINK(1)", SJT\Service\Reports::cell('=HYPERLINK(1)'), 'formula');
    eq('-5', SJT\Service\Reports::cell('-5'), 'negative number untouched');
    ok(str_starts_with(SJT\Service\Reports::csv(['a'], [['x']]), "\xEF\xBB\xBF"), 'UTF-8 BOM for Excel');
});
test('WhatsApp template parameters are sanitised', function () {
    eq('a b c', SJT\Service\WhatsAppClient::clean("a\n\tb     c"), 'newlines/tabs/spaces');
    eq('-', SJT\Service\WhatsAppClient::clean(''), 'empty');
});
test('Payment gateway signatures (Razorpay) are verified with HMAC', function () {
    $g = new SJT\Service\PaymentGateway(new SJT\Core\Database(['driver' => 'sqlite', 'path' => ':memory:']));
    $sig = hash_hmac('sha256', 'order_1|pay_1', 'rzp-secret');
    ok($g->checkoutSignatureValid('order_1', 'pay_1', $sig), 'valid signature accepted');
    ok(!$g->checkoutSignatureValid('order_1', 'pay_2', $sig), 'tampered payment id rejected');
    ok(!$g->checkoutSignatureValid('order_1', 'pay_1', ''), 'empty rejected');
    ok($g->webhookSignatureValid('{"a":1}', hash_hmac('sha256', '{"a":1}', 'rzp-hook')), 'webhook ok');
    ok(!$g->webhookSignatureValid('{"a":2}', hash_hmac('sha256', '{"a":1}', 'rzp-hook')), 'webhook tampered');
});

echo "\nBooking flows\n";
test('Individual service booking (Special Puja) — booking number, totals, pending status', function () use ($dev, $special, &$created) {
    $r = book($dev, $special());
    eq(200, $r['review']['status'], 'review page');
    ok(str_contains($r['review']['body'], '₹501'), 'review shows price');
    eq(303, $r['confirm']['status'], 'confirm redirects');
    ok((bool) preg_match('/^SJT-' . date('Y') . '-\d{6}$/', (string) $r['no']), 'booking number format: ' . $r['no']);
    $b = booking($r['no']);
    eq(50100, (int) $b['total_paise'], 'total');
    eq('pending', $b['payment_status'], 'payment status');
    eq('pending', $b['booking_status'], 'not confirmed yet');
    ok((bool) preg_match('#^SJTB/\d{4}-\d{2}/\d{6}$#', $b['invoice_no']), 'invoice number ' . $b['invoice_no']);
    $p = $dev->get('/b/' . $r['no'] . '/pay');
    ok(str_contains($p['body'], $r['no']) && str_contains($p['body'], 'not yet a confirmed booking'), 'payment page shows number + request status');
    $created['single'] = $r['no'];
});
test('Multiple services in one booking (Special + Vehicle Puja)', function () use ($dev, $special, $svc, $slot, &$created) {
    $v = $svc['vehicle-puja'];
    $f = $special();
    $f['services'][] = $v;
    $f['slot'][$v] = $slot[$v];
    $f['f'][$v] = ['vehicle_type' => 'Car', 'vehicle_number' => 'KA01AB1234'];
    $r = book($dev, $f);
    $b = booking((string) $r['no']);
    eq(50100 + 25100, (int) $b['total_paise'], 'combined total');
    eq(2, (int) q1("SELECT COUNT(*) FROM booking_items WHERE booking_id = ? AND item_type = 'service'", [$b['id']]), 'two service lines');
    $created['multi'] = $r['no'];
});
test('Service booking with ₹150 food coupons (4 persons)', function () use ($dev, $special, $c150) {
    $r = book($dev, $special(['add_food' => '1', 'food_cat' => [$c150 => '1'], 'food' => [$c150 => '4']]));
    $b = booking((string) $r['no']);
    eq(50100 + 60000, (int) $b['total_paise'], 'total = 501 + 150×4');
    $line = q("SELECT * FROM booking_items WHERE booking_id = ? AND item_type = 'food'", [$b['id']])[0];
    eq([15000, 4, 60000], [(int) $line['unit_price_paise'], (int) $line['quantity'], (int) $line['amount_paise']], 'food line');
});
test('Service booking with ₹250 food coupons (2 persons)', function () use ($dev, $special, $c250) {
    $r = book($dev, $special(['add_food' => '1', 'food_cat' => [$c250 => '1'], 'food' => [$c250 => '2']]));
    eq(50100 + 50000, (int) booking((string) $r['no'])['total_paise'], 'total = 501 + 250×2');
});
test('Combined coupon categories: 150×4 + 250×2 = ₹1,100 food, itemised separately', function () use ($dev, $special, $c150, $c250, &$created) {
    $r = book($dev, $special(['add_food' => '1', 'food_cat' => [$c150 => '1', $c250 => '1'], 'food' => [$c150 => '4', $c250 => '2']]));
    ok(str_contains($r['review']['body'], 'Food charges: ₹1,100'), 'review shows food charges ₹1,100');
    $b = booking((string) $r['no']);
    eq(50100 + 110000, (int) $b['total_paise'], 'grand total');
    $food = q("SELECT name, quantity, amount_paise FROM booking_items WHERE booking_id = ? AND item_type = 'food' ORDER BY id", [$b['id']]);
    eq(2, count($food), 'two separate food lines');
    eq(110000, array_sum(array_column($food, 'amount_paise')), 'food sum');
    $created['combined'] = $r['no'];
});
test('Food-only booking (no puja) with both categories', function () use ($dev, $svc, $slot, $c150, $c250, $d, &$created) {
    $fo = $svc['food-only'];
    $r = book($dev, ['name' => 'Ravi Kumar', 'mobile' => '9900112233', 'services' => [$fo], 'service_date' => $d(4), 'slot' => [$fo => $slot[$fo]],
        'food_cat' => [$c150 => '1', $c250 => '1'], 'food' => [$c150 => '3', $c250 => '1']]);
    eq(303, $r['confirm']['status'] ?? 0, 'created');
    $b = booking((string) $r['no']);
    eq('food_only', $b['booking_type'], 'type');
    eq(15000 * 3 + 25000, (int) $b['total_paise'], 'total = 150×3 + 250×1');
    eq('not_required', $b['approval_status'], 'no approval needed');
    $created['food'] = $r['no'];
});
test('Food-only booking requires a coupon category and persons', function () use ($dev, $svc, $slot, $d) {
    $fo = $svc['food-only'];
    $r = book($dev, ['name' => 'Ravi Kumar', 'mobile' => '9900112233', 'services' => [$fo], 'service_date' => $d(4), 'slot' => [$fo => $slot[$fo]]], false);
    eq(422, $r['review']['status'], 'rejected');
    ok(str_contains($r['review']['body'], 'choose at least one food coupon category'), 'message');
});
test('Food-only cannot be combined with another service', function () use ($dev, $svc, $special, $c150) {
    $f = $special(['food_cat' => [$c150 => '1'], 'food' => [$c150 => '2']]);
    $f['services'][] = $svc['food-only'];
    $r = book($dev, $f, false);
    eq(422, $r['review']['status'], 'rejected');
    ok(str_contains($r['review']['body'], 'cannot be combined'), 'message');
});
test('Selected coupon category with 0 persons is rejected', function () use ($dev, $special, $c150) {
    $r = book($dev, $special(['add_food' => '1', 'food_cat' => [$c150 => '1'], 'food' => [$c150 => '0']]), false);
    eq(422, $r['review']['status'], 'rejected');
    ok(str_contains($r['review']['body'], 'number of persons'), 'message');
});
test('"Add food" ticked without any category is rejected', function () use ($dev, $special) {
    $r = book($dev, $special(['add_food' => '1']), false);
    eq(422, $r['review']['status'], 'rejected');
});
test('Quantity above the per-booking maximum is rejected', function () use ($dev, $special, $c150) {
    $r = book($dev, $special(['add_food' => '1', 'food_cat' => [$c150 => '1'], 'food' => [$c150 => '201']]), false);
    eq(422, $r['review']['status'], 'rejected');
});
test('Tampered price fields from the browser are ignored (server-side pricing)', function () use ($dev, $special) {
    $r = book($dev, $special(['price' => '1', 'total_paise' => '100', 'quote' => ['total_paise' => 1]]));
    eq(50100, (int) booking((string) $r['no'])['total_paise'], 'server price used');
});
test('Invoice: HTML and PDF contain booking, invoice number and correct totals', function () use ($dev, &$created) {
    $no = $created['combined'];
    $h = $dev->get("/b/$no/invoice");
    eq(200, $h['status'], 'html');
    foreach ([$no, 'INVOICE', '₹1,601', '₹600', '₹500', 'Food Coupon (Standard)', 'PAYMENT PENDING'] as $s) {
        ok(str_contains($h['body'], $s), "invoice contains $s");
    }
    $p = $dev->get("/b/$no/invoice.pdf");
    eq(200, $p['status'], 'pdf');
    ok(str_starts_with($p['body'], '%PDF-'), 'is a PDF');
    ok(str_contains($p['headers']['content-type'][0] ?? '', 'application/pdf'), 'content-type');
});
test('Duplicate submission (double click / resubmit) creates only one booking', function () use ($dev, $special) {
    $before = (int) q1('SELECT COUNT(*) FROM bookings');
    $r = book($dev, $special());
    $again = $dev->post('/book/confirm', ['_csrf' => $r['csrf'], 'form_id' => $r['form_id'], 'agree' => '1']);
    eq(303, $again['status'], 'second submit redirects');
    ok(str_contains((string) $again['location'], (string) $r['no']), 'to the same booking');
    eq($before + 1, (int) q1('SELECT COUNT(*) FROM bookings'), 'only one new row');
});
test('Concurrent submissions get unique, sequential booking numbers', function () use ($base, $special) {
    $clients = [];
    $mh = curl_multi_init();
    $handles = [];
    for ($i = 0; $i < 12; $i++) {
        $c = new Client($base);
        $r = book($c, $special(), false);
        $clients[] = [$c, $r];
    }
    foreach ($clients as [$c, $r]) {
        $ch = curl_init($base . '/book/confirm');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
            CURLOPT_POSTFIELDS => http_build_query(['_csrf' => $r['csrf'], 'form_id' => $r['form_id'], 'agree' => '1']),
            CURLOPT_HTTPHEADER => ['Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($c->cookies), $c->cookies))]]);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running);
    $nos = [];
    foreach ($handles as $ch) {
        if (preg_match('#Location: \S*/b/([A-Z0-9-]+)#', (string) curl_multi_getcontent($ch), $m)) {
            $nos[] = $m[1];
        }
    }
    eq(12, count($nos), 'all 12 created');
    eq(12, count(array_unique($nos)), 'all unique');
    $seq = array_map(fn ($n) => (int) substr($n, -6), $nos);
    sort($seq);
    eq(range($seq[0], $seq[0] + 11), $seq, 'no gaps / duplicates');
    eq((int) q1('SELECT COUNT(*) FROM bookings'), (int) q1('SELECT COUNT(DISTINCT booking_no) FROM bookings'), 'db unique');
});
test('Price change between review and submit is shown before saving', function () use ($dev, $special, $svc) {
    $page = $dev->get('/book');
    preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $m1);
    preg_match('/name="form_id" value="([^"]+)"/', $page['body'], $m2);
    $dev->post('/book/review', ['_csrf' => $m1[1], 'form_id' => $m2[1]] + $special());
    q('UPDATE services SET price_paise = 60100 WHERE id = ?', [$svc['special-puja']]);
    $before = (int) q1('SELECT COUNT(*) FROM bookings');
    $r = $dev->post('/book/confirm', ['_csrf' => $m1[1], 'form_id' => $m2[1], 'agree' => '1']);
    q('UPDATE services SET price_paise = 50100 WHERE id = ?', [$svc['special-puja']]);
    eq(200, $r['status'], 'review shown again');
    ok(str_contains($r['body'], 'Prices were updated'), 'notice');
    eq($before, (int) q1('SELECT COUNT(*) FROM bookings'), 'nothing saved');
});

echo "\nAvailability\n";
test('Past dates and dates beyond the booking window are rejected', function () use ($dev, $special, $d) {
    eq(422, book($dev, $special(['service_date' => $d(-1)]), false)['review']['status'], 'past');
    eq(422, book($dev, $special(['service_date' => $d(0)]), false)['review']['status'], 'inside lead time');
    eq(422, book($dev, $special(['service_date' => $d(400)]), false)['review']['status'], 'too far');
});
test('Blocked (unavailable) dates are rejected with the reason', function () use ($dev, $special, $d) {
    q("INSERT INTO blocked_dates (service_id, block_date, reason, created_at) VALUES (NULL, ?, 'Rath Yatra', ?)", [$d(9), date('Y-m-d H:i:s')]);
    $r = book($dev, $special(['service_date' => $d(9)]), false);
    eq(422, $r['review']['status'], 'rejected');
    ok(str_contains($r['review']['body'], 'Rath Yatra'), 'reason shown');
    $api = json_decode($dev->get('/api/availability?date=' . $d(9))['body'], true);
    ok($api['services'][array_key_first($api['services'])]['available'] === false, 'availability API reports closed');
});
test('Full time slot cannot be overbooked', function () use ($dev, $svc, $slot, $d) {
    $s = $svc['shraddha-karma'];
    $f = fn () => ['name' => 'Sunil Das', 'mobile' => '9845099999', 'services' => [$s], 'service_date' => $d(12), 'slot' => [$s => $slot[$s]],
        'participants' => [$s => '5'], 'f' => [$s => ['departed_name' => 'Late Sri Das']]];
    for ($i = 0; $i < 3; $i++) {
        eq(303, book($dev, $f())['confirm']['status'] ?? 0, "booking $i fits capacity 3");
    }
    $r = book($dev, $f(), false);
    eq(422, $r['review']['status'], '4th rejected');
    ok(str_contains($r['review']['body'], 'fully booked'), 'message');
});

echo "\nPayments & admin workflow\n";
setSetting('upi_vpa', 'sjtemple@sbi');
setSetting('upi_verified', '1');
$admin = adminClient($base);
test('Submitting a UPI reference does NOT mark the booking paid', function () use ($dev, &$created) {
    $no = $created['single'];
    $t = $dev->csrf("/b/$no/pay");
    $r = $dev->post("/b/$no/payment", ['_csrf' => $t, 'reference' => '4278 1234 5678']);
    eq(303, $r['status'], 'accepted');
    $b = booking($no);
    eq('awaiting_verification', $b['payment_status'], 'awaiting verification');
    eq('pending', $b['booking_status'], 'still not confirmed');
    eq('427812345678', $b['payment_ref'], 'reference stored');
    ok(str_contains($dev->get("/b/$no")['body'], 'awaiting verification'), 'status page explains');
});
test('Invalid and reused UPI references are rejected', function () use ($dev, &$created) {
    $no = $created['multi'];
    $t = $dev->csrf("/b/$no/pay");
    $dev->post("/b/$no/payment", ['_csrf' => $t, 'reference' => '12']);
    eq('pending', booking($no)['payment_status'], 'too short rejected');
    $dev->post("/b/$no/payment", ['_csrf' => $t, 'reference' => '427812345678']);
    eq('pending', booking($no)['payment_status'], 'reference used on another booking rejected');
});
test('Admin verifies payment → paid and confirmed, with audit trail', function () use ($admin, &$created) {
    $no = $created['single'];
    $pid = (int) q1("SELECT p.id FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE b.booking_no = ? AND p.status = 'submitted'", [$no]);
    $r = adminAction($admin, $no, 'verify_payment', ['payment_id' => $pid, 'reference' => '427812345678', 'amount' => '501']);
    eq(303, $r['status'], 'redirect');
    $b = booking($no);
    eq('paid', $b['payment_status'], 'paid');
    eq('confirmed', $b['booking_status'], 'confirmed');
    ok($b['paid_at'] !== null, 'paid_at');
    $a = q("SELECT actor FROM audit_log WHERE booking_id = ? AND action = 'payment.verified'", [$b['id']]);
    eq('admin:admin', $a[0]['actor'] ?? null, 'audit records who verified');
});
test('Amount mismatch needs an explanatory note', function () use ($admin, &$created) {
    $no = $created['combined'];
    adminAction($admin, $no, 'verify_payment', ['reference' => '999988887777', 'amount' => '1000']);
    eq('pending', booking($no)['payment_status'], 'blocked without note');
});
test('Admin rejects a payment → devotee can resubmit', function () use ($dev, $admin, &$created) {
    $no = $created['food'];
    $t = $dev->csrf("/b/$no/pay");
    $dev->post("/b/$no/payment", ['_csrf' => $t, 'reference' => '111122223333']);
    adminAction($admin, $no, 'reject_payment', ['reason' => 'No credit found']);
    eq('failed', booking($no)['payment_status'], 'failed');
    $t = $dev->csrf("/b/$no/pay");
    $dev->post("/b/$no/payment", ['_csrf' => $t, 'reference' => '111122224444']);
    eq('awaiting_verification', booking($no)['payment_status'], 'resubmitted');
    adminAction($admin, $no, 'verify_payment', ['payment_id' => (int) q1("SELECT MAX(id) FROM payments WHERE reference = '111122224444'"), 'reference' => '111122224444', 'amount' => '700']);
    eq('confirmed', booking($no)['booking_status'], 'food-only confirmed after verification');
});
test('Ceremony needing approval: paid ≠ confirmed until approved', function () use ($dev, $admin, $svc, $slot, $d) {
    $s = $svc['brata-ghara'];
    $r = book($dev, ['name' => 'Prakash Mishra', 'mobile' => '9886001122', 'services' => [$s], 'service_date' => $d(20), 'slot' => [$s => $slot[$s]],
        'participants' => [$s => '40'], 'f' => [$s => ['batu_name' => 'Aditya', 'father_name' => 'Prakash Mishra']]]);
    $no = (string) $r['no'];
    eq('pending', booking($no)['approval_status'], 'approval pending');
    adminAction($admin, $no, 'verify_payment', ['reference' => '555566667777', 'amount' => '2101']);
    eq(['paid', 'pending'], [booking($no)['payment_status'], booking($no)['booking_status']], 'paid but not confirmed');
    adminAction($admin, $no, 'approve', ['note' => 'Arrive by 6:30 AM']);
    eq('confirmed', booking($no)['booking_status'], 'confirmed after approval');
});
test('Reschedule moves date and slot, checks capacity, is audited', function () use ($admin, $svc, $slot, $d, &$created) {
    $no = $created['multi'];
    $items = q("SELECT id, service_id FROM booking_items WHERE booking_id = ? AND item_type = 'service'", [booking($no)['id']]);
    $slots = [];
    foreach ($items as $it) {
        $slots[$it['id']] = (int) q1('SELECT MAX(id) FROM time_slots WHERE service_id = ?', [$it['service_id']]);
    }
    $r = adminAction($admin, $no, 'reschedule', ['date' => $d(15), 'slots' => $slots, 'note' => 'Priest request']);
    eq(303, $r['status'], 'redirect');
    eq($d(15), booking($no)['service_date'], 'date moved');
    ok((int) q1("SELECT COUNT(*) FROM audit_log WHERE action = 'booking.rescheduled' AND booking_id = ?", [booking($no)['id']]) === 1, 'audited');
    // a full slot needs override
    $s = $svc['shraddha-karma'];
    $full = q1('SELECT b.booking_no FROM bookings b JOIN booking_items i ON i.booking_id = b.id WHERE i.service_id = ? LIMIT 1', [$s]);
    $r2 = book(new Client($GLOBALS['base']), ['name' => 'Gita Rout', 'mobile' => '9845011111', 'services' => [$s], 'service_date' => $d(13), 'slot' => [$s => $slot[$s]], 'participants' => [$s => '2'], 'f' => [$s => ['departed_name' => 'Late Rout']]]);
    $iid = (int) q1("SELECT id FROM booking_items WHERE booking_id = ? AND item_type = 'service'", [booking((string) $r2['no'])['id']]);
    adminAction($admin, (string) $r2['no'], 'reschedule', ['date' => $d(12), 'slots' => [$iid => $slot[$s]]]);
    eq($d(13), booking((string) $r2['no'])['service_date'], 'blocked by full slot');
    adminAction($admin, (string) $r2['no'], 'reschedule', ['date' => $d(12), 'slots' => [$iid => $slot[$s]], 'override' => '1']);
    eq($d(12), booking((string) $r2['no'])['service_date'], 'override works');
});
test('Cancel then refund a paid booking', function () use ($admin, &$created) {
    $no = $created['single'];
    adminAction($admin, $no, 'refund', ['reference' => 'RFND1']);
    eq('paid', booking($no)['payment_status'], 'cannot refund an active booking');
    adminAction($admin, $no, 'cancel', ['reason' => 'Devotee travelling']);
    eq('cancelled', booking($no)['booking_status'], 'cancelled');
    adminAction($admin, $no, 'refund', ['reference' => 'RFND1']);
    eq('refunded', booking($no)['payment_status'], 'refunded');
});
test('Other Temple Services: free request, admin sets charges, devotee then pays', function () use ($dev, $admin, $svc, $slot, $d) {
    $s = $svc['other-services'];
    $r = book($dev, ['name' => 'Meera Pattnaik', 'mobile' => '9845022222', 'services' => [$s], 'service_date' => $d(7), 'slot' => [$s => $slot[$s]],
        'participants' => [$s => '10'], 'f' => [$s => ['service_request' => 'Annadana for 100 people']]]);
    $no = (string) $r['no'];
    eq('not_required', booking($no)['payment_status'], 'no payment due initially');
    eq(303, $r['confirm']['status'], 'created');
    ok(!str_contains((string) $r['confirm']['location'], '/pay'), 'not sent to payment page');
    adminAction($admin, $no, 'adjust', ['label' => 'Annadana charges', 'amount' => '5000']);
    $b = booking($no);
    eq([500000, 'pending'], [(int) $b['total_paise'], $b['payment_status']], 'total and status updated');
});
test('Cancelled bookings free their slot', function () use ($admin, $svc, $slot, $d) {
    $s = $svc['shraddha-karma'];
    $f = fn (string $m) => ['name' => 'Hari Sahoo', 'mobile' => $m, 'services' => [$s], 'service_date' => $d(30), 'slot' => [$s => $slot[$s]], 'participants' => [$s => '2'], 'f' => [$s => ['departed_name' => 'Late Sahoo']]];
    $nos = [];
    foreach (['9845033331', '9845033332', '9845033333'] as $m) {
        $nos[] = book(new Client($GLOBALS['base']), $f($m))['no'];
    }
    eq(422, book(new Client($GLOBALS['base']), $f('9845033334'), false)['review']['status'], 'slot full');
    adminAction($admin, (string) $nos[0], 'cancel', ['reason' => 'Devotee cancelled']);
    $r = book(new Client($GLOBALS['base']), $f('9845033334'));
    eq(303, $r['confirm']['status'] ?? 0, 'place available again after cancellation');
});

echo "\nWhatsApp notifications\n";
test('Not configured: booking saved, failure logged for follow-up', function () use ($special) {
    $before = (int) q1("SELECT COUNT(*) FROM notifications WHERE status = 'failed'");
    $r = book(new Client($GLOBALS['base']), $special());
    ok(!empty(booking((string) $r['no'])), 'booking saved');
    ok((int) q1("SELECT COUNT(*) FROM notifications WHERE status = 'failed'") >= $before + 2, 'devotee + admin failures logged');
});
test('Enabled: devotee + admin receive template messages; status "sent"', function () use ($admin, $special, $tmp) {
    $t = $admin->csrf('/admin/settings');
    $admin->post('/admin/settings', ['_csrf' => $t, 'section' => 'whatsapp', 'whatsapp_enabled' => '1', 'admin_whatsapp_numbers' => '98450 11111, +91 98450 22222',
        'whatsapp_language' => 'en', 'wa_tpl_devotee_ack' => 'sjt_booking_received', 'wa_tpl_devotee_update' => 'sjt_booking_update', 'wa_tpl_admin_new' => 'sjt_admin_new_booking', 'wa_tpl_admin_payment' => 'sjt_admin_payment_submitted']);
    eq('+919845011111, +919845022222', q1("SELECT svalue FROM settings WHERE skey = 'admin_whatsapp_numbers'"), 'numbers normalised');
    @unlink("$tmp/mock.log");
    $r = book(new Client($GLOBALS['base']), $special());
    $b = booking((string) $r['no']);
    $n = q('SELECT * FROM notifications WHERE booking_id = ? ORDER BY id', [$b['id']]);
    eq(3, count($n), '1 devotee + 2 admin messages');
    foreach ($n as $row) {
        eq('sent', $row['status'], 'sent ' . $row['recipient']);
        ok(str_starts_with((string) $row['provider_message_id'], 'wamid.'), 'provider id stored');
    }
    $calls = array_map(fn ($l) => json_decode($l, true), file("$tmp/mock.log", FILE_IGNORE_NEW_LINES));
    $dev = array_values(array_filter($calls, fn ($c) => $c['body']['template']['name'] === 'sjt_booking_received'))[0];
    $params = array_column($dev['body']['template']['components'][0]['parameters'], 'text');
    eq($b['booking_no'], $params[1], 'booking number in message');
    ok(str_contains($params[7], '/b/' . $b['booking_no'] . '?t='), 'secure booking link in message');
    ok($dev['auth'], 'bearer token sent server-side');
    $adm = array_values(array_filter($calls, fn ($c) => $c['body']['template']['name'] === 'sjt_admin_new_booking'))[0];
    ok(str_contains(end($adm['body']['template']['components'][0]['parameters'])['text'], '/admin/bookings/' . $b['booking_no']), 'admin dashboard link');
});
test('Provider rejection: booking still saved, error recorded, no retry for permanent errors', function () use ($special) {
    $r = book(new Client($GLOBALS['base']), $special(['mobile' => '9845010000']));
    $b = booking((string) $r['no']);
    ok(!empty($b), 'booking saved');
    $n = q("SELECT * FROM notifications WHERE booking_id = ? AND audience = 'devotee'", [$b['id']])[0];
    eq('failed', $n['status'], 'failed');
    ok(str_contains((string) $n['error'], 'HTTP 400'), 'error text kept');
    eq(null, $n['next_attempt_at'], 'permanent error not retried');
});
test('Temporary provider failure is scheduled for automatic retry', function () use ($special, $root) {
    $r = book(new Client($GLOBALS['base']), $special(['mobile' => '9845015555']));
    $n = q("SELECT * FROM notifications WHERE booking_id = ? AND audience = 'devotee'", [booking((string) $r['no'])['id']])[0];
    eq('failed', $n['status'], 'failed');
    ok($n['next_attempt_at'] !== null, 'retry scheduled');
    q("UPDATE notifications SET next_attempt_at = '2000-01-01 00:00:00' WHERE id = ?", [$n['id']]);
    sh('php ' . escapeshellarg("$root/bin/console.php") . ' notifications:retry');
    eq(2, (int) q1('SELECT attempts FROM notifications WHERE id = ?', [$n['id']]), 'cron retried');
});
test('Status-change messages are sent (payment verified)', function () use ($admin, $special) {
    $r = book(new Client($GLOBALS['base']), $special());
    adminAction($admin, (string) $r['no'], 'verify_payment', ['reference' => '777788889999', 'amount' => '501']);
    $n = q("SELECT * FROM notifications WHERE booking_id = ? AND event = 'booking_update'", [booking((string) $r['no'])['id']]);
    eq(1, count($n), 'update queued');
    eq('sent', $n[0]['status'], 'sent');
    ok(str_contains($n[0]['params_json'], 'has been verified'), 'message text');
});
test('Delivery webhook: valid signature updates status; invalid is refused', function () use ($dev) {
    $pid = (string) q1("SELECT provider_message_id FROM notifications WHERE status = 'sent' LIMIT 1");
    $body = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['id' => $pid, 'status' => 'delivered']]]]]]]]);
    $bad = $dev->req('POST', '/webhooks/whatsapp', [], ['Content-Type: application/json', 'X-Hub-Signature-256: sha256=deadbeef'], $body);
    eq(401, $bad['status'], 'bad signature refused');
    $good = $dev->req('POST', '/webhooks/whatsapp', [], ['Content-Type: application/json', 'X-Hub-Signature-256: sha256=' . hash_hmac('sha256', $body, 'app-secret')], $body);
    eq(200, $good['status'], 'accepted');
    eq('delivered', q1('SELECT status FROM notifications WHERE provider_message_id = ?', [$pid]), 'now delivered');
    $v = $dev->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345');
    eq('12345', $v['body'], 'verification handshake');
});

echo "\nSecurity\n";
test('Dashboard pages require login', function () use ($base) {
    $anon = new Client($base);
    foreach (['/admin', '/admin/bookings', '/admin/bookings/export.csv', '/admin/settings', '/admin/reports/export.csv'] as $p) {
        $r = $anon->get($p);
        ok($r['status'] === 303 && str_contains((string) $r['location'], '/admin/login'), "$p redirects to login (got {$r['status']})");
    }
    $t = $anon->csrf('/admin/login');
    $r = $anon->post('/admin/bookings/SJT-2026-000001/action', ['_csrf' => $t, 'action' => 'cancel', 'reason' => 'x']);
    eq(303, $r['status'], 'POST action blocked');
});
test('Staff cannot open senior-admin pages', function () use ($base) {
    $s = adminClient($base, 'staff', 'Staff-Pass-2026');
    eq(200, $s->get('/admin/bookings')['status'], 'staff sees bookings');
    eq(403, $s->get('/admin/settings')['status'], 'settings forbidden');
    eq(403, $s->get('/admin/services')['status'], 'prices forbidden');
});
test('Wrong passwords lock the account after 5 attempts', function () use ($base) {
    $c = new Client($base);
    for ($i = 0; $i < 5; $i++) {
        $t = $c->csrf('/admin/login');
        $c->post('/admin/login', ['_csrf' => $t, 'username' => 'staff', 'password' => 'wrong-pass-' . $i]);
    }
    $t = $c->csrf('/admin/login');
    $r = $c->post('/admin/login', ['_csrf' => $t, 'username' => 'staff', 'password' => 'Staff-Pass-2026']);
    eq(401, $r['status'], 'correct password refused while locked');
    ok(str_contains($r['body'], 'temporarily locked'), 'lock message');
});
test('Forms without a valid CSRF token are refused', function () use ($base, $special) {
    $c = new Client($base);
    $c->get('/book');
    eq(419, $c->post('/book/review', ['_csrf' => 'forged'] + $special())['status'], 'review');
    eq(419, $c->post('/admin/login', ['username' => 'admin', 'password' => 'x'])['status'], 'login');
});
test('Bookings cannot be opened without the private link or lookup', function () use ($base, &$created) {
    $c = new Client($base);
    $no = $created['combined'];
    eq(404, $c->get("/b/$no")['status'], 'no token');
    eq(404, $c->get("/b/$no?t=" . str_repeat('a', 32))['status'], 'wrong token');
    eq(404, $c->get("/b/$no/invoice.pdf")['status'], 'invoice protected');
    $t = $c->csrf('/lookup');
    eq(404, $c->post('/lookup', ['_csrf' => $t, 'booking_no' => $no, 'mobile' => '9000000000'])['status'], 'wrong mobile');
    $r = $c->post('/lookup', ['_csrf' => $t, 'booking_no' => strtolower($no), 'mobile' => '+91 98450 12345']);
    eq(303, $r['status'], 'correct lookup');
    eq(200, $c->get("/b/$no")['status'], 'now visible');
});
test('Script injection is rejected in names and escaped everywhere else', function () use ($dev, $special, $admin) {
    $r = book($dev, $special(['name' => '<script>alert(1)</script>']), false);
    eq(422, $r['review']['status'], 'name rejected');
    $r = book($dev, $special(['instructions' => '<img src=x onerror=alert(1)>', 'f' => [array_key_first($special()['f']) => ['sankalpa_names' => '"><script>x()</script>']]]));
    $no = (string) $r['no'];
    $page = $dev->get("/b/$no")['body'] . $admin->get("/admin/bookings/$no")['body'] . $dev->get("/b/$no/invoice")['body'];
    ok(!str_contains($page, '<script>x()') && !str_contains($page, '<img src=x'), 'no raw HTML in output');
    ok(str_contains($page, '&lt;script&gt;x()'), 'escaped');
});
test('SQL-injection style input is treated as plain text', function () use ($admin, $dev) {
    $r = $admin->get('/admin/bookings?q=' . urlencode("' OR 1=1 --") . '&payment_status=' . urlencode("paid' OR '1'='1"));
    eq(200, $r['status'], 'search works');
    ok(str_contains($r['body'], 'No bookings match'), 'no rows leaked');
    $t = $dev->csrf('/lookup');
    eq(404, $dev->post('/lookup', ['_csrf' => $t, 'booking_no' => "' OR '1'='1", 'mobile' => '9845012345'])['status'], 'lookup safe');
    ok((int) q1('SELECT COUNT(*) FROM bookings') > 10, 'tables intact');
});
test('Array / malformed parameters do not crash the app', function () use ($dev) {
    $t = $dev->csrf('/book');
    $r = $dev->post('/book/review', ['_csrf' => $t, 'name' => ['x'], 'services' => 'notarray', 'food' => 'x', 'slot' => [[1]]]);
    eq(422, $r['status'], 'validation error, not a crash');
    eq(400, $dev->get('/api/availability?date=../../etc')['status'], 'bad date');
    eq(404, $dev->get('/b/..%2F..%2Fetc%2Fpasswd')['status'], 'path traversal');
});
test('Booking lookup is rate limited', function () use ($base) {
    $c = new Client($base);
    $t = $c->csrf('/lookup');
    $codes = [];
    for ($i = 0; $i < 17; $i++) {
        $codes[] = $c->post('/lookup', ['_csrf' => $t, 'booking_no' => 'SJT-2026-99999' . ($i % 10), 'mobile' => '9845012345'])['status'];
    }
    ok(in_array(429, $codes, true), 'got 429 after repeated attempts');
});
test('Security headers are sent', function () use ($dev) {
    $h = $dev->get('/')['headers'];
    ok(str_contains($h['content-security-policy'][0] ?? '', "default-src 'self'"), 'CSP');
    eq('nosniff', $h['x-content-type-options'][0] ?? null, 'nosniff');
    ok(str_contains(implode(';', $h['set-cookie'] ?? ['HttpOnly']), 'HttpOnly'), 'HttpOnly session cookie');
});

echo "\nAdmin reporting & configuration\n";
test('CSV exports (bookings, items, payments) download with correct totals', function () use ($admin) {
    $r = $admin->get('/admin/bookings/export.csv');
    eq(200, $r['status'], 'bookings csv');
    ok(str_starts_with($r['body'], "\xEF\xBB\xBF") && str_contains(strtok($r['body'], "\n"), 'Booking no'), 'BOM + header');
    eq((int) q1('SELECT COUNT(*) FROM bookings') + 1, count(array_filter(explode("\n", trim($r['body'])))), 'one row per booking');
    foreach (['items', 'payments', 'summary'] as $type) {
        eq(200, $admin->get('/admin/reports/export.csv?type=' . $type . '&from=' . date('Y-m-01') . '&to=' . date('Y-m-d', strtotime('+1 day')))['status'], "$type csv");
    }
});
test('Daily / weekly / monthly summaries match verified payments', function () use ($admin) {
    $verified = (int) q1("SELECT SUM(amount_paise) FROM payments WHERE status IN ('verified','refunded')");
    $r = $admin->get('/admin/reports?group=month&from=' . date('Y-m-01') . '&to=' . date('Y-m-d'));
    ok(str_contains($r['body'], money($verified)), 'verified revenue ' . money($verified) . ' shown');
    eq(200, $admin->get('/admin/reports?group=week')['status'], 'weekly');
    eq(200, $admin->get('/admin')['status'], 'dashboard');
});
test('Admin changes prices; new bookings use them, old bookings keep theirs', function () use ($admin, $special, $svc) {
    $old = (string) q1("SELECT booking_no FROM bookings WHERE total_paise = 50100 LIMIT 1");
    $t = $admin->csrf('/admin/services/' . $svc['special-puja']);
    $admin->post('/admin/services/save', ['_csrf' => $t, 'id' => $svc['special-puja'], 'name' => 'Special Puja', 'price' => '551', 'is_active' => '1', 'requires_slot' => '1',
        'collect_participants' => '1', 'max_participants' => '20', 'allow_food' => '1', 'is_combinable' => '1',
        'field_key' => ['sankalpa_names'], 'field_label' => ['Name(s) for sankalpa'], 'field_type' => ['text'], 'field_required' => ['1']]);
    eq(55100, (int) q1('SELECT price_paise FROM services WHERE id = ?', [$svc['special-puja']]), 'price saved');
    $r = book(new Client($GLOBALS['base']), $special());
    eq(55100, (int) booking((string) $r['no'])['total_paise'], 'new price applied');
    eq(50100, (int) booking($old)['total_paise'], 'existing booking unchanged');
    ok((int) q1("SELECT COUNT(*) FROM audit_log WHERE action = 'service.updated'") >= 1, 'price change audited');
});
test('Admin changes food coupon price', function () use ($admin, $c150) {
    $t = $admin->csrf('/admin/coupons');
    $admin->post('/admin/coupons/save', ['_csrf' => $t, 'id' => $c150, 'name' => 'Food Coupon (Standard)', 'price' => '160', 'max_per_booking' => '200', 'is_active' => '1']);
    eq(16000, (int) q1('SELECT price_paise FROM booking_coupons WHERE id = ?', [$c150]), 'coupon price saved');
});
test('Disabled services disappear from booking and are refused', function () use ($admin, $dev, $svc, $special) {
    q('UPDATE services SET is_active = 0 WHERE id = ?', [$svc['vehicle-puja']]);
    ok(!str_contains($dev->get('/book')['body'], 'name="services[]" value="' . $svc['vehicle-puja'] . '"'), 'not listed');
    $f = $special();
    $f['services'] = [$svc['vehicle-puja']];
    eq(422, book($dev, $f, false)['review']['status'], 'refused');
    q('UPDATE services SET is_active = 1 WHERE id = ?', [$svc['vehicle-puja']]);
});
test('UPI QR only appears after payment details are verified', function () use ($admin, $dev, &$created) {
    $no = $created['combined'];
    setSetting('upi_verified', '0');
    ok(str_contains($dev->get("/b/$no/pay")['body'], 'Online payment is being set up'), 'fallback message before verification');
    $t = $admin->csrf('/admin/settings');
    $admin->post('/admin/settings', ['_csrf' => $t, 'section' => 'payment', 'upi_qr_mode' => 'dynamic', 'upi_vpa' => 'sjtemple@sbi', 'upi_payee_name' => 'SJT Bengaluru', 'upi_verified' => '1', 'reverify' => '1', 'payment_instructions' => 'Pay']);
    $p = $dev->get("/b/$no/pay");
    ok(str_contains($p['body'], 'upi://pay?pa=sjtemple@sbi') && str_contains($p['body'], 'am=1601.00') && str_contains($p['body'], 'tn=' . $no), 'dynamic QR link with amount + booking no');
    $png = $dev->get("/b/$no/qr.png");
    ok(str_starts_with($png['body'], "\x89PNG"), 'QR PNG download');
    $t = $admin->csrf('/admin/settings');
    $admin->post('/admin/settings', ['_csrf' => $t, 'section' => 'payment', 'upi_qr_mode' => 'dynamic', 'upi_vpa' => 'someone@okaxis', 'upi_payee_name' => 'X', 'upi_verified' => '1', 'payment_instructions' => 'Pay']);
    eq('0', q1("SELECT svalue FROM settings WHERE skey = 'upi_verified'"), 'changing the UPI ID resets verification');
});
test('Data persists: bookings, items, payments and audit are in the database', function () {
    ok((int) q1('SELECT COUNT(*) FROM bookings') >= 30, 'bookings stored');
    eq(0, (int) q1('SELECT COUNT(*) FROM bookings b WHERE total_paise <> subtotal_paise + additional_paise'), 'every total = subtotal + additional');
    eq(0, (int) q1('SELECT COUNT(*) FROM bookings b WHERE subtotal_paise <> (SELECT SUM(amount_paise) FROM booking_items i WHERE i.booking_id = b.id)'), 'every subtotal = sum of items');
    eq(0, (int) q1('SELECT COUNT(*) FROM booking_items WHERE amount_paise <> unit_price_paise * quantity'), 'every line = rate × qty');
    eq(0, (int) q1("SELECT COUNT(*) FROM bookings WHERE booking_status = 'confirmed' AND payment_status NOT IN ('paid','not_required')"), 'no confirmed booking without verified payment');
});

// ------------------------------------------------------------------ report
$pass = count(array_filter($results, fn ($r) => $r[1]));
$total = count($results);
echo "\n$pass / $total passed\n";
$dbLabel = $driver === 'mysql' ? 'MySQL-protocol server ' . $pdo->query('SELECT VERSION()')->fetchColumn() : 'SQLite ' . $pdo->query('SELECT sqlite_version()')->fetchColumn();
$md = "# Automated test results ($driver)\n\nRun: " . date('Y-m-d H:i T') . " · PHP " . PHP_VERSION . " · $dbLabel · mock WhatsApp Cloud API\n\n**$pass of $total tests passed.**\n\n| # | Test | Result |\n|---|------|--------|\n";
foreach ($results as $i => [$n, $okk, $msg]) {
    $md .= '| ' . ($i + 1) . ' | ' . str_replace('|', '\\|', $n) . ' | ' . ($okk ? 'PASS' : 'FAIL: ' . str_replace('|', '\\|', $msg)) . " |\n";
}
file_put_contents($root . '/docs/TEST-RESULTS-' . strtoupper($driver) . '.md', $md);
exit($pass === $total ? 0 : 1);
