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

function write_temp(string $name, string $bytes): string
{
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt_brand_' . $name;
    file_put_contents($path, $bytes);
    return $path;
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
$temps = [];
$extraFiles = [];
$brandDir = APP_ROOT . '/storage/brand';
$beforeFiles = glob($brandDir . DIRECTORY_SEPARATOR . 'logo*') ?: [];
$brandBackup = [];
foreach ($beforeFiles as $existing) {
    if (is_file($existing)) {
        $brandBackup[basename($existing)] = (string) file_get_contents($existing);
    }
}
$hostBefore = load_messaging_settings()['smtp_host'];
$nameBefore = app_display_name();
$logoBefore = brand_setting(BRAND_LOGO_KEY);

try {
    check($png !== false && $png !== '', 'a sample image is available');
    $temps[] = $pngPath = write_temp('dot.png', (string) $png);
    $pngAsHeic = inspect_brand_logo($pngPath, 'temple.heic');
    check(($pngAsHeic['extension'] ?? '') === 'png', 'a real image is accepted under any image extension');

    $temps[] = $textPath = write_temp('fake.png', 'this is not an image');
    $text = inspect_brand_logo($textPath, 'photo.png');
    check(isset($text['error']), 'a text file with an image extension is refused');

    $temps[] = $phpPath = write_temp('page.php', (string) $png);
    $phpNamed = inspect_brand_logo($phpPath, 'shell.php');
    check(($phpNamed['extension'] ?? '') === 'png', 'a real image keeps an image extension, never .php');

    $temps[] = $scriptSvg = write_temp('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $badSvg = inspect_brand_logo($scriptSvg, 'logo.svg');
    check(isset($badSvg['error']), 'an SVG logo with a script is refused');

    $temps[] = $cleanSvg = write_temp('ok.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8"><rect width="8" height="8" fill="#800"/></svg>');
    $goodSvg = inspect_brand_logo($cleanSvg, 'logo.svg');
    check(($goodSvg['extension'] ?? '') === 'svg', 'a plain SVG logo is accepted');

    $temps[] = $wbmp = write_temp('mark.wbmp', "\x00\x00\x01\x01\x00");
    $unusual = inspect_brand_logo($wbmp, 'mark.wbmp');
    check(($unusual['extension'] ?? '') === 'wbmp', 'an unusual image type keeps its extension');

    check(save_brand_identity('', null, false) !== null, 'an empty temple name is refused');
    check(save_brand_identity(str_repeat('A', 81), null, false) !== null, 'a temple name over 80 characters is refused');
    check(save_brand_identity('Temple <script>', null, false) !== null, 'a temple name cannot contain markup');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $saved = save_brand_identity('Temple Test', [
            'uploaded' => false,
            'source' => $pngPath,
            'name' => 'temple.heic',
        ], false);
        check($saved === null, 'a temple name and logo can be saved');
        check(app_display_name() === 'Temple Test', 'the saved name is the name on screen');
        check(brand_setting(BRAND_LOGO_KEY) === 'logo.png', 'the logo is stored from the image type');
        check(is_file($brandDir . DIRECTORY_SEPARATOR . 'logo.png'), 'the logo file is stored');
        check(load_messaging_settings()['smtp_host'] === $hostBefore, 'saving the logo does not change mail settings');

        $current = load_messaging_settings();
        $mail = save_messaging_settings($current + ['clear_smtp_password' => ''], $current);
        check($mail === null && app_display_name() === 'Temple Test', 'saving messages leaves the temple name in place');

        $marked = save_brand_identity('Temple Test', null, false, '15', '40');
        check(
            $marked === null
            && brand_watermark_level('receipt') === 15
            && brand_watermark_level('coupon') === 40,
            'receipt and coupon watermarks save from 0 to 100'
        );
        $rejected = save_brand_identity('Temple Test', null, false, '140', '40');
        check($rejected !== null && brand_watermark_level('receipt') === 15, 'a receipt watermark above 100 is refused');
        $rejectedCoupon = save_brand_identity('Temple Test', null, false, '15', '-1');
        check($rejectedCoupon !== null && brand_watermark_level('coupon') === 40, 'a coupon watermark below 0 is refused');

        $restored = save_brand_identity('Temple Test', null, true);
        check($restored === null && !brand_has_custom_logo(), 'the built-in logo can be restored');
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    $svg = (string) file_get_contents(APP_ROOT . '/static/logo.svg');
    $builtIn = brand_rasterize_svg($svg, 128);
    check(
        is_array($builtIn) && $builtIn['width'] === 128 && strlen($builtIn['rgb']) === 128 * 128 * 3,
        'the built-in logo becomes an image'
    );
    $center = 64 * 128 + 64;
    check(ord($builtIn['alpha'][$center] ?? "\0") > 0, 'the logo mark is visible in the middle');
    $raster = brand_logo_raster();
    check(is_array($raster) && $raster['width'] > 0 && $raster['height'] > 0, 'the saved logo can be drawn on a document');

    $png = brand_decode_png($pngPath);
    check(is_array($png) && $png['width'] === 1 && $png['height'] === 1, 'a PNG logo is read from the image itself');

    $receiptPath = APP_ROOT . '/storage/receipts/RCPT-BRAND-CHECK.pdf';
    $couponPath = APP_ROOT . '/storage/coupons/batch_987654.pdf';
    $sampleFree = !is_file($receiptPath) && !is_file($couponPath);
    check($sampleFree, 'the sample documents do not replace a real file');
    if ($sampleFree) {
    $extraFiles[] = generate_receipt_pdf([
        'donation_date' => '2026-09-27',
        'donation_type' => 'Cash',
        'amount' => 100,
        'purpose' => 'General',
        'payment_mode' => 'Cash',
    ], ['name' => 'Sample Devotee', 'phone' => '', 'pan_number' => ''], 'RCPT-BRAND-CHECK');
    $extraFiles[] = generate_coupon_batch_pdf(987654, 'Mahaprasad', 50, 1, 1, 1758920820);
    $receiptPdf = (string) file_get_contents($receiptPath);
    $couponPdf = (string) file_get_contents($couponPath);
    $temple = app_display_name();
    $receiptMark = brand_watermark_opacity('receipt');
    $couponMark = brand_watermark_opacity('coupon');
    check(
        str_contains($receiptPdf, $temple)
        && str_contains($receiptPdf, '/Subtype /Image')
        && ($receiptMark <= 0.0 || $receiptMark >= 0.999 || str_contains($receiptPdf, ' gs')),
        'a receipt prints the temple name and a logo watermark'
    );
    check(
        str_contains($couponPdf, $temple)
        && str_contains($couponPdf, 'CU-1758920820-0001')
        && str_contains($couponPdf, '/Subtype /Image')
        && ($couponMark <= 0.0 || $couponMark >= 0.999 || str_contains($couponPdf, ' gs')),
        'a coupon prints the temple name, the serial, and a logo watermark'
    );
    $mail = smtp_data_payload('Temple', 'seva@temple.test', 'devotee@example.com', 'Hello', "Namaskar\nSample");
    check(
        str_contains($mail, $temple)
        && str_contains($mail, 'cid:temple-logo')
        && str_contains($mail, 'multipart/related')
        && !str_contains($mail, 'multipart/mixed'),
        'an email signature carries the temple name and logo'
    );
    }
} finally {
    foreach (array_merge($temps, $extraFiles) as $temp) {
        if (is_file($temp)) {
            unlink($temp);
        }
    }
    $afterFiles = glob($brandDir . DIRECTORY_SEPARATOR . 'logo*') ?: [];
    foreach ($afterFiles as $created) {
        if (is_file($created) && !array_key_exists(basename($created), $brandBackup)) {
            unlink($created);
        }
    }
    foreach ($brandBackup as $name => $bytes) {
        file_put_contents($brandDir . DIRECTORY_SEPARATOR . $name, $bytes);
    }
}

check(app_display_name() === $nameBefore, 'the live temple name is unchanged after the test');
check(load_messaging_settings()['smtp_host'] === $hostBefore, 'the live mail server is unchanged after the test');
check(brand_setting(BRAND_LOGO_KEY) === $logoBefore, 'the live logo setting is unchanged after the test');

exit($failed === 0 ? 0 : 1);
