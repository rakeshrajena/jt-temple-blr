<?php
declare(strict_types=1);

/**
 * Favicon — same endpoint as public home (CRM Settings › Identity logo).
 */
function streaming_favicon_link(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/Streaming/index.php'));
    $base = rtrim(dirname($script), '/');
    $parent = dirname($base);
    if ($parent === '/' || $parent === '.' || $parent === '') {
        $href = '/home/api/favicon.php';
    } else {
        $href = rtrim($parent, '/') . '/home/api/favicon.php';
    }
    $href = htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<link rel="icon" href="' . $href . '" type="image/png">' . "\n"
        . '<link rel="apple-touch-icon" href="' . $href . '">';
}