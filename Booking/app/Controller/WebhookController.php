<?php
declare(strict_types=1);

namespace SJT\Controller;

use SJT\Core\Container;
use SJT\Core\Env;
use SJT\Core\Logger;
use SJT\Core\Request;
use SJT\Core\Response;

/** Server-to-server callbacks. Each is authenticated by an HMAC signature. */
final class WebhookController extends Controller
{
    /** Meta webhook verification handshake (GET). */
    public function whatsappVerify(Request $req): Response
    {
        $expected = (string) Env::get('WA_WEBHOOK_VERIFY_TOKEN');
        $mode = (string) ($req->query['hub_mode'] ?? '');
        $token = (string) ($req->query['hub_verify_token'] ?? '');
        if ($expected !== '' && $mode === 'subscribe' && hash_equals($expected, $token)) {
            return new Response((string) ($req->query['hub_challenge'] ?? ''), 200, ['Content-Type' => 'text/plain']);
        }
        return new Response('Forbidden', 403, ['Content-Type' => 'text/plain']);
    }

    /** Delivery status updates (sent / delivered / read / failed). */
    public function whatsapp(Request $req): Response
    {
        $body = $req->rawBody();
        $secret = (string) Env::get('WA_APP_SECRET');
        $sig = $req->header('X-Hub-Signature-256');
        if ($secret === '' || !hash_equals('sha256=' . hash_hmac('sha256', $body, $secret), $sig)) {
            Logger::warning('Rejected WhatsApp webhook with invalid signature');
            return Response::json(['error' => 'invalid signature'], 401);
        }
        $data = json_decode($body, true) ?: [];
        $n = Container::notifier();
        foreach ($data['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $st) {
                    $err = isset($st['errors'][0]) ? (($st['errors'][0]['code'] ?? '') . ' ' . ($st['errors'][0]['title'] ?? '') . ' ' . ($st['errors'][0]['error_data']['details'] ?? '')) : null;
                    $n->applyDeliveryStatus((string) ($st['id'] ?? ''), (string) ($st['status'] ?? ''), $err);
                }
            }
        }
        return Response::json(['ok' => true]);
    }

    /** Active gateway webhook (alias: /webhooks/razorpay for Razorpay). */
    public function payment(Request $req): Response
    {
        $pay = Container::onlinePay();
        $body = $req->rawBody();
        $sig = $req->header('X-Razorpay-Signature') ?: $req->header('X-Webhook-Signature');
        if (!$pay->verifyWebhookSignature($body, $sig)) {
            Logger::warning('Rejected payment webhook with invalid signature', ['driver' => $pay->driverId()]);
            return Response::json(['error' => 'invalid signature'], 401);
        }
        foreach ($pay->parseWebhookCaptures($body) as $cap) {
            $row = $pay->paymentForOrder($cap->orderId);
            if ($row && (int) $row['amount_paise'] === $cap->amountPaise) {
                Container::service()->markGatewayPaid($row, $cap->paymentId, 'webhook');
            } elseif ($row) {
                Logger::warning('Payment webhook amount mismatch', ['order' => $cap->orderId, 'driver' => $pay->driverId()]);
            }
        }
        return Response::json(['ok' => true]);
    }

    /** @deprecated Use payment(); kept for existing Razorpay dashboard URLs. */
    public function razorpay(Request $req): Response
    {
        return $this->payment($req);
    }
}
