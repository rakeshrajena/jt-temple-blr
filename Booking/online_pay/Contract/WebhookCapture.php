<?php
declare(strict_types=1);

namespace SJT\OnlinePay\Contract;

/** Normalised payment.captured-style event from a provider webhook. */
final class WebhookCapture
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $paymentId,
        public readonly int $amountPaise,
    ) {
    }
}
