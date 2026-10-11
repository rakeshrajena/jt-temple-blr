<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;
use SJT\Repo\Catalog;
use SJT\Repo\Settings;

/** Date windows, blocked dates, daily and per-slot capacity. */
final class Availability
{
    /** Bookings in these states do not hold a place. */
    private const INACTIVE = "('rejected','cancelled')";

    public function __construct(private Database $db, private Catalog $catalog, private Settings $settings)
    {
    }

    public function minDate(?array $service = null): string
    {
        $lead = $service['lead_days'] ?? null;
        $lead ??= $this->settings->int('booking_lead_days', 1);
        return date('Y-m-d', strtotime(today() . ' +' . max(0, $lead) . ' days'));
    }

    public function maxDate(): string
    {
        return date('Y-m-d', strtotime(today() . ' +' . max(1, $this->settings->int('booking_max_days', 90)) . ' days'));
    }

    public function weekday(string $date): string
    {
        return (string) date('N', strtotime($date));
    }

    /** Places used in a slot on a date (optionally ignoring one booking, for rescheduling). */
    public function slotUsed(int $slotId, string $date, ?int $exceptBooking = null): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM booking_items i JOIN bookings b ON b.id = i.booking_id
             WHERE i.slot_id = ? AND b.service_date = ? AND b.booking_status NOT IN ' . self::INACTIVE .
            ($exceptBooking ? ' AND b.id <> ' . (int) $exceptBooking : ''),
            [$slotId, $date]
        );
    }

    public function serviceUsed(int $serviceId, string $date, ?int $exceptBooking = null): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM booking_items i JOIN bookings b ON b.id = i.booking_id
             WHERE i.item_type = 'service' AND i.service_id = ? AND b.service_date = ? AND b.booking_status NOT IN " . self::INACTIVE .
            ($exceptBooking ? ' AND b.id <> ' . (int) $exceptBooking : ''),
            [$serviceId, $date]
        );
    }

    /**
     * Why a service cannot be booked on a date (null = available).
     * $checkWindow=false lets administrators reschedule outside the public booking window.
     */
    public function serviceProblem(array $service, string $date, ?int $exceptBooking = null, bool $checkWindow = true): ?string
    {
        if (!$service['is_active']) {
            return 'This service is not currently available for online booking.';
        }
        if ($checkWindow && $date < $this->minDate($service)) {
            return 'Please choose a date on or after ' . fmt_date($this->minDate($service)) . '.';
        }
        if ($checkWindow && $date > $this->maxDate()) {
            return 'Bookings are open only up to ' . fmt_date($this->maxDate()) . '.';
        }
        if ($reason = $this->catalog->blockReason($service['id'], $date)) {
            return $service['name'] . ' is not available on ' . fmt_date($date) . ': ' . $reason . '.';
        }
        if ($service['daily_capacity'] !== null && $this->serviceUsed($service['id'], $date, $exceptBooking) >= $service['daily_capacity']) {
            return $service['name'] . ' is fully booked on ' . fmt_date($date) . '. Please choose another date.';
        }
        if ($service['requires_slot'] && !$this->slotsFor($service, $date, $exceptBooking, onlyOpen: true)) {
            return $this->slotsFor($service, $date, $exceptBooking)
                ? $service['name'] . ' is fully booked on ' . fmt_date($date) . '. Please choose another date.'
                : $service['name'] . ' is not offered on ' . date('l', strtotime($date)) . 's. Please choose another date.';
        }
        return null;
    }

    public function slotProblem(array $service, array $slot, string $date, ?int $exceptBooking = null): ?string
    {
        if ($slot['service_id'] !== $service['id'] || !$slot['is_active']) {
            return 'Please choose a valid time slot.';
        }
        if (!str_contains($slot['weekdays'], $this->weekday($date))) {
            return 'The ' . $slot['label'] . ' slot is not offered on ' . date('l', strtotime($date)) . 's.';
        }
        if ($slot['capacity'] !== null && $this->slotUsed($slot['id'], $date, $exceptBooking) >= $slot['capacity']) {
            return 'The ' . $slot['label'] . ' slot is fully booked on ' . fmt_date($date) . '. Please choose another time or date.';
        }
        return null;
    }

    /** Slots for a service on a date with remaining places (null remaining = no limit). */
    public function slotsFor(array $service, string $date, ?int $exceptBooking = null, bool $onlyOpen = false): array
    {
        $wd = $this->weekday($date);
        $out = [];
        foreach ($this->catalog->slots($service['id']) as $s) {
            if (!str_contains($s['weekdays'], $wd)) {
                continue;
            }
            $remaining = $s['capacity'] === null ? null : max(0, $s['capacity'] - $this->slotUsed($s['id'], $date, $exceptBooking));
            if ($onlyOpen && $remaining === 0) {
                continue;
            }
            $out[] = ['id' => $s['id'], 'label' => $s['label'], 'remaining' => $remaining];
        }
        return $out;
    }

    /** Public availability summary for one date, used by the booking form. */
    public function forDate(string $date): array
    {
        $out = [];
        foreach ($this->catalog->services() as $svc) {
            $problem = $this->serviceProblem($svc, $date);
            $out[$svc['id']] = [
                'available' => $problem === null,
                'message' => $problem,
                'slots' => $svc['requires_slot'] ? $this->slotsFor($svc, $date) : [],
            ];
        }
        return $out;
    }
}
