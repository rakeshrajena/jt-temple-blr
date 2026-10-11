<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;

/**
 * Gap-tolerant, collision-free counters (booking numbers, invoice numbers).
 * Must be called inside a transaction; the UPDATE row lock serialises callers.
 */
final class Sequence
{
    public function __construct(private Database $db)
    {
    }

    public function next(string $name, string $period): int
    {
        $this->db->run($this->db->insertIgnore() . ' INTO sequences (name, period, value) VALUES (?, ?, 0)', [$name, $period]);
        $this->db->run('UPDATE sequences SET value = value + 1 WHERE name = ? AND period = ?', [$name, $period]);
        return (int) $this->db->value('SELECT value FROM sequences WHERE name = ? AND period = ?', [$name, $period]);
    }

    /** Indian financial year label for a date, e.g. 2026-10-10 -> "2026-27". */
    public static function financialYear(string $ymd): string
    {
        $y = (int) substr($ymd, 0, 4);
        $m = (int) substr($ymd, 5, 2);
        $start = $m >= 4 ? $y : $y - 1;
        return $start . '-' . substr((string) ($start + 1), -2);
    }
}
