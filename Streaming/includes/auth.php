<?php
declare(strict_types=1);

const STREAMING_ROOT = __DIR__ . '/..';

require_once __DIR__ . '/env.php';

/** Value from Streaming/.env — required to save stream URL. */
function streaming_save_token_expected(): string
{
    return trim(streaming_env('STREAM_SAVE_TOKEN', ''));
}

function streaming_save_token_valid(string $given): bool
{
    $expected = streaming_save_token_expected();
    if ($expected === '') {
        return false;
    }
    return hash_equals($expected, trim($given));
}

function streaming_boot_session(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        $dir = STREAMING_ROOT . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_name('streaming_ui');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => streaming_public_dir() . '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
    }
    $booted = true;
}

function streaming_public_dir(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $looksLikeFilesystem = $script !== '' && (
        preg_match('#^[A-Za-z]:/#', $script) === 1
        || str_contains($script, '/Program Files/')
        || str_contains($script, '/Ampps/')
    );
    if ($script !== '' && !$looksLikeFilesystem && str_starts_with($script, '/')) {
        return rtrim(dirname($script), '/');
    }
    $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (is_string($uri) && $uri !== '') {
        $uri = rtrim(str_replace('\\', '/', $uri), '/');
        if (str_ends_with($uri, '.php')) {
            return rtrim(dirname($uri), '/') ?: '/Streaming';
        }
        return $uri !== '' ? $uri : '/Streaming';
    }
    return '/Streaming';
}

function streaming_public_path(string $suffix = ''): string
{
    $base = streaming_public_dir();
    $suffix = ltrim(str_replace('\\', '/', $suffix), '/');
    return $suffix === '' ? $base : $base . '/' . $suffix;
}

function streaming_set_flash(string $type, string $message): void
{
    $_SESSION['streaming_flash'] = [$type, $message];
}

/** @return array{0:string,1:string}|null */
function streaming_pull_flash(): ?array
{
    if (empty($_SESSION['streaming_flash'])) {
        return null;
    }
    $flash = $_SESSION['streaming_flash'];
    unset($_SESSION['streaming_flash']);
    return is_array($flash) && count($flash) === 2 ? [$flash[0], $flash[1]] : null;
}

function streaming_save_stream_from_post(): never
{
    streaming_boot_session();
    $home = streaming_public_path('index.php');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Location: ' . $home);
        exit;
    }

    if (streaming_save_token_expected() === '') {
        streaming_set_flash('error', 'Stream save is not configured on the server.');
        header('Location: ' . $home . '?open=1');
        exit;
    }

    $given = (string) ($_POST['stream_key'] ?? '');
    if (!streaming_save_token_valid($given)) {
        streaming_set_flash('error', 'Incorrect staff save key.');
        header('Location: ' . $home . '?open=1');
        exit;
    }

    $result = streaming_write_url((string) ($_POST['stream_url'] ?? ''));
    if (!$result['ok']) {
        streaming_set_flash('error', $result['error'] ?? 'Could not save.');
        header('Location: ' . $home . '?open=1');
        exit;
    }

    streaming_set_flash('ok', 'Live stream URL saved.');
    header('Location: ' . $home);
    exit;
}
