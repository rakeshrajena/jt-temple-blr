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

check(qr_gf_mul(0x80, 2) === 0x1D, 'the QR field doubles a high byte');
check(qr_format_bits(0) === 0x5412, 'error correction M with mask 0 has the standard format bits');

$data = [0x40, 0x14, 0x54];
$code = array_merge($data, qr_ecc($data, 10));
[$exp] = qr_gf();
$syndromeClear = true;
for ($i = 0; $i < 10; $i++) {
    $syndrome = 0;
    foreach ($code as $coef) {
        $syndrome = qr_gf_mul($syndrome, $exp[$i]) ^ $coef;
    }
    if ($syndrome !== 0) {
        $syndromeClear = false;
    }
}
check($syndromeClear, 'the QR error correction checks out');
$code[0] ^= 1;
$detects = false;
for ($i = 0; $i < 10; $i++) {
    $syndrome = 0;
    foreach ($code as $coef) {
        $syndrome = qr_gf_mul($syndrome, $exp[$i]) ^ $coef;
    }
    if ($syndrome !== 0) {
        $detects = true;
    }
}
check($detects, 'a changed QR module is detected');

$serial = 'CU-1758920820-0007';
$matrix = qr_matrix($serial);
$size = count($matrix);
check($size === 25 && strlen($matrix[0]) === 25, 'a coupon serial fits in a version 2 QR code');
check(qr_finder_ok($matrix, 0, 0) && qr_finder_ok($matrix, 0, $size - 7) && qr_finder_ok($matrix, $size - 7, 0), 'the QR code has three finder squares');
check($matrix[6][8] === '1' && $matrix[6][9] === '0' && $matrix[8][6] === '1', 'the QR timing pattern alternates');
check($matrix[$size - 8][8] === '1', 'the QR code keeps the dark module');

$format = 0;
$first = [
    [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
    [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
];
foreach ($first as $i => [$row, $col]) {
    $format |= (int) $matrix[$row][$col] << $i;
}
$second = 0;
for ($i = 0; $i < 8; $i++) {
    $second |= (int) $matrix[8][$size - 1 - $i] << $i;
}
for ($i = 8; $i < 15; $i++) {
    $second |= (int) $matrix[$size - 15 + $i][8] << $i;
}
$known = false;
for ($mask = 0; $mask < 8; $mask++) {
    if ($format === qr_format_bits($mask)) {
        $known = true;
    }
}
check($known && $format === $second, 'both QR format copies name the same mask');
check(qr_version_bits(7) === 0x07C94, 'version 7 uses the standard QR version bits');
check(qr_matrix('') === [], 'an empty serial is not encoded');
$longer = qr_matrix(str_repeat('A', 43));
check(count($longer) === 33 && strlen($longer[0]) === 33, 'a 43-character link fits in a version 4 QR code');
$link = 'https://temple.example/index.php?r=coupons/scan&code=CU-1758920820-0007';
$linked = qr_matrix($link);
check(count($linked) >= 37 && count($linked) === strlen($linked[0]), 'a coupon scan link fits in one QR code');
check(qr_matrix(str_repeat('A', 181)) === [], 'a link longer than version 9 is refused');

$path = APP_ROOT . '/storage/coupons/batch_987653.pdf';
if (is_file($path)) {
    check(false, 'the QR sample does not replace a real coupon file');
} else {
    generate_coupon_batch_pdf(987653, 'Mahaprasad', 50, 7, 1, 1758920820);
    $pdf = (string) file_get_contents($path);
    check(str_contains($pdf, $serial), 'the coupon sheet prints the serial beside the QR code');
    check(substr_count($pdf, ' re f') > 80, 'the coupon sheet draws the QR code as a grid');
    unlink($path);
}

function qr_finder_ok(array $matrix, int $row, int $col): bool
{
    if (($matrix[$row][$col] ?? '') !== '1' || ($matrix[$row + 1][$col + 1] ?? '') !== '0') {
        return false;
    }
    return ($matrix[$row + 3][$col + 3] ?? '') === '1'
        && ($matrix[$row + 6][$col + 6] ?? '') === '1';
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} failed\n");
    exit(1);
}
echo "all QR tests passed\n";
