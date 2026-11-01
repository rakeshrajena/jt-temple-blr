<?php
declare(strict_types=1);

/** Shared staff session between ERP (php/) and Booking (/Booking). */
function staff_session_save_path(): string
{
    $dir = APP_ROOT . '/storage/sessions';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create storage/sessions');
    }
    return $dir;
}

function staff_session_cookie_path(): string
{
    $override = trim(env_value('STAFF_SESSION_PATH', ''));
    if ($override !== '') {
        return rtrim(str_replace('\\', '/', $override), '/') ?: '/';
    }
    $dir = app_dir_url();
    if ($dir === '' || $dir === '/') {
        return '/';
    }
    $parent = dirname(str_replace('\\', '/', $dir));
    if ($parent === '/' || $parent === '.' || $parent === '') {
        return '/';
    }
    return rtrim($parent, '/');
}

function staff_booking_base_path(): string
{
    $override = rtrim(env_value('BOOKING_APP_URL', ''), '/');
    if ($override !== '') {
        $path = parse_url($override, PHP_URL_PATH);
        return is_string($path) ? rtrim($path, '/') : staff_session_cookie_path() . '/Booking';
    }
    $base = staff_session_cookie_path();
    return ($base === '/' ? '' : $base) . '/Booking';
}

function staff_session_clear_legacy_cookies(): void
{
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $shared = staff_session_cookie_path();
    $sharedPath = ($shared === '/' ? '/' : $shared . '/');
    $paths = [];
    $phpDir = app_dir_url();
    if ($phpDir !== '' && $phpDir !== '/') {
        $paths[] = $phpDir . '/';
    }
    $booking = staff_booking_base_path();
    if ($booking !== '' && $booking !== '/') {
        $paths[] = $booking . '/';
    }
    foreach (array_unique($paths) as $path) {
        if ($path === $sharedPath) {
            continue;
        }
        setcookie(SESSION_NAME, '', time() - 3600, $path, '', $secure, true);
        setcookie('SJTSESS', '', time() - 3600, $path, '', $secure, true);
    }
}

function staff_session_bootstrap(): void
{
    staff_session_clear_legacy_cookies();
    session_save_path(staff_session_save_path());
    $path = staff_session_cookie_path();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => ($path === '/' ? '/' : $path . '/'),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ]);
}

function staff_erp_login_url(string $returnPath = ''): string
{
    $returnPath = trim($returnPath);
    if ($returnPath !== '' && $returnPath[0] !== '/') {
        $returnPath = '';
    }
    $q = ['r' => 'login'];
    if ($returnPath !== '') {
        $q['next'] = $returnPath;
    }
    return url('', $q);
}

function staff_erp_logout_url(): string
{
    return url('', ['r' => 'logout']);
}
