<?php
declare(strict_types=1);

namespace SJT\OnlinePay\Adapters;

use SJT\Core\App;
use SJT\Core\Env;
use SJT\Core\Logger;
use SJT\OnlinePay\Contract\GatewayAdapter;
use SJT\OnlinePay\Contract\VerifiedCheckout;
use SJT\OnlinePay\Contract\WebhookCapture;

final class RazorpayAdapter implements GatewayAdapter
{
    public function id(): string
    {
        return 'razorpay';
    }

    public function isConfigured(): bool
    {
        return Env::get('PAYMENT_GATEWAY') === 'razorpay'
            && Env::get('RAZORPAY_KEY_ID') !== ''
            && Env::get('RAZORPAY_KEY_SECRET') !== '';
    }

    public function publicKey(): string
    {
        return (string) Env::get('RAZORPAY_KEY_ID');
    }

    public function createProviderOrder(array $booking, int $amountPaise): array
    {
        $res = $this->api('POST', '/v1/orders', [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => (string) $booking['booking_no'],
            'notes' => ['booking_no' => (string) $booking['booking_no']],
        ]);
        if (empty($res['id'])) {
            throw new \RuntimeException('Payment gateway order could not be created: ' . ($res['error']['description'] ?? 'unknown error'));
        }
        return [
            'order_id' => (string) $res['id'],
            'amount' => $amountPaise,
            'currency' => 'INR',
            'client' => [],
        ];
    }

    public function verifyCheckoutResponse(array $post): ?VerifiedCheckout
    {
        $orderId = trim((string) ($post['razorpay_order_id'] ?? ''));
        $paymentId = trim((string) ($post['razorpay_payment_id'] ?? ''));
        $sig = trim((string) ($post['razorpay_signature'] ?? ''));
        if ($orderId === '' || $paymentId === '' || $sig === '') {
            return null;
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) Env::get('RAZORPAY_KEY_SECRET'));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        return new VerifiedCheckout($orderId, $paymentId);
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        $secret = (string) Env::get('RAZORPAY_WEBHOOK_SECRET');
        return $secret !== '' && $signatureHeader !== ''
            && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureHeader);
    }

    public function parseWebhookCaptures(string $rawBody): array
    {
        $data = json_decode($rawBody, true) ?: [];
        $event = (string) ($data['event'] ?? '');
        $p = $data['payload']['payment']['entity'] ?? null;
        if (!in_array($event, ['payment.captured', 'order.paid'], true) || !$p || ($p['status'] ?? '') !== 'captured') {
            return [];
        }
        $orderId = (string) ($p['order_id'] ?? '');
        $paymentId = (string) ($p['id'] ?? '');
        if ($orderId === '' || $paymentId === '') {
            return [];
        }
        return [new WebhookCapture($orderId, $paymentId, (int) ($p['amount'] ?? 0))];
    }

    public function checkoutScriptUrl(): ?string
    {
        return 'https://checkout.razorpay.com/v1/checkout.js';
    }

    public function contentSecurityPolicy(): array
    {
        return [
            'script' => ['https://checkout.razorpay.com'],
            'frame' => ['https://api.razorpay.com', 'https://checkout.razorpay.com'],
            'connect' => ['https://lumberjack.razorpay.com', 'https://api.razorpay.com'],
        ];
    }

    public function fetchCapturedPaymentsForOrder(string $orderId): array
    {
        if ($orderId === '') {
            return [];
        }
        $res = $this->api('GET', '/v1/orders/' . rawurlencode($orderId) . '/payments', []);
        $out = [];
        foreach ($res['items'] ?? [] as $p) {
            if (($p['status'] ?? '') !== 'captured') {
                continue;
            }
            $pid = (string) ($p['id'] ?? '');
            if ($pid === '') {
                continue;
            }
            $out[] = new WebhookCapture($orderId, $pid, (int) ($p['amount'] ?? 0));
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private function api(string $method, string $path, array $body): array
    {
        $ch = curl_init('https://api.razorpay.com' . $path);
        $keyId = trim((string) Env::get('RAZORPAY_KEY_ID'));
        $keySecret = trim((string) Env::get('RAZORPAY_KEY_SECRET'));
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERPWD => $keyId . ':' . $keySecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ];
        if ($method !== 'GET' && $body !== []) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        self::applyOutboundTls($ch);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) {
            Logger::error('Razorpay API network error', ['error' => $err]);
            return ['error' => ['description' => 'Network error']];
        }
        $decoded = json_decode((string) $raw, true) ?: [];
        if ($code >= 400 && empty($decoded['error']['description'])) {
            $decoded['error'] = ['description' => 'HTTP ' . $code];
        }
        return $decoded;
    }

    /** AMPPS / some Windows PHP builds lack a trusted CA bundle; production always verifies TLS. */
    private static function applyOutboundTls(\CurlHandle $ch): void
    {
        $verify = Env::bool('GATEWAY_SSL_VERIFY', true);
        if (App::isProduction()) {
            $verify = true;
        }
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
}
