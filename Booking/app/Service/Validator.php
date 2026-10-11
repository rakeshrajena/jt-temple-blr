<?php
declare(strict_types=1);

namespace SJT\Service;

final class Validator
{
    /**
     * Normalise a mobile number to digits with country code (E.164 without "+").
     * Indian numbers: 10 digits starting 6-9, optional +91/91/0 prefix.
     * Other countries: must start with "+" and have 8-15 digits.
     */
    public static function normaliseMobile(string $input): ?string
    {
        $raw = trim($input);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/[^\d\s+()\-]/', $raw)) {
            return null;
        }
        $plus = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $d = preg_replace('/\D/', '', $raw);
        if (str_starts_with($raw, '00')) {
            $d = substr($d, 2);
        }
        if (!$plus && strlen($d) === 11 && $d[0] === '0') {
            $d = substr($d, 1);
        }
        if (strlen($d) === 10 && preg_match('/^[6-9]/', $d) && !$plus) {
            return '91' . $d;
        }
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            return preg_match('/^91[6-9]\d{9}$/', $d) ? $d : null;
        }
        if ($plus && strlen($d) >= 8 && strlen($d) <= 15 && $d[0] !== '0') {
            return $d;
        }
        return null;
    }

    public static function name(string $v): ?string
    {
        $v = preg_replace('/\s+/u', ' ', trim($v));
        if (mb_strlen($v) < 2 || mb_strlen($v) > 100) {
            return null;
        }
        return preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'\\-]*$/u", $v) ? $v : null;
    }

    public static function email(string $v): ?string
    {
        $v = trim($v);
        return (strlen($v) <= 150 && filter_var($v, FILTER_VALIDATE_EMAIL)) ? strtolower($v) : null;
    }

    public static function date(string $v): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($v));
        return ($d && $d->format('Y-m-d') === trim($v)) ? $d->format('Y-m-d') : null;
    }

    /** Free text: strip control characters, collapse whitespace, enforce length. */
    public static function text(string $v, int $max, bool $multiline = false): string
    {
        $v = preg_replace($multiline ? '/[^\P{C}\n]/u' : '/\p{C}/u', '', $v) ?? '';
        $v = $multiline ? preg_replace("/[ \t]+/u", ' ', $v) : preg_replace('/\s+/u', ' ', $v);
        return mb_substr(trim((string) $v), 0, $max);
    }

    /** UPI UTR / transaction reference: 6-35 letters/digits (UTR is normally 12 digits). */
    public static function paymentReference(string $v): ?string
    {
        $v = strtoupper(preg_replace('/[\s\-]/', '', $v));
        return preg_match('/^[A-Z0-9]{6,35}$/', $v) ? $v : null;
    }
}
