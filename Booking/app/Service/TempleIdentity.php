<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\App;

/**
 * Temple name, place, and contact — canonical values live in CRM Identity (`app_settings`),
 * with fallback to Booking `settings` and then install defaults.
 */
final class TempleIdentity
{
    public const DEFAULT_NAME = 'Shree Jagannath Temple';
    public const DEFAULT_PLACE = 'Sarjapura, Bengaluru';

    /** @var array<string, string>|null */
    private static ?array $brand = null;

    private const KEY_MAP = [
        'temple_name' => 'brand_name',
        'temple_address' => 'brand_address',
        'temple_phone' => 'brand_phone',
        'temple_email' => 'brand_email',
        'temple_website' => 'brand_website',
        'temple_gstin' => 'brand_gstin',
    ];

    /** @return array<string, string> */
    private static function brand(): array
    {
        if (self::$brand !== null) {
            return self::$brand;
        }
        self::$brand = [];
        try {
            $db = App::db();
            $exists = $db->value(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'app_settings' LIMIT 1"
            );
            if (!$exists) {
                return self::$brand;
            }
            $keys = array_values(self::KEY_MAP);
            $keys[] = 'brand_place';
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            foreach ($db->all(
                "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)",
                $keys
            ) as $row) {
                self::$brand[(string) $row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (\Throwable) {
            self::$brand = [];
        }

        return self::$brand;
    }

    public static function get(string $bookingKey, ?string $default = null): ?string
    {
        $brand = self::brand();
        $brandKey = self::KEY_MAP[$bookingKey] ?? null;
        if ($brandKey !== null) {
            $v = trim($brand[$brandKey] ?? '');
            if ($v !== '') {
                return $v;
            }
        }

        $fromTable = App::settings()->get($bookingKey);
        if ($fromTable !== null && $fromTable !== '') {
            return $fromTable;
        }

        if ($default !== null) {
            return $default;
        }

        return match ($bookingKey) {
            'temple_name' => self::DEFAULT_NAME,
            default => null,
        };
    }

    public static function name(): string
    {
        return self::get('temple_name', self::DEFAULT_NAME) ?? self::DEFAULT_NAME;
    }

    public static function place(): string
    {
        $brand = self::brand();
        $place = trim($brand['brand_place'] ?? '');

        return $place !== '' ? $place : self::DEFAULT_PLACE;
    }

    /** Browser title suffix: name and short place line. */
    public static function titleSuffix(): string
    {
        $name = self::name();
        $place = self::place();

        return $place !== '' ? $name . ', ' . $place : $name;
    }
}