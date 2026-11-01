<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\OnlinePay\OnlinePaymentService;

/**
 * Backward-compatible facade for {@see OnlinePaymentService}.
 * New code should use Container::onlinePay().
 */
final class PaymentGateway
{
    public function __construct(private OnlinePaymentService $pay)
    {
    }

    public function enabled(): bool
    {
        return $this->pay->enabled();
    }

    public static function upiManualForDevotees(): bool
    {
        return OnlinePaymentService::upiManualForDevotees();
    }

    public function keyId(): string
    {
        return $this->pay->publicKey();
    }

    public function order(array $b): array
    {
        return $this->pay->order($b);
    }

    public function checkoutSignatureValid(string $orderId, string $paymentId, string $signature): bool
    {
        $v = $this->pay->verifyCheckoutResponse([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]);
        return $v !== null && $v->orderId === $orderId && $v->paymentId === $paymentId;
    }

    public function webhookSignatureValid(string $body, string $signature): bool
    {
        return $this->pay->verifyWebhookSignature($body, $signature);
    }

    public function paymentForOrder(string $orderId): ?array
    {
        return $this->pay->paymentForOrder($orderId);
    }
}
