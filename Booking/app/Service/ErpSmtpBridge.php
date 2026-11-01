<?php
declare(strict_types=1);

namespace SJT\Service;

/** Sends mail through ERP `send_smtp_message()` (same as donation receipts). */
final class ErpSmtpBridge
{
    private static bool $loaded = false;

    /**
     * @param array<string, string> $settings ERP messaging settings (smtp_* keys)
     * @param array{filename: string, content: string, mime: string}|null $attachment
     */
    public static function send(
        array $settings,
        string $to,
        string $subject,
        string $body,
        ?array $attachment,
        string $fromDisplayName,
    ): ?string {
        self::ensureLoaded($fromDisplayName);
        return send_smtp_message($settings, $to, $subject, $body, $attachment);
    }

    private static function ensureLoaded(string $fromDisplayName): void
    {
        if (self::$loaded) {
            return;
        }
        if (!defined('APP_NAME')) {
            define('APP_NAME', $fromDisplayName !== '' ? $fromDisplayName : 'Shree Jagannath Temple');
        }
        if (!defined('APP_PLACE')) {
            define('APP_PLACE', 'Sarjapura, Bengaluru');
        }
        if (!function_exists('db_one')) {
            /** @param array<int, mixed> $params */
            function db_one(string $sql, array $params = []): ?array
            {
                $row = \SJT\Core\App::db()->one($sql, $params);
                return $row ?: null;
            }
        }
        if (!function_exists('db_all')) {
            /** @param array<int, mixed> $params */
            function db_all(string $sql, array $params = []): array
            {
                return \SJT\Core\App::db()->all($sql, $params);
            }
        }
        $erp = TempleLogo::erpRoot();
        require_once $erp . '/includes/settings.php';
        if (!function_exists('brand_logo_email_image')) {
            require_once $erp . '/includes/brand_mark.php';
        }
        self::$loaded = true;
    }
}
