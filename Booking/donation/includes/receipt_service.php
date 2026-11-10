<?php
declare(strict_types=1);

use SJT\Core\Database;
use SJT\Core\Env;
use SJT\Core\Logger;
use SJT\Service\TempleLogo;

/** @return array{number: ?string, share_token: ?string, pdf_ready: bool} */
function donation_issue_full_receipt(Database $db, int $donationId): array
{
    $row = $db->one(
        'SELECT id, receipt_number, receipt_generated, receipt_share_token, online_booking_no FROM donations WHERE id = ?',
        [$donationId]
    );
    if (!$row) {
        return ['number' => null, 'share_token' => null, 'pdf_ready' => false];
    }

    $pdfReady = donation_receipt_pdf_ready((string) ($row['receipt_number'] ?? ''));
    if (!$pdfReady || (int) ($row['receipt_generated'] ?? 0) !== 1 || trim((string) ($row['receipt_share_token'] ?? '')) === '') {
        donation_run_cli_issue_receipt($donationId);
        $row = $db->one(
            'SELECT receipt_number, receipt_generated, receipt_share_token FROM donations WHERE id = ?',
            [$donationId]
        ) ?: $row;
        $pdfReady = donation_receipt_pdf_ready((string) ($row['receipt_number'] ?? ''));
    }

    $token = trim((string) ($row['receipt_share_token'] ?? ''));
    if ($pdfReady && $token === '' && donation_run_cli_issue_receipt($donationId)) {
        $row = $db->one('SELECT receipt_number, receipt_share_token FROM donations WHERE id = ?', [$donationId]) ?: $row;
        $token = trim((string) ($row['receipt_share_token'] ?? ''));
    }

    $number = trim((string) ($row['receipt_number'] ?? ''));

    return [
        'number' => $number !== '' ? $number : null,
        'share_token' => preg_match('/^[a-f0-9]{32}$/', $token) === 1 ? $token : null,
        'pdf_ready' => donation_receipt_pdf_ready($number),
    ];
}

function donation_receipt_pdf_ready(string $receiptNumber): bool
{
    $receiptNumber = trim($receiptNumber);
    if ($receiptNumber === '' || !donation_receipt_number_valid($receiptNumber)) {
        return false;
    }
    $path = TempleLogo::erpRoot() . '/storage/receipts/' . $receiptNumber . '.pdf';

    return is_file($path);
}

function donation_receipt_number_valid(string $number): bool
{
    return preg_match('/^RCPT-\d{10}-\d{4,6}$/', $number) === 1
        || preg_match('/^RCPT-\d{4}-\d{4}$/', $number) === 1;
}

function donation_php_cli_candidates(): array
{
    $candidates = [];
    $fromEnv = trim((string) Env::get('PHP_CLI_PATH', ''));
    if ($fromEnv !== '') {
        $candidates[] = $fromEnv;
    }

    $bookingRoot = defined('BOOKING_ROOT') ? BOOKING_ROOT : dirname(__DIR__, 2);
    $walk = $bookingRoot;
    for ($i = 0; $i < 8; $i++) {
        if (basename(str_replace('\\', '/', $walk)) === 'www') {
            $ampps = dirname($walk) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
            if (is_file($ampps)) {
                $candidates[] = $ampps;
            }
        }
        $parent = dirname($walk);
        if ($parent === $walk) {
            break;
        }
        $walk = $parent;
    }

    if (DIRECTORY_SEPARATOR === '\\' && is_file('C:\\Program Files\\Ampps\\php\\php.exe')) {
        $candidates[] = 'C:\\Program Files\\Ampps\\php\\php.exe';
    }

    if (defined('PHP_BINDIR')) {
        $guess = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php' . (DIRECTORY_SEPARATOR === '\\' ? '.exe' : '');
        if (is_file($guess)) {
            $candidates[] = $guess;
        }
    }

    if (defined('PHP_BINARY') && donation_php_cli_is_php_exe(PHP_BINARY)) {
        $candidates[] = PHP_BINARY;
    }

    $candidates[] = 'php';

    $unique = [];
    foreach ($candidates as $c) {
        $c = trim((string) $c);
        if ($c !== '' && !in_array($c, $unique, true)) {
            $unique[] = $c;
        }
    }

    return $unique;
}

function donation_php_cli_is_php_exe(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }
    if (preg_match('/php(?:\.exe)?$/i', $path) !== 1) {
        return false;
    }

    return donation_php_cli_responds($path);
}

function donation_php_cli_responds(string $php): bool
{
    $lines = [];
    $code = 1;
    $suffix = DIRECTORY_SEPARATOR === '\\' ? ' 2>NUL' : ' 2>/dev/null';
    @exec(escapeshellarg($php) . ' -r "echo 1;"' . $suffix, $lines, $code);

    return $code === 0 && ($lines[0] ?? '') === '1';
}

function donation_php_cli(): string
{
    foreach (donation_php_cli_candidates() as $php) {
        if ($php === 'php') {
            if (donation_php_cli_responds('php')) {
                return 'php';
            }
            continue;
        }
        if (donation_php_cli_is_php_exe($php)) {
            return $php;
        }
    }

    return 'C:\\Program Files\\Ampps\\php\\php.exe';
}

function donation_worker_token(): string
{
    $secret = trim((string) Env::get('APP_KEY', ''));
    if ($secret !== '') {
        return $secret;
    }

    return trim((string) Env::get('INSTALL_TOKEN', ''));
}

/** @param array<string, string> $fields */
function donation_http_worker(string $script, array $fields): ?string
{
    $token = donation_worker_token();
    if ($token === '') {
        return null;
    }

    $url = donation_url('worker/' . ltrim($script, '/'));
    $fields['token'] = $token;
    $body = http_build_query($fields);
    $headers = "Content-Type: application/x-www-form-urlencoded\r\n"
        . 'X-SJT-Donation-Worker: ' . $token . "\r\n"
        . "Content-Length: " . strlen($body) . "\r\n";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'X-SJT-Donation-Worker: ' . $token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 120,
        ]);
        $out = curl_exec($ch);
        curl_close($ch);

        return is_string($out) ? $out : null;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => $body,
            'timeout' => 120,
            'ignore_errors' => true,
        ],
    ]);
    $out = @file_get_contents($url, false, $ctx);

    return is_string($out) ? $out : null;
}

function donation_run_cli_issue_receipt(int $donationId): bool
{
    $script = TempleLogo::erpRoot() . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'issue_donation_receipt.php';
    $output = '';
    $code = 0;

    if (is_file($script)) {
        $php = donation_php_cli();
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . $donationId;
        $lines = [];
        if (function_exists('exec') && !donation_function_disabled('exec')) {
            exec($cmd . (DIRECTORY_SEPARATOR === '\\' ? ' 2>NUL' : ' 2>/dev/null'), $lines, $code);
            $output = implode("\n", $lines);
        }
    }

    if ($code === 0 && donation_receipt_pdf_ready_after_id($donationId)) {
        return true;
    }

    Logger::warning('Donation receipt CLI failed or skipped', [
        'donation' => $donationId,
        'code' => $code,
        'output' => $output,
    ]);

    $http = donation_http_worker('issue.php', ['id' => (string) $donationId]);
    if ($http !== null && str_contains($http, 'OK')) {
        return donation_receipt_pdf_ready_after_id($donationId);
    }

    Logger::warning('Donation receipt HTTP worker failed', ['donation' => $donationId, 'output' => $http ?? '']);

    return false;
}

function donation_receipt_pdf_ready_after_id(int $donationId): bool
{
    $db = \SJT\Core\App::db();
    $row = $db->one('SELECT receipt_number FROM donations WHERE id = ?', [$donationId]);
    if (!$row) {
        return false;
    }

    return donation_receipt_pdf_ready((string) ($row['receipt_number'] ?? ''));
}

function donation_function_disabled(string $fn): bool
{
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    return in_array($fn, $disabled, true);
}

/** @return array{error: ?string, email: string, number: string} */
function donation_send_receipt_email(int $donationId, ?string $to = null): array
{
    $output = '';
    $code = 0;

    $script = DONATION_ROOT . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'send_donation_receipt_email.php';
    if (is_file($script) && function_exists('exec') && !donation_function_disabled('exec')) {
        $php = donation_php_cli();
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . $donationId;
        if ($to !== null && trim($to) !== '') {
            $cmd .= ' ' . escapeshellarg(trim($to));
        }
        $lines = [];
        exec($cmd . (DIRECTORY_SEPARATOR === '\\' ? ' 2>NUL' : ' 2>/dev/null'), $lines, $code);
        $output = trim(implode("\n", $lines));
    }

    if ($code !== 0 || $output === '' || !str_contains($output, 'OK')) {
        $fields = ['id' => (string) $donationId];
        if ($to !== null && trim($to) !== '') {
            $fields['email'] = trim($to);
        }
        $http = donation_http_worker('send_email.php', $fields);
        if (is_string($http) && $http !== '') {
            $output = trim($http);
            $code = str_contains($output, 'OK') ? 0 : 1;
        }
    }

    if ($code !== 0) {
        Logger::warning('Donation receipt email failed', ['donation' => $donationId, 'output' => $output]);
        $parsed = donation_parse_email_cli_output($output);

        return [
            'error' => donation_sanitize_user_error($parsed['error'] ?? $output),
            'email' => $parsed['email'] !== '' ? $parsed['email'] : (string) $to,
            'number' => $parsed['number'],
        ];
    }

    $parsed = donation_parse_email_cli_output($output);

    return [
        'error' => $parsed['error'] !== null ? donation_sanitize_user_error($parsed['error']) : null,
        'email' => $parsed['email'],
        'number' => $parsed['number'],
    ];
}

function donation_sanitize_user_error(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return 'Could not send receipt email. Use View or Download PDF on this page, or try again later.';
    }
    if (preg_match('/ERROR:\s*(.+)/', $raw, $m) === 1) {
        $raw = trim($m[1]);
    }
    if (preg_match('/\[(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s+/i', $raw)
        || str_contains($raw, 'AH02965')
        || str_contains($raw, 'mpm_winnt')
        || str_contains($raw, 'httpd')
    ) {
        return 'Email could not be sent right now (server busy). Use View or Download PDF on this page, or try again after restarting Apache in AMPPS.';
    }
    if (str_contains($raw, 'Outgoing mail is not configured') || str_contains($raw, 'smtp')) {
        return 'Outgoing mail is not configured in temple CRM settings. You can still view or download the PDF on this page.';
    }
    if (str_contains($raw, 'receipt PDF is not ready')) {
        return 'Receipt PDF is still being prepared. Refresh this page in a minute, then use View PDF or try email again.';
    }

    return mb_strlen($raw) > 200 ? 'Could not send receipt email. Use View or Download PDF on this page.' : $raw;
}

/** @return array{error: ?string, email: string, number: string} */
function donation_parse_email_cli_output(string $output): array
{
    $error = null;
    $email = '';
    $number = '';
    foreach (explode("\n", $output) as $line) {
        if (str_starts_with($line, 'ERROR:')) {
            $error = trim(substr($line, 6));
        }
        if (str_starts_with($line, 'EMAIL:')) {
            $email = trim(substr($line, 6));
        }
        if (str_starts_with($line, 'NUMBER:')) {
            $number = trim(substr($line, 7));
        }
        if ($line === 'OK') {
            $error = null;
        }
    }

    return ['error' => $error, 'email' => $email, 'number' => $number];
}

function donation_crm_public_origin(): string
{
    foreach ([
        rtrim((string) Env::get('ERP_APP_URL', ''), '/'),
        rtrim(env_value_from_crm('APP_URL'), '/'),
        rtrim((string) Env::get('APP_URL', ''), '/'),
    ] as $configured) {
        if ($configured !== '' && preg_match('#^https?://#i', $configured) === 1) {
            $parts = parse_url($configured);

            return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'localhost')
                . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }
    }
    if (!empty($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string) $_SERVER['HTTP_HOST'];

        return ($https ? 'https' : 'http') . '://' . $host;
    }

    return 'http://localhost';
}

function env_value_from_crm(string $key): string
{
    $erp = TempleLogo::erpRoot();
    $file = $erp . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($file)) {
        return '';
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) {
            return trim($v, " \t\"'");
        }
    }

    return '';
}

function donation_crm_index_script(): string
{
    $erp = rtrim((string) Env::get('ERP_APP_URL', ''), '/');
    if ($erp !== '' && preg_match('#^https?://#i', $erp) === 1) {
        $path = parse_url($erp, PHP_URL_PATH);
        if (is_string($path) && $path !== '' && $path !== '/') {
            return rtrim($path, '/') . '/index.php';
        }
    }

    $booking = rtrim((string) Env::get('APP_URL', ''), '/');
    if ($booking === '') {
        $booking = env_value_from_crm('BOOKING_APP_URL');
    }
    if ($booking !== '' && preg_match('#^https?://#i', $booking) === 1) {
        $path = rtrim((string) (parse_url($booking, PHP_URL_PATH) ?? ''), '/');
        if ($path !== '') {
            $parent = preg_replace('#/Booking$#i', '', $path);
            $erpFolder = basename(str_replace('\\', '/', TempleLogo::erpRoot()));
            if (!in_array($erpFolder, ['crm', 'php'], true)) {
                $erpFolder = 'crm';
            }

            return ($parent !== '' ? $parent : '') . '/' . $erpFolder . '/index.php';
        }
    }

    $crmUrl = env_value_from_crm('APP_URL');
    if ($crmUrl !== '' && preg_match('#^https?://#i', $crmUrl) === 1) {
        $path = parse_url($crmUrl, PHP_URL_PATH);
        if (is_string($path) && $path !== '' && $path !== '/') {
            return rtrim($path, '/') . '/index.php';
        }
    }

    return '/crm/index.php';
}

function donation_crm_receipt_public_url(string $token): string
{
    if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
        return '';
    }

    return donation_crm_public_origin() . donation_crm_index_script() . '?' . http_build_query(['r' => 'receipts/open/' . $token]);
}

/** @return array{view_url: string, pdf_url: string, share_token: string, receipt_number: string}|null */
function donation_receipt_links(Database $db, int $donationId): ?array
{
    $row = $db->one(
        "SELECT receipt_number, receipt_generated, receipt_share_token, receipt_cancelled
         FROM donations WHERE id = ? AND receipt_generated = 1
           AND receipt_number IS NOT NULL AND receipt_number <> ''",
        [$donationId]
    );
    if (!$row || (int) ($row['receipt_cancelled'] ?? 0) === 1) {
        return null;
    }
    $token = trim((string) ($row['receipt_share_token'] ?? ''));
    if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
        return null;
    }
    $number = trim((string) $row['receipt_number']);
    $view = donation_crm_receipt_public_url($token);
    if ($view === '') {
        return null;
    }

    return [
        'view_url' => $view,
        'pdf_url' => donation_url('receipt.php', ['t' => $token]),
        'share_token' => $token,
        'receipt_number' => $number,
    ];
}
