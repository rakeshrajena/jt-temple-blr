<?php
declare(strict_types=1);

namespace SJT\Service;

/** Renders audit details JSON as short human-readable lines. */
final class AuditFormat
{
    private const MONEY = ['amount', 'total', 'due', 'paid', 'previous_total', 'total_paise', 'price', 'price_paise', 'additional_paise'];

    public static function details(?string $json): string
    {
        $d = $json ? json_decode($json, true) : null;
        if (!is_array($d) || !$d) {
            return '';
        }
        $lines = [];
        self::walk($d, '', $lines);
        return implode("\n", array_slice($lines, 0, 30));
    }

    private static function walk(array $d, string $prefix, array &$lines): void
    {
        foreach ($d as $k => $v) {
            $label = $prefix . str_replace('_', ' ', (string) $k);
            if (is_array($v)) {
                if (array_key_exists('from', $v) && array_key_exists('to', $v) && !is_array($v['from']) && !is_array($v['to'])) {
                    $lines[] = $label . ': ' . self::val((string) $k, $v['from']) . ' → ' . self::val((string) $k, $v['to']);
                } else {
                    self::walk($v, $label . ' › ', $lines);
                }
            } elseif ($v !== null && $v !== '' && $v !== false) {
                $lines[] = $label . ': ' . self::val((string) $k, $v);
            }
        }
    }

    private static function val(string $k, mixed $v): string
    {
        if (in_array($k, self::MONEY, true) && is_numeric($v)) {
            return money((int) $v);
        }
        if ($v === true) {
            return 'yes';
        }
        $s = (string) $v;
        return mb_strlen($s) > 140 ? mb_substr($s, 0, 140) . '…' : $s;
    }
}
