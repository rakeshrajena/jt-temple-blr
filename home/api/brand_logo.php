<?php
declare(strict_types=1);

/**
 * Resolve temple brand logo on disk — same order as CRM app_logo_url / Booking TempleLogo.
 */
function home_api_brand_logo_path(string $crmRoot, ?PDO $pdo = null): ?string
{
    $brand = $crmRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'brand';
    $print = $brand . DIRECTORY_SEPARATOR . 'logo-print.png';
    if (is_file($print)) {
        return $print;
    }

    if ($pdo instanceof PDO) {
        try {
            $has = $pdo->query("SHOW TABLES LIKE 'app_settings'")->fetchColumn();
            if ($has !== false) {
                $file = $pdo->query(
                    "SELECT setting_value FROM app_settings WHERE setting_key = 'brand_logo' LIMIT 1"
                )->fetchColumn();
                $file = is_string($file) ? trim($file) : '';
                if (preg_match('/^logo\.[a-z0-9]{1,8}$/i', $file) === 1) {
                    $candidate = $brand . DIRECTORY_SEPARATOR . $file;
                    if (is_file($candidate)) {
                        return realpath($candidate) ?: $candidate;
                    }
                }
            }
        } catch (Throwable) {
            // continue
        }
    }

    foreach (['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp', 'logo.gif'] as $name) {
        $candidate = $brand . DIRECTORY_SEPARATOR . $name;
        if (is_file($candidate)) {
            return realpath($candidate) ?: $candidate;
        }
    }

    $svg = $crmRoot . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'logo.svg';
    if (is_file($svg)) {
        return $svg;
    }

    $fallback = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . 'branding' . DIRECTORY_SEPARATOR . 'logo.jpg';
    if (is_file($fallback)) {
        return realpath($fallback) ?: $fallback;
    }

    return null;
}

function home_api_brand_mime(?string $path): string
{
    if ($path === null) {
        return 'image/svg+xml';
    }

    return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/svg+xml',
    };
}

/** URL to CRM brand/logo route or static fallback (for JSON / JS). */
function home_api_brand_public_url(string $crmRoot, ?string $path): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = preg_replace('#/home/api/.*$#', '', $script);
    if (!is_string($base) || $base === '') {
        $base = '';
    }

    if ($path === null) {
        $svg = $crmRoot . '/static/logo.svg';
        $v = is_file($svg) ? (string) filemtime($svg) : '1';

        return rtrim($base, '/') . '/crm/static/logo.svg?v=' . rawurlencode($v);
    }

    if (str_contains(str_replace('\\', '/', $path), '/home/photos/branding/')) {
        $rel = 'home/photos/branding/logo.jpg';
        $v = is_file($path) ? (string) filemtime($path) : '1';

        return rtrim($base, '/') . '/' . $rel . '?v=' . rawurlencode($v);
    }

    $crmIndex = rtrim($base, '/') . '/crm/index.php';

    return $crmIndex . '?' . http_build_query(['r' => 'brand/logo', 'v' => (string) filemtime($path)]);
}

/** Web path to unified favicon endpoint (same icon everywhere on the public site). */
function home_api_favicon_url(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = preg_replace('#/(Streaming|maintenance)/.*$#', '', $script);
    if ($base === '' || $base === '.') {
        $base = preg_replace('#/home/api/.*$#', '', $script);
    }
    $base = rtrim((string) $base, '/');

    return ($base === '' ? '' : $base) . '/home/api/favicon.php';
}