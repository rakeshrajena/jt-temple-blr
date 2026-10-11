<?php
declare(strict_types=1);

namespace SJT\Core;

/** Daily rotating file logger (storage/logs/app-YYYY-MM-DD.log). */
final class Logger
{
    private static string $dir = '';

    public static function init(string $dir): void
    {
        self::$dir = $dir;
    }

    public static function info(string $msg, array $ctx = []): void { self::write('INFO', $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::write('WARNING', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::write('ERROR', $msg, $ctx); }

    public static function write(string $level, string $msg, array $ctx = []): void
    {
        if (self::$dir === '') {
            error_log("[$level] $msg");
            return;
        }
        // Never log secrets.
        foreach ($ctx as $k => $v) {
            if (preg_match('/token|secret|password|pass|key/i', (string) $k)) {
                $ctx[$k] = '***';
            }
        }
        $line = sprintf("[%s] %s: %s%s\n", date('Y-m-d H:i:s'), $level, $msg, $ctx ? ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
        @file_put_contents(self::$dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
