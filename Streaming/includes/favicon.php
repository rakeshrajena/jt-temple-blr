<?php
declare(strict_types=1);

/**
 * Favicon for Streaming — same sources as CRM / Booking (storage/brand, else static logo.svg).
 *
 * @return array{href:string,type:string}
 */
function streaming_favicon(): array
{
    $crmRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'crm';
    $path = streaming_resolve_brand_logo_path($crmRoot);
    $webCrm = streaming_crm_web_base();

    if ($path !== null) {
        $href = $webCrm . '/index.php?' . http_build_query([
            'r' => 'brand/logo',
            'v' => (string) filemtime($path),
        ]);

        return ['href' => $href, 'type' => streaming_mime_for_path($path)];
    }

    $svg = $crmRoot . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'logo.svg';
    $v = is_file($svg) ? (string) filemtime($svg) : '1';

    return [
        'href' => $webCrm . '/static/logo.svg?v=' . rawurlencode($v),
        'type' => 'image/svg+xml',
    ];
}

/** Brand raster only (same sources as CRM app_logo_url / Booking TempleLogo); not the static SVG fallback. */
function streaming_resolve_brand_logo_path(string $crmRoot): ?string
{
    $brand = $crmRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'brand';
    $print = $brand . DIRECTORY_SEPARATOR . 'logo-print.png';
    if (is_file($print)) {
        return $print;
    }
    foreach (['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp'] as $name) {
        $candidate = $brand . DIRECTORY_SEPARATOR . $name;
        if (is_file($candidate)) {
            return realpath($candidate) ?: $candidate;
        }
    }

    return null;
}

function streaming_mime_for_path(string $path): string
{
    return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/svg+xml',
    };
}

/** Web path to CRM folder (sibling of Streaming), no trailing slash. */
function streaming_crm_web_base(): string
{
    $streamScript = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/Streaming/index.php'));
    $streamDir = rtrim(dirname($streamScript), '/');
    if ($streamDir === '' || $streamDir === '.') {
        return '/crm';
    }
    $parent = dirname($streamDir);
    if ($parent === '/' || $parent === '.') {
        return '/crm';
    }

    return rtrim($parent, '/') . '/crm';
}

function streaming_favicon_link(): string
{
    $f = streaming_favicon();
    $href = htmlspecialchars($f['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $type = htmlspecialchars($f['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<link rel="icon" href="' . $href . '" type="' . $type . '">';
}
