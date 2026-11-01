<?php
declare(strict_types=1);

namespace SJT\Lib;

use SJT\Core\Env;
use SJT\Core\Logger;

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
        $opened = self::openSocket($settings);
        if (is_string($opened)) {
            $viaPhp = self::sendViaPhpMail($settings, $to, $subject, $body, $fromDisplayName, $attachment);
            return $viaPhp === null ? null : ($viaPhp ?: $opened);
        }
        [$socket, $encryption, $useAuth] = $opened;
        stream_set_timeout($socket, 12);
        $ehloName = self::ehloName($settings);
        try {
            if (!str_starts_with(self::read($socket), '220')) {
                return 'The mail server did not accept the connection.';
            }
            $hello = self::command($socket, 'EHLO ' . $ehloName);
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
                if (!str_starts_with(self::command($socket, 'EHLO ' . $ehloName), '250')) {
                    return 'The mail server refused the greeting.';
                }
            }
            if ($useAuth && ($settings['smtp_username'] ?? '') !== '') {
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

    /** @param array<string, string> $settings */
    private static function ehloName(array $settings): string
    {
        $from = (string) ($settings['smtp_from_email'] ?? '');
        if (str_contains($from, '@')) {
            $host = substr($from, strrpos($from, '@') + 1);
            if ($host !== '' && preg_match('/^[a-z0-9.-]+$/i', $host) === 1) {
                return $host;
            }
        }
        $hn = gethostname();
        return is_string($hn) && $hn !== '' ? $hn : 'localhost';
    }

    /**
     * @param array<string, string> $settings
     * @return array{0: resource, 1: string, 2: bool}|string Socket, encryption mode, use AUTH — or error text.
     */
    private static function openSocket(array $settings): array|string
    {
        $plans = [];
        $enc = (string) ($settings['smtp_encryption'] ?? 'tls');
        $host = trim((string) ($settings['smtp_host'] ?? ''));
        $port = (int) ($settings['smtp_port'] ?? 587);
        $localPlans = [];
        if (!Env::bool('SMTP_SKIP_LOCAL_MTA')) {
            foreach (['127.0.0.1', 'localhost'] as $localHost) {
                $localPlans[] = [
                    'remote' => 'tcp://' . $localHost . ':25',
                    'encryption' => 'none',
                    'auth' => false,
                    'label' => 'Local MTA (' . $localHost . ')',
                    'timeout' => 5,
                ];
            }
        }
        $remotePlans = [];
        if ($host !== '' && !Env::bool('SMTP_ONLY_LOCAL_MTA')) {
            $remotePlans[] = [
                'remote' => ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
                'encryption' => $enc,
                'auth' => true,
                'label' => 'ERP SMTP',
                'timeout' => 8,
            ];
        }
        if (self::godaddyRelayEnabled($settings) && !Env::bool('SMTP_ONLY_LOCAL_MTA')) {
            $remotePlans[] = [
                'remote' => 'tcp://relay-hosting.secureserver.net:25',
                'encryption' => 'none',
                'auth' => false,
                'label' => 'GoDaddy relay',
                'timeout' => 8,
            ];
        }
        $plans = self::preferLocalMtaFirst() ? array_merge($localPlans, $remotePlans) : array_merge($remotePlans, $localPlans);
        $lastErr = 'Could not reach the mail server.';
        foreach ($plans as $plan) {
            $errno = 0;
            $errstr = '';
            $timeout = (int) ($plan['timeout'] ?? 12);
            $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $socket = @stream_socket_client(
                $plan['remote'],
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                str_starts_with($plan['remote'], 'ssl://') ? $ctx : null,
            );
            if ($socket !== false) {
                if ($plan['label'] !== 'ERP SMTP') {
                    Logger::info('SMTP connected via ' . $plan['label'], ['remote' => $plan['remote']]);
                }
                return [$socket, $plan['encryption'], $plan['auth']];
            }
            Logger::warning('SMTP connect failed', [
                'route' => $plan['label'],
                'remote' => $plan['remote'],
                'errno' => $errno,
                'errstr' => $errstr,
            ]);
            $lastErr = 'Could not reach the mail server.'
                . ($errstr !== '' ? ' (' . $plan['label'] . ': ' . $errstr . ')' : '');
        }
        return $lastErr;
    }

    /** @param array<string, string> $settings */
    private static function preferLocalMtaFirst(): bool
    {
        if (Env::bool('SMTP_PREFER_LOCAL_MTA') || Env::bool('SMTP_ONLY_LOCAL_MTA')) {
            return true;
        }
        $hn = strtolower((string) gethostname());
        return str_contains($hn, 'secureserver.net');
    }

    private static function godaddyRelayEnabled(array $settings): bool
    {
        if (Env::bool('SMTP_TRY_GODADDY_RELAY') || Env::bool('GODADDY_SMTP_RELAY')) {
            return true;
        }
        if (($settings['smtp_try_godaddy_relay'] ?? '') === '1') {
            return true;
        }
        $host = strtolower(trim((string) ($settings['smtp_host'] ?? '')));
        return str_contains($host, 'secureserver.net');
    }

    /**
     * Last resort when outbound SMTP ports are blocked (common on shared hosting).
     *
     * @param array{filename: string, content: string, mime: string}|null $attachment
     */
    private static function sendViaPhpMail(
        array $settings,
        string $to,
        string $subject,
        string $body,
        string $fromDisplayName,
        ?array $attachment,
    ): ?string {
        if (Env::bool('SMTP_DISABLE_PHP_MAIL')) {
            return 'Outbound SMTP is blocked on this server and PHP mail fallback is disabled (SMTP_DISABLE_PHP_MAIL).';
        }
        if (!function_exists('mail')) {
            return 'Outbound SMTP is blocked and PHP mail() is not available on this server.';
        }
        $from = trim((string) ($settings['smtp_from_email'] ?? ''));
        $fromName = trim((string) ($settings['smtp_from_name'] ?? ''));
        if ($fromName === '') {
            $fromName = $fromDisplayName;
        }
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        $text = str_replace("\n", "\r\n", rtrim($text) . "\r\n\r\n--\r\n" . $fromDisplayName);
        $headers = 'MIME-Version: 1.0' . "\r\n"
            . 'From: ' . self::quotedName($fromName) . ' <' . $from . '>' . "\r\n"
            . 'Reply-To: ' . $from . "\r\n";
        $message = $text;
        if ($attachment !== null) {
            $boundary = 'jt_' . bin2hex(random_bytes(8));
            $encoded = rtrim(chunk_split(base64_encode($attachment['content']), 76, "\r\n"));
            $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";
            $message = '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                . $text . "\r\n"
                . '--' . $boundary . "\r\n"
                . 'Content-Type: application/pdf; name="' . $attachment['filename'] . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; filename="' . $attachment['filename'] . "\"\r\n\r\n"
                . $encoded . "\r\n"
                . '--' . $boundary . "--\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        $params = '-f' . $from;
        $ok = @mail($to, $subject, $message, $headers, $params);
        if (!$ok) {
            Logger::warning('PHP mail() fallback failed', ['to' => $to, 'sendmail' => ini_get('sendmail_path')]);
            return 'Could not send from this hosting account (SMTP ports blocked; PHP mail() failed). Ask GoDaddy to enable mail or use relay via local Exim.';
        }
        Logger::info('Booking email sent via PHP mail() (local MTA)', ['to' => $to]);
        return null;
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
        if ($attachment === null) {
            $alternative = 'jt_alt_' . bin2hex(random_bytes(6));
            $altBody = '--' . $alternative . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $plain . "\r\n"
                . '--' . $alternative . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $html . "\r\n"
                . '--' . $alternative . "--\r\n";
            $raw = $headers
                . 'Content-Type: multipart/alternative; boundary="' . $alternative . "\"\r\n\r\n"
                . $altBody;
            return self::dotStuff($raw) . "\r\n.\r\n";
        }
        // With PDF: plain + attachment (flat multipart/mixed — nested alternative is rejected by some hosts).
        $mixed = 'jt_' . bin2hex(random_bytes(8));
        $encoded = rtrim(chunk_split(base64_encode($attachment['content']), 76, "\r\n"));
        $raw = $headers
            . 'Content-Type: multipart/mixed; boundary="' . $mixed . "\"\r\n\r\n"
            . '--' . $mixed . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $plain . "\r\n"
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
