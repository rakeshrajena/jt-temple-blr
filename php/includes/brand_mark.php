<?php
declare(strict_types=1);

/**
 * @return array{width:int,height:int,jpeg:?string,rgb:string,alpha:?string,colorSpace:string}|null
 */
function brand_logo_raster(): ?array
{
    $path = brand_logo_path() ?? (APP_ROOT . '/static/logo.svg');
    if (!is_file($path)) {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $mime = is_string($mime) ? strtolower($mime) : '';
    $info = @getimagesize($path);
    if (is_array($info) && ($info[2] ?? 0) === IMAGETYPE_JPEG && $mime === 'image/jpeg') {
        $channels = (int) ($info['channels'] ?? 3);
        if ($channels === 4) {
            return null;
        }
        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        return [
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'jpeg' => $bytes,
            'rgb' => '',
            'alpha' => null,
            'colorSpace' => $channels === 1 ? 'DeviceGray' : 'DeviceRGB',
        ];
    }
    if ($mime === 'image/png' || (is_array($info) && ($info[2] ?? 0) === IMAGETYPE_PNG)) {
        return brand_decode_png($path);
    }
    $svg = file_get_contents($path);
    if ($svg === false) {
        return null;
    }
    if ($mime === 'image/svg+xml' || str_contains(strtolower($svg), '<svg')) {
        return brand_rasterize_svg($svg, 128);
    }
    return null;
}

/** @return array{mime:string,bytes:string}|null */
function brand_logo_email_image(): ?array
{
    $raster = brand_logo_raster();
    if ($raster === null) {
        return null;
    }
    if ($raster['jpeg'] !== null && $raster['alpha'] === null) {
        return ['mime' => 'image/jpeg', 'bytes' => $raster['jpeg']];
    }
    $png = brand_png_bytes($raster['width'], $raster['height'], $raster['rgb'], $raster['alpha']);
    return $png === null ? null : ['mime' => 'image/png', 'bytes' => $png];
}

function refresh_branded_pdfs(): void
{
    $rows = db_all(
        "SELECT d.*, don.name, don.phone, don.email, don.address, don.pan_number
         FROM donations d
         JOIN donors don ON d.donor_id = don.id
         WHERE d.receipt_generated = 1 AND d.receipt_number IS NOT NULL AND d.receipt_number <> ''"
    );
    foreach ($rows as $row) {
        $path = APP_ROOT . '/storage/receipts/' . $row['receipt_number'] . '.pdf';
        if (!is_file($path)) {
            continue;
        }
        try {
            generate_receipt_pdf($row, $row, (string) $row['receipt_number']);
        } catch (Throwable $e) {
            error_log('[jt_blr] receipt logo: ' . $e->getMessage());
        }
    }
    $batches = db_all('SELECT id, coupon_name, cost, start_sl_no, quantity FROM food_coupon_batches');
    foreach ($batches as $batch) {
        $batchId = (int) $batch['id'];
        if (!is_file(coupon_pdf_path($batchId))) {
            continue;
        }
        try {
            generate_coupon_batch_pdf(
                $batchId,
                (string) $batch['coupon_name'],
                (float) $batch['cost'],
                (int) $batch['start_sl_no'],
                (int) $batch['quantity']
            );
        } catch (Throwable $e) {
            error_log('[jt_blr] coupon logo: ' . $e->getMessage());
        }
    }
}

/**
 * @return array{width:int,height:int,jpeg:?string,rgb:string,alpha:?string,colorSpace:string}|null
 */
function brand_decode_png(string $path): ?array
{
    $raw = file_get_contents($path);
    if ($raw === false || strlen($raw) < 8 || substr($raw, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return null;
    }
    $offset = 8;
    $width = 0;
    $height = 0;
    $bitDepth = 0;
    $colorType = 0;
    $idat = '';
    $palette = '';
    $trns = '';
    $length = strlen($raw);
    while ($offset + 8 <= $length) {
        $chunkLength = unpack('N', substr($raw, $offset, 4));
        $size = is_array($chunkLength) ? (int) $chunkLength[1] : 0;
        $type = substr($raw, $offset + 4, 4);
        $data = substr($raw, $offset + 8, $size);
        $offset += 12 + $size;
        if ($type === 'IHDR' && strlen($data) >= 13) {
            $header = unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccomp/Cfilter/Cinterlace', $data);
            if (!is_array($header) || (int) $header['interlace'] !== 0 || (int) $header['depth'] !== 8) {
                return null;
            }
            $width = (int) $header['width'];
            $height = (int) $header['height'];
            $bitDepth = (int) $header['depth'];
            $colorType = (int) $header['color'];
        } elseif ($type === 'PLTE') {
            $palette = $data;
        } elseif ($type === 'tRNS') {
            $trns = $data;
        } elseif ($type === 'IDAT') {
            $idat .= $data;
        } elseif ($type === 'IEND') {
            break;
        }
    }
    if ($width < 1 || $height < 1 || $bitDepth !== 8 || $idat === '') {
        return null;
    }
    $bpp = match ($colorType) {
        0 => 1,
        2 => 3,
        3 => 1,
        4 => 2,
        6 => 4,
        default => 0,
    };
    if ($bpp === 0) {
        return null;
    }
    $inflated = zlib_decode($idat);
    if ($inflated === false) {
        return null;
    }
    $stride = $width * $bpp;
    $expected = ($stride + 1) * $height;
    if (strlen($inflated) < $expected) {
        return null;
    }
    $rows = [];
    $cursor = 0;
    for ($y = 0; $y < $height; $y++) {
        $filter = ord($inflated[$cursor]);
        $cursor++;
        $row = substr($inflated, $cursor, $stride);
        $cursor += $stride;
        $prior = $rows[$y - 1] ?? str_repeat("\0", $stride);
        $rows[] = brand_unfilter_png_row($filter, $row, $prior, $bpp);
    }
    $rgb = '';
    $alpha = '';
    $opaque = true;
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $pixel = substr($rows[$y], $x * $bpp, $bpp);
            [$r, $g, $b, $a] = brand_png_pixel($pixel, $colorType, $palette, $trns);
            $rgb .= chr($r) . chr($g) . chr($b);
            $alpha .= chr($a);
            if ($a !== 255) {
                $opaque = false;
            }
        }
    }
    return [
        'width' => $width,
        'height' => $height,
        'jpeg' => null,
        'rgb' => $rgb,
        'alpha' => $opaque ? null : $alpha,
        'colorSpace' => 'DeviceRGB',
    ];
}

function brand_unfilter_png_row(int $filter, string $row, string $prior, int $bpp): string
{
    $stride = strlen($row);
    $out = '';
    for ($i = 0; $i < $stride; $i++) {
        $raw = ord($row[$i]);
        $left = $i >= $bpp ? ord($out[$i - $bpp]) : 0;
        $up = ord($prior[$i]);
        $upLeft = $i >= $bpp ? ord($prior[$i - $bpp]) : 0;
        $value = match ($filter) {
            1 => $raw + $left,
            2 => $raw + $up,
            3 => $raw + intdiv($left + $up, 2),
            4 => $raw + brand_paeth($left, $up, $upLeft),
            default => $raw,
        };
        $out .= chr($value & 255);
    }
    return $out;
}

function brand_paeth(int $left, int $up, int $upLeft): int
{
    $estimate = $left + $up - $upLeft;
    $leftDistance = abs($estimate - $left);
    $upDistance = abs($estimate - $up);
    $diagonal = abs($estimate - $upLeft);
    if ($leftDistance <= $upDistance && $leftDistance <= $diagonal) {
        return $left;
    }
    return $upDistance <= $diagonal ? $up : $upLeft;
}

/** @return array{0:int,1:int,2:int,3:int} */
function brand_png_pixel(string $pixel, int $colorType, string $palette, string $trns): array
{
    if ($colorType === 6 && strlen($pixel) >= 4) {
        return [ord($pixel[0]), ord($pixel[1]), ord($pixel[2]), ord($pixel[3])];
    }
    if ($colorType === 2 && strlen($pixel) >= 3) {
        $alpha = 255;
        if (strlen($trns) >= 6 && $pixel === substr($trns, 0, 3)) {
            $alpha = 0;
        }
        return [ord($pixel[0]), ord($pixel[1]), ord($pixel[2]), $alpha];
    }
    if ($colorType === 0 && $pixel !== '') {
        $gray = ord($pixel[0]);
        $alpha = strlen($trns) >= 2 && $gray === ord($trns[1]) ? 0 : 255;
        return [$gray, $gray, $gray, $alpha];
    }
    if ($colorType === 4 && strlen($pixel) >= 2) {
        $gray = ord($pixel[0]);
        return [$gray, $gray, $gray, ord($pixel[1])];
    }
    if ($colorType === 3 && $pixel !== '' && $palette !== '') {
        $index = ord($pixel[0]);
        $color = substr($palette, $index * 3, 3);
        if (strlen($color) < 3) {
            return [0, 0, 0, 0];
        }
        $alpha = $trns !== '' && isset($trns[$index]) ? ord($trns[$index]) : 255;
        return [ord($color[0]), ord($color[1]), ord($color[2]), $alpha];
    }
    return [0, 0, 0, 0];
}

/**
 * @return array{width:int,height:int,jpeg:?string,rgb:string,alpha:?string,colorSpace:string}|null
 */
function brand_rasterize_svg(string $svg, int $size): ?array
{
    if (!str_contains(strtolower($svg), '<svg') || str_contains(strtolower($svg), '<script')) {
        return null;
    }
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($svg, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $dom->documentElement;
    if ($loaded !== true || $root === null || strtolower($root->localName) !== 'svg') {
        return null;
    }
    $box = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox'))) ?: [];
    $minX = isset($box[0]) ? (float) $box[0] : 0.0;
    $minY = isset($box[1]) ? (float) $box[1] : 0.0;
    $boxW = isset($box[2]) ? (float) $box[2] : (float) ($root->getAttribute('width') ?: $size);
    $boxH = isset($box[3]) ? (float) $box[3] : (float) ($root->getAttribute('height') ?: $size);
    if ($boxW <= 0.0 || $boxH <= 0.0) {
        return null;
    }
    $buffer = str_repeat("\0", $size * $size * 4);
    foreach ($root->childNodes as $node) {
        if ($node instanceof DOMElement) {
            brand_paint_svg($buffer, $size, $node, $minX, $minY, $boxW, $boxH);
        }
    }
    $rgb = '';
    $alpha = '';
    $opaque = true;
    $visible = false;
    for ($i = 0; $i < $size * $size; $i++) {
        $r = ord($buffer[$i * 4]);
        $g = ord($buffer[$i * 4 + 1]);
        $b = ord($buffer[$i * 4 + 2]);
        $a = ord($buffer[$i * 4 + 3]);
        $rgb .= chr($r) . chr($g) . chr($b);
        $alpha .= chr($a);
        if ($a !== 255) {
            $opaque = false;
        }
        if ($a > 0) {
            $visible = true;
        }
    }
    if (!$visible) {
        return null;
    }
    return [
        'width' => $size,
        'height' => $size,
        'jpeg' => null,
        'rgb' => $rgb,
        'alpha' => $opaque ? null : $alpha,
        'colorSpace' => 'DeviceRGB',
    ];
}

function brand_paint_svg(
    string &$buffer,
    int $size,
    DOMElement $node,
    float $minX,
    float $minY,
    float $boxW,
    float $boxH
): void {
    $name = strtolower($node->localName);
    if ($name === 'g') {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                brand_paint_svg($buffer, $size, $child, $minX, $minY, $boxW, $boxH);
            }
        }
        return;
    }
    $fill = brand_svg_color($node->getAttribute('fill'));
    $stroke = brand_svg_color($node->getAttribute('stroke'));
    $strokeWidth = (float) ($node->getAttribute('stroke-width') ?: 1);
    $scaleX = $size / $boxW;
    $scaleY = $size / $boxH;
    $px = static fn (float $x): int => (int) round(($x - $minX) * $scaleX);
    $py = static fn (float $y): int => (int) round(($y - $minY) * $scaleY);
    if ($name === 'circle') {
        $cx = $px((float) $node->getAttribute('cx'));
        $cy = $py((float) $node->getAttribute('cy'));
        $rx = max(1, (int) round((float) $node->getAttribute('r') * $scaleX));
        $ry = max(1, (int) round((float) $node->getAttribute('r') * $scaleY));
        if ($fill !== null) {
            brand_fill_ellipse($buffer, $size, $cx, $cy, $rx, $ry, $fill);
        }
        if ($stroke !== null) {
            $innerX = max(0, $rx - (int) round($strokeWidth * $scaleX));
            $innerY = max(0, $ry - (int) round($strokeWidth * $scaleY));
            brand_stroke_ellipse($buffer, $size, $cx, $cy, $rx, $ry, $innerX, $innerY, $stroke);
        }
        return;
    }
    if ($name === 'rect' && $fill !== null) {
        $x = $px((float) $node->getAttribute('x'));
        $y = $py((float) $node->getAttribute('y'));
        $w = max(1, (int) round((float) $node->getAttribute('width') * $scaleX));
        $h = max(1, (int) round((float) $node->getAttribute('height') * $scaleY));
        brand_fill_rect($buffer, $size, $x, $y, $w, $h, $fill);
        return;
    }
    if ($name === 'path' && $fill !== null) {
        brand_fill_path($buffer, $size, $node->getAttribute('d'), $px, $py, $fill);
    }
}

/** @return array{0:int,1:int,2:int}|null */
function brand_svg_color(string $value): ?array
{
    $value = strtolower(trim($value));
    if ($value === '' || $value === 'none') {
        return null;
    }
    if (preg_match('/^#([0-9a-f]{3})$/', $value, $short) === 1) {
        return [
            hexdec($short[1][0] . $short[1][0]),
            hexdec($short[1][1] . $short[1][1]),
            hexdec($short[1][2] . $short[1][2]),
        ];
    }
    if (preg_match('/^#([0-9a-f]{6})$/', $value, $full) === 1) {
        return [
            hexdec(substr($full[1], 0, 2)),
            hexdec(substr($full[1], 2, 2)),
            hexdec(substr($full[1], 4, 2)),
        ];
    }
    return null;
}

/** @param array{0:int,1:int,2:int} $color */
function brand_plot(string &$buffer, int $size, int $x, int $y, array $color): void
{
    if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) {
        return;
    }
    $offset = ($y * $size + $x) * 4;
    $buffer[$offset] = chr($color[0]);
    $buffer[$offset + 1] = chr($color[1]);
    $buffer[$offset + 2] = chr($color[2]);
    $buffer[$offset + 3] = "\xff";
}

/** @param array{0:int,1:int,2:int} $color */
function brand_fill_rect(string &$buffer, int $size, int $x, int $y, int $w, int $h, array $color): void
{
    for ($py = $y; $py < $y + $h; $py++) {
        for ($px = $x; $px < $x + $w; $px++) {
            brand_plot($buffer, $size, $px, $py, $color);
        }
    }
}

/** @param array{0:int,1:int,2:int} $color */
function brand_fill_ellipse(string &$buffer, int $size, int $cx, int $cy, int $rx, int $ry, array $color): void
{
    for ($y = $cy - $ry; $y <= $cy + $ry; $y++) {
        for ($x = $cx - $rx; $x <= $cx + $rx; $x++) {
            $dx = ($x - $cx) / $rx;
            $dy = ($y - $cy) / $ry;
            if (($dx * $dx) + ($dy * $dy) <= 1) {
                brand_plot($buffer, $size, $x, $y, $color);
            }
        }
    }
}

/** @param array{0:int,1:int,2:int} $color */
function brand_stroke_ellipse(
    string &$buffer,
    int $size,
    int $cx,
    int $cy,
    int $rx,
    int $ry,
    int $innerX,
    int $innerY,
    array $color
): void {
    for ($y = $cy - $ry; $y <= $cy + $ry; $y++) {
        for ($x = $cx - $rx; $x <= $cx + $rx; $x++) {
            $dx = ($x - $cx) / max(1, $rx);
            $dy = ($y - $cy) / max(1, $ry);
            $outer = ($dx * $dx) + ($dy * $dy) <= 1;
            $ix = ($x - $cx) / max(1, $innerX);
            $iy = ($y - $cy) / max(1, $innerY);
            $inner = ($ix * $ix) + ($iy * $iy) <= 1;
            if ($outer && !$inner) {
                brand_plot($buffer, $size, $x, $y, $color);
            }
        }
    }
}

/**
 * @param callable(float): int $px
 * @param callable(float): int $py
 * @param array{0:int,1:int,2:int} $color
 */
function brand_fill_path(string &$buffer, int $size, string $path, callable $px, callable $py, array $color): void
{
    preg_match_all('/([MLHVZ])|([+-]?(?:\d+\.?\d*|\.\d+))/i', $path, $tokens, PREG_SET_ORDER);
    $points = [];
    $cx = 0.0;
    $cy = 0.0;
    $startX = 0.0;
    $startY = 0.0;
    $index = 0;
    $count = count($tokens);
    $flush = static function () use (&$points, &$buffer, $size, $color): void {
        if (count($points) >= 3) {
            brand_fill_polygon($buffer, $size, $points, $color);
        }
        $points = [];
    };
    while ($index < $count) {
        $command = strtoupper($tokens[$index][1] ?? '');
        if ($command === '') {
            $index++;
            continue;
        }
        $index++;
        if ($command === 'M' || $command === 'L') {
            $x = (float) ($tokens[$index][2] ?? 0);
            $y = (float) ($tokens[$index + 1][2] ?? 0);
            $index += 2;
            if ($command === 'M' && $points !== []) {
                $flush();
            }
            $cx = $x;
            $cy = $y;
            if ($command === 'M') {
                $startX = $x;
                $startY = $y;
            }
            $points[] = [$px($cx), $py($cy)];
            continue;
        }
        if ($command === 'H') {
            $cx = (float) ($tokens[$index][2] ?? $cx);
            $index++;
            $points[] = [$px($cx), $py($cy)];
            continue;
        }
        if ($command === 'V') {
            $cy = (float) ($tokens[$index][2] ?? $cy);
            $index++;
            $points[] = [$px($cx), $py($cy)];
            continue;
        }
        if ($command === 'Z') {
            $points[] = [$px($startX), $py($startY)];
            $flush();
            $cx = $startX;
            $cy = $startY;
        }
    }
    $flush();
}

/** @param list<array{0:int,1:int}> $points
 * @param array{0:int,1:int,2:int} $color */
function brand_fill_polygon(string &$buffer, int $size, array $points, array $color): void
{
    $count = count($points);
    $minY = $size;
    $maxY = 0;
    foreach ($points as $point) {
        $minY = min($minY, $point[1]);
        $maxY = max($maxY, $point[1]);
    }
    $minY = max(0, $minY);
    $maxY = min($size - 1, $maxY);
    for ($y = $minY; $y <= $maxY; $y++) {
        $crossings = [];
        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];
            if ($a[1] === $b[1] || $y < min($a[1], $b[1]) || $y >= max($a[1], $b[1])) {
                continue;
            }
            $crossings[] = $a[0] + ($y - $a[1]) * ($b[0] - $a[0]) / ($b[1] - $a[1]);
        }
        sort($crossings);
        for ($i = 0; $i + 1 < count($crossings); $i += 2) {
            $from = (int) ceil($crossings[$i]);
            $to = (int) floor($crossings[$i + 1]);
            for ($x = $from; $x <= $to; $x++) {
                brand_plot($buffer, $size, $x, $y, $color);
            }
        }
    }
}

function brand_png_bytes(int $width, int $height, string $rgb, ?string $alpha): ?string
{
    if ($width < 1 || $height < 1) {
        return null;
    }
    $colorType = $alpha === null ? 2 : 6;
    if (strlen($rgb) < $width * $height * 3) {
        return null;
    }
    $raw = '';
    for ($y = 0; $y < $height; $y++) {
        $raw .= "\0";
        for ($x = 0; $x < $width; $x++) {
            $index = ($y * $width + $x) * 3;
            $raw .= substr($rgb, $index, 3);
            if ($alpha !== null) {
                $raw .= $alpha[$y * $width + $x] ?? "\xff";
            }
        }
    }
    $compressed = zlib_encode($raw, ZLIB_ENCODING_DEFLATE);
    if ($compressed === false) {
        return null;
    }
    $header = pack('NNCCCCC', $width, $height, 8, $colorType, 0, 0, 0);
    return "\x89PNG\r\n\x1a\n"
        . brand_png_chunk('IHDR', $header)
        . brand_png_chunk('IDAT', $compressed)
        . brand_png_chunk('IEND', '');
}

function brand_png_chunk(string $type, string $data): string
{
    return pack('N', strlen($data)) . $type . $data . hash('crc32b', $type . $data, true);
}
