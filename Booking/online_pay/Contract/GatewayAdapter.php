<?php
declare(strict_types=1);

namespace SJT\OnlinePay\Contract;

/**
 * One implementation per provider (Razorpay, PayU, etc.).
 * Booking controllers talk only to {@see \SJT\OnlinePay\OnlinePaymentService}, not adapters directly.
 */
interface GatewayAdapter
{
    /** Short id matching PAYMENT_GATEWAY in .env (e.g. razorpay). */
    public function id(): string;

    public function isConfigured(): bool;

    /** Public / client key shown in checkout (Razorpay key id, Stripe publishable key, …). */
    public function publicKey(): string;

    /**
     * Create a provider order for amountPaise. Return provider order id and any client-only fields.
     *
     * @param array<string, mixed> $booking booking row (booking_no, id, …)
     * @return array{order_id: string, amount: int, currency: string, client: array<string, mixed>}
     */
    public function createProviderOrder(array $booking, int $amountPaise): array;

    /**
     * Verify browser callback POST body after checkout. Null if invalid.
     *
     * @param array<string, mixed> $post
     */
    public function verifyCheckoutResponse(array $post): ?VerifiedCheckout;

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool;

    /**
     * @return list<WebhookCapture> captured payments to reconcile (empty if event ignored)
     */
    public function parseWebhookCaptures(string $rawBody): array;

    /** Checkout JS URL, or null if the provider uses a redirect-only flow. */
    public function checkoutScriptUrl(): ?string;

    /** Extra CSP sources when this gateway is active. */
    public function contentSecurityPolicy(): array;

    /**
     * Server-side lookup when checkout verify failed but money may have been captured (network glitch, tab closed).
     *
     * @return list<WebhookCapture>
     */
    public function fetchCapturedPaymentsForOrder(string $orderId): array;
}
