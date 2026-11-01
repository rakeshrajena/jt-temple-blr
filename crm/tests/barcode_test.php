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

$patterns = code128_patterns();
check(count($patterns) === 107, 'Code 128 has a pattern for every symbol');
$widthsOk = true;
foreach ($patterns as $index => $pattern) {
    $sum = 0;
    $length = strlen($pattern);
    for ($i = 0; $i < $length; $i++) {
        $sum += (int) $pattern[$i];
    }
    if ($sum !== ($index === 106 ? 13 : 11)) {
        $widthsOk = false;
    }
}
check($widthsOk, 'every Code 128 pattern has the right width');

$symbol = code128_modules('A');
$expected = str_repeat('0', 10)
    . code128_expand('211214')
    . code128_expand('111323')
    . code128_expand('131123')
    . code128_expand('2331112')
    . str_repeat('0', 10);
check($symbol === $expected, 'the letter A encodes as Code 128 set B');
check(code128_modules('CU-1758920820-0001') !== '', 'a coupon serial can be encoded');
check(code128_modules("bad\ncode") === '', 'a serial with a line break is not encoded');

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all barcode tests passed\n";
