<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();
$admin = $pdo->query("SELECT password_hash FROM users WHERE username = 'admin'")->fetch();
if ($admin === false || !password_verify('temple@123', (string) $admin['password_hash'])) {
    fwrite(STDERR, "password fail\n");
    exit(1);
}

$summary = dashboard_summary($pdo);
if ($summary['donor_count'] < 1 || $summary['inventory_count'] < 1) {
    fwrite(STDERR, "seed incomplete\n");
    exit(1);
}

$csv = APP_ROOT . '/storage/uploads/smoke.csv';
$date = (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');
file_put_contents($csv, "Date,Description,Amount,Type\n{$date},Test credit,1500,Credit\n");
$rows = parse_statement_file($csv);
unlink($csv);
if (count($rows) !== 1 || $rows[0]['txn_type'] !== 'Credit' || abs($rows[0]['amount'] - 1500) > 0.001) {
    fwrite(STDERR, "csv parse fail\n");
    exit(1);
}

$donation = $pdo->query('SELECT * FROM donations WHERE amount IS NOT NULL ORDER BY id LIMIT 1')->fetch();
$donor = $pdo->prepare('SELECT * FROM donors WHERE id = ?');
$donor->execute([(int) $donation['donor_id']]);
$donorRow = $donor->fetch();
if ($donorRow === false) {
    fwrite(STDERR, "donor missing\n");
    exit(1);
}
$path = generate_receipt_pdf($donation, $donorRow, 'RCPT-TEST-0001');
$pdf = (string) file_get_contents($path);
unlink($path);
if (!str_starts_with($pdf, '%PDF') || strlen($pdf) < 500) {
    fwrite(STDERR, "pdf fail\n");
    exit(1);
}

$coupon = generate_coupon_batch_pdf(999001, 'Lunch Mahaprasad', 50, 1, 8);
$couponPdf = (string) file_get_contents($coupon);
unlink($coupon);
if (!str_starts_with($couponPdf, '%PDF')) {
    fwrite(STDERR, "coupon pdf fail\n");
    exit(1);
}

echo 'smoke ok donations=' . $summary['total_donations'] . ' low_stock=' . $summary['low_stock'] . PHP_EOL;
