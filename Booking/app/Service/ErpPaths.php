<?php
declare(strict_types=1);

namespace SJT\Service;

/** Locate the temple ERP folder (crm/ or legacy php/) beside Booking/. */
final class ErpPaths
{
    public static function root(): string
    {
        $parent = dirname(BOOKING_ROOT);
        foreach (['crm', 'php'] as $name) {
            $path = $parent . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                return $path;
            }
        }
        return $parent . DIRECTORY_SEPARATOR . 'crm';
    }
}
