<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\App;
use SJT\Core\Database;

/** Append-only audit trail: who changed what, and when. */
final class Audit
{
    public function __construct(private Database $db)
    {
    }

    /** @param array{id:?int,name:string} $actor */
    public function log(array $actor, string $entity, string|int|null $entityId, ?int $bookingId, string $action, array $details = []): void
    {
        $ip = null;
        try {
            $ip = App::request()->ip();
        } catch (\Throwable) {
        }
        $this->db->insert('audit_log', [
            'admin_id' => $actor['id'] ?? null,
            'actor' => mb_substr($actor['name'], 0, 100),
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'booking_id' => $bookingId,
            'action' => $action,
            'details_json' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip' => PHP_SAPI === 'cli' ? 'cli' : $ip,
            'created_at' => now(),
        ]);
    }

    public static function devotee(): array
    {
        return ['id' => null, 'name' => 'devotee'];
    }

    public static function system(string $what = 'system'): array
    {
        return ['id' => null, 'name' => 'system:' . $what];
    }
}
