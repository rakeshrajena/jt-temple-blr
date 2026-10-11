<?php
declare(strict_types=1);

namespace SJT\Service;

/**
 * Booking state rules.
 *  payment_status : pending | awaiting_verification | paid | failed | refunded | not_required
 *  approval_status: not_required | pending | approved | rejected
 *  booking_status : pending | confirmed | rejected | cancelled | completed
 *
 * A booking is CONFIRMED only when payment is verified (or no payment is due)
 * AND, if the service needs it, the temple has approved it.
 */
final class Status
{
    public const PAYMENT = [
        'pending' => 'Payment pending',
        'awaiting_verification' => 'Awaiting verification',
        'paid' => 'Paid (verified)',
        'failed' => 'Payment not verified',
        'refunded' => 'Refunded',
        'not_required' => 'No payment due',
    ];
    public const APPROVAL = [
        'not_required' => 'Not required',
        'pending' => 'Awaiting approval',
        'approved' => 'Approved',
        'rejected' => 'Not approved',
    ];
    public const BOOKING = [
        'pending' => 'Request received (not yet confirmed)',
        'confirmed' => 'Confirmed',
        'rejected' => 'Not approved',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
    ];

    public static function label(string $kind, string $status): string
    {
        $map = match ($kind) {
            'payment' => self::PAYMENT,
            'approval' => self::APPROVAL,
            default => self::BOOKING,
        };
        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function derive(string $payment, string $approval, string $current): string
    {
        if (in_array($current, ['rejected', 'cancelled', 'completed'], true)) {
            return $current;
        }
        if ($approval === 'rejected') {
            return 'rejected';
        }
        $paid = in_array($payment, ['paid', 'not_required'], true);
        $approved = in_array($approval, ['not_required', 'approved'], true);
        return ($paid && $approved) ? 'confirmed' : 'pending';
    }

    /** Plain-language headline for devotees: [tone, title, explanation]. */
    public static function headline(array $b): array
    {
        $p = $b['payment_status'];
        $a = $b['approval_status'];
        return match ($b['booking_status']) {
            'confirmed' => ['ok', 'Booking confirmed', 'Your booking is confirmed. Please quote your booking number at the temple counter.'],
            'completed' => ['ok', 'Service completed', 'Thank you. This booking has been completed.'],
            'cancelled' => ['bad', 'Booking cancelled', 'This booking has been cancelled. Please contact the temple office for any help.'],
            'rejected' => ['bad', 'Booking not approved', 'The temple office could not approve this request. Please contact the temple office.'],
            default => match (true) {
                $p === 'pending' => ['warn', 'Booking request received — payment pending', 'This is a booking request, not yet a confirmed booking. Please complete the payment and submit the UPI reference number.'],
                $p === 'awaiting_verification' => ['info', 'Payment submitted — awaiting verification', 'We have received your payment reference. The temple office will verify it and confirm your booking.'],
                $p === 'failed' => ['bad', 'Payment could not be verified', 'The temple office could not match your payment. Please check the UPI reference number and submit it again, or contact the temple office.'],
                $a === 'pending' => ['info', 'Awaiting temple approval', 'Your payment is recorded. The temple office will review and confirm this booking.'],
                default => ['info', 'Booking request received', 'The temple office will confirm your booking.'],
            },
        };
    }
}
