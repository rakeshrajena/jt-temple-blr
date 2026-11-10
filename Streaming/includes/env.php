<?php
declare(strict_types=1);

/** @return array<string, string> */
function streaming_env_values(): array
{
    static $values = null;
    if (is_array($values)) {
        return $values;
    }
    $values = [];
    $path = dirname(__DIR__) . '/.env';
    if (!is_file($path) || !is_readable($path)) {
        return $values;
    }
    $raw = file_get_contents($path);
    if (!is_string($raw)) {
        return $values;
    }
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

function streaming_env(string $key, string $fallback = ''): string
{
    $value = trim(streaming_env_values()[$key] ?? '');
    return $value !== '' ? $value : $fallback;
}
