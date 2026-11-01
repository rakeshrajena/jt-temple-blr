<?php
declare(strict_types=1);

namespace SJT\OnlinePay\Adapters;

use SJT\OnlinePay\Contract\GatewayAdapter;
use SJT\OnlinePay\Contract\VerifiedCheckout;

/** No online gateway — manual UPI / counter only. */
final class NullAdapter implements GatewayAdapter
{
    public function id(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function publicKey(): string
    {
        return '';
    }

    public function createProviderOrder(array $booking, int $amountPaise): array
    {
        throw new \RuntimeException('Online payment is not configured.');
    }

    public function verifyCheckoutResponse(array $post): ?VerifiedCheckout
    {
        return null;
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        return false;
    }

    public function parseWebhookCaptures(string $rawBody): array
    {
        return [];
    }

    public function checkoutScriptUrl(): ?string
    {
        return null;
    }

    public function contentSecurityPolicy(): array
    {
        return ['script' => [], 'frame' => [], 'connect' => []];
    }
}
