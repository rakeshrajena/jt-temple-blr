<?php
declare(strict_types=1);

namespace SJT\Core;

use SJT\Repo\Settings;

/** Small service locator: one DB connection and settings cache per request. */
final class App
{
    private static ?Database $db = null;
    private static ?Settings $settings = null;
    private static ?string $basePath = null;
    private static ?Request $request = null;

    public static function db(): Database
    {
        return self::$db ??= Database::fromEnv();
    }

    public static function setDb(Database $db): void
    {
        self::$db = $db;
        self::$settings = null;
    }

    public static function settings(): Settings
    {
        return self::$settings ??= new Settings(self::db());
    }

    public static function request(): Request
    {
        return self::$request ??= Request::fromGlobals();
    }

    public static function setRequest(Request $r): void
    {
        self::$request = $r;
    }

    /** URL path prefix, e.g. "/Booking" for https://sjtbengaluru.org/Booking */
    public static function basePath(): string
    {
        if (self::$basePath === null) {
            $p = parse_url((string) Env::get('APP_URL', ''), PHP_URL_PATH) ?: '';
            self::$basePath = rtrim($p, '/');
        }
        return self::$basePath;
    }

    public static function publicPath(): string
    {
        return APP_ROOT;
    }

    public static function isProduction(): bool
    {
        return Env::get('APP_ENV', 'production') === 'production';
    }

    public static function debug(): bool
    {
        return Env::bool('APP_DEBUG') && !self::isProduction();
    }
}
