<?php
declare(strict_types=1);

namespace SJT\Controller;

use SJT\Core\App;
use SJT\Core\Container;
use SJT\Core\HttpException;
use SJT\Core\Logger;
use SJT\Core\Request;
use SJT\Core\Response;
use SJT\Core\Session;
use SJT\Core\ValidationException;
use SJT\Service\Status;
use SJT\Service\Validator;

final class PublicController extends Controller
{
    public function home(Request $req): Response
    {
        return $this->view('public/home', [
            'services' => Container::catalog()->services(),
            'coupons' => Container::catalog()->coupons(),
        ]);
    }

    public function health(Request $req): Response
    {
        try {
            App::db()->value('SELECT 1');
            return Response::json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Logger::error('Health check failed: ' . $e->getMessage());
            return Response::json(['status' => 'error'], 503);
        }
    }

    /** Booking acknowledgement / status page. */
    public function status(Request $req): Response
    {
        $b = $this->accessibleBooking($req);
        $ack = Session::get('just_paid') === $b['booking_no'] ? 'payment' : (Session::get('just_booked') === $b['booking_no'] ? 'booking' : null);
        Session::forget('just_paid');
        if ($ack) {
            Session::forget('just_booked');
        }
        return $this->view('public/status', $this->bookingData($b) + [
            'title' => 'Booking ' . $b['booking_no'],
            'ack' => $ack,
        ]);
    }

    /** Invoice summary + payment QR page. */
    public function pay(Request $req): Response
    {
        $b = $this->accessibleBooking($req);
        $data = $this->bookingData($b);
        $upi = Container::upi();
        $online = Container::onlinePay();
        $data['canPay'] = in_array($b['payment_status'], ['pending', 'failed'], true)
            && in_array($b['booking_status'], ['pending', 'confirmed'], true) && (int) $b['total_paise'] > 0;
        $data['showUpi'] = $data['canPay'] && $online::upiManualForDevotees() && $upi->available();
        $data['upiAvailable'] = $data['showUpi'];
        $data['upiMode'] = $upi->mode();
        $data['upiUri'] = $data['showUpi'] && $upi->mode() === 'dynamic' ? $upi->uri($b) : null;
        $data['qrSvg'] = ($data['showUpi'] && $data['upiUri']) ? $upi->qrSvg($data['upiUri']) : null;
        $data['upiVpa'] = $data['showUpi'] && $upi->mode() === 'dynamic' ? $this->cfg()->get('upi_vpa') : null;
        $data['instructions'] = $this->cfg()->get('payment_instructions', '');
        $data['gateway'] = $online->enabled() && $data['canPay'];
        $data['gatewayKey'] = $data['gateway'] ? $online->publicKey() : null;
        $data['checkoutScriptUrl'] = $data['gateway'] ? $online->checkoutScriptUrl() : null;
        $data['gatewayDriver'] = $online->driverId();
        $data['title'] = 'Pay for booking ' . $b['booking_no'];
        $data['errors'] = Session::get('pay_errors', []);
        $data['oldRef'] = Session::get('pay_ref', '');
        Session::forget('pay_errors');
        Session::forget('pay_ref');
        $data['justBooked'] = Session::get('just_booked') === $b['booking_no'];
        return $this->view('public/pay', $data);
    }

    public function submitPayment(Request $req): Response
    {
        if (!\SJT\OnlinePay\OnlinePaymentService::upiManualForDevotees()) {
            throw new HttpException(404, 'Manual UPI reference submission is not available. Please pay online.');
        }
        $b = $this->accessibleBooking($req);
        $this->throttle('pay:' . $b['booking_no'], 10, 3600);
        $this->throttle('pay-ip:' . $req->ip(), 30, 3600);
        try {
            Container::service()->submitPayment($b, $req->str('reference'), $req->str('payer_note'));
        } catch (ValidationException $e) {
            Session::set('pay_errors', $e->errors);
            Session::set('pay_ref', mb_substr($req->str('reference'), 0, 40));
            return Response::redirect('/b/' . $b['booking_no'] . '/pay#payment-form');
        }
        Session::set('just_paid', $b['booking_no']);
        return Response::redirect('/b/' . $b['booking_no']);
    }

    public function invoice(Request $req): Response
    {
        $b = $this->accessibleBooking($req);
        return Response::html(Container::invoices()->html($b));
    }

    public function invoicePdf(Request $req): Response
    {
        $b = $this->accessibleBooking($req);
        $this->throttle('pdf:' . $req->ip(), 60, 600);
        return Response::file(Container::invoices()->pdf($b), 'application/pdf', 'Invoice-' . $b['booking_no'] . '.pdf', $req->str('download') !== '1');
    }

    public function qr(Request $req): Response
    {
        $b = $this->accessibleBooking($req);
        if (!\SJT\OnlinePay\OnlinePaymentService::upiManualForDevotees()) {
            throw new HttpException(404, 'No payment QR is available for this booking.');
        }
        $upi = Container::upi();
        if (!$upi->available() || !in_array($b['payment_status'], ['pending', 'failed'], true)) {
            throw new HttpException(404, 'No payment QR is available for this booking.');
        }
        $png = $upi->bookingQrPng($b);
        if (!$png) {
            throw new HttpException(404);
        }
        return Response::file($png, 'image/png', 'Pay-' . $b['booking_no'] . '.png', $req->str('download') !== '1');
    }

    public function lookupForm(Request $req): Response
    {
        return $this->view('public/lookup', ['title' => 'Find my booking', 'in' => []]);
    }

    public function lookup(Request $req): Response
    {
        $this->throttle('lookup:' . $req->ip(), 15, 900);
        $no = strtoupper($req->str('booking_no'));
        $mobile = Validator::normaliseMobile($req->str('mobile'));
        $b = ($no !== '' && $mobile) ? Container::bookings()->findByNo($no) : null;
        if (!$b || !hash_equals($b['mobile'], (string) $mobile)) {
            return $this->view('public/lookup', [
                'title' => 'Find my booking',
                'in' => ['booking_no' => $no, 'mobile' => $req->str('mobile')],
                'errors' => ['lookup' => 'We could not find a booking with that booking number and mobile number. Please check both and try again.'],
            ], 404);
        }
        self::grant($b);
        return Response::redirect('/b/' . $b['booking_no']);
    }

    // ---------------------------------------------------------------- gateway (optional)

    public function gatewayOrder(Request $req): Response
    {
        $b = $this->accessibleBooking($req);
        $gw = Container::gateway();
        if (!$gw->enabled() || !in_array($b['payment_status'], ['pending', 'failed'], true) || (int) $b['total_paise'] <= 0) {
            return Response::json(['error' => 'Online payment is not available for this booking.'], 400);
        }
        $this->throttle('gw:' . $b['booking_no'], 20, 3600);
        try {
            $o = $gw->order($b);
        } catch (\Throwable $e) {
            Logger::error('Gateway order failed', ['booking' => $b['booking_no'], 'error' => $e->getMessage()]);
            return Response::json(['error' => 'Online payment is temporarily unavailable. Please use the UPI QR code.'], 502);
        }
        return Response::json([
            'key' => $gw->keyId(), 'order_id' => $o['order_id'], 'amount' => $o['amount'], 'currency' => 'INR',
            'name' => $this->cfg()->get('temple_name'), 'description' => 'Booking ' . $b['booking_no'],
            'prefill' => ['name' => $b['devotee_name'], 'contact' => '+' . $b['mobile'], 'email' => (string) $b['email']],
        ]);
    }

    public function gatewayVerify(Request $req): Response
    {
        $b = $this->accessibleBooking($req);
        $pay = Container::onlinePay();
        $verified = $pay->verifyCheckoutResponse($req->post());
        $payment = $verified ? $pay->paymentForOrder($verified->orderId) : null;
        if (!$verified || !$payment || (int) $payment['booking_id'] !== (int) $b['id']) {
            Logger::warning('Gateway signature check failed', ['booking' => $b['booking_no']]);
            return Response::json(['error' => 'Payment could not be verified. If money was deducted, it will be reconciled by the temple office.'], 400);
        }
        Container::service()->markGatewayPaid($payment, $verified->paymentId, 'checkout');
        Session::set('just_paid', $b['booking_no']);
        return Response::json(['redirect' => url('/b/' . $b['booking_no'])]);
    }

    // ----------------------------------------------------------------

    private function bookingData(array $b): array
    {
        $repo = Container::bookings();
        return [
            'b' => $b,
            'items' => $repo->items((int) $b['id']),
            'headline' => Status::headline($b),
            'link' => url('/b/' . $b['booking_no'], ['t' => $b['access_token']]),
            'temple' => [
                'name' => $this->cfg()->get('temple_name'),
                'phone' => $this->cfg()->get('temple_phone'),
                'email' => $this->cfg()->get('temple_email'),
                'address' => $this->cfg()->get('temple_address'),
            ],
        ];
    }
}
