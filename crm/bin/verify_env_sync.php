<?php
declare(strict_types=1);

/**
 * Pre-upload: ensure production .env files agree (ignores .env.local — upload content only).
 *   php crm/bin/verify_env_sync.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$crmRoot = dirname(__DIR__);
$repoRoot = dirname($crmRoot);

require_once $crmRoot . '/includes/env.php';

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL {$msg}\n");
    exit(1);
};
$ok = static function (string $msg): void {
    fwrite(STDOUT, "ok  {$msg}\n");
};

$readEnv = static function (string $path): array {
    if (!is_readable($path)) {
        return [];
    }
    return env_parse((string) file_get_contents($path));
};

$crmEnvPath = $crmRoot . '/.env';
$crmParsed = $readEnv($crmEnvPath);
if ($crmParsed === []) {
    $fail('crm/.env missing — copy crm/env.example → crm/.env');
}

$bookingEnvPath = $repoRoot . '/Booking/.env';
$bookingParsed = $readEnv($bookingEnvPath);
if ($bookingParsed === []) {
    $fail('Booking/.env missing — copy Booking/.env.example → Booking/.env');
}

$dbKeys = ['DB_HOST', 'DB_NAME', 'DB_USER'];
$crmDb = [
    'DB_HOST' => trim($crmParsed['DB_HOST'] ?? 'localhost'),
    'DB_NAME' => trim($crmParsed['DB_NAME'] ?? $crmParsed['MYSQL_DATABASE'] ?? ''),
    'DB_USER' => trim($crmParsed['DB_USER'] ?? $crmParsed['MYSQL_USER'] ?? ''),
];
$bookingDb = [
    'DB_HOST' => trim($bookingParsed['DB_HOST'] ?? 'localhost'),
    'DB_NAME' => trim($bookingParsed['DB_NAME'] ?? ''),
    'DB_USER' => trim($bookingParsed['DB_USER'] ?? ''),
];

foreach ($dbKeys as $key) {
    if ($crmDb[$key] === '' || $bookingDb[$key] === '') {
        $fail("{$key} must be set in both crm/.env and Booking/.env");
    }
    if ($crmDb[$key] !== $bookingDb[$key]) {
        $fail("{$key} mismatch: crm={$crmDb[$key]} booking={$bookingDb[$key]}");
    }
}

if (strtolower($crmDb['DB_USER']) === 'root') {
    $fail('crm/.env DB_USER must be the cPanel MySQL user for upload (not root)');
}

$crmToken = trim($crmParsed['INSTALL_TOKEN'] ?? '');
$bookingToken = trim($bookingParsed['INSTALL_TOKEN'] ?? '');
if ($crmToken === '' || strlen($crmToken) < 16) {
    $fail('INSTALL_TOKEN in crm/.env must be at least 16 characters until install completes');
}
if (!hash_equals($crmToken, $bookingToken)) {
    $fail('INSTALL_TOKEN must match in crm/.env and Booking/.env');
}

$appUrl = rtrim(trim($crmParsed['APP_URL'] ?? ''), '/');
if (!str_ends_with(strtolower($appUrl), '/crm')) {
    $fail('crm/.env APP_URL must end with /crm');
}

$bookingUrl = rtrim(trim($bookingParsed['APP_URL'] ?? ''), '/');
if (!str_ends_with(strtolower($bookingUrl), '/booking')) {
    $fail('Booking/.env APP_URL must end with /Booking');
}

$erp = rtrim(trim($bookingParsed['ERP_APP_URL'] ?? ''), '/');
if ($erp !== $appUrl) {
    $fail('Booking/.env ERP_APP_URL must equal crm/.env APP_URL');
}

$streamingEnv = $repoRoot . '/Streaming/.env';
$streamParsed = $readEnv($streamingEnv);
if ($streamParsed === []) {
    $fail('Streaming/.env missing — copy Streaming/.env.example');
}
if (strlen(trim($streamParsed['STREAM_SAVE_TOKEN'] ?? '')) < 16) {
    $fail('Streaming/.env STREAM_SAVE_TOKEN must be at least 16 characters');
}

$ok('crm + Booking DB_* and INSTALL_TOKEN aligned');
$ok('APP_URL / ERP_APP_URL production paths');
$ok('Streaming/.env present');
fwrite(STDOUT, "\nUpload: public_html/crm/.env, public_html/Booking/.env, public_html/Streaming/.env\n");
fwrite(STDOUT, "Do not upload: */.env.local, repo-root .env\n");
exit(0);