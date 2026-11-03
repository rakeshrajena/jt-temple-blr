<?php
declare(strict_types=1);

/** Online booking app settings (shared `settings` table with Booking). */

function booking_settings_can_edit(?string $role): bool
{
    return in_array($role ?? '', ['Admin', 'Treasurer'], true);
}

function booking_settings_all(): array
{
    if (!booking_module_installed()) {
        return [];
    }
    $out = [];
    foreach (db_all('SELECT skey, svalue FROM settings') as $row) {
        $out[$row['skey']] = (string) $row['svalue'];
    }
    return $out;
}

function booking_setting(string $key, ?string $default = null): ?string
{
    $all = booking_settings_all();
    $v = $all[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}

function booking_setting_set(string $key, ?string $value, string $actor): void
{
    $exists = db_value('SELECT 1 FROM settings WHERE skey = ?', [$key]);
    $now = date('Y-m-d H:i:s');
    if ($exists) {
        db_exec('UPDATE settings SET svalue = ?, updated_at = ?, updated_by = ? WHERE skey = ?', [$value, $now, $actor, $key]);
    } else {
        db_exec('INSERT INTO settings (skey, svalue, updated_at, updated_by) VALUES (?,?,?,?)', [$key, $value, $now, $actor]);
    }
}

/** @return array{error:?string} */
function booking_settings_save_contact(array $post, string $actor): array
{
    $name = trim((string) ($post['temple_name'] ?? ''));
    if (mb_strlen($name) < 2) {
        return ['error' => 'Temple name is required.'];
    }
    $email = trim((string) ($post['temple_email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['error' => 'Temple email is not valid.'];
    }
    $keys = [
        'temple_name' => mb_substr($name, 0, 200),
        'temple_address' => mb_substr(trim((string) ($post['temple_address'] ?? '')), 0, 1000),
        'temple_phone' => mb_substr(trim((string) ($post['temple_phone'] ?? '')), 0, 200),
        'temple_email' => mb_substr($email, 0, 200),
        'temple_website' => mb_substr(trim((string) ($post['temple_website'] ?? '')), 0, 200),
        'temple_gstin' => mb_substr(trim((string) ($post['temple_gstin'] ?? '')), 0, 200),
        'terms_text' => mb_substr(trim((string) ($post['terms_text'] ?? '')), 0, 1000),
    ];
    foreach ($keys as $k => $v) {
        booking_setting_set($k, $v, $actor);
    }
    return ['error' => null];
}

/** @return array{error:?string} */
function booking_settings_save_rules(array $post, string $actor): array
{
    $lead = max(0, min(730, (int) ($post['booking_lead_days'] ?? 1)));
    $max = max(1, min(730, (int) ($post['booking_max_days'] ?? 90)));
    $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($post['booking_prefix'] ?? '')));
    $inv = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($post['invoice_prefix'] ?? '')));
    if ($prefix === '' || strlen($prefix) > 8 || $inv === '' || strlen($inv) > 8) {
        return ['error' => 'Booking and invoice prefixes must be 1–8 letters or digits.'];
    }
    $extraRaw = trim((string) ($post['additional_charge'] ?? '0'));
    $extraPaise = booking_parse_rupees($extraRaw);
    if ($extraPaise === null) {
        return ['error' => 'Additional charge must be a valid rupee amount.'];
    }
    booking_setting_set('booking_lead_days', (string) $lead, $actor);
    booking_setting_set('booking_max_days', (string) $max, $actor);
    booking_setting_set('booking_prefix', $prefix, $actor);
    booking_setting_set('invoice_prefix', $inv, $actor);
    booking_setting_set('additional_charge_label', mb_substr(trim((string) ($post['additional_charge_label'] ?? '')), 0, 200), $actor);
    booking_setting_set('additional_charge_paise', (string) $extraPaise, $actor);
    booking_setting_set('pay_later_enabled', isset($post['pay_later_enabled']) ? '1' : '0', $actor);
    return ['error' => null];
}

/** @return array{error:?string} */
function booking_settings_save_whatsapp(array $post, string $actor): array
{
    $enabled = isset($post['whatsapp_enabled']) ? '1' : '0';
    $lang = (string) ($post['whatsapp_language'] ?? 'en');
    if (!preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $lang)) {
        $lang = 'en';
    }
    $nums = [];
    foreach (preg_split('/[,\n]+/', (string) ($post['admin_whatsapp_numbers'] ?? '')) as $n) {
        $n = trim($n);
        if ($n === '') {
            continue;
        }
        $d = booking_normalise_mobile($n);
        if ($d === null) {
            return ['error' => '"' . $n . '" is not a valid mobile number.'];
        }
        $nums[] = '+' . $d;
    }
    $tpl = static fn (string $k) => preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($post[$k] ?? '')));
    booking_setting_set('whatsapp_enabled', $enabled, $actor);
    booking_setting_set('admin_whatsapp_numbers', implode(', ', array_unique($nums)), $actor);
    booking_setting_set('whatsapp_language', $lang, $actor);
    booking_setting_set('wa_tpl_devotee_ack', $tpl('wa_tpl_devotee_ack'), $actor);
    booking_setting_set('wa_tpl_devotee_update', $tpl('wa_tpl_devotee_update'), $actor);
    booking_setting_set('wa_tpl_admin_new', $tpl('wa_tpl_admin_new'), $actor);
    booking_setting_set('wa_tpl_admin_payment', $tpl('wa_tpl_admin_payment'), $actor);
    return ['error' => null];
}

function booking_parse_rupees(string $raw): ?int
{
    $raw = str_replace([',', '₹', ' '], '', trim($raw));
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    $f = (float) $raw;
    if ($f < 0) {
        return null;
    }
    return (int) round($f * 100);
}

function booking_normalise_mobile(string $raw): ?string
{
    $m = preg_replace('/[^\d+]/', '', $raw);
    if (preg_match('/^(\+?91|0)?([6-9]\d{9})$/', $m, $x)) {
        return '91' . $x[2];
    }
    if (preg_match('/^\+\d{8,15}$/', $m)) {
        return ltrim($m, '+');
    }
    return null;
}

/** Read non-secret flags from Booking/.env for the settings UI. */
function booking_env_flags(): array
{
    $path = dirname(APP_ROOT) . '/Booking/.env';
    $vars = [];
    if (is_readable($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
                $vars[$m[1]] = trim($m[2], " \t\"'");
            }
        }
    }
    $keyId = (string) ($vars['RAZORPAY_KEY_ID'] ?? '');
    return [
        'payment_gateway' => strtolower((string) ($vars['PAYMENT_GATEWAY'] ?? 'none')),
        'upi_manual' => (($vars['BOOKING_UPI_MANUAL'] ?? 'false') === 'true'),
        'razorpay_key_hint' => $keyId !== '' ? (substr($keyId, 0, 12) . '…') : '',
        'razorpay_configured' => $keyId !== '' && ($vars['RAZORPAY_KEY_SECRET'] ?? '') !== '',
        'wa_configured' => ($vars['WA_ACCESS_TOKEN'] ?? '') !== '' && ($vars['WA_PHONE_NUMBER_ID'] ?? '') !== '',
        'booking_app_url' => rtrim((string) ($vars['APP_URL'] ?? ''), '/'),
    ];
}

function booking_whatsapp_webhook_url(): string
{
    $base = booking_env_flags()['booking_app_url'];
    if ($base === '') {
        $base = rtrim(booking_public_app_url(), '/');
    }
    return $base === '' ? '' : $base . '/webhooks/whatsapp';
}

function booking_razorpay_webhook_url(): string
{
    $base = booking_env_flags()['booking_app_url'];
    if ($base === '') {
        $base = rtrim(booking_public_app_url(), '/');
    }
    return $base === '' ? '' : $base . '/webhooks/razorpay';
}

function booking_public_app_url(): string
{
    $app = rtrim(env_value('BOOKING_APP_URL', ''), '/');
    if ($app !== '') {
        return $app;
    }
    $dir = app_dir_url();
    if ($dir === '' || $dir === '/') {
        return '/Booking';
    }
    if (str_ends_with($dir, '/php')) {
        return substr($dir, 0, -4) . '/Booking';
    }
    return rtrim(dirname($dir), '/') . '/Booking';
}

function erp_settings_online_booking_url(): string
{
    return url('settings') . '#online-booking';
}
