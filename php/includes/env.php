<?php
declare(strict_types=1);

/** @return array<string, string> */
function env_values(): array
{
    static $values = null;
    if (is_array($values)) {
        return $values;
    }
    $app = dirname(__DIR__);
    $values = [];
    // The file next to index.php is the one uploaded to the server.
    // A .env one folder above it, on this computer only, overrides that file.
    foreach ([$app . '/.env', $app . '/../.env'] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            continue;
        }
        $values = array_merge($values, env_parse($raw));
    }
    return $values;
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
