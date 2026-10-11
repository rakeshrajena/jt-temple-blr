<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;
use SJT\Core\Logger;
use SJT\Repo\Bookings;
use SJT\Repo\Settings;

/**
 * Queues and sends WhatsApp notifications. Every message is stored in the
 * `notifications` table first, so failures are visible in the dashboard and
 * can be retried. Nothing here can roll back or block a booking.
 *
 * Status meanings: queued (not yet attempted) · sent (accepted by WhatsApp —
 * NOT proof of delivery) · delivered / read (confirmed by Meta webhook) ·
 * failed (see error; retried automatically if temporary) · resolved (handled
 * manually by staff).
 */
final class Notifier
{
    private const BACKOFF_MIN = [5, 30, 120, 360];
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private Database $db,
        private Bookings $bookings,
        private Settings $settings,
        private WhatsAppClient $wa,
    ) {
    }

    public function bookingCreated(array $b): void
    {
        $ctx = $this->context($b);
        $this->queue($b, 'devotee', $b['mobile'], 'booking_received', 'wa_tpl_devotee_ack', [
            $b['devotee_name'], $b['booking_no'], $ctx['services'], $ctx['when'], $ctx['food'],
            money((int) $b['total_paise']), $ctx['status'], $ctx['link'], $ctx['contact'],
        ]);
        foreach ($this->settings->adminNumbers() as $n) {
            $this->queue($b, 'admin', $n, 'admin_new_booking', 'wa_tpl_admin_new', [
                $b['booking_no'], $b['devotee_name'], fmt_mobile($b['mobile']), $ctx['services'], $ctx['when'],
                $ctx['food'], money((int) $b['total_paise']), $ctx['status'], $ctx['admin_link'],
            ]);
        }
        if (!$this->settings->adminNumbers()) {
            $this->recordFailure($b, 'admin', '-', 'admin_new_booking', 'No administrator WhatsApp number is configured (Settings > Notifications).');
        }
    }

    public function paymentSubmitted(array $b, string $reference): void
    {
        $ctx = $this->context($b);
        foreach ($this->settings->adminNumbers() as $n) {
            $this->queue($b, 'admin', $n, 'admin_payment_submitted', 'wa_tpl_admin_payment', [
                $b['booking_no'], $b['devotee_name'], money((int) $b['total_paise']), $reference, $ctx['admin_link'],
            ]);
        }
    }

    /** Payment verified/failed, approval, rejection, cancellation, rescheduling, charge changes. */
    public function statusUpdate(array $b, string $message): void
    {
        $ctx = $this->context($b);
        $this->queue($b, 'devotee', $b['mobile'], 'booking_update', 'wa_tpl_devotee_update', [
            $b['devotee_name'], $b['booking_no'], $message, status_label('booking', $b['booking_status']),
            status_label('payment', $b['payment_status']), $ctx['link'], $ctx['contact'],
        ]);
    }

    private function context(array $b): array
    {
        $id = (int) $b['id'];
        $slots = $this->bookings->slotSummary($id);
        $phone = $this->settings->get('temple_phone', '');
        return [
            'services' => $this->bookings->serviceSummary($id) ?: '-',
            'when' => fmt_date($b['service_date']) . ($slots ? ', ' . $slots : ''),
            'food' => $this->bookings->foodSummary($id),
            'status' => Status::headline($b)[1],
            'link' => absolute_url('/b/' . $b['booking_no'], ['t' => $b['access_token']]),
            'admin_link' => absolute_url('/admin/bookings/' . $b['booking_no']),
            'contact' => trim($this->settings->get('temple_name', '') . ($phone ? ', ' . $phone : '')),
        ];
    }

    private function queue(array $b, string $audience, string $recipient, string $event, string $templateSetting, array $params): void
    {
        $now = now();
        try {
            $id = $this->db->insert('notifications', [
                'booking_id' => (int) $b['id'], 'channel' => 'whatsapp', 'audience' => $audience,
                'recipient' => $recipient, 'event' => $event,
                'template' => $this->settings->get($templateSetting),
                'params_json' => json_encode(array_values($params), JSON_UNESCAPED_UNICODE),
                'status' => 'queued', 'attempts' => 0, 'next_attempt_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->dispatch($id);
        } catch (\Throwable $e) {
            Logger::error('Notification could not be queued', ['booking' => $b['booking_no'], 'event' => $event, 'error' => $e->getMessage()]);
        }
    }

    private function recordFailure(array $b, string $audience, string $recipient, string $event, string $error): void
    {
        $now = now();
        $this->db->insert('notifications', [
            'booking_id' => (int) $b['id'], 'channel' => 'whatsapp', 'audience' => $audience, 'recipient' => $recipient,
            'event' => $event, 'template' => null, 'params_json' => null, 'status' => 'failed', 'error' => $error,
            'attempts' => 0, 'next_attempt_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ]);
        Logger::warning('Notification not sent', ['booking' => $b['booking_no'], 'event' => $event, 'error' => $error]);
    }

    /** Attempt one notification now. Safe to call repeatedly. */
    public function dispatch(int $id): bool
    {
        $n = $this->db->one('SELECT * FROM notifications WHERE id = ?', [$id]);
        if (!$n || !in_array($n['status'], ['queued', 'failed'], true) || !$n['template'] || !$n['params_json']) {
            return false;
        }
        $attempts = (int) $n['attempts'] + 1;
        if (!$this->settings->bool('whatsapp_enabled')) {
            $res = ['ok' => false, 'id' => null, 'error' => 'WhatsApp notifications are switched off (Settings > WhatsApp).', 'retryable' => false];
        } else {
            $res = $this->wa->sendTemplate($n['recipient'], $n['template'], $this->settings->get('whatsapp_language', 'en'), json_decode($n['params_json'], true) ?: []);
        }
        $now = now();
        if ($res['ok']) {
            $this->db->update('notifications', [
                'status' => 'sent', 'provider_message_id' => $res['id'], 'error' => null, 'attempts' => $attempts,
                'next_attempt_at' => null, 'sent_at' => $now, 'updated_at' => $now,
            ], 'id = :id', ['id' => $id]);
            return true;
        }
        $next = null;
        if ($res['retryable'] && $attempts < self::MAX_ATTEMPTS) {
            $next = date('Y-m-d H:i:s', time() + 60 * self::BACKOFF_MIN[min($attempts - 1, count(self::BACKOFF_MIN) - 1)]);
        }
        $this->db->update('notifications', [
            'status' => 'failed', 'error' => mb_substr((string) $res['error'], 0, 1000), 'attempts' => $attempts,
            'next_attempt_at' => $next, 'updated_at' => $now,
        ], 'id = :id', ['id' => $id]);
        Logger::warning('WhatsApp notification failed', ['notification' => $id, 'event' => $n['event'], 'error' => $res['error']]);
        return false;
    }

    /** Cron: retry temporary failures that are due. Returns [attempted, sent]. */
    public function retryDue(int $limit = 50): array
    {
        $rows = $this->db->all(
            "SELECT id FROM notifications WHERE status IN ('queued','failed') AND next_attempt_at IS NOT NULL AND next_attempt_at <= ? AND attempts < ? ORDER BY id LIMIT " . (int) $limit,
            [now(), self::MAX_ATTEMPTS]
        );
        $sent = 0;
        foreach ($rows as $r) {
            $sent += $this->dispatch((int) $r['id']) ? 1 : 0;
        }
        return [count($rows), $sent];
    }

    /** Apply a delivery status from the Meta webhook. */
    public function applyDeliveryStatus(string $providerId, string $status, ?string $error): void
    {
        $map = ['sent' => 'sent', 'delivered' => 'delivered', 'read' => 'read', 'failed' => 'failed'];
        if (!isset($map[$status])) {
            return;
        }
        $rank = ['queued' => 0, 'failed' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4, 'resolved' => 5];
        $n = $this->db->one('SELECT id, status FROM notifications WHERE provider_message_id = ?', [$providerId]);
        if (!$n) {
            return;
        }
        if ($status !== 'failed' && ($rank[$map[$status]] ?? 0) <= ($rank[$n['status']] ?? 0)) {
            return; // statuses can arrive out of order; never downgrade
        }
        $this->db->update('notifications', [
            'status' => $map[$status],
            'error' => $status === 'failed' ? mb_substr((string) $error, 0, 1000) : null,
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int) $n['id']]);
    }
}
