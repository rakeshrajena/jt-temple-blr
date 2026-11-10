<?php
declare(strict_types=1);

/** Subscription invoice payments via the same Razorpay keys as Booking/.env */

function ensure_subscription_gateway_schema(PDO $pdo): void
{
    ensure_column($pdo, 'subscription_invoices', 'gateway_order_id', 'VARCHAR(64) NULL');
}

/** @return array<string, string> */
function subscription_gateway_env(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $cache = [];
    $bookingRoot = dirname(APP_ROOT) . DIRECTORY_SEPARATOR . 'Booking';
    $files = [];
    if (is_file($bookingRoot . DIRECTORY_SEPARATOR . '.env')) {
        $files[] = $bookingRoot . DIRECTORY_SEPARATOR . '.env';
    }
    $repoEnv = dirname(APP_ROOT) . DIRECTORY_SEPARATOR . '.env';
    if (is_file($repoEnv)) {
        $files[] = $repoEnv;
    }
    foreach ($files as $file) {
        foreach (env_parse((string) file_get_contents($file)) as $k => $v) {
            if (in_array($k, ['PAYMENT_GATEWAY', 'RAZORPAY_KEY_ID', 'RAZORPAY_KEY_SECRET', 'GATEWAY_SSL_VERIFY', 'APP_ENV'], true)) {
                $cache[$k] = $v;
            }
        }
    }
    return $cache;
}

function subscription_gateway_enabled(): bool
{
    $env = subscription_gateway_env();

    return ($env['PAYMENT_GATEWAY'] ?? '') === 'razorpay'
        && trim($env['RAZORPAY_KEY_ID'] ?? '') !== ''
        && trim($env['RAZORPAY_KEY_SECRET'] ?? '') !== '';
}

function subscription_gateway_public_key(): string
{
    return trim(subscription_gateway_env()['RAZORPAY_KEY_ID'] ?? '');
}

function subscription_gateway_env_bool(string $key, bool $default = true): bool
{
    $raw = strtolower(trim(subscription_gateway_env()[$key] ?? ''));
    if ($raw === '') {
        return $default;
    }
    if (in_array($raw, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    if (in_array($raw, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    return $default;
}

function subscription_gateway_tls_peer_verify(): bool
{
    $verify = subscription_gateway_env_bool('GATEWAY_SSL_VERIFY', true);
    $appEnv = strtolower(trim(subscription_gateway_env()['APP_ENV'] ?? 'production'));
    if ($appEnv === 'production') {
        return true;
    }

    return $verify;
}

/** @param resource|\CurlHandle $ch */
function subscription_gateway_apply_tls($ch): void
{
    $verify = subscription_gateway_tls_peer_verify();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
    if ($verify) {
        foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $ca) {
            if (is_string($ca) && $ca !== '' && is_file($ca)) {
                curl_setopt($ch, CURLOPT_CAINFO, $ca);
                break;
            }
        }
    }
}

function subscription_invoice_amount_paise(array $inv): int
{
    return (int) round((float) ($inv['amount'] ?? 0) * 100);
}

/** @return array{order_id: string, amount: int} */
function subscription_gateway_create_order(array $inv): array
{
    $amount = subscription_invoice_amount_paise($inv);
    if ($amount <= 0) {
        throw new RuntimeException('Invoice amount is invalid.');
    }
    $invoiceId = (int) $inv['id'];
    $existing = trim((string) ($inv['gateway_order_id'] ?? ''));
    if ($existing !== '') {
        return ['order_id' => $existing, 'amount' => $amount];
    }
    $receipt = (string) ($inv['invoice_number'] ?? ('SUB-' . $invoiceId));
    $created = subscription_razorpay_api('POST', '/v1/orders', [
        'amount' => $amount,
        'currency' => 'INR',
        'receipt' => mb_substr($receipt, 0, 40),
        'notes' => ['invoice_number' => $receipt, 'subscription_invoice_id' => (string) $invoiceId],
    ]);
    if (empty($created['id'])) {
        throw new RuntimeException('Payment gateway order could not be created: ' . ($created['error']['description'] ?? 'unknown'));
    }
    $orderId = (string) $created['id'];
    db_exec('UPDATE subscription_invoices SET gateway_order_id = ? WHERE id = ?', [$orderId, $invoiceId]);

    return ['order_id' => $orderId, 'amount' => $amount];
}

/** @param array<string, mixed> $post */
function subscription_gateway_verify_post(array $post): ?array
{
    $orderId = trim((string) ($post['razorpay_order_id'] ?? ''));
    $paymentId = trim((string) ($post['razorpay_payment_id'] ?? ''));
    $sig = trim((string) ($post['razorpay_signature'] ?? ''));
    if ($orderId === '' || $paymentId === '' || $sig === '') {
        return null;
    }
    $secret = trim(subscription_gateway_env()['RAZORPAY_KEY_SECRET'] ?? '');
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    return ['order_id' => $orderId, 'payment_id' => $paymentId];
}

function subscription_gateway_fetch_captured_payment(string $orderId, int $expectedPaise): ?string
{
    $res = subscription_razorpay_api('GET', '/v1/orders/' . rawurlencode($orderId) . '/payments', []);
    foreach ($res['items'] ?? [] as $p) {
        if (($p['status'] ?? '') !== 'captured') {
            continue;
        }
        if ((int) ($p['amount'] ?? 0) !== $expectedPaise) {
            continue;
        }
        $pid = (string) ($p['id'] ?? '');
        if ($pid !== '') {
            return $pid;
        }
    }

    return null;
}

/**
 * Mark invoice paid, issue ERP receipt, email subscriber.
 *
 * @return array{ok: bool, error: ?string, ref: string}
 */
function subscription_complete_gateway_payment(int $invoiceId, string $paymentId): array
{
    $saved = record_paid_invoice($invoiceId, 'UPI', $paymentId);
    if ($saved === 'error') {
        return ['ok' => false, 'error' => 'Payment could not be recorded.', 'ref' => $paymentId];
    }
    if ($saved === 'paid') {
        return ['ok' => true, 'error' => null, 'ref' => $paymentId];
    }
    try {
        $receipt = issue_subscription_receipt($invoiceId, null);
        if (($receipt['error'] ?? null) !== null) {
            error_log('[jt_blr] subscription receipt: ' . (string) $receipt['error']);
        } elseif (($receipt['created'] ?? false) === true) {
            $mail = send_subscription_receipt($invoiceId, load_messaging_settings());
            if (($mail['error'] ?? null) !== null) {
                error_log('[jt_blr] subscription receipt email: ' . (string) $mail['error']);
            }
        }
    } catch (Throwable $e) {
        error_log('[jt_blr] subscription receipt: ' . $e->getMessage());
    }

    return ['ok' => true, 'error' => null, 'ref' => $paymentId];
}

/** @param array<string, mixed>|array<int, mixed> $body */
function subscription_razorpay_api(string $method, string $path, array $body): array
{
    $env = subscription_gateway_env();
    $keyId = trim($env['RAZORPAY_KEY_ID'] ?? '');
    $keySecret = trim($env['RAZORPAY_KEY_SECRET'] ?? '');
    $ch = curl_init('https://api.razorpay.com' . $path);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERPWD => $keyId . ':' . $keySecret,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ];
    if ($method !== 'GET' && $body !== []) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    subscription_gateway_apply_tls($ch);
    $raw = curl_exec($ch);
    if ($raw === false) {
        return ['error' => ['description' => curl_error($ch) ?: 'Network error']];
    }
    curl_close($ch);

    return json_decode((string) $raw, true) ?: [];
}

function subscription_gateway_json(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
