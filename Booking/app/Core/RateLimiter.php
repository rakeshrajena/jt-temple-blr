<?php
declare(strict_types=1);

namespace SJT\Core;

/** Fixed-window rate limiter stored in the database (works on shared hosting). */
final class RateLimiter
{
    public function __construct(private Database $db)
    {
    }

    /** Record a hit; returns false when the limit for this window is exceeded. */
    public function hit(string $key, int $max, int $windowSeconds): bool
    {
        $key = substr($key, 0, 190);
        $now = time();
        return $this->db->transaction(function (Database $db) use ($key, $max, $windowSeconds, $now) {
            $row = $db->one('SELECT hits, window_start FROM rate_limits WHERE rkey = ?' . $db->forUpdate(), [$key]);
            if (!$row || ($now - (int) $row['window_start']) >= $windowSeconds) {
                $db->run('DELETE FROM rate_limits WHERE rkey = ?', [$key]);
                $db->insert('rate_limits', ['rkey' => $key, 'hits' => 1, 'window_start' => $now]);
                return true;
            }
            if ((int) $row['hits'] >= $max) {
                return false;
            }
            $db->run('UPDATE rate_limits SET hits = hits + 1 WHERE rkey = ?', [$key]);
            return true;
        });
    }

    public function tooMany(string $key, int $max, int $windowSeconds): bool
    {
        $row = $this->db->one('SELECT hits, window_start FROM rate_limits WHERE rkey = ?', [substr($key, 0, 190)]);
        return $row && (time() - (int) $row['window_start']) < $windowSeconds && (int) $row['hits'] >= $max;
    }

    public function clear(string $key): void
    {
        $this->db->run('DELETE FROM rate_limits WHERE rkey = ?', [substr($key, 0, 190)]);
    }

    public function prune(int $olderThanSeconds = 86400): void
    {
        $this->db->run('DELETE FROM rate_limits WHERE window_start < ?', [time() - $olderThanSeconds]);
    }
}
