<?php
declare(strict_types=1);

use SJT\Controller\AdminBookingController as AB;
use SJT\Controller\AdminConfigController as AC;
use SJT\Controller\AdminController as A;
use SJT\Controller\BookingController as B;
use SJT\Controller\PublicController as P;
use SJT\Controller\WebhookController as W;
use SJT\Core\Router;

$r = new Router();
$admin = ['admin' => true];
$super = ['admin' => true, 'role' => 'super'];

// ---- Devotee-facing
$r->get('/', [P::class, 'home']);
$r->get('/book', [B::class, 'form']);
$r->post('/book/review', [B::class, 'review']);
$r->get('/book/review', [B::class, 'reviewAgain']);
$r->post('/book/confirm', [B::class, 'confirm']);
$r->get('/api/availability', [B::class, 'availability']);
$r->get('/b/{no}', [P::class, 'status']);
$r->get('/b/{no}/pay', [P::class, 'pay']);
$r->post('/b/{no}/payment', [P::class, 'submitPayment']);
$r->get('/b/{no}/invoice', [P::class, 'invoice']);
$r->get('/b/{no}/invoice.pdf', [P::class, 'invoicePdf']);
$r->get('/b/{no}/qr.png', [P::class, 'qr']);
$r->post('/b/{no}/gateway/order', [P::class, 'gatewayOrder']);
$r->post('/b/{no}/gateway/verify', [P::class, 'gatewayVerify']);
$r->get('/lookup', [P::class, 'lookupForm']);
$r->post('/lookup', [P::class, 'lookup']);
$r->get('/health', [P::class, 'health']);

// ---- Webhooks (authenticated by signature, not CSRF)
$r->get('/webhooks/whatsapp', [W::class, 'whatsappVerify']);
$r->post('/webhooks/whatsapp', [W::class, 'whatsapp'], ['csrf' => false]);
$r->post('/webhooks/razorpay', [W::class, 'razorpay'], ['csrf' => false]);

// ---- Administrator
$r->get('/admin/login', [A::class, 'loginForm']);
$r->post('/admin/login', [A::class, 'login']);
$r->post('/admin/logout', [A::class, 'logout'], $admin);
$r->get('/admin', [A::class, 'dashboard'], $admin);
$r->get('/admin/password', [A::class, 'passwordForm'], $admin);
$r->post('/admin/password', [A::class, 'password'], $admin);

$r->get('/admin/bookings', [AB::class, 'index'], $admin);
$r->get('/admin/bookings/export.csv', [AB::class, 'export'], $admin);
$r->get('/admin/bookings/{no}', [AB::class, 'show'], $admin);
$r->get('/admin/bookings/{no}/invoice.pdf', [AB::class, 'invoicePdf'], $admin);
$r->post('/admin/bookings/{no}/action', [AB::class, 'action'], $admin);

$r->get('/admin/notifications', [AB::class, 'notifications'], $admin);
$r->post('/admin/notifications/retry-all', [AB::class, 'retryAll'], $admin);
$r->post('/admin/notifications/{id}/retry', [AB::class, 'retry'], $admin);
$r->post('/admin/notifications/{id}/resolve', [AB::class, 'resolve'], $admin);

$r->get('/admin/reports', [AB::class, 'reports'], $admin);
$r->get('/admin/reports/export.csv', [AB::class, 'reportExport'], $admin);
$r->get('/admin/audit', [AB::class, 'audit'], $admin);

$r->get('/admin/availability', [AC::class, 'availability'], $admin);
$r->post('/admin/availability/block', [AC::class, 'block'], $admin);
$r->post('/admin/availability/unblock', [AC::class, 'unblock'], $admin);

$r->get('/admin/services', [AC::class, 'services'], $super);
$r->get('/admin/services/new', [AC::class, 'serviceForm'], $super);
$r->get('/admin/services/{id}', [AC::class, 'serviceForm'], $super);
$r->post('/admin/services/save', [AC::class, 'serviceSave'], $super);
$r->post('/admin/slots/save', [AC::class, 'slotSave'], $super);
$r->get('/admin/coupons', [AC::class, 'coupons'], $super);
$r->post('/admin/coupons/save', [AC::class, 'couponSave'], $super);
$r->post('/admin/prices/confirm', [AC::class, 'confirmPrices'], $super);

$r->get('/admin/settings', [AC::class, 'settings'], $super);
$r->post('/admin/settings', [AC::class, 'settingsSave'], $super);
$r->post('/admin/settings/qr-upload', [AC::class, 'qrUpload'], $super);
$r->get('/admin/settings/qr-preview.png', [AC::class, 'qrPreview'], $super);
$r->post('/admin/settings/whatsapp-test', [AC::class, 'whatsappTest'], $super);

$r->get('/admin/users', [AC::class, 'users'], $super);
$r->post('/admin/users/save', [AC::class, 'userSave'], $super);

return $r;
