<?php
declare(strict_types=1);

/** Title suffix from CRM Identity (`app_settings`), no full CRM bootstrap. */
function streaming_identity_title_suffix(): string
{
    static $cached = null;
    if (is_string($cached)) {
        return $cached;
    }
    $name = '';
    $place = '';
    $pdo = streaming_identity_pdo();
    if ($pdo instanceof PDO) {
        $stmt = $pdo->query(
            "SELECT setting_key, setting_value FROM app_settings
             WHERE setting_key IN ('brand_name', 'brand_place')"
        );
        if ($stmt !== false) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['setting_key'] === 'brand_name') {
                    $name = trim((string) $row['setting_value']);
                }
                if ($row['setting_key'] === 'brand_place') {
                    $place = trim((string) $row['setting_value']);
                }
            }
        }
    }
    if ($name === '') {
        $name = 'Shree Jagannath Temple';
    }
    if ($place === '') {
        $place = 'Sarjapura, Bengaluru';
    }
    $cached = $place !== '' ? $name . ', ' . $place : $name;

    return $cached;
}

function streaming_identity_pdo(): ?PDO
{
    static $pdo = null;
    if ($pdo === false) {
        return null;
    }
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $crmEnv = dirname(__DIR__, 2) . '/crm/.env';
    $vars = streaming_env_file($crmEnv);
    $host = $vars['DB_HOST'] ?? 'localhost';
    $name = $vars['DB_NAME'] ?? ($vars['MYSQL_DATABASE'] ?? '');
    $user = $vars['DB_USER'] ?? ($vars['MYSQL_USER'] ?? '');
    $pass = $vars['DB_PASS'] ?? ($vars['DB_PASSWORD'] ?? ($vars['MYSQL_PASSWORD'] ?? ''));
    if ($name === '') {
        $pdo = false;

        return null;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable) {
        $pdo = false;

        return null;
    }

    return $pdo;
}

/** @return array<string, string> */
function streaming_env_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $out[$k] = trim($v, " \t\"'");
    }

    return $out;
}