<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/receipt_service.php';
require __DIR__ . '/includes/ledger.php';
require __DIR__ . '/includes/router.php';
require __DIR__ . '/includes/thanks_data.php';
require __DIR__ . '/includes/view.php';
require __DIR__ . '/includes/api.php';

use SJT\Core\App;
use SJT\Core\Container;
use SJT\Core\Request;
use SJT\Core\Session;

$req = Request::fromGlobals();
Session::start($req->isHttps());

if ($req->method === 'POST') {
    $action = trim((string) ($req->post['action'] ?? ''));
    if ($action !== '') {
        donation_handle_api($action, $req->post);
    }
}

donation_ensure_schema(App::db());

$route = donation_parse_route($req);

$legacyPaid = trim((string) ($req->query['paid'] ?? ''));
if ($route['type'] === 'form' && $legacyPaid !== '' && preg_match('/^ODN-/i', $legacyPaid) === 1) {
    header('Location: ' . donation_ref_url($legacyPaid), true, 302);
    exit;
}

if ($route['type'] === 'receipt_pdf' && !empty($route['ref'])) {
    donation_serve_receipt_by_ref((string) $route['ref'], !empty($route['download']) || ($req->query['download'] ?? '') === '1');
}

$pay = Container::onlinePay();
$gatewayOn = $pay->enabled();
$csrf = Session::csrfToken();
$checkoutScript = $pay->checkoutScriptUrl() ?? 'https://checkout.razorpay.com/v1/checkout.js';

if ($route['type'] === 'thanks' && !empty($route['ref'])) {
    $ref = (string) $route['ref'];
    $summary = donation_paid_summary(App::db(), $ref);
    if ($summary === null) {
        http_response_code(404);
        donation_render('Donation not found', 'form', [
            'gatewayOn' => $gatewayOn,
            'csrf' => $csrf,
            'checkoutScript' => $checkoutScript,
            'flashes' => [['type' => 'warn', 'message' => 'This donation reference was not found or payment is not complete yet.']],
        ]);
        exit;
    }

    $justPaid = Session::get('donation_just_paid') === $ref;
    Session::forget('donation_just_paid');

    $emailNotice = Session::get('donation_email_notice');
    Session::forget('donation_email_notice');

    $scripts = '<script>window.SJT_DONATION_RESEND = { postUrl: ' . json_encode(donation_ref_url($ref), JSON_THROW_ON_ERROR) . ' };</script>'
        . '<script src="' . e(donation_asset('donation-resend.js')) . '" defer></script>';

    donation_render('Donation receipt', 'thanks', [
        'summary' => $summary,
        'justPaid' => $justPaid,
        'emailNotice' => $emailNotice,
        'csrf' => $csrf,
    ], $scripts);
    exit;
}

$scripts = '';
if ($gatewayOn) {
    $scripts = '<script src="' . e($checkoutScript) . '"></script>'
        . '<script>window.SJT_DONATION = {'
        . 'postUrl: ' . json_encode(donation_url(), JSON_THROW_ON_ERROR) . ','
        . 'maxRupees: ' . DONATION_MAX_RUPEES . ','
        . 'offlineMessage: ' . json_encode(DONATION_OFFLINE_MESSAGE, JSON_THROW_ON_ERROR)
        . '};</script>'
        . '<script src="' . e(donation_asset('donation.js')) . '"></script>';
}

donation_render('Donate online', 'form', [
    'gatewayOn' => $gatewayOn,
    'csrf' => $csrf,
    'checkoutScript' => $checkoutScript,
], $scripts);
