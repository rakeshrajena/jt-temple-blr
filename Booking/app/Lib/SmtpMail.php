<?php
declare(strict_types=1);

namespace SJT\Lib;

/**
 * Minimal SMTP client (same behaviour as ERP Settings › Messages).
 * Keeps the booking app free of Composer while sharing temple mail settings in app_settings.
 */
final class SmtpMail
{
    /** @param array<string, string> $settings */
    public static function isReady(array $settings): bool
    {
        return trim($settings['smtp_host'] ?? '') !== ''
            && filter_var($settings['smtp_from_email'] ?? '', FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param array<string, string> $settings
     * @param array{filename: string, content: string, mime: string}|null $attachment
     * @return string|null Error message, or null when accepted by the server.
     */
    public static function send(
        array $settings,
        string $to,
        string $subject,
        string $body,
        string $fromDisplayName,
        ?array $attachment = null,
        ?string $html = null,
    ): ?string {
        if (preg_match('/[\r\n]/', $to . $subject) === 1 || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return 'That email address is not valid.';
        }
        if ($attachment !== null) {
            $name = (string) ($attachment['filename'] ?? '');
            $mime = (string) ($attachment['mime'] ?? '');
            if (preg_match('/^[A-Za-z0-9._-]+$/', $name) !== 1 || $mime !== 'application/pdf') {
                return 'The receipt file cannot be attached.';
            }
            if (($attachment['content'] ?? '') === '') {
                return 'The receipt file is empty.';
            }
        }
        if (!self::isReady($settings)) {
            return 'Outgoing mail is not configured.';
        }
        $encryption = $settings['smtp_encryption'] ?? 'tls';
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $settings['smtp_host'] . ':' . (int) ($settings['smtp_port'] ?? 587);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            return 'Could not reach the mail server.';
        }
        stream_set_timeout($socket, 12);
        try {
            if (!str_starts_with(self::read($socket), '220')) {
                return 'The mail server did not accept the connection.';
            }
            $hello = self::command($socket, 'EHLO localhost');
            if (!str_starts_with($hello, '250')) {
                return 'The mail server refused the greeting.';
            }
            if ($encryption === 'tls') {
                if (!str_starts_with(self::command($socket, 'STARTTLS'), '220')) {
                    return 'The mail server could not start a secure connection.';
                }
                if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    return 'The mail server could not start a secure connection.';
                }
                if (!str_starts_with(self::command($socket, 'EHLO localhost'), '250')) {
                    return 'The mail server refused the greeting.';
                }
            }
            if (($settings['smtp_username'] ?? '') !== '') {
                if (!str_starts_with(self::command($socket, 'AUTH LOGIN'), '334')) {
                    return 'The mail server refused the login.';
                }
                if (!str_starts_with(self::command($socket, base64_encode($settings['smtp_username'])), '334')) {
                    return 'The mail server refused the login.';
                }
                if (!str_starts_with(self::command($socket, base64_encode($settings['smtp_password'] ?? '')), '235')) {
                    return 'The mail server refused the username or password.';
                }
            }
            $from = $settings['smtp_from_email'];
            if (!str_starts_with(self::command($socket, 'MAIL FROM:<' . $from . '>'), '250')) {
                return 'The mail server refused the From address.';
            }
            if (!str_starts_with(self::command($socket, 'RCPT TO:<' . $to . '>'), '250')) {
                return 'The mail server refused that recipient.';
            }
            if (!str_starts_with(self::command($socket, 'DATA'), '354')) {
                return 'The mail server refused the message.';
            }
            $fromName = trim($settings['smtp_from_name'] ?? '') !== '' ? trim($settings['smtp_from_name']) : $fromDisplayName;
            fwrite($socket, self::dataPayload($fromName, $from, $to, $subject, $body, $attachment, $html, $fromDisplayName));
            $accepted = self::read($socket);
            self::command($socket, 'QUIT');
            if (!str_starts_with($accepted, '250')) {
                return 'The mail server refused the message.';
            }
            return null;
        } finally {
            fclose($socket);
        }
    }

    /** @param array{filename: string, content: string, mime: string}|null $attachment */
    private static function dataPayload(
        string $fromName,
        string $from,
        string $to,
        string $subject,
        string $body,
        ?array $attachment,
        ?string $htmlOverride,
        string $signatureName,
    ): string {
        $headers = 'From: ' . self::quotedName($fromName) . ' <' . $from . ">\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . self::quotedName($subject) . "\r\n"
            . "MIME-Version: 1.0\r\n";
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        $text = str_replace("\n", "\r\n", $text);
        $plain = rtrim($text) . "\r\n\r\n--\r\n" . $signatureName;
        $html = $htmlOverride ?? self::htmlDocument($body, $signatureName);
        $alternative = 'jt_alt_' . bin2hex(random_bytes(6));
        $altBody = '--' . $alternative . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $plain . "\r\n"
            . '--' . $alternative . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $html . "\r\n"
            . '--' . $alternative . "--\r\n";
        $message = 'Content-Type: multipart/alternative; boundary="' . $alternative . "\"\r\n\r\n" . $altBody;
        if ($attachment === null) {
            $raw = $headers . "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $plain;
            return self::dotStuff($raw) . "\r\n.\r\n";
        }
        $mixed = 'jt_' . bin2hex(random_bytes(8));
        $encoded = rtrim(chunk_split(base64_encode($attachment['content']), 76, "\r\n"));
        $raw = $headers
            . 'Content-Type: multipart/mixed; boundary="' . $mixed . "\"\r\n\r\n"
            . '--' . $mixed . "\r\n" . $message
            . '--' . $mixed . "\r\n"
            . 'Content-Type: application/pdf; name="' . $attachment['filename'] . "\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . 'Content-Disposition: attachment; filename="' . $attachment['filename'] . "\"\r\n\r\n"
            . $encoded . "\r\n"
            . '--' . $mixed . "--\r\n";
        return self::dotStuff($raw) . "\r\n.\r\n";
    }

    private static function htmlDocument(string $message, string $signatureName): string
    {
        $safe = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $sig = htmlspecialchars($signatureName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<!DOCTYPE html><html><body style="margin:0;padding:16px;font-family:Georgia,serif;color:#222;">'
            . '<div style="font-size:15px;line-height:1.5;">' . $safe . '</div>'
            . '<p style="margin-top:18px;border-top:1px solid #e6dcc8;padding-top:12px;font-size:13px;color:#6b625a;">'
            . $sig . '</p></body></html>';
    }

    /** @param resource $socket */
    private static function command($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        return self::read($socket);
    }

    /** @param resource $socket */
    private static function read($socket): string
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

    private static function quotedName(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private static function dotStuff(string $body): string
    {
        return preg_replace('/^\./m', '..', $body) ?? $body;
    }
}
