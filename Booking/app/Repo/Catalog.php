<?php
declare(strict_types=1);

namespace SJT\Repo;

use SJT\Core\Database;

/** Services, food coupons, time slots and blocked dates. */
final class Catalog
{
    public function __construct(private Database $db)
    {
    }

    public function services(bool $activeOnly = true): array
    {
        $rows = $this->db->all('SELECT * FROM services' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id');
        return array_map([$this, 'hydrateService'], $rows);
    }

    public function service(int $id): ?array
    {
        $r = $this->db->one('SELECT * FROM services WHERE id = ?', [$id]);
        return $r ? $this->hydrateService($r) : null;
    }

    private function hydrateService(array $r): array
    {
        $r['id'] = (int) $r['id'];
        foreach (['price_paise', 'max_participants', 'sort_order'] as $k) {
            $r[$k] = (int) $r[$k];
        }
        foreach (['requires_approval', 'requires_slot', 'collect_participants', 'allow_food', 'is_food_only', 'is_combinable', 'is_active'] as $k) {
            $r[$k] = (bool) (int) $r[$k];
        }
        $r['daily_capacity'] = $r['daily_capacity'] === null ? null : (int) $r['daily_capacity'];
        $r['lead_days'] = $r['lead_days'] === null ? null : (int) $r['lead_days'];
        $r['fields'] = json_decode((string) ($r['fields_json'] ?? '[]'), true) ?: [];
        return $r;
    }

    public function coupons(bool $activeOnly = true): array
    {
        $rows = $this->db->all('SELECT * FROM booking_coupons' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id');
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['price_paise'] = (int) $r['price_paise'];
            $r['max_per_booking'] = (int) $r['max_per_booking'];
            $r['is_active'] = (bool) (int) $r['is_active'];
        }
        return $rows;
    }

    public function coupon(int $id): ?array
    {
        foreach ($this->coupons(false) as $c) {
            if ($c['id'] === $id) {
                return $c;
            }
        }
        return null;
    }

    public function slots(?int $serviceId = null, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM time_slots WHERE 1=1';
        $p = [];
        if ($serviceId !== null) {
            $sql .= ' AND service_id = ?';
            $p[] = $serviceId;
        }
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $rows = $this->db->all($sql . ' ORDER BY service_id, sort_order, start_time, id', $p);
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['service_id'] = (int) $r['service_id'];
            $r['capacity'] = $r['capacity'] === null ? null : (int) $r['capacity'];
            $r['is_active'] = (bool) (int) $r['is_active'];
        }
        return $rows;
    }

    public function slot(int $id): ?array
    {
        $r = $this->db->one('SELECT * FROM time_slots WHERE id = ?', [$id]);
        if (!$r) {
            return null;
        }
        $r['id'] = (int) $r['id'];
        $r['service_id'] = (int) $r['service_id'];
        $r['capacity'] = $r['capacity'] === null ? null : (int) $r['capacity'];
        $r['is_active'] = (bool) (int) $r['is_active'];
        return $r;
    }

    public function blockedDates(?string $from = null): array
    {
        $sql = 'SELECT b.*, s.name AS service_name FROM blocked_dates b LEFT JOIN services s ON s.id = b.service_id';
        $p = [];
        if ($from) {
            $sql .= ' WHERE b.block_date >= ?';
            $p[] = $from;
        }
        return $this->db->all($sql . ' ORDER BY b.block_date, b.id', $p);
    }

    /** Reason the date is blocked for this service (or for everything), else null. */
    public function blockReason(int $serviceId, string $date): ?string
    {
        $r = $this->db->one(
            'SELECT reason FROM blocked_dates WHERE block_date = ? AND (service_id IS NULL OR service_id = ?) ORDER BY service_id IS NULL DESC LIMIT 1',
            [$date, $serviceId]
        );
        return $r ? ($r['reason'] ?: 'Not available on this date') : null;
    }
}
