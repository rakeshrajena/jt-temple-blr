<?php
declare(strict_types=1);

require __DIR__ . '/brand_logo.php';

$crmRoot = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'crm');
if ($crmRoot === false || !is_file($crmRoot . '/config.php')) {
    http_response_code(503);
    exit;
}

define('APP_ROOT', $crmRoot);
require APP_ROOT . '/config.php';
require APP_ROOT . '/includes/db.php';

header('Cache-Control: public, max-age=300');

try {
    $pdo = db_connect();
    $path = home_api_brand_logo_path($crmRoot, $pdo);
} catch (Throwable) {
    $path = home_api_brand_logo_path($crmRoot, null);
}

if ($path === null || !is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = home_api_brand_mime($path);
$etag = '"' . md5($path . ':' . filemtime($path) . ':' . filesize($path)) . '"';
header('ETag: ' . $etag);
header('Content-Type: ' . $mime);

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Length: ' . (string) filesize($path));
readfile($path);