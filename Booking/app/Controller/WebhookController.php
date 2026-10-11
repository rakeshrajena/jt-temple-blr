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

    /** Razorpay: payment.captured / order.paid. */
    public function razorpay(Request $req): Response
    {
        $gw = Container::gateway();
        $body = $req->rawBody();
        if (!$gw->webhookSignatureValid($body, $req->header('X-Razorpay-Signature'))) {
            Logger::warning('Rejected Razorpay webhook with invalid signature');
            return Response::json(['error' => 'invalid signature'], 401);
        }
        $data = json_decode($body, true) ?: [];
        $event = (string) ($data['event'] ?? '');
        $p = $data['payload']['payment']['entity'] ?? null;
        if (in_array($event, ['payment.captured', 'order.paid'], true) && $p && ($p['status'] ?? '') === 'captured') {
            $row = $gw->paymentForOrder((string) ($p['order_id'] ?? ''));
            if ($row && (int) $row['amount_paise'] === (int) ($p['amount'] ?? -1)) {
                Container::service()->markGatewayPaid($row, (string) $p['id'], 'webhook');
            } elseif ($row) {
                Logger::warning('Razorpay webhook amount mismatch', ['order' => $p['order_id'] ?? null]);
            }
        }
        return Response::json(['ok' => true]);
    }
}
