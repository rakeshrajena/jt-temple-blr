<?php
declare(strict_types=1);

namespace SJT\Repo;

use SJT\Core\Database;

/** Key/value settings editable from the admin dashboard (never secrets — those live in .env). */
final class Settings
{
    private ?array $cache = null;

    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->db->all('SELECT skey, svalue FROM settings') as $r) {
                $this->cache[$r['skey']] = $r['svalue'];
            }
        }
        return $this->cache;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $v = $this->all()[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->get($key);
        return $v === null ? $default : (int) $v;
    }

    public function bool(string $key): bool
    {
        return $this->get($key) === '1';
    }

    public function set(string $key, ?string $value, string $actor): void
    {
        $exists = $this->db->value('SELECT 1 FROM settings WHERE skey = ?', [$key]);
        if ($exists) {
            $this->db->update('settings', ['svalue' => $value, 'updated_at' => now(), 'updated_by' => $actor], 'skey = :k', ['k' => $key]);
        } else {
            $this->db->insert('settings', ['skey' => $key, 'svalue' => $value, 'updated_at' => now(), 'updated_by' => $actor]);
        }
        $this->cache = null;
    }

    /** Admin WhatsApp numbers as digit strings (E.164 without +). */
    public function adminNumbers(): array
    {
        $out = [];
        foreach (preg_split('/[,\s]+/', (string) $this->get('admin_whatsapp_numbers', '')) as $n) {
            $d = \SJT\Service\Validator::normaliseMobile($n);
            if ($d) {
                $out[] = $d;
            }
        }
        return array_values(array_unique($out));
    }
}
