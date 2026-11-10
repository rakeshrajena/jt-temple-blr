<?php
declare(strict_types=1);

use SJT\Core\App;
use SJT\Core\Container;
use SJT\Core\Logger;
use SJT\Core\Session;

if (!function_exists('donation_ref_url')) {
    require_once dirname(__DIR__) . '/includes/router.php';
}

const DONATION_MAX_RUPEES = 10000;
const DONATION_OFFLINE_MESSAGE = 'For donations above ₹10,000, please contact temple management for confirmation and pay offline after confirmation.';

function donation_json(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

/** @param array<string, mixed> $post */
function donation_handle_api(string $action, array $post): void
{
    if ($action === 'order') {
        donation_api_order($post);
    }
    if ($action === 'verify') {
        donation_api_verify($post);
    }
    if ($action === 'sync') {
        donation_api_sync($post);
    }
    if ($action === 'resend_receipt') {
        donation_api_resend_receipt($post);
    }
    donation_json(404, ['error' => 'Unknown action.']);
}

/** @param array<string, mixed> $post */
function donation_api_order(array $post): void
{
    if (!Session::verifyCsrf($post['_csrf'] ?? null)) {
        donation_json(419, ['error' => 'Your session expired. Please refresh the page and try again.']);
    }

    $pay = Container::onlinePay();
    if (!$pay->enabled()) {
        donation_json(503, ['error' => 'Online payment is temporarily unavailable. Please try again later or visit the temple office.']);
    }

    $name = trim((string) ($post['name'] ?? ''));
    $phone = trim((string) ($post['phone'] ?? ''));
    $email = trim((string) ($post['email'] ?? ''));
    $pan = trim((string) ($post['pan'] ?? ''));
    $purpose = trim((string) ($post['purpose'] ?? 'General temple donation'));
    $rupees = filter_var($post['amount_rupees'] ?? null, FILTER_VALIDATE_INT);

    if ($name === '' || mb_strlen($name) > 150) {
        donation_json(400, ['error' => 'Please enter your full name.']);
    }
    $mobile = donation_normalise_mobile($phone);
    if (strlen(preg_replace('/\D/', '', $phone) ?? '') !== 10) {
        donation_json(400, ['error' => 'Please enter a valid 10-digit mobile number.']);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        donation_json(400, ['error' => 'Please enter a valid email address so we can send your receipt PDF.']);
    }
    if ($rupees === false || $rupees < 1) {
        donation_json(400, ['error' => 'Please enter a donation amount.']);
    }
    if ($rupees > DONATION_MAX_RUPEES) {
        donation_json(400, [
            'error' => DONATION_OFFLINE_MESSAGE,
            'offline_required' => true,
        ]);
    }

    $amountPaise = $rupees * 100;
    $db = App::db();
    donation_ensure_schema($db);

    $refNo = donation_new_ref_no();
    $intentId = (int) $db->insert('online_donation_intents', [
        'ref_no' => $refNo,
        'donor_name' => $name,
        'phone' => $mobile,
        'email' => $email !== '' ? $email : null,
        'pan_number' => $pan !== '' ? mb_substr(strtoupper($pan), 0, 20) : null,
        'purpose' => mb_substr($purpose, 0, 200),
        'amount_paise' => $amountPaise,
        'status' => 'awaiting_payment',
    ]);

    try {
        $created = $pay->adapter()->createProviderOrder(['booking_no' => $refNo], $amountPaise);
    } catch (\Throwable $e) {
        $db->update('online_donation_intents', ['status' => 'failed'], 'id = :id', ['id' => $intentId]);
        Logger::error('Donation gateway order failed', ['ref' => $refNo, 'error' => $e->getMessage()]);
        donation_json(502, ['error' => 'Online payment could not be started. Please try again in a few minutes.']);
    }

    $orderId = (string) ($created['order_id'] ?? '');
    if ($orderId === '') {
        donation_json(502, ['error' => 'Online payment could not be started.']);
    }

    $db->update('online_donation_intents', ['gateway_order_id' => $orderId], 'id = :id', ['id' => $intentId]);

    $temple = (string) App::settings()->get('temple_name', 'Shree Jagannatha Temple Bengaluru');
    $digits = preg_replace('/\D/', '', $phone) ?? '';

    donation_json(200, [
        'key' => $pay->publicKey(),
        'order_id' => $orderId,
        'amount' => $amountPaise,
        'currency' => 'INR',
        'name' => $temple,
        'description' => 'Temple donation ' . $refNo,
        'ref_no' => $refNo,
        'prefill' => [
            'name' => $name,
            'contact' => '+91' . $digits,
            'email' => $email,
        ],
        'checkout_script' => $pay->checkoutScriptUrl(),
    ]);
}

/** @param array<string, mixed> $post */
function donation_api_verify(array $post): void
{
    $refNo = trim((string) ($post['ref_no'] ?? ''));
    if ($refNo === '') {
        donation_json(400, ['error' => 'Missing donation reference.']);
    }

    $db = App::db();
    donation_ensure_schema($db);
    $intent = $db->one('SELECT * FROM online_donation_intents WHERE ref_no = ? LIMIT 1', [$refNo]);
    if (!$intent) {
        donation_json(404, ['error' => 'Donation reference was not found.']);
    }

    if (($intent['status'] ?? '') === 'paid') {
        donation_json(200, donation_success_payload($db, $intent));
    }

    $pay = Container::onlinePay();
    $verified = $pay->verifyCheckoutResponse($post);
    if (!$verified) {
        donation_json(400, ['error' => 'Payment could not be verified. If money was deducted, use “Check payment status” or contact the temple office.', 'retry_sync' => true]);
    }

    if ($verified->orderId !== (string) ($intent['gateway_order_id'] ?? '')) {
        donation_json(400, ['error' => 'Payment order does not match this donation.']);
    }

    donation_mark_paid($db, $pay, $intent, $verified->paymentId);
}

/** @param array<string, mixed> $post */
function donation_api_sync(array $post): void
{
    $refNo = trim((string) ($post['ref_no'] ?? ''));
    if ($refNo === '') {
        donation_json(400, ['error' => 'Missing donation reference.']);
    }

    $db = App::db();
    donation_ensure_schema($db);
    $intent = $db->one('SELECT * FROM online_donation_intents WHERE ref_no = ? LIMIT 1', [$refNo]);
    if (!$intent) {
        donation_json(404, ['error' => 'Donation reference was not found.']);
    }
    if (($intent['status'] ?? '') === 'paid') {
        donation_json(200, donation_success_payload($db, $intent));
    }

    $orderId = (string) ($intent['gateway_order_id'] ?? '');
    if ($orderId === '') {
        donation_json(404, ['error' => 'No pending payment was found for this donation.']);
    }

    $pay = Container::onlinePay();
    if (!$pay->enabled()) {
        donation_json(503, ['error' => 'Online payment is not configured.']);
    }

    $expected = (int) $intent['amount_paise'];
    $paymentId = null;
    foreach ($pay->adapter()->fetchCapturedPaymentsForOrder($orderId) as $cap) {
        if ($cap->amountPaise === $expected) {
            $paymentId = $cap->paymentId;
            break;
        }
    }
    if ($paymentId === null) {
        donation_json(404, ['error' => 'We could not find a completed payment yet. If money was deducted, wait a minute and try again.']);
    }

    donation_mark_paid($db, $pay, $intent, $paymentId);
}

/** @param array<string, mixed> $intent */
function donation_mark_paid(\SJT\Core\Database $db, \SJT\OnlinePay\OnlinePaymentService $pay, array $intent, string $paymentId): never
{
    if ((int) ($intent['amount_paise'] ?? 0) <= 0) {
        donation_json(400, ['error' => 'Invalid donation amount.']);
    }

    try {
        $ledger = donation_post_to_erp($db, $intent, $paymentId);
    } catch (\Throwable $e) {
        Logger::error('Donation ERP post failed', ['ref' => $intent['ref_no'] ?? '', 'error' => $e->getMessage()]);
        donation_json(500, ['error' => 'Payment was received but recording failed. Please contact the temple office with your reference.']);
    }

    $db->update('online_donation_intents', [
        'status' => 'paid',
        'gateway_payment_id' => $paymentId,
        'donation_id' => $ledger['donation_id'],
        'paid_at' => now(),
    ], 'id = :id', ['id' => (int) $intent['id']]);

    $fresh = $db->one('SELECT * FROM online_donation_intents WHERE id = ?', [(int) $intent['id']]);
    Session::set('donation_just_paid', (string) ($fresh['ref_no'] ?? $intent['ref_no'] ?? ''));
    donation_maybe_email_receipt($fresh ?: $intent, (int) $ledger['donation_id']);
    donation_json(200, donation_success_payload($db, $fresh ?: $intent, $ledger['receipt_number']));
}

/** @param array<string, mixed> $post */
function donation_api_resend_receipt(array $post): void
{
    if (!Session::verifyCsrf($post['_csrf'] ?? null)) {
        donation_json(419, ['error' => 'Your session expired. Please refresh the page.']);
    }
    $refNo = trim((string) ($post['ref_no'] ?? ''));
    if ($refNo === '') {
        donation_json(400, ['error' => 'Missing donation reference.']);
    }
    $db = App::db();
    $intent = $db->one('SELECT * FROM online_donation_intents WHERE ref_no = ? AND status = ? LIMIT 1', [$refNo, 'paid']);
    if (!$intent || empty($intent['donation_id'])) {
        donation_json(404, ['error' => 'Paid donation not found.']);
    }
    $email = trim((string) ($post['email'] ?? $intent['email'] ?? ''));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        donation_json(400, ['error' => 'Please enter a valid email address to receive the receipt.']);
    }
    donation_issue_full_receipt($db, (int) $intent['donation_id']);
    $result = donation_send_receipt_email((int) $intent['donation_id'], $email);
    Session::set('donation_email_notice', $result);
    if (($result['error'] ?? null) !== null) {
        donation_json(400, ['error' => (string) $result['error']]);
    }
    donation_json(200, ['ok' => true, 'message' => 'Receipt sent to ' . $email . '.']);
}

/** @param array<string, mixed> $intent */
function donation_maybe_email_receipt(array $intent, int $donationId): void
{
    $email = trim((string) ($intent['email'] ?? ''));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        Session::set('donation_email_notice', ['error' => 'no_email', 'email' => '', 'number' => '']);

        return;
    }
    donation_issue_full_receipt(App::db(), $donationId);
    $result = donation_send_receipt_email($donationId, $email);
    Session::set('donation_email_notice', $result);
}

/** @param array<string, mixed> $intent */
function donation_success_payload(\SJT\Core\Database $db, array $intent, ?string $receiptOverride = null): array
{
    $receipt = $receiptOverride;
    if ($receipt === null && !empty($intent['donation_id'])) {
        $d = $db->one('SELECT receipt_number, receipt_generated FROM donations WHERE id = ?', [(int) $intent['donation_id']]);
        if ($d) {
            $receipt = donation_receipt_label($d);
        }
    }
    return [
        'ok' => true,
        'redirect' => donation_ref_url((string) $intent['ref_no']),
        'ref_no' => (string) $intent['ref_no'],
        'receipt_number' => $receipt,
        'amount_rupees' => (int) round((int) $intent['amount_paise'] / 100),
    ];
}
