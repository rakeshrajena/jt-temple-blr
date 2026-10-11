<?php
declare(strict_types=1);

namespace SJT\Repo;

use SJT\Core\Database;

final class Bookings
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM bookings WHERE id = ?', [$id]);
    }

    public function findByNo(string $no): ?array
    {
        return $this->db->one('SELECT * FROM bookings WHERE booking_no = ?', [strtoupper(trim($no))]);
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        return $this->db->one('SELECT * FROM bookings WHERE idempotency_key = ?', [$key]);
    }

    public function items(int $bookingId): array
    {
        $rows = $this->db->all('SELECT * FROM booking_items WHERE booking_id = ? ORDER BY sort_order, id', [$bookingId]);
        foreach ($rows as &$r) {
            $r['details'] = $r['details_json'] ? (json_decode($r['details_json'], true) ?: []) : [];
        }
        return $rows;
    }

    public function payments(int $bookingId): array
    {
        return $this->db->all('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC', [$bookingId]);
    }

    public function notifications(int $bookingId): array
    {
        return $this->db->all('SELECT * FROM notifications WHERE booking_id = ? ORDER BY id DESC', [$bookingId]);
    }

    public function audit(int $bookingId): array
    {
        return $this->db->all('SELECT * FROM audit_log WHERE booking_id = ? ORDER BY id DESC', [$bookingId]);
    }

    /** "Special Puja, Vehicle Puja" */
    public function serviceSummary(int $bookingId): string
    {
        $names = $this->db->all("SELECT name FROM booking_items WHERE booking_id = ? AND item_type = 'service' ORDER BY sort_order", [$bookingId]);
        return implode(', ', array_column($names, 'name'));
    }

    /** "Morning (7:00 AM – 9:00 AM)" (distinct slot labels) */
    public function slotSummary(int $bookingId): string
    {
        $rows = $this->db->all("SELECT DISTINCT slot_label FROM booking_items WHERE booking_id = ? AND slot_label IS NOT NULL", [$bookingId]);
        return implode('; ', array_column($rows, 'slot_label'));
    }

    /** "Food Coupon (Standard) × 4, Food Coupon (Special) × 2" or "None" */
    public function foodSummary(int $bookingId): string
    {
        $rows = $this->db->all("SELECT name, quantity FROM booking_items WHERE booking_id = ? AND item_type = 'food' ORDER BY sort_order", [$bookingId]);
        return $rows ? implode(', ', array_map(fn ($r) => $r['name'] . ' × ' . $r['quantity'], $rows)) : 'None';
    }

    /**
     * Admin search. Filters: q (booking no / name / mobile), service_id, payment_status,
     * booking_status, approval_status, date_from, date_to (service date), created_from, created_to.
     */
    public function search(array $f, int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = $this->filters($f);
        $total = (int) $this->db->value("SELECT COUNT(*) FROM bookings b WHERE $where", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->all(
            "SELECT b.* FROM bookings b WHERE $where ORDER BY b.created_at DESC, b.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );
        foreach ($rows as &$r) {
            $r['services'] = $this->serviceSummary((int) $r['id']);
        }
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    /** All matching bookings (for CSV export), oldest first. */
    public function export(array $f): array
    {
        [$where, $params] = $this->filters($f);
        return $this->db->all("SELECT b.* FROM bookings b WHERE $where ORDER BY b.created_at, b.id", $params);
    }

    private function filters(array $f): array
    {
        $w = ['1=1'];
        $p = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $digits = preg_replace('/\D/', '', $q);
            $parts = ["b.booking_no LIKE ? ESCAPE '!'", "b.devotee_name LIKE ? ESCAPE '!'", "b.invoice_no LIKE ? ESCAPE '!'", "b.payment_ref LIKE ? ESCAPE '!'"];
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            array_push($p, $like, $like, $like, $like);
            if (strlen($digits) >= 4) {
                $parts[] = 'b.mobile LIKE ?';
                $p[] = '%' . $digits . '%';
            }
            $w[] = '(' . implode(' OR ', $parts) . ')';
        }
        if (!empty($f['service_id'])) {
            $w[] = "EXISTS (SELECT 1 FROM booking_items i WHERE i.booking_id = b.id AND i.item_type = 'service' AND i.service_id = ?)";
            $p[] = (int) $f['service_id'];
        }
        if (($f['has_food'] ?? '') === '1') {
            $w[] = "EXISTS (SELECT 1 FROM booking_items i WHERE i.booking_id = b.id AND i.item_type = 'food')";
        }
        foreach (['payment_status', 'booking_status', 'approval_status'] as $k) {
            if (!empty($f[$k]) && is_string($f[$k])) {
                $w[] = "b.$k = ?";
                $p[] = $f[$k];
            }
        }
        foreach (['date_from' => 'b.service_date >= ?', 'date_to' => 'b.service_date <= ?'] as $k => $sql) {
            if (!empty($f[$k]) && \SJT\Service\Validator::date((string) $f[$k])) {
                $w[] = $sql;
                $p[] = $f[$k];
            }
        }
        if (!empty($f['created_from']) && \SJT\Service\Validator::date((string) $f['created_from'])) {
            $w[] = 'b.created_at >= ?';
            $p[] = $f['created_from'] . ' 00:00:00';
        }
        if (!empty($f['created_to']) && \SJT\Service\Validator::date((string) $f['created_to'])) {
            $w[] = 'b.created_at <= ?';
            $p[] = $f['created_to'] . ' 23:59:59';
        }
        return [implode(' AND ', $w), $p];
    }
}
