<?php
declare(strict_types=1);

namespace SJT\OnlinePay\Contract;

/** Server-verified result of a devotee completing hosted checkout. */
final class VerifiedCheckout
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $paymentId,
    ) {
    }
}
