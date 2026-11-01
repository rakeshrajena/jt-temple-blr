<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\App;
use SJT\Lib\PngRaster;

/**
 * Temple logo for booking invoices — same sources as ERP billing/receipts and Settings › logo:
 * {@see php/includes/brand_mark.php} / {@see brand_logo_path()}.
 *
 * Order: storage/brand/logo-print.png → app_settings brand_logo file → storage/brand/logo.* → php/static/logo.svg
 */
final class TempleLogo
{
    private const ERP_BRAND_KEY = 'brand_logo';

    public static function erpRoot(): string
    {
        return ErpPaths::root();
    }

    public static function erpBrandDir(): string
    {
        return self::erpRoot() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'brand';
    }

    public static function erpStaticSvg(): string
    {
        return self::erpRoot() . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'logo.svg';
    }

    /** Absolute path to logo file, or null. */
    public static function filePath(): ?string
    {
        $dir = self::erpBrandDir();
        $print = $dir . DIRECTORY_SEPARATOR . 'logo-print.png';
        if (is_file($print)) {
            return $print;
        }
        $name = self::erpLogoFilename();
        if ($name !== null) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_file($path)) {
                return realpath($path) ?: $path;
            }
        }
        foreach (['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp'] as $guess) {
            $path = $dir . DIRECTORY_SEPARATOR . $guess;
            if (is_file($path)) {
                return realpath($path) ?: $path;
            }
        }
        $svg = self::erpStaticSvg();
        return is_file($svg) ? $svg : null;
    }

    private static function erpLogoFilename(): ?string
    {
        try {
            $db = App::db();
            if (!$db->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'app_settings' LIMIT 1")) {
                return null;
            }
            $file = (string) $db->value('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1', [self::ERP_BRAND_KEY]);
            return preg_match('/^logo\.[a-z0-9]{1,8}$/i', $file) === 1 ? $file : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** PNG/JPEG bytes for HTML img src (public invoice page). */
    public static function publicUrl(): ?string
    {
        $path = self::filePath();
        if ($path === null) {
            return null;
        }
        return url('/brand/logo', ['v' => (string) @filemtime($path)]);
    }

    /**
     * Raster for PdfDocument::addImage().
     *
     * @return array{width:int,height:int,jpeg:?string,rgb:string,alpha:?string,colorSpace:string}|null
     */
    public static function pdfRaster(): ?array
    {
        static $cache = null;
        static $key = '';
        $path = self::filePath();
        if ($path === null || str_ends_with(strtolower($path), '.svg')) {
            $print = self::erpBrandDir() . DIRECTORY_SEPARATOR . 'logo-print.png';
            $path = is_file($print) ? $print : null;
        }
        if ($path === null) {
            return null;
        }
        $k = $path . ':' . (string) @filemtime($path);
        if ($cache !== null && $key === $k) {
            return $cache;
        }
        $key = $k;
        $cache = self::decodeImageFile($path);
        return $cache;
    }

    /**
     * @return array{width:int,height:int,jpeg:?string,rgb:string,alpha:?string,colorSpace:string}|null
     */
    private static function decodeImageFile(string $path): ?array
    {
        $info = @getimagesize($path);
        if (!is_array($info)) {
            return null;
        }
        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w < 1 || $h < 1 || $w > 512 || $h > 512) {
            return null;
        }
        $type = (int) ($info[2] ?? 0);
        if ($type === IMAGETYPE_JPEG) {
            $bytes = file_get_contents($path);
            if ($bytes === false || $bytes === '') {
                return null;
            }
            return [
                'width' => $w,
                'height' => $h,
                'jpeg' => $bytes,
                'rgb' => '',
                'alpha' => null,
                'colorSpace' => ((int) ($info['channels'] ?? 3)) === 1 ? 'DeviceGray' : 'DeviceRGB',
            ];
        }
        if ($type === IMAGETYPE_PNG) {
            return PngRaster::decodeFile($path);
        }
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $im = match ($type) {
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => null,
        };
        if ($im === false || $im === null) {
            return null;
        }
        imagealphablending($im, true);
        imagesavealpha($im, true);
        $rgb = '';
        $alpha = '';
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($im, $x, $y);
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $a = 127 - (($rgba >> 24) & 0x7f);
                $rgb .= chr($r) . chr($g) . chr($b);
                $alpha .= chr((int) round($a * 255 / 127));
            }
        }
        imagedestroy($im);
        return [
            'width' => $w,
            'height' => $h,
            'jpeg' => null,
            'rgb' => $rgb,
            'alpha' => $alpha,
            'colorSpace' => 'DeviceRGB',
        ];
    }

    /** @return array{0: float, 1: float} width and height for a max box */
    public static function fitBox(int $w, int $h, float $max): array
    {
        if ($w < 1 || $h < 1) {
            return [0.0, 0.0];
        }
        $scale = min($max / $w, $max / $h, 1.0);
        return [$w * $scale, $h * $scale];
    }
}
