<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

function check(bool $ok, string $label): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL  $label\n");
        exit(1);
    }
    fwrite(STDOUT, "ok  $label\n");
}

$defaults = report_default_last_month();
check(preg_match('/^\d{4}-\d{2}-\d{2}$/', $defaults['start']) === 1, 'default start is a date');
check(preg_match('/^\d{4}-\d{2}-\d{2}$/', $defaults['end']) === 1, 'default end is a date');
check($defaults['start'] < $defaults['end'], 'default range spans about one month');

$period = report_resolve_period(null, null, true);
check($period === $defaults, 'first visit uses the default last-month range');

$cleared = report_resolve_period('', '', false);
check($cleared['start'] === null && $cleared['end'] === null, 'cleared filters show all dates');

$csv = report_build_csv(['Date', 'Amount'], [['2025-01-01', '100.00']]);
check(str_starts_with($csv, "\xEF\xBB\xBF"), 'csv includes a utf-8 bom for excel');
check(str_contains($csv, '2025-01-01'), 'csv includes row data');

$filename = report_export_filename('donations', 'csv', '2025-10-01', '2025-11-01');
check($filename === 'donations_2025-10-01_2025-11-01.csv', 'export filename includes the chosen dates');

$table = report_export_table('donations', []);
check($table['headers'] !== [] && $table['rows'] === [], 'an empty donations export still has headers');

$sample = [
    ['donor_name' => 'Rama', 'donation_type' => 'Cash', 'purpose' => 'Annadan', 'payment_mode' => 'UPI', 'amount' => 100],
    ['donor_name' => 'Sita', 'donation_type' => 'Kind', 'purpose' => 'Festival', 'payment_mode' => 'Cash', 'amount' => 50],
];
$filtered = report_filter_data('donations', $sample, ['q' => 'rama', 'dtype' => '', 'pay' => '']);
check(count($filtered) === 1, 'search text filters donation rows');
$filtered = report_filter_data('donations', $sample, ['q' => '', 'dtype' => 'Kind', 'pay' => '']);
check(count($filtered) === 1 && $filtered[0]['donor_name'] === 'Sita', 'gift type filter keeps matching rows');

$xml = report_build_excel_xml(['Amount'], [['100.00']]);
check(str_contains($xml, '<?mso-application progid="Excel.Sheet"?>'), 'excel xml fallback is spreadsheetml');
$payload = report_excel_download_payload(['A'], [['1']], 'donations_test.xlsx');
check($payload['filename'] !== '' && ($payload['body'] !== null || $payload['path'] !== null), 'excel export always returns a download payload');

fwrite(STDOUT, "all report export tests passed\n");