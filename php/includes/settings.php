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
function smtp_data_payload(string $fromName, string $from, string $to, string $subject, string $body, ?array $attachment = null): string
{
    $headers = 'From: ' . smtp_quoted_name($fromName) . ' <' . $from . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . smtp_quoted_name($subject) . "\r\n"
        . "MIME-Version: 1.0\r\n";
    if ($attachment === null) {
        $raw = $headers
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "\r\n"
            . $body;
        return smtp_dot_stuff($raw) . "\r\n.\r\n";
    }
    $boundary = 'jt_' . bin2hex(random_bytes(8));
    $encoded = rtrim(chunk_split(base64_encode($attachment['content']), 76, "\r\n"));
    $raw = $headers
        . 'Content-Type: multipart/mixed; boundary="' . $boundary . "\"\r\n"
        . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "\r\n"
        . $body . "\r\n"
        . '--' . $boundary . "\r\n"
        . 'Content-Type: application/pdf; name="' . $attachment['filename'] . "\"\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . 'Content-Disposition: attachment; filename="' . $attachment['filename'] . "\"\r\n"
        . "\r\n"
        . $encoded . "\r\n"
        . '--' . $boundary . "--\r\n";
    return smtp_dot_stuff($raw) . "\r\n.\r\n";
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
