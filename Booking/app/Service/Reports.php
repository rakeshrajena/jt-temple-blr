<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;

/**
 * Booking and revenue summaries. Aggregation is done in PHP so the same code
 * works on MySQL and SQLite; temple volumes are small enough for this.
 *
 *  - "Bookings" / "Booked value": bookings created in the period, excluding
 *    rejected and cancelled ones.
 *  - "Verified revenue": payments verified in the period (by verification date).
 *  - "Refunded": refunds recorded against bookings paid in the period.
 */
final class Reports
{
    public function __construct(private Database $db)
    {
    }

    public function summary(): array
    {
        $t = today();
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');
        return [
            'Today' => $this->totals($t, $t),
            'This week' => $this->totals($weekStart, $t),
            'This month' => $this->totals($monthStart, $t),
        ];
    }

    public function totals(string $from, string $to): array
    {
        $r = $this->rows($from, $to, 'month');
        $sum = ['bookings' => 0, 'value' => 0, 'revenue' => 0, 'refunded' => 0, 'food_persons' => 0, 'pending_value' => 0];
        foreach ($r['rows'] as $row) {
            foreach ($sum as $k => $_) {
                $sum[$k] += $row[$k];
            }
        }
        return $sum;
    }

    /**
     * @param string $group day|week|month
     * @return array{rows: array, services: array, coupons: array}
     */
    public function rows(string $from, string $to, string $group): array
    {
        $key = fn (string $ymd) => match ($group) {
            'week' => date('o-\WW', strtotime($ymd)) . ' (' . date('d M', strtotime('monday this week', strtotime($ymd))) . ')',
            'month' => date('Y-m (M)', strtotime($ymd)),
            default => $ymd,
        };
        $rows = [];
        $blank = ['bookings' => 0, 'value' => 0, 'pending_value' => 0, 'revenue' => 0, 'refunded' => 0, 'food_persons' => 0];
        $services = [];
        $coupons = [];

        $bookings = $this->db->all(
            "SELECT id, created_at, total_paise, booking_status, payment_status FROM bookings
             WHERE created_at >= ? AND created_at <= ? AND booking_status NOT IN ('rejected','cancelled')",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
        $ids = array_column($bookings, 'id');
        foreach ($bookings as $b) {
            $k = $key(substr($b['created_at'], 0, 10));
            $rows[$k] ??= $blank;
            $rows[$k]['bookings']++;
            $rows[$k]['value'] += (int) $b['total_paise'];
            if (!in_array($b['payment_status'], ['paid', 'not_required', 'refunded'], true)) {
                $rows[$k]['pending_value'] += (int) $b['total_paise'];
            }
        }
        if ($ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                $in = implode(',', array_map('intval', $chunk));
                $items = $this->db->all("SELECT i.*, b.created_at FROM booking_items i JOIN bookings b ON b.id = i.booking_id WHERE i.booking_id IN ($in)");
                foreach ($items as $i) {
                    if ($i['item_type'] === 'food') {
                        $rows[$key(substr($i['created_at'], 0, 10))]['food_persons'] += (int) $i['quantity'];
                        $coupons[$i['name']] ??= ['persons' => 0, 'amount' => 0];
                        $coupons[$i['name']]['persons'] += (int) $i['quantity'];
                        $coupons[$i['name']]['amount'] += (int) $i['amount_paise'];
                    } else {
                        $services[$i['name']] ??= ['count' => 0, 'amount' => 0];
                        $services[$i['name']]['count']++;
                        $services[$i['name']]['amount'] += (int) $i['amount_paise'];
                    }
                }
            }
        }
        $payments = $this->db->all(
            "SELECT amount_paise, reviewed_at, status FROM payments WHERE status IN ('verified','refunded') AND reviewed_at >= ? AND reviewed_at <= ?",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
        foreach ($payments as $p) {
            $k = $key(substr($p['reviewed_at'], 0, 10));
            $rows[$k] ??= $blank;
            $rows[$k]['revenue'] += (int) $p['amount_paise'];
            if ($p['status'] === 'refunded') {
                $rows[$k]['refunded'] += (int) $p['amount_paise'];
            }
        }
        ksort($rows);
        ksort($services);
        ksort($coupons);
        return ['rows' => $rows, 'services' => $services, 'coupons' => $coupons];
    }

    /** Spreadsheet-safe CSV (UTF-8 with BOM so Excel shows ₹ and Indian names correctly). */
    public static function csv(array $header, iterable $rows): string
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($fh, array_map([self::class, 'cell'], array_values($r)), ',', '"', '');
        }
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);
        return $out;
    }

    /** Neutralise spreadsheet formula injection (=, +, -, @ at the start of text). */
    public static function cell(mixed $v): string
    {
        $s = (string) ($v ?? '');
        if ($s !== '' && !is_numeric($s) && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $s;
        }
        return $s;
    }

    public static function rupees(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }
}
