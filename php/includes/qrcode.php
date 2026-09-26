<?php
declare(strict_types=1);

/**
 * QR Code, byte mode, error correction M, versions 1–3.
 * Row 0 is the top. Each row is '1' for a dark module and '0' for a light one.
 *
 * @return list<string>
 */
function qr_matrix(string $text): array
{
    if ($text === '' || strlen($text) > 42) {
        return [];
    }
    $version = strlen($text) <= 14 ? 1 : (strlen($text) <= 26 ? 2 : 3);
    $spec = qr_version_spec($version);
    $size = 17 + (4 * $version);
    $grid = [];
    $reserved = [];
    for ($row = 0; $row < $size; $row++) {
        $grid[$row] = array_fill(0, $size, 0);
        $reserved[$row] = array_fill(0, $size, false);
    }

    qr_place_finder($grid, $reserved, 0, 0);
    qr_place_finder($grid, $reserved, 0, $size - 7);
    qr_place_finder($grid, $reserved, $size - 7, 0);
    for ($i = 0; $i < $size; $i++) {
        if ($reserved[6][$i] === false) {
            $grid[6][$i] = $i % 2 === 0 ? 1 : 0;
            $reserved[6][$i] = true;
        }
        if ($reserved[$i][6] === false) {
            $grid[$i][6] = $i % 2 === 0 ? 1 : 0;
            $reserved[$i][6] = true;
        }
    }
    foreach (qr_alignment_centers($spec['align'], $size) as [$row, $col]) {
        qr_place_alignment($grid, $reserved, $row, $col);
    }
    $grid[$size - 8][8] = 1;
    $reserved[$size - 8][8] = true;
    foreach (qr_format_positions($size) as [$row, $col]) {
        $reserved[$row][$col] = true;
    }

    $bits = qr_data_bits($text, $spec['data'], $spec['ecc'], $spec['remainder']);
    $best = null;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $candidate = $grid;
        qr_place_data($candidate, $reserved, $bits, $mask);
        qr_place_format($candidate, $mask);
        $score = qr_penalty($candidate);
        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $candidate;
        }
    }
    if ($best === null) {
        return [];
    }

    $rows = [];
    foreach ($best as $row) {
        $rows[] = implode('', array_map(static fn (int $module): string => $module === 1 ? '1' : '0', $row));
    }
    return $rows;
}

function qr_format_bits(int $mask): int
{
    $value = $mask << 10;
    for ($bit = 4; $bit >= 0; $bit--) {
        if ((($value >> ($bit + 10)) & 1) === 1) {
            $value ^= 0x537 << $bit;
        }
    }
    return (($mask << 10) | ($value & 0x3FF)) ^ 0x5412;
}

/**
 * @return array{data:int,ecc:int,align:list<int>,remainder:int}
 */
function qr_version_spec(int $version): array
{
    return match ($version) {
        1 => ['data' => 16, 'ecc' => 10, 'align' => [], 'remainder' => 0],
        2 => ['data' => 28, 'ecc' => 16, 'align' => [6, 18], 'remainder' => 7],
        3 => ['data' => 44, 'ecc' => 26, 'align' => [6, 22], 'remainder' => 7],
        default => throw new InvalidArgumentException('That QR version is not supported.'),
    };
}

/** @param list<int> $data */
function qr_ecc(array $data, int $count): array
{
    [$exp] = qr_gf();
    $generator = [1];
    for ($i = 0; $i < $count; $i++) {
        $generator = qr_poly_mul($generator, [1, $exp[$i]]);
    }
    $remainder = array_merge($data, array_fill(0, $count, 0));
    $dataLength = count($data);
    for ($i = 0; $i < $dataLength; $i++) {
        $factor = $remainder[$i];
        if ($factor === 0) {
            continue;
        }
        $genLength = count($generator);
        for ($j = 1; $j < $genLength; $j++) {
            $remainder[$i + $j] ^= qr_gf_mul($generator[$j], $factor);
        }
    }
    return array_slice($remainder, -$count);
}

function qr_gf_mul(int $left, int $right): int
{
    if ($left === 0 || $right === 0) {
        return 0;
    }
    [, $log] = qr_gf();
    [$exp] = qr_gf();
    return $exp[$log[$left] + $log[$right]];
}

/** @return array{0:list<int>,1:array<int,int>} */
function qr_gf(): array
{
    static $exp = null;
    static $log = null;
    if (is_array($exp) && is_array($log)) {
        return [$exp, $log];
    }
    $exp = [];
    $log = array_fill(0, 256, 0);
    $value = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $value;
        $log[$value] = $i;
        $value <<= 1;
        if (($value & 0x100) !== 0) {
            $value ^= 0x11D;
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }
    return [$exp, $log];
}

/**
 * @param list<list<int>> $grid
 * @param list<list<bool>> $reserved
 */
function qr_place_finder(array &$grid, array &$reserved, int $row, int $col): void
{
    $size = count($grid);
    for ($r = -1; $r <= 7; $r++) {
        for ($c = -1; $c <= 7; $c++) {
            $rr = $row + $r;
            $cc = $col + $c;
            if ($rr < 0 || $cc < 0 || $rr >= $size || $cc >= $size) {
                continue;
            }
            $inside = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
            $border = $r === 0 || $r === 6 || $c === 0 || $c === 6;
            $core = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
            $grid[$rr][$cc] = ($inside && ($border || $core)) ? 1 : 0;
            $reserved[$rr][$cc] = true;
        }
    }
}

/**
 * @param list<int> $centers
 * @return list<array{0:int,1:int}>
 */
function qr_alignment_centers(array $centers, int $size): array
{
    $points = [];
    foreach ($centers as $row) {
        foreach ($centers as $col) {
            if (qr_overlaps_finder($row, $col, $size)) {
                continue;
            }
            $points[] = [$row, $col];
        }
    }
    return $points;
}

function qr_overlaps_finder(int $row, int $col, int $size): bool
{
    $boxes = [
        [0, 8, 0, 8],
        [0, 8, $size - 9, $size - 1],
        [$size - 9, $size - 1, 0, 8],
    ];
    foreach ($boxes as [$top, $bottom, $left, $right]) {
        if ($row - 2 <= $bottom && $row + 2 >= $top && $col - 2 <= $right && $col + 2 >= $left) {
            return true;
        }
    }
    return false;
}

/**
 * @param list<list<int>> $grid
 * @param list<list<bool>> $reserved
 */
function qr_place_alignment(array &$grid, array &$reserved, int $row, int $col): void
{
    for ($r = -2; $r <= 2; $r++) {
        for ($c = -2; $c <= 2; $c++) {
            $grid[$row + $r][$col + $c] = max(abs($r), abs($c)) === 1 ? 0 : 1;
            $reserved[$row + $r][$col + $c] = true;
        }
    }
}

/** @return list<array{0:int,1:int}> */
function qr_format_positions(int $size): array
{
    $positions = [
        [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
        [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
    ];
    for ($i = 0; $i < 8; $i++) {
        $positions[] = [8, $size - 1 - $i];
    }
    for ($i = 8; $i < 15; $i++) {
        $positions[] = [$size - 15 + $i, 8];
    }
    return $positions;
}

function qr_data_bits(string $text, int $dataCodewords, int $eccCount, int $remainder): string
{
    $bits = '0100' . sprintf('%08b', strlen($text));
    $length = strlen($text);
    for ($i = 0; $i < $length; $i++) {
        $bits .= sprintf('%08b', ord($text[$i]));
    }
    $capacity = $dataCodewords * 8;
    $terminator = min(4, max(0, $capacity - strlen($bits)));
    $bits .= str_repeat('0', $terminator);
    if (strlen($bits) % 8 !== 0) {
        $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
    }
    $pad = ['11101100', '00010001'];
    $padIndex = 0;
    while (strlen($bits) < $capacity) {
        $bits .= $pad[$padIndex % 2];
        $padIndex++;
    }
    $codewords = [];
    for ($i = 0; $i < $dataCodewords; $i++) {
        $codewords[] = bindec(substr($bits, $i * 8, 8));
    }
    $stream = '';
    foreach (array_merge($codewords, qr_ecc($codewords, $eccCount)) as $byte) {
        $stream .= sprintf('%08b', $byte);
    }
    return $stream . str_repeat('0', $remainder);
}

/**
 * @param list<list<int>> $grid
 * @param list<list<bool>> $reserved
 */
function qr_place_data(array &$grid, array $reserved, string $bits, int $mask): void
{
    $size = count($grid);
    $index = 0;
    $length = strlen($bits);
    $upward = true;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($step = 0; $step < $size; $step++) {
            $row = $upward ? $size - 1 - $step : $step;
            for ($offset = 0; $offset < 2; $offset++) {
                $col = $right - $offset;
                if ($reserved[$row][$col]) {
                    continue;
                }
                $dark = $index < $length && $bits[$index] === '1';
                $index++;
                if (qr_mask_flips($mask, $row, $col)) {
                    $dark = !$dark;
                }
                $grid[$row][$col] = $dark ? 1 : 0;
            }
        }
        $upward = !$upward;
    }
    if ($index !== $length) {
        throw new RuntimeException('The QR code could not be laid out.');
    }
}

function qr_mask_flips(int $mask, int $row, int $col): bool
{
    return match ($mask) {
        0 => ($row + $col) % 2 === 0,
        1 => $row % 2 === 0,
        2 => $col % 3 === 0,
        3 => ($row + $col) % 3 === 0,
        4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
        5 => (($row * $col) % 2) + (($row * $col) % 3) === 0,
        6 => ((($row * $col) % 2) + (($row * $col) % 3)) % 2 === 0,
        7 => ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0,
        default => false,
    };
}

/** @param list<list<int>> $grid */
function qr_place_format(array &$grid, int $mask): void
{
    $bits = qr_format_bits($mask);
    $size = count($grid);
    $aroundTopLeft = [
        [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
        [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
    ];
    foreach ($aroundTopLeft as $i => [$row, $col]) {
        $grid[$row][$col] = ($bits >> $i) & 1;
    }
    for ($i = 0; $i < 8; $i++) {
        $grid[8][$size - 1 - $i] = ($bits >> $i) & 1;
    }
    for ($i = 8; $i < 15; $i++) {
        $grid[$size - 15 + $i][8] = ($bits >> $i) & 1;
    }
}

/** @param list<list<int>> $grid */
function qr_penalty(array $grid): int
{
    $size = count($grid);
    $score = qr_run_penalty($grid, $size, true) + qr_run_penalty($grid, $size, false);
    for ($row = 0; $row < $size - 1; $row++) {
        for ($col = 0; $col < $size - 1; $col++) {
            $color = $grid[$row][$col];
            if (
                $color === $grid[$row][$col + 1]
                && $color === $grid[$row + 1][$col]
                && $color === $grid[$row + 1][$col + 1]
            ) {
                $score += 3;
            }
        }
    }
    $dark = 0;
    foreach ($grid as $row) {
        $dark += array_sum($row);
    }
    $score += 10 * intdiv((int) abs((($dark * 100) / ($size * $size)) - 50), 5);
    return $score;
}

/** @param list<list<int>> $grid */
function qr_run_penalty(array $grid, int $size, bool $horizontal): int
{
    $score = 0;
    for ($a = 0; $a < $size; $a++) {
        $color = -1;
        $run = 0;
        for ($b = 0; $b < $size; $b++) {
            $module = $horizontal ? $grid[$a][$b] : $grid[$b][$a];
            if ($module === $color) {
                $run++;
                if ($run === 5) {
                    $score += 3;
                } elseif ($run > 5) {
                    $score++;
                }
                continue;
            }
            $color = $module;
            $run = 1;
        }
    }
    return $score;
}

/** @param list<int> $left @param list<int> $right @return list<int> */
function qr_poly_mul(array $left, array $right): array
{
    $result = array_fill(0, count($left) + count($right) - 1, 0);
    foreach ($left as $i => $a) {
        foreach ($right as $j => $b) {
            $result[$i + $j] ^= qr_gf_mul($a, $b);
        }
    }
    return $result;
}
