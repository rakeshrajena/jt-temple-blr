<?php
declare(strict_types=1);

namespace SJT\Service;

/** Subjects and devotee-facing sentences for email and WhatsApp (template variable {{3}} on updates). */
final class BookingDevoteeCopy
{
    public static function emailSubjectCreated(array $b): string
    {
        $no = (string) $b['booking_no'];
        return match ($b['payment_status'] ?? '') {
            'pending' => 'Temple booking received - payment pending (' . $no . ')',
            'not_required' => 'Temple booking received - no payment due (' . $no . ')',
            default => 'Temple booking received (' . $no . ')',
        };
    }

    public static function emailSubjectUpdate(array $b): string
    {
        $no = (string) $b['booking_no'];
        return match ($b['payment_status'] ?? '') {
            'paid' => ($b['booking_status'] ?? '') === 'confirmed'
                ? 'Payment successful - booking confirmed (' . $no . ')'
                : 'Payment received (' . $no . ')',
            'failed' => 'Payment could not be verified (' . $no . ')',
            'awaiting_verification' => 'Payment submitted - awaiting verification (' . $no . ')',
            'refunded' => 'Refund recorded (' . $no . ')',
            default => 'Update on your temple booking (' . $no . ')',
        };
    }

    /** Opening paragraph for the booking-received email. */
    public static function emailLeadCreated(array $b): string
    {
        [, $title, $explain] = Status::headline($b);
        return $title . '. ' . $explain;
    }

    public static function emailFooterCreated(array $b): string
    {
        if (($b['payment_status'] ?? '') === 'pending' && (int) ($b['total_paise'] ?? 0) > 0) {
            return 'Please complete payment using the link below. Your invoice is attached for your records.';
        }
        if (($b['payment_status'] ?? '') === 'not_required') {
            return 'Your invoice is attached. Quote your booking number at the temple counter.';
        }
        return 'Your invoice is attached. Use the link below to view or download it anytime.';
    }

    public static function emailFooterUpdate(array $b): string
    {
        return ($b['payment_status'] ?? '') === 'paid'
            ? 'Thank you. Your updated invoice is attached.'
            : 'Your latest invoice is attached for your records.';
    }

    /**
     * Rich update text for WhatsApp {{3}} and email (includes services, date, prasadam).
     *
     * @param array{services: string, when: string, food: string} $ctx
     */
    public static function updateDetail(array $b, array $ctx, string $coreSentence): string
    {
        $parts = [rtrim($coreSentence, '.') . '.'];
        $svc = trim($ctx['services'] ?? '');
        if ($svc !== '' && $svc !== '-') {
            $parts[] = 'Services: ' . $svc . '.';
        }
        $when = trim($ctx['when'] ?? '');
        if ($when !== '' && $when !== '-') {
            $parts[] = 'Date and time: ' . $when . '.';
        }
        $food = trim($ctx['food'] ?? '');
        if ($food !== '' && $food !== '-' && strcasecmp($food, 'None') !== 0) {
            $parts[] = 'Prasadam / food coupons: ' . $food . '.';
        }
        $parts[] = 'Amount: ' . money((int) $b['total_paise']) . '.';
        return implode(' ', $parts);
    }

    public static function paymentReceivedCore(array $b, int $amountPaise, bool $onlineGateway, ?string $reference = null): string
    {
        $amt = money($amountPaise);
        $base = $onlineGateway
            ? 'Your online payment of ' . $amt . ' was received successfully'
            : 'Your payment of ' . $amt . ($reference !== null && $reference !== '' ? ' (reference ' . $reference . ')' : '') . ' has been verified';
        $suffix = ($b['booking_status'] ?? '') === 'confirmed'
            ? '. Your booking is now confirmed'
            : '. Your booking is recorded; the temple office will confirm it after review';
        return $base . $suffix;
    }

    public static function paymentPendingReminder(array $b, array $ctx): string
    {
        return self::updateDetail(
            $b,
            $ctx,
            'Your booking request is saved. Payment is still pending — please pay using your booking link to confirm',
        );
    }
}
