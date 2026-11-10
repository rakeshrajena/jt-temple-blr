<?php
declare(strict_types=1);

use SJT\Core\App;
use SJT\Core\Env;
use SJT\Core\Session;
use SJT\Service\TempleLogo;

/** True when unified install loaded CRM helpers first (same request). */
function booking_helpers_crm_context(): bool
{
    return function_exists('e') && !function_exists('app_script');
}

/** Read an environment value (from .env or the real environment). */
if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        return Env::get($key, $default);
    }
}

/** HTML-escape for output. Always use this in templates. */
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** Build an absolute path inside the app (respects the /Booking base path). */
if (!function_exists('url') || booking_helpers_crm_context()) {
    if (!function_exists('booking_url')) {
        function booking_url(string $path = '/', array $query = []): string
        {
            $base = App::basePath();
            $path = '/' . ltrim($path, '/');
            $u = $base . ($path === '/' ? '/' : $path);
            if ($query) {
                $u .= '?' . http_build_query($query);
            }
            return $u;
        }
    }
    if (!function_exists('url')) {
        function url(string $path = '/', array $query = []): string
        {
            return booking_url($path, $query);
        }
    }
}

/** Full URL including scheme and host (for WhatsApp links, PDFs). */
if (!function_exists('absolute_url')) {
    function absolute_url(string $path = '/', array $query = []): string
    {
        $appUrl = rtrim((string) env('APP_URL', ''), '/');
        $parts = parse_url($appUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'localhost') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $origin . url($path, $query);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $file = App::publicPath() . '/assets/' . ltrim($path, '/');
        $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
        return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
    }
}

if (!function_exists('parse_rupees')) {
    function parse_rupees(string $input): ?int
    {
        $v = str_replace([',', '₹', ' '], '', trim($input));
        if ($v === '' || !preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v)) {
            return null;
        }
        [$r, $p] = array_pad(explode('.', $v, 2), 2, '0');
        return ((int) $r) * 100 + (int) str_pad(substr($p, 0, 2), 2, '0');
    }
}

/** Format paise as Indian rupees (not used when CRM money() is already loaded). */
if (!function_exists('money')) {
    function money(int $paise, bool $symbol = true): string
    {
        $rupees = intdiv(abs($paise), 100);
        $p = abs($paise) % 100;
        $s = (string) $rupees;
        if (strlen($s) > 3) {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $s = $rest . ',' . $last3;
        }
        $out = ($paise < 0 ? '-' : '') . ($symbol ? '₹' : '') . $s;
        if ($p !== 0) {
            $out .= '.' . str_pad((string) $p, 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('today')) {
    function today(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('booking_schedule_from_draft')) {
    /** @return list<array{name:string,time:?string,needs_time:bool,cancelled:bool}> */
    function booking_schedule_from_draft(array $draft): array
    {
        $lines = [];
        foreach ($draft['services'] ?? [] as $line) {
            $svc = $line['service'] ?? [];
            $lines[] = [
                'name' => (string) ($svc['name'] ?? 'Service'),
                'time' => isset($line['slot']['label']) ? (string) $line['slot']['label'] : null,
                'needs_time' => !empty($svc['requires_slot']),
                'cancelled' => false,
            ];
        }
        return $lines;
    }
}

if (!function_exists('booking_schedule_from_items')) {
    /** @return list<array{name:string,time:?string,needs_time:bool,cancelled:bool}> */
    function booking_schedule_from_items(array $items): array
    {
        $lines = [];
        foreach ($items as $it) {
            if (($it['item_type'] ?? '') !== 'service') {
                continue;
            }
            $lines[] = [
                'name' => (string) ($it['name'] ?? 'Service'),
                'time' => !empty($it['slot_label']) ? (string) $it['slot_label'] : null,
                'needs_time' => !empty($it['slot_id']),
                'cancelled' => !empty($it['cancelled_at']),
            ];
        }
        return $lines;
    }
}

if (!function_exists('pay_later_enabled')) {
    function pay_later_enabled(): bool
    {
        return setting('pay_later_enabled', '1') === '1';
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(?string $ymd): string
    {
        if (!$ymd) {
            return '-';
        }
        $t = strtotime($ymd);
        return $t ? date('D, j M Y', $t) : $ymd;
    }
}

if (!function_exists('fmt_datetime')) {
    function fmt_datetime(?string $dt): string
    {
        if (!$dt) {
            return '-';
        }
        $t = strtotime($dt);
        return $t ? date('j M Y, g:i A', $t) : $dt;
    }
}

if (!function_exists('booking_csrf_token')) {
    function booking_csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return booking_csrf_token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(booking_csrf_token()) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('setting')) {
    function setting(string $key, ?string $default = null): ?string
    {
        return App::settings()->get($key, $default);
    }
}

if (!function_exists('mask_mobile')) {
    function mask_mobile(string $mobile): string
    {
        $d = preg_replace('/\D/', '', $mobile);
        return strlen($d) > 4 ? str_repeat('•', max(0, strlen($d) - 4)) . substr($d, -4) : $d;
    }
}

if (!function_exists('fmt_mobile')) {
    function fmt_mobile(string $digits): string
    {
        if (preg_match('/^91([6-9]\d{4})(\d{5})$/', $digits, $m)) {
            return '+91 ' . $m[1] . ' ' . $m[2];
        }
        return '+' . $digits;
    }
}

if (!function_exists('status_label')) {
    function status_label(string $kind, string $status): string
    {
        return \SJT\Service\Status::label($kind, $status);
    }
}

if (!function_exists('badge')) {
    function badge(string $kind, ?string $status): string
    {
        $st = (string) $status;
        $tone = match ($st) {
            'confirmed', 'paid', 'approved', 'completed', 'not_required', 'sent', 'delivered', 'read', 'resolved', 'verified' => 'ok',
            'awaiting_verification', 'pending', 'queued', 'submitted', 'initiated' => 'warn',
            'failed', 'rejected', 'cancelled', 'refunded' => 'bad',
            default => 'info',
        };
        $label = in_array($kind, ['booking', 'payment', 'approval'], true) ? status_label($kind, $st) : ucfirst(str_replace('_', ' ', $st));
        return '<span class="badge badge-' . $tone . '">' . e($label) . '</span>';
    }
}

/** Same logo as CRM Settings › identity (served via /brand/logo). */
if (!function_exists('temple_logo_url')) {
    function temple_logo_url(): string
    {
        return TempleLogo::publicUrl() ?? asset('img/logo.svg');
    }
}

if (!function_exists('temple_logo_icon_type')) {
    function temple_logo_icon_type(): string
    {
        $path = TempleLogo::filePath();
        if ($path === null) {
            return 'image/svg+xml';
        }
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/svg+xml',
        };
    }
}

if (!function_exists('booking_busy_overlay')) {
    function booking_busy_overlay(): void
    {
        echo '<div id="appBusy" class="busy-screen" hidden>'
            . '<div class="busy-card" role="status" aria-live="polite">'
            . '<img src="' . e(temple_logo_url()) . '" alt="" class="busy-logo">'
            . '<div class="busy-spin" aria-hidden="true"></div>'
            . '<p id="appBusyMessage">Please wait…</p>'
            . '</div></div>';
    }
}
