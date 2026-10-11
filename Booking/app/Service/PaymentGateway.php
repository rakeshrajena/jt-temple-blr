<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;
use SJT\Core\Env;
use SJT\Core\Logger;

/**
 * Optional online payment gateway (Razorpay). Disabled unless PAYMENT_GATEWAY=razorpay
 * and keys are set in .env. A booking is marked paid only after the gateway's
 * HMAC signature is verified on the server — never on the browser's word.
 * Enable "automatic capture" for payments in the Razorpay dashboard.
 */
final class PaymentGateway
{
    public function __construct(private Database $db)
    {
    }

    public function enabled(): bool
    {
        return Env::get('PAYMENT_GATEWAY') === 'razorpay' && Env::get('RAZORPAY_KEY_ID') && Env::get('RAZORPAY_KEY_SECRET');
    }

    public function keyId(): string
    {
        return (string) Env::get('RAZORPAY_KEY_ID');
    }

    /** Create (or reuse) a gateway order for the booking's current total. */
    public function order(array $b): array
    {
        $existing = $this->db->one(
            "SELECT * FROM payments WHERE booking_id = ? AND method = 'gateway' AND status = 'initiated' AND amount_paise = ? ORDER BY id DESC LIMIT 1",
            [$b['id'], (int) $b['total_paise']]
        );
        if ($existing) {
            return ['order_id' => $existing['gateway_order_id'], 'amount' => (int) $existing['amount_paise']];
        }
        $res = $this->api('POST', '/v1/orders', [
            'amount' => (int) $b['total_paise'],
            'currency' => 'INR',
            'receipt' => $b['booking_no'],
            'notes' => ['booking_no' => $b['booking_no']],
        ]);
        if (empty($res['id'])) {
            throw new \RuntimeException('Payment gateway order could not be created: ' . ($res['error']['description'] ?? 'unknown error'));
        }
        $this->db->insert('payments', [
            'booking_id' => (int) $b['id'], 'method' => 'gateway', 'reference' => null,
            'amount_paise' => (int) $b['total_paise'], 'status' => 'initiated',
            'gateway_order_id' => $res['id'], 'submitted_at' => now(),
        ]);
        return ['order_id' => $res['id'], 'amount' => (int) $b['total_paise']];
    }

    public function checkoutSignatureValid(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) Env::get('RAZORPAY_KEY_SECRET'));
        return $signature !== '' && hash_equals($expected, $signature);
    }

    public function webhookSignatureValid(string $body, string $signature): bool
    {
        $secret = (string) Env::get('RAZORPAY_WEBHOOK_SECRET');
        return $secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    /** Find the initiated payment row for an order. */
    public function paymentForOrder(string $orderId): ?array
    {
        return $this->db->one("SELECT * FROM payments WHERE method = 'gateway' AND gateway_order_id = ?", [$orderId]);
    }

    private function api(string $method, string $path, array $body): array
    {
        $ch = curl_init('https://api.razorpay.com' . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERPWD => Env::get('RAZORPAY_KEY_ID') . ':' . Env::get('RAZORPAY_KEY_SECRET'),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            Logger::error('Razorpay API network error', ['error' => $err]);
            return ['error' => ['description' => 'Network error']];
        }
        return json_decode((string) $raw, true) ?: [];
    }
}
