<?php
declare(strict_types=1);

namespace SJT\Lib;

/** Decode PNG to PdfDocument image arrays without GD (same idea as ERP brand_decode_png). */
final class PngRaster
{
    /**
     * @return array{width:int,height:int,jpeg:null,rgb:string,alpha:?string,colorSpace:string}|null
     */
    public static function decodeFile(string $path, int $maxSide = 512): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        return is_string($raw) ? self::decodeBytes($raw, $maxSide) : null;
    }

    /**
     * @return array{width:int,height:int,jpeg:null,rgb:string,alpha:?string,colorSpace:string}|null
     */
    public static function decodeBytes(string $raw, int $maxSide = 512): ?array
    {
        if (strlen($raw) < 8 || substr($raw, 0, 8) !== "\x89PNG\r\n\x1a\n") {
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
        if ($width < 1 || $height < 1 || $width > $maxSide || $height > $maxSide || $bitDepth !== 8 || $idat === '') {
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
            $rows[] = self::unfilterRow($filter, $row, $prior, $bpp);
        }
        $rgb = '';
        $alpha = '';
        $opaque = true;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = substr($rows[$y], $x * $bpp, $bpp);
                [$r, $g, $b, $a] = self::pixel($pixel, $colorType, $palette, $trns);
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

    private static function unfilterRow(int $filter, string $row, string $prior, int $bpp): string
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
                4 => $raw + self::paeth($left, $up, $upLeft),
                default => $raw,
            };
            $out .= chr($value & 255);
        }
        return $out;
    }

    private static function paeth(int $left, int $up, int $upLeft): int
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
    private static function pixel(string $pixel, int $colorType, string $palette, string $trns): array
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
}
