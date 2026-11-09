<?php
declare(strict_types=1);

namespace SJT\Repo;

use SJT\Core\Database;
use SJT\Core\Env;
use SJT\Service\StaffAccounts;

/** Which table supplies devotee-facing food / prasadam coupon lines. */
final class CouponCatalog
{
    public const ERP_TABLE = 'food_coupon_catalog';
    public const LEGACY_TABLE = 'booking_coupons';

    public function __construct(private Database $db)
    {
    }

    /** Online menu is edited in ERP (Booking admin is read-only). */
    public function usesErpCatalog(): bool
    {
        return $this->usesSharedCatalog();
    }

    /** Devotee-facing rows and admin read-only when ERP shares this database. */
    public function usesSharedCatalog(): bool
    {
        if (!Env::bool('USE_ERP_COUPON_CATALOG', true)) {
            return false;
        }
        if (!$this->tableExists(self::ERP_TABLE)) {
            return false;
        }
        if ($this->tableExists('donors') || $this->tableExists('food_coupon_batches')) {
            return true;
        }
        try {
            return (new StaffAccounts($this->db))->sharedWithErp();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return self::ERP_TABLE|self::LEGACY_TABLE */
    public function table(): string
    {
        return $this->usesSharedCatalog() ? self::ERP_TABLE : self::LEGACY_TABLE;
    }

    /** @return list<array<string,mixed>> */
    public function rows(bool $activeOnly = true): array
    {
        $t = $this->table();
        $sql = 'SELECT * FROM ' . $t . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id';
        $rows = $this->db->all($sql);
        foreach ($rows as &$r) {
            $r = $this->hydrate($r);
        }
        unset($r);
        return $rows;
    }

    public function find(int $id): ?array
    {
        $t = $this->table();
        $r = $this->db->one('SELECT * FROM ' . $t . ' WHERE id = ?', [$id]);
        if (!$r) {
            return null;
        }
        return $this->hydrate($r);
    }

    /** @param array<string,mixed> $r */
    private function hydrate(array $r): array
    {
        $r['id'] = (int) $r['id'];
        $r['price_paise'] = (int) $r['price_paise'];
        $r['max_per_booking'] = (int) $r['max_per_booking'];
        $daily = $r['daily_max'] ?? null;
        $r['daily_max'] = ($daily === null || $daily === '' || (int) $daily < 1) ? null : (int) $daily;
        $r['is_active'] = (bool) (int) $r['is_active'];
        $r['sort_order'] = (int) $r['sort_order'];
        return $r;
    }

    private function tableExists(string $name): bool
    {
        try {
            if ($this->db->driver === 'mysql') {
                return (bool) $this->db->value(
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                    [$name]
                );
            }
            return (bool) $this->db->value(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$name]
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
