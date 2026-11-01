<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\App;
use SJT\Core\Env;

/** Shared PHP session with ERP so one login covers Booking admin. */
final class ErpStaffSession
{
    public static function enabled(): bool
    {
        if (!Env::bool('STAFF_SSO', true)) {
            return false;
        }
        try {
            return (new StaffAccounts(App::db()))->sharedWithErp();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function sessionName(): string
    {
        return Env::get('STAFF_SESSION_NAME', 'jt_blr_session') ?: 'jt_blr_session';
    }

    public static function cookiePath(): string
    {
        $override = trim((string) Env::get('STAFF_SESSION_PATH', ''));
        if ($override !== '') {
            return rtrim(str_replace('\\', '/', $override), '/') ?: '/';
        }
        $base = App::basePath();
        if ($base === '' || $base === '/') {
            return '/';
        }
        $parent = dirname(str_replace('\\', '/', $base));
        if ($parent === '/' || $parent === '.' || $parent === '') {
            return '/';
        }
        return rtrim($parent, '/');
    }

    public static function savePath(): string
    {
        $configured = trim((string) Env::get('STAFF_SESSION_SAVE_PATH', ''));
        if ($configured !== '') {
            return $configured;
        }
        $sibling = ErpPaths::root() . '/storage/sessions';
        if (is_dir($sibling) || @mkdir($sibling, 0755, true)) {
            return $sibling;
        }
        $local = BOOKING_ROOT . '/storage/sessions';
        if (!is_dir($local)) {
            @mkdir($local, 0755, true);
        }
        return $local;
    }

    public static function clearLegacyCookies(bool $https): void
    {
        if (headers_sent()) {
            return;
        }
        $shared = self::cookiePath();
        $sharedPath = ($shared === '/' ? '/' : $shared . '/');
        $paths = [];
        $booking = App::basePath();
        if ($booking !== '' && $booking !== '/') {
            $paths[] = $booking . '/';
        }
        $erp = self::erpAppBasePath();
        if ($erp !== '' && $erp !== '/') {
            $paths[] = $erp . '/';
        }
        foreach (array_unique($paths) as $path) {
            if ($path === $sharedPath) {
                continue;
            }
            setcookie(self::sessionName(), '', time() - 3600, $path, '', $https, true);
            setcookie('SJTSESS', '', time() - 3600, $path, '', $https, true);
        }
    }

    public static function applyBeforeStart(bool $https): void
    {
        self::clearLegacyCookies($https);
        $dir = self::savePath();
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '7200');
        session_name(self::sessionName());
        $path = self::cookiePath();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => ($path === '/' ? '/' : $path . '/'),
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function erpAppBasePath(): string
    {
        $override = rtrim((string) Env::get('ERP_APP_URL', ''), '/');
        if ($override !== '') {
            $path = parse_url($override, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                return rtrim($path, '/');
            }
        }
        $parent = self::cookiePath();
        return ($parent === '/' ? '' : $parent) . '/php';
    }

    public static function erpLoginUrl(string $bookingReturnPath): string
    {
        $bookingReturnPath = trim($bookingReturnPath);
        if ($bookingReturnPath === '' || $bookingReturnPath[0] !== '/') {
            $bookingReturnPath = App::basePath() . '/admin';
        }
        $erpBase = self::erpAppBasePath();
        $script = ($erpBase === '' ? '' : $erpBase) . '/index.php';
        return $script . '?' . http_build_query(['r' => 'login', 'next' => $bookingReturnPath]);
    }

    public static function erpLogoutUrl(): string
    {
        $erpBase = self::erpAppBasePath();
        $script = ($erpBase === '' ? '' : $erpBase) . '/index.php';
        return $script . '?' . http_build_query(['r' => 'logout']);
    }

    /** Temple ERP › Settings › Online booking › Prasadam menu. */
    public static function erpFoodCouponsUrl(): string
    {
        $erpBase = self::erpAppBasePath();
        $script = ($erpBase === '' ? '' : $erpBase) . '/index.php';
        return $script . '?' . http_build_query(['r' => 'settings']) . '#ob-prasadam';
    }

    /** Printed CU coupon batches (separate from online prasadam menu). */
    public static function erpPrintedCouponsUrl(): string
    {
        $erpBase = self::erpAppBasePath();
        $script = ($erpBase === '' ? '' : $erpBase) . '/index.php';
        return $script . '?' . http_build_query(['r' => 'food/coupons']);
    }

    public static function erpOnlineBookingSettingsUrl(): string
    {
        $erpBase = self::erpAppBasePath();
        $script = ($erpBase === '' ? '' : $erpBase) . '/index.php';
        return $script . '?' . http_build_query(['r' => 'settings']) . '#online-booking';
    }

    public static function erpBookingServicesUrl(): string
    {
        $erpBase = self::erpAppBasePath();
        $script = ($erpBase === '' ? '' : $erpBase) . '/index.php';
        return $script . '?' . http_build_query(['r' => 'settings']) . '#ob-services';
    }

    public static function erpBookingAvailabilityUrl(): string
    {
        $erpBase = self::erpAppBasePath();
        $script = ($erpBase === '' ? '' : $erpBase) . '/index.php';
        return $script . '?' . http_build_query(['r' => 'settings']) . '#ob-availability';
    }

    public static function erpBookingServiceEditUrl(int $id): string
    {
        $erpBase = self::erpAppBasePath();
        $script = ($erpBase === '' ? '' : $erpBase) . '/index.php';
        return $script . '?' . http_build_query(['r' => 'settings/booking-service', 'id' => $id > 0 ? $id : null], PHP_QUERY_RFC3986);
    }
}
