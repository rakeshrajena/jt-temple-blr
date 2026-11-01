<?php
declare(strict_types=1);

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

$blank = invoice_filters([]);
check($blank === ['q' => '', 'status' => '', 'period' => '', 'from' => '', 'to' => '', 'receipt' => ''], 'no filters by default');
check(!invoice_filters_active($blank), 'default filters are not active');
$clean = invoice_filters(['q' => '  Sujata  ', 'status' => 'paid', 'period' => 'September 2026', 'from' => '2026-09-01', 'to' => '2026-09-30', 'receipt' => 'without']);
check($clean === ['q' => 'Sujata', 'status' => 'Paid', 'period' => 'September 2026', 'from' => '2026-09-01', 'to' => '2026-09-30', 'receipt' => 'without'], 'filters are trimmed and the status takes the stored spelling');
check(invoice_filters_active($clean), 'chosen filters are active');
$bad = invoice_filters(['status' => 'Deleted', 'from' => '2026-02-30', 'to' => 'soon', 'receipt' => 'maybe', 'q' => ['array'], 'period' => str_repeat('x', 80)]);
check($bad['status'] === '' && $bad['from'] === '' && $bad['to'] === '' && $bad['receipt'] === '' && $bad['q'] === '', 'unknown status, impossible dates, and odd values are ignored');
check(mb_strlen($bad['period']) <= 30, 'a period filter is kept short');
$swapped = invoice_filters(['from' => '2026-09-30', 'to' => '2026-09-01']);
check($swapped['from'] === '2026-09-01' && $swapped['to'] === '2026-09-30', 'a reversed date range is put in order');

$mobileA = '6' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
$mobileB = '6' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
$pdo = db();
$pdo->beginTransaction();
try {
    $base = ['plan_name' => 'Annadan Seva', 'plan_amount' => '501', 'frequency' => 'Monthly', 'status' => 'Active'];
    $a = add_subscriber(subscriber_input($base + ['name' => 'Filter Alpha Zq', 'mobile' => $mobileA])['values']);
    $b = add_subscriber(subscriber_input($base + ['name' => 'Filter Beta Zq', 'mobile' => $mobileB])['values']);
    $insert = static function (int $sub, string $number, string $period, string $due, string $status): int {
        return db_exec(
            'INSERT INTO subscription_invoices (subscriber_id, invoice_number, amount, period_label, due_date, status, payment_token) VALUES (?,?,?,?,?,?,?)',
            [$sub, $number, 501, $period, $due, $status, random_token()]
        );
    };
    $tag = strtoupper(bin2hex(random_bytes(3)));
    $insert($a, "INV-ZQ{$tag}-1", 'July 2026', '2026-07-10', 'Paid');
    $insert($a, "INV-ZQ{$tag}-2", 'August 2026', '2026-08-10', 'Pending');
    $insert($b, "INV-ZQ{$tag}-3", 'August 2026', '2026-08-12', 'Sent');

    $numbers = static function (array $filters): array {
        $rows = invoice_list(invoice_filters($filters + ['q' => 'Zq']));
        $found = array_map(static fn (array $r): string => (string) $r['invoice_number'], $rows);
        sort($found);
        return $found;
    };
    check($numbers([]) === ["INV-ZQ{$tag}-1", "INV-ZQ{$tag}-2", "INV-ZQ{$tag}-3"], 'searching a name finds all of that name’s invoices');
    check($numbers(['q' => $mobileB]) === ["INV-ZQ{$tag}-3"], 'searching a phone number finds that subscriber');
    check($numbers(['q' => "INV-ZQ{$tag}-2"]) === ["INV-ZQ{$tag}-2"], 'searching an invoice number finds that invoice');
    check($numbers(['status' => 'Paid']) === ["INV-ZQ{$tag}-1"], 'status filter keeps only that status');
    check($numbers(['period' => 'August 2026']) === ["INV-ZQ{$tag}-2", "INV-ZQ{$tag}-3"], 'period filter keeps only that period');
    check($numbers(['from' => '2026-08-11']) === ["INV-ZQ{$tag}-3"], 'from date keeps invoices due on or after it');
    check($numbers(['to' => '2026-08-10']) === ["INV-ZQ{$tag}-1", "INV-ZQ{$tag}-2"], 'to date keeps invoices due on or before it');
    check($numbers(['receipt' => 'without', 'status' => 'Paid']) === ["INV-ZQ{$tag}-1"], 'a paid invoice without a receipt can be found');
    check($numbers(['receipt' => 'with']) === [], 'no invoice has a receipt yet');
    check($numbers(['q' => "50%' OR '1'='1"]) === [], 'search text is treated as text, not SQL');
    check(in_array('August 2026', invoice_periods(), true), 'the period list offers stored periods');
} finally {
    $pdo->rollBack();
}

echo $failed === 0 ? "passed\n" : "failed {$failed}\n";
exit($failed === 0 ? 0 : 1);
