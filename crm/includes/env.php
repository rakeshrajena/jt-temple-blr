<?php
declare(strict_types=1);

/** True on AMPPS/localhost — parent public_html/.env and crm/.env.local may override DB_*. */
function env_local_dev_host(): bool
{
    if (PHP_SAPI === 'cli') {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && !preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host)) {
            return false;
        }
        $app = dirname(__DIR__);
        return str_contains(strtolower($app), 'ampps')
            || str_contains(strtolower($app), 'xampp')
            || is_file($app . '/.env.local');
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return $host === '' || preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) === 1;
}

/** @return array<string, string> */
function env_values(): array
{
    static $values = null;
    if (is_array($values)) {
        return $values;
    }
    $app = dirname(__DIR__);
    $values = [];
    $paths = [$app . '/.env'];
    // Parent public_html/.env is AMPPS-only — on sjtbengaluru.org it must not replace cPanel DB creds.
    $parent = $app . '/../.env';
    if (env_local_dev_host()) {
        $paths[] = $parent;
    }
    foreach ($paths as $path) {
        if (!is_file($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            continue;
        }
        $parsed = env_parse($raw);
        if ($path === $parent) {
            $parsed = array_intersect_key($parsed, array_flip(env_local_override_keys()));
        }
        $values = array_merge($values, $parsed);
    }
    $localApp = $app . '/.env.local';
    if (env_local_dev_host() && is_file($localApp)) {
        $raw = file_get_contents($localApp);
        if (is_string($raw)) {
            $parsed = env_parse($raw);
            $parsed = array_intersect_key($parsed, array_flip(env_local_override_keys()));
            $values = array_merge($values, $parsed);
        }
    }
    return $values;
}

/** Keys allowed from repo-root .env on AMPPS (production values stay in crm/.env). */
function env_local_override_keys(): array
{
    return [
        'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PASS',
        'MYSQL_USER', 'MYSQL_PASSWORD', 'MYSQL_DATABASE',
        'BOOKING_APP_URL', 'BOOKING_ADMIN_URL', 'INSTALL_TOKEN',
        'SMTP_ONLY_LOCAL_MTA', 'SMTP_PREFER_LOCAL_MTA', 'SMTP_SKIP_LOCAL_MTA', 'SMTP_DISABLE_PHP_MAIL',
        'SMTP_TRY_GODADDY_RELAY', 'GODADDY_SMTP_RELAY',
    ];
}

/** @return array<string, string> */
function env_parse(string $raw): array
{
    $values = [];
    foreach (preg_split('/\R/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
            continue;
        }
        $value = trim($value);
        if (
            strlen($value) >= 2
            && (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            )
        ) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }
    return $values;
}

function env_value(string $key, string $fallback = ''): string
{
    $value = trim(env_values()[$key] ?? '');
    return $value !== '' ? $value : $fallback;
}

/** @param list<string> $keys */
function env_first(array $keys, string $fallback = ''): string
{
    foreach ($keys as $key) {
        $value = trim(env_values()[$key] ?? '');
        if ($value !== '') {
            return $value;
        }
    }
    return $fallback;
}

function install_token_matches(string $given): bool
{
    $token = env_value('INSTALL_TOKEN');
    if (strlen($token) < 16 || strlen($given) > 200) {
        return false;
    }
    return hash_equals($token, $given);
}
