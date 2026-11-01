<?php
declare(strict_types=1);

namespace SJT\OnlinePay;

use SJT\Core\Database;
use SJT\Core\Env;
use SJT\OnlinePay\Contract\GatewayAdapter;
use SJT\OnlinePay\Contract\VerifiedCheckout;
use SJT\OnlinePay\Contract\WebhookCapture;

/**
 * Booking-facing online payment API: provider adapter + shared DB persistence.
 * UPI manual flow stays in {@see \SJT\Service\UpiPayment}; only card/UPI-via-gateway lives here.
 */
final class OnlinePaymentService
{
    public function __construct(private Database $db, private GatewayAdapter $adapter)
    {
    }

    public function adapter(): GatewayAdapter
    {
        return $this->adapter;
    }

    public function driverId(): string
    {
        return $this->adapter->id();
    }

    public function enabled(): bool
    {
        return $this->adapter->isConfigured();
    }

    /** When false (default), devotees pay only via the configured gateway; UPI QR + UTR is hidden. */
    public static function upiManualForDevotees(): bool
    {
        return Env::bool('BOOKING_UPI_MANUAL', false);
    }

    public function publicKey(): string
    {
        return $this->adapter->publicKey();
    }

    public function checkoutScriptUrl(): ?string
    {
        return $this->adapter->checkoutScriptUrl();
    }

    /** @return array{script: list<string>, frame: list<string>, connect: list<string>} */
    public function contentSecurityPolicy(): array
    {
        $c = $this->adapter->contentSecurityPolicy();
        return [
            'script' => $c['script'] ?? [],
            'frame' => $c['frame'] ?? [],
            'connect' => $c['connect'] ?? [],
        ];
    }

    /** Create (or reuse) a gateway order row for the booking's current total. */
    public function order(array $booking): array
    {
        $amount = (int) $booking['total_paise'];
        $existing = $this->db->one(
            "SELECT * FROM payments WHERE booking_id = ? AND method = 'gateway' AND status = 'initiated' AND amount_paise = ? ORDER BY id DESC LIMIT 1",
            [$booking['id'], $amount]
        );
        if ($existing) {
            return ['order_id' => $existing['gateway_order_id'], 'amount' => (int) $existing['amount_paise']];
        }
        $created = $this->adapter->createProviderOrder($booking, $amount);
        $this->db->insert('payments', [
            'booking_id' => (int) $booking['id'],
            'method' => 'gateway',
            'reference' => null,
            'amount_paise' => $amount,
            'status' => 'initiated',
            'gateway_order_id' => $created['order_id'],
            'submitted_at' => now(),
        ]);
        return ['order_id' => $created['order_id'], 'amount' => $created['amount']];
    }

    /** @param array<string, mixed> $post */
    public function verifyCheckoutResponse(array $post): ?VerifiedCheckout
    {
        return $this->adapter->verifyCheckoutResponse($post);
    }

    public function paymentForOrder(string $orderId): ?array
    {
        return $this->db->one("SELECT * FROM payments WHERE method = 'gateway' AND gateway_order_id = ?", [$orderId]);
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        return $this->adapter->verifyWebhookSignature($rawBody, $signatureHeader);
    }

    /** @return list<WebhookCapture> */
    public function parseWebhookCaptures(string $rawBody): array
    {
        return $this->adapter->parseWebhookCaptures($rawBody);
    }
}
