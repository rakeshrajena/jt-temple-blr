<?php
declare(strict_types=1);

const DEFAULT_MESSAGE_TEMPLATE = 'Namaskar {name}, your {period} seva contribution of Rs.{amount} is due. Pay here: {link} — Shree Jagannath Temple, invoice {invoice}.';

function ensure_messaging_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_settings (
            setting_key    VARCHAR(64) PRIMARY KEY,
            setting_value  TEXT NULL
        ) ENGINE=InnoDB"
    );
}

/** @return array<string, string> */
function messaging_defaults(): array
{
    return [
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => APP_NAME,
        'whatsapp_country_code' => '91',
        'whatsapp_template' => DEFAULT_MESSAGE_TEMPLATE,
    ];
}

/** @return array<string, string> */
function load_messaging_settings(): array
{
    $settings = messaging_defaults();
    $rows = db_all('SELECT setting_key, setting_value FROM app_settings');
    foreach ($rows as $row) {
        $key = (string) $row['setting_key'];
        if (array_key_exists($key, $settings)) {
            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }
    }
    return $settings;
}

/**
 * @param array<string, string> $input
 */
function messaging_settings_error(array $input): ?string
{
    $port = (int) ($input['smtp_port'] ?? 0);
    if ($port < 1 || $port > 65535) {
        return 'Enter a mail port from 1 to 65535.';
    }
    if (!in_array($input['smtp_encryption'] ?? '', ['none', 'tls', 'ssl'], true)) {
        return 'Choose no encryption, TLS, or SSL.';
    }
    $from = trim((string) ($input['smtp_from_email'] ?? ''));
    $host = trim((string) ($input['smtp_host'] ?? ''));
    if ($host !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        return 'Enter the From email address for outgoing mail.';
    }
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        return 'The From email address is not valid.';
    }
    $code = preg_replace('/\D+/', '', (string) ($input['whatsapp_country_code'] ?? '')) ?? '';
    if ($code === '' || strlen($code) > 4) {
        return 'Enter a country code of 1 to 4 digits, such as 91.';
    }
    $template = trim((string) ($input['whatsapp_template'] ?? ''));
    if ($template === '') {
        return 'Enter the message text.';
    }
    if (strlen($template) > 1000) {
        return 'The message text is too long.';
    }
    return null;
}

/**
 * @param array<string, string> $input
 * @param array<string, string> $current
 */
function save_messaging_settings(array $input, array $current): ?string
{
    $error = messaging_settings_error($input);
    if ($error !== null) {
        return $error;
    }
    $password = (string) ($input['smtp_password'] ?? '');
    if ($password === '') {
        $password = $current['smtp_password'];
    }
    if (($input['clear_smtp_password'] ?? '') === '1') {
        $password = '';
    }
    $stored = [
        'smtp_host' => trim($input['smtp_host']),
        'smtp_port' => (string) (int) $input['smtp_port'],
        'smtp_encryption' => $input['smtp_encryption'],
        'smtp_username' => trim($input['smtp_username']),
        'smtp_password' => $password,
        'smtp_from_email' => trim($input['smtp_from_email']),
        'smtp_from_name' => trim($input['smtp_from_name']),
        'whatsapp_country_code' => preg_replace('/\D+/', '', $input['whatsapp_country_code']) ?? '',
        'whatsapp_template' => trim($input['whatsapp_template']),
    ];
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        foreach ($stored as $key => $value) {
            db_exec(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$key, $value]
            );
        }
        if ($own) {
            $pdo->commit();
        }
        return null;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** @param array<string, string> $settings */
function smtp_is_ready(array $settings): bool
{
    return trim($settings['smtp_host']) !== ''
        && filter_var($settings['smtp_from_email'], FILTER_VALIDATE_EMAIL) !== false;
}

function whatsapp_phone_digits(string $phone, string $countryCode): ?string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    $digits = ltrim($digits, '0');
    if ($digits === '') {
        return null;
    }
    $code = preg_replace('/\D+/', '', $countryCode) ?? '';
    if ($code !== '' && !str_starts_with($digits, $code) && strlen($digits) <= 10) {
        $digits = $code . $digits;
    }
    if (strlen($digits) < 8 || strlen($digits) > 15) {
        return null;
    }
    return $digits;
}

function whatsapp_web_url(string $phone, string $message, string $countryCode): ?string
{
    $digits = whatsapp_phone_digits($phone, $countryCode);
    if ($digits === null) {
        return null;
    }
    return 'https://web.whatsapp.com/send?phone=' . $digits . '&text=' . rawurlencode($message);
}

/** @param array<string, string> $vars */
function fill_message_template(string $template, array $vars): string
{
    $out = $template;
    foreach ($vars as $key => $value) {
        $out = str_replace('{' . $key . '}', $value, $out);
    }
    return $out;
}

/**
 * @param array<string, string> $invoice
 * @param array<string, string> $settings
 */
function invoice_notice_text(array $invoice, string $payUrl, array $settings): string
{
    return fill_message_template($settings['whatsapp_template'], [
        'name' => (string) ($invoice['name'] ?? ''),
        'period' => (string) ($invoice['period_label'] ?? ''),
        'amount' => number_format((float) ($invoice['amount'] ?? 0), 0),
        'link' => $payUrl,
        'invoice' => (string) ($invoice['invoice_number'] ?? ''),
    ]);
}

/**
 * @param array{filename: string, content: string, mime: string}|null $attachment
 */
function bulk_compose_error(string $message, int $count): ?string
{
    if ($count < 1) {
        return 'Select at least one person.';
    }
    if ($count > 50) {
        return 'Select up to 50 people at a time.';
    }
    $message = trim($message);
    if ($message === '') {
        return 'Enter the message.';
    }
    if (mb_strlen($message) > 1000) {
        return 'The message is too long.';
    }
    return null;
}

/** @return array{0: string, 1: string} */
function bulk_result_flash(int $sent, int $skipped, int $failed): array
{
    if ($sent === 0 && $failed === 0) {
        return ['error', 'No messages were sent. The selected people need a valid email address.'];
    }
    if ($sent === 0) {
        return ['error', 'No messages were sent. The mail server did not accept them.'];
    }
    $message = 'Emailed ' . $sent . ($sent === 1 ? ' person.' : ' people.');
    if ($skipped > 0) {
        $message .= ' ' . $skipped . ' skipped because the email address is missing or not valid.';
    }
    if ($failed > 0) {
        $message .= ' ' . $failed . ' could not be sent.';
    }
    return ['success', $message];
}

function smtp_attachment_error(?array $attachment): ?string
{
    if ($attachment === null) {
        return null;
    }
    $name = (string) ($attachment['filename'] ?? '');
    $mime = (string) ($attachment['mime'] ?? '');
    if (preg_match('/^[A-Za-z0-9._-]+$/', $name) !== 1 || $mime !== 'application/pdf') {
        return 'The receipt file cannot be attached.';
    }
    if (($attachment['content'] ?? '') === '') {
        return 'The receipt file is empty.';
    }
    return null;
}

/**
 * @param array{filename: string, content: string, mime: string}|null $attachment
 */
function email_signature_text(): string
{
    return "--\r\n" . app_display_name() . "\r\n" . APP_PLACE;
}

function email_signature_html(bool $withLogo): string
{
    $name = htmlspecialchars(app_display_name(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $place = htmlspecialchars(APP_PLACE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $logo = $withLogo
        ? '<img src="cid:temple-logo" width="48" height="48" alt="" style="display:block;width:48px;height:48px;border:0;">'
        : '';
    return '<table role="presentation" style="margin-top:18px;border-top:1px solid #e6dcc8;padding-top:12px;">'
        . '<tr><td style="padding-right:12px;vertical-align:middle;">' . $logo . '</td>'
        . '<td style="vertical-align:middle;font-family:Georgia,serif;">'
        . '<div style="font-weight:700;font-size:15px;color:#7A1626;">' . $name . '</div>'
        . '<div style="font-size:13px;color:#6b625a;">' . $place . '</div>'
        . '</td></tr></table>';
}

function email_html_document(string $message, bool $withLogo): string
{
    $safe = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    return '<!DOCTYPE html><html><body style="margin:0;padding:16px;font-family:Georgia,serif;color:#222;">'
        . '<div style="font-size:15px;line-height:1.5;">' . $safe . '</div>'
        . email_signature_html($withLogo)
        . '</body></html>';
}

function smtp_data_payload(string $fromName, string $from, string $to, string $subject, string $body, ?array $attachment = null): string
{
    $headers = 'From: ' . smtp_quoted_name($fromName) . ' <' . $from . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . smtp_quoted_name($subject) . "\r\n"
        . "MIME-Version: 1.0\r\n";
    $text = str_replace(["\r\n", "\r"], "\n", $body);
    $text = str_replace("\n", "\r\n", $text);
    $plain = rtrim($text) . "\r\n\r\n" . email_signature_text();
    $logo = brand_logo_email_image();
    $html = email_html_document($body, $logo !== null);
    $message = smtp_signed_message($plain, $html, $logo);
    if ($attachment === null) {
        if ($logo === null) {
            $raw = $headers . "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $plain;
            return smtp_dot_stuff($raw) . "\r\n.\r\n";
        }
        $raw = $headers . $message;
        return smtp_dot_stuff($raw) . "\r\n.\r\n";
    }
    $mixed = 'jt_' . bin2hex(random_bytes(8));
    $encoded = rtrim(chunk_split(base64_encode($attachment['content']), 76, "\r\n"));
    $raw = $headers
        . 'Content-Type: multipart/mixed; boundary="' . $mixed . "\"\r\n"
        . "\r\n"
        . '--' . $mixed . "\r\n"
        . $message
        . '--' . $mixed . "\r\n"
        . 'Content-Type: application/pdf; name="' . $attachment['filename'] . "\"\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . 'Content-Disposition: attachment; filename="' . $attachment['filename'] . "\"\r\n"
        . "\r\n"
        . $encoded . "\r\n"
        . '--' . $mixed . "--\r\n";
    return smtp_dot_stuff($raw) . "\r\n.\r\n";
}

/** @param array{mime:string,bytes:string}|null $logo */
function smtp_signed_message(string $plain, string $html, ?array $logo): string
{
    $alternative = 'jt_alt_' . bin2hex(random_bytes(6));
    $body = '--' . $alternative . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "\r\n"
        . $plain . "\r\n"
        . '--' . $alternative . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "\r\n"
        . $html . "\r\n"
        . '--' . $alternative . "--\r\n";
    if ($logo === null) {
        return 'Content-Type: multipart/alternative; boundary="' . $alternative . "\"\r\n\r\n" . $body;
    }
    $related = 'jt_rel_' . bin2hex(random_bytes(6));
    $encoded = rtrim(chunk_split(base64_encode($logo['bytes']), 76, "\r\n"));
    $subtype = $logo['mime'] === 'image/jpeg' ? 'jpeg' : 'png';
    return 'Content-Type: multipart/related; boundary="' . $related . "\"\r\n"
        . "\r\n"
        . '--' . $related . "\r\n"
        . 'Content-Type: multipart/alternative; boundary="' . $alternative . "\"\r\n"
        . "\r\n"
        . $body
        . '--' . $related . "\r\n"
        . 'Content-Type: image/' . $subtype . "\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "Content-ID: <temple-logo>\r\n"
        . "Content-Disposition: inline; filename=\"logo." . $subtype . "\"\r\n"
        . "\r\n"
        . $encoded . "\r\n"
        . '--' . $related . "--\r\n";
}

/**
 * Returns an error message, or null when the message was accepted.
 *
 * @param array<string, string> $settings
 * @param array{filename: string, content: string, mime: string}|null $attachment
 */
function send_smtp_message(array $settings, string $to, string $subject, string $body, ?array $attachment = null): ?string
{
    if (preg_match('/[\r\n]/', $to . $subject) === 1 || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return 'That email address is not valid.';
    }
    $attachmentError = smtp_attachment_error($attachment);
    if ($attachmentError !== null) {
        return $attachmentError;
    }
    if (!smtp_is_ready($settings)) {
        return 'Outgoing mail is not configured.';
    }
    $encryption = $settings['smtp_encryption'];
    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $settings['smtp_host'] . ':' . (int) $settings['smtp_port'];
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
    if ($socket === false) {
        return 'Could not reach the mail server.';
    }
    stream_set_timeout($socket, 12);
    try {
        if (!str_starts_with(smtp_read($socket), '220')) {
            return 'The mail server did not accept the connection.';
        }
        $hello = smtp_command($socket, 'EHLO localhost');
        if (!str_starts_with($hello, '250')) {
            return 'The mail server refused the greeting.';
        }
        if ($encryption === 'tls') {
            if (!str_starts_with(smtp_command($socket, 'STARTTLS'), '220')) {
                return 'The mail server could not start a secure connection.';
            }
            $secure = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($secure !== true) {
                return 'The mail server could not start a secure connection.';
            }
            if (!str_starts_with(smtp_command($socket, 'EHLO localhost'), '250')) {
                return 'The mail server refused the greeting.';
            }
        }
        if ($settings['smtp_username'] !== '') {
            if (!str_starts_with(smtp_command($socket, 'AUTH LOGIN'), '334')) {
                return 'The mail server refused the login.';
            }
            if (!str_starts_with(smtp_command($socket, base64_encode($settings['smtp_username'])), '334')) {
                return 'The mail server refused the login.';
            }
            if (!str_starts_with(smtp_command($socket, base64_encode($settings['smtp_password'])), '235')) {
                return 'The mail server refused the username or password.';
            }
        }
        $from = $settings['smtp_from_email'];
        if (!str_starts_with(smtp_command($socket, 'MAIL FROM:<' . $from . '>'), '250')) {
            return 'The mail server refused the From address.';
        }
        if (!str_starts_with(smtp_command($socket, 'RCPT TO:<' . $to . '>'), '250')) {
            return 'The mail server refused that recipient.';
        }
        if (!str_starts_with(smtp_command($socket, 'DATA'), '354')) {
            return 'The mail server refused the message.';
        }
        $fromName = trim($settings['smtp_from_name']) !== '' ? trim($settings['smtp_from_name']) : APP_NAME;
        fwrite($socket, smtp_data_payload($fromName, $from, $to, $subject, $body, $attachment));
        $accepted = smtp_read($socket);
        smtp_command($socket, 'QUIT');
        if (!str_starts_with($accepted, '250')) {
            return 'The mail server refused the message.';
        }
        return null;
    } finally {
        fclose($socket);
    }
}

/** @param resource $socket */
function smtp_command($socket, string $command): string
{
    fwrite($socket, $command . "\r\n");
    return smtp_read($socket);
}

/** @param resource $socket */
function smtp_read($socket): string
{
    $lines = '';
    while (($line = fgets($socket, 512)) !== false) {
        $lines .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $lines;
}

function smtp_quoted_name(string $value): string
{
    if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function smtp_dot_stuff(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = str_replace("\n", "\r\n", $body);
    return preg_replace('/^\./m', '..', $body) ?? $body;
}

/** @return array{country_code: string, template: string, smtp_ready: bool} */
function messaging_for_page(): array
{
    $settings = load_messaging_settings();
    return [
        'country_code' => $settings['whatsapp_country_code'],
        'template' => $settings['whatsapp_template'],
        'smtp_ready' => smtp_is_ready($settings),
    ];
}

const BRAND_NAME_KEY = 'brand_name';
const BRAND_LOGO_KEY = 'brand_logo';
const BRAND_RECEIPT_WATERMARK_KEY = 'watermark_receipt';
const BRAND_COUPON_WATERMARK_KEY = 'watermark_coupon';
const BRAND_LOGO_MAX_BYTES = 2097152;

function brand_watermark_error(string $raw): ?string
{
    if (preg_match('/^(100|[1-9]?[0-9])$/', trim($raw)) !== 1) {
        return 'Enter a watermark level from 0 to 100.';
    }
    return null;
}

function brand_watermark_level(string $document): int
{
    $key = $document === 'coupon' ? BRAND_COUPON_WATERMARK_KEY : BRAND_RECEIPT_WATERMARK_KEY;
    $raw = brand_setting($key);
    if (brand_watermark_error($raw) !== null) {
        return 22;
    }
    return (int) $raw;
}

function brand_watermark_opacity(string $document): float
{
    return brand_watermark_level($document) / 100;
}

function brand_setting(string $key): string
{
    $row = db_one('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$key]);
    if ($row === null) {
        return '';
    }
    return trim((string) ($row['setting_value'] ?? ''));
}

function app_display_name(): string
{
    $name = brand_setting(BRAND_NAME_KEY);
    return $name !== '' ? $name : APP_NAME;
}

function app_logo_url(): string
{
    $path = brand_logo_path();
    if ($path === null) {
        $fallback = APP_ROOT . '/static/logo.svg';
        $version = is_file($fallback) ? (string) filemtime($fallback) : '1';
        return asset('logo.svg') . '?v=' . rawurlencode($version);
    }
    return url('brand/logo', ['v' => (string) filemtime($path)]);
}

function brand_has_custom_logo(): bool
{
    return brand_logo_path() !== null;
}

function brand_logo_path(): ?string
{
    $file = brand_setting(BRAND_LOGO_KEY);
    if (preg_match('/^logo\.[a-z0-9]{1,8}$/', $file) !== 1) {
        return null;
    }
    $dir = APP_ROOT . '/storage/brand';
    $candidate = $dir . DIRECTORY_SEPARATOR . $file;
    if (!is_file($candidate)) {
        return null;
    }
    $real = realpath($candidate);
    $root = realpath($dir);
    if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $real;
}

/**
 * @return array{extension: string}|array{error: string}
 */
function inspect_brand_logo(string $path, string $originalName): array
{
    if (!is_file($path)) {
        return ['error' => 'The logo file could not be read.'];
    }
    $size = filesize($path);
    if ($size === false || $size < 1) {
        return ['error' => 'The logo file is empty.'];
    }
    if ($size > BRAND_LOGO_MAX_BYTES) {
        return ['error' => 'The logo must be 2 MB or smaller.'];
    }
    $mime = brand_mime_type($path);
    $info = @getimagesize($path);
    $typeExtension = is_array($info) ? brand_extension_for_image_type((int) ($info[2] ?? 0)) : null;
    $imageMime = (is_array($info) && isset($info['mime']) && is_string($info['mime'])) ? strtolower($info['mime']) : '';
    if (str_starts_with($imageMime, 'image/')) {
        $mime = $imageMime;
    }
    $originalExtension = brand_original_extension($originalName);

    if ($typeExtension !== null) {
        return ['extension' => $typeExtension];
    }
    $looksLikeSvg = $mime === 'image/svg+xml'
        || ($originalExtension === 'svg' && ($mime === '' || str_starts_with($mime, 'text/') || str_starts_with($mime, 'image/') || $mime === 'application/xml' || $mime === 'application/octet-stream'));
    if ($looksLikeSvg) {
        if (!brand_svg_is_safe($path)) {
            return ['error' => 'This SVG logo cannot be used. Remove scripts and event handlers, then try again.'];
        }
        return ['extension' => 'svg'];
    }
    if (str_starts_with($mime, 'image/')) {
        $mapped = brand_extension_for_mime($mime) ?? $typeExtension;
        if ($mapped !== null) {
            return ['extension' => $mapped];
        }
        if ($originalExtension !== null) {
            return ['extension' => $originalExtension];
        }
        return ['error' => 'This image type needs a normal file extension, such as .heic or .bmp.'];
    }
    if ($typeExtension !== null) {
        return ['extension' => $typeExtension];
    }
    return ['error' => 'Choose an image file. Other file types cannot be used as the logo.'];
}

/**
 * Saves the temple name. A new logo replaces the current one. Restoring the built-in logo
 * applies only when no new file was chosen.
 *
 * @param mixed $file
 */
function save_brand_identity(
    string $name,
    mixed $file,
    bool $useDefaultLogo,
    ?string $receiptWatermark = null,
    ?string $couponWatermark = null
): ?string
{
    $name = trim($name);
    if ($name === '') {
        return 'Enter the temple name.';
    }
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($nameLength > 80) {
        return 'The temple name can be at most 80 characters.';
    }
    if (preg_match('/[\x00-\x1F\x7F<>]/u', $name) === 1) {
        return 'The temple name cannot contain those characters.';
    }
    if ($receiptWatermark !== null || $couponWatermark !== null) {
        if (brand_watermark_error((string) $receiptWatermark) !== null) {
            return 'Enter a receipt watermark from 0 to 100.';
        }
        if (brand_watermark_error((string) $couponWatermark) !== null) {
            return 'Enter a coupon watermark from 0 to 100.';
        }
    }

    $upload = brand_upload($file);
    if (isset($upload['error'])) {
        return $upload['error'];
    }
    $extension = null;
    if ($upload['path'] !== null) {
        $inspected = inspect_brand_logo($upload['path'], $upload['name']);
        if (isset($inspected['error'])) {
            return $inspected['error'];
        }
        $extension = $inspected['extension'];
    }

    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    $written = null;
    try {
        brand_upsert(BRAND_NAME_KEY, $name);
        if ($receiptWatermark !== null && $couponWatermark !== null) {
            brand_upsert(BRAND_RECEIPT_WATERMARK_KEY, (string) (int) $receiptWatermark);
            brand_upsert(BRAND_COUPON_WATERMARK_KEY, (string) (int) $couponWatermark);
        }
        if ($extension !== null && $upload['path'] !== null) {
            $written = brand_write_logo($upload['path'], $extension, $upload['uploaded']);
            brand_upsert(BRAND_LOGO_KEY, 'logo.' . $extension);
            brand_remove_other_logos('logo.' . $extension);
            brand_clear_print_png();
            if (brand_ensure_print_png($written) === null && brand_decode_logo_file($written) === null) {
                throw new RuntimeException('The logo could not be prepared for receipts.');
            }
        } elseif ($useDefaultLogo) {
            brand_remove_other_logos('');
            brand_clear_print_png();
            db_exec('DELETE FROM app_settings WHERE setting_key = ?', [BRAND_LOGO_KEY]);
        }
        if ($own) {
            $pdo->commit();
        }
        return null;
    } catch (Throwable $e) {
        if ($written !== null && is_file($written)) {
            unlink($written);
        }
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getMessage() === 'The logo could not be prepared for receipts.') {
            return 'This server could not prepare that logo for receipts and coupons. Upload a PNG or JPG image.';
        }
        throw $e;
    }
}

function serve_brand_logo(): void
{
    $path = brand_logo_path();
    if ($path === null) {
        http_response_code(404);
        echo 'Logo not found.';
        return;
    }
    $mime = brand_mime_type($path);
    if ($mime === '' || !str_starts_with($mime, 'image/')) {
        http_response_code(404);
        echo 'Logo not found.';
        return;
    }
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
}

function brand_extension_for_mime(string $mime): ?string
{
    return match ($mime) {
        'image/jpeg', 'image/jpg', 'image/pjpeg' => 'jpg',
        'image/png', 'image/apng', 'image/x-png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/bmp', 'image/x-ms-bmp', 'image/x-bmp' => 'bmp',
        'image/tiff', 'image/tif' => 'tif',
        'image/avif' => 'avif',
        'image/heic' => 'heic',
        'image/heif', 'image/heic-sequence', 'image/heif-sequence' => 'heif',
        'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
        'image/jxl' => 'jxl',
        'image/jp2', 'image/jpx', 'image/jpm' => 'jp2',
        'image/vnd.wap.wbmp' => 'wbmp',
        default => null,
    };
}

function brand_extension_for_image_type(int $type): ?string
{
    $map = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_BMP => 'bmp',
        IMAGETYPE_ICO => 'ico',
        IMAGETYPE_TIFF_II => 'tif',
        IMAGETYPE_TIFF_MM => 'tif',
    ];
    if (defined('IMAGETYPE_AVIF')) {
        $map[IMAGETYPE_AVIF] = 'avif';
    }
    return $map[$type] ?? null;
}

function brand_original_extension(string $originalName): ?string
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (preg_match('/^[a-z0-9]{1,8}$/', $extension) !== 1) {
        return null;
    }
    $blocked = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'pht', 'cgi', 'pl', 'py',
        'exe', 'dll', 'so', 'js', 'mjs', 'html', 'htm', 'xhtml', 'shtml', 'asp',
        'aspx', 'jsp', 'sh', 'bat', 'cmd', 'com', 'msi', 'jar', 'svgz',
    ];
    return in_array($extension, $blocked, true) ? null : $extension;
}

function brand_svg_is_safe(string $path): bool
{
    $raw = file_get_contents($path, false, null, 0, BRAND_LOGO_MAX_BYTES);
    if (!is_string($raw) || $raw === '') {
        return false;
    }
    if (!str_contains(strtolower($raw), '<svg')) {
        return false;
    }
    $lower = strtolower($raw);
    foreach (['<script', 'javascript:', '<foreignobject', 'data:text/html', '<?php', '<!entity'] as $blocked) {
        if (str_contains($lower, $blocked)) {
            return false;
        }
    }
    return preg_match('/\son[a-z]+\s*=/i', $raw) !== 1;
}

/**
 * @param mixed $file
 * @return array{path: ?string, name: string, uploaded: bool}|array{error: string}
 */
function brand_upload(mixed $file): array
{
    if (is_array($file) && ($file['uploaded'] ?? null) === false && isset($file['source']) && is_string($file['source'])) {
        if (!is_file($file['source'])) {
            return ['error' => 'The logo file could not be read.'];
        }
        return [
            'path' => $file['source'],
            'name' => (string) ($file['name'] ?? 'logo'),
            'uploaded' => false,
        ];
    }
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'name' => '', 'uploaded' => false];
    }
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'The logo must be 2 MB or smaller.'];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['error' => 'The logo upload did not complete.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['error' => 'The logo upload did not complete.'];
    }
    return [
        'path' => $tmp,
        'name' => (string) ($file['name'] ?? 'logo'),
        'uploaded' => true,
    ];
}

function brand_write_logo(string $source, string $extension, bool $uploaded): string
{
    $dir = APP_ROOT . '/storage/brand';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not store the logo.');
    }
    $dest = $dir . DIRECTORY_SEPARATOR . 'logo.' . $extension;
    $stored = $uploaded ? move_uploaded_file($source, $dest) : copy($source, $dest);
    if ($stored !== true) {
        throw new RuntimeException('Could not store the logo.');
    }
    return $dest;
}

function brand_is_stored_logo_name(string $basename): bool
{
    return preg_match('/^logo\.[a-z0-9]{1,8}$/', $basename) === 1;
}

function brand_remove_other_logos(string $keep): void
{
    $dir = APP_ROOT . '/storage/brand';
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'logo.*') ?: [] as $existing) {
        $name = basename($existing);
        if ($name !== $keep && brand_is_stored_logo_name($name) && is_file($existing)) {
            unlink($existing);
        }
    }
}

function brand_upsert(string $key, string $value): void
{
    db_exec(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
}
