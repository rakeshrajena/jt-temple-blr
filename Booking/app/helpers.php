<?php
declare(strict_types=1);

use SJT\Core\App;
use SJT\Core\Env;
use SJT\Core\Session;

/** Read an environment value (from .env or the real environment). */
function env(string $key, ?string $default = null): ?string
{
    return Env::get($key, $default);
}

/** HTML-escape for output. Always use this in templates. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an absolute path inside the app (respects the /Booking base path). */
function url(string $path = '/', array $query = []): string
{
    $base = App::basePath();
    $path = '/' . ltrim($path, '/');
    $u = $base . ($path === '/' ? '/' : $path);
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

/** Full URL including scheme and host (for WhatsApp links, PDFs). */
function absolute_url(string $path = '/', array $query = []): string
{
    $appUrl = rtrim((string) env('APP_URL', ''), '/');
    $parts = parse_url($appUrl);
    $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'localhost') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    return $origin . url($path, $query);
}

function asset(string $path): string
{
    $file = App::publicPath() . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Format paise as Indian rupees, e.g. 110000 -> "₹1,100". */
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

/** Parse a rupee amount typed by an admin ("1,100.50") into paise. Returns null if invalid. */
function parse_rupees(string $input): ?int
{
    $v = str_replace([',', '₹', ' '], '', trim($input));
    if ($v === '' || !preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v)) {
        return null;
    }
    [$r, $p] = array_pad(explode('.', $v, 2), 2, '0');
    return ((int) $r) * 100 + (int) str_pad(substr($p, 0, 2), 2, '0');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function fmt_date(?string $ymd): string
{
    if (!$ymd) {
        return '—';
    }
    $t = strtotime($ymd);
    return $t ? date('D, j M Y', $t) : $ymd;
}

function fmt_datetime(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $t = strtotime($dt);
    return $t ? date('j M Y, g:i A', $t) : $dt;
}

function csrf_token(): string
{
    return Session::csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

function setting(string $key, ?string $default = null): ?string
{
    return App::settings()->get($key, $default);
}

function mask_mobile(string $mobile): string
{
    $d = preg_replace('/\D/', '', $mobile);
    return strlen($d) > 4 ? str_repeat('•', max(0, strlen($d) - 4)) . substr($d, -4) : $d;
}

/** Display an E.164-ish digits string as +91 98765 43210. */
function fmt_mobile(string $digits): string
{
    if (preg_match('/^91([6-9]\d{4})(\d{5})$/', $digits, $m)) {
        return '+91 ' . $m[1] . ' ' . $m[2];
    }
    return '+' . $digits;
}

function status_label(string $kind, string $status): string
{
    return \SJT\Service\Status::label($kind, $status);
}

/** Coloured status pill for admin tables. */
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
