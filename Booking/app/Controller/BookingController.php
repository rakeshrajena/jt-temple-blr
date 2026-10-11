<?php
declare(strict_types=1);

namespace SJT\Controller;

use SJT\Core\App;
use SJT\Core\Container;
use SJT\Core\Request;
use SJT\Core\Response;
use SJT\Core\Session;
use SJT\Core\ValidationException;
use SJT\Service\Validator;

/** Booking wizard: choose services -> details -> food -> server-side review -> submit. */
final class BookingController extends Controller
{
    private const INPUT_KEYS = ['services', 'name', 'mobile', 'email', 'service_date', 'instructions', 'slot', 'participants', 'f', 'add_food', 'food_cat', 'food'];

    public function form(Request $req): Response
    {
        $in = [];
        if ($req->str('edit') === '1') {
            $in = Session::get('booking_input', []);
        }
        if (!$in && ($code = $req->str('service')) !== '') {
            foreach (Container::catalog()->services() as $s) {
                if ($s['code'] === $code) {
                    $in['services'] = [(string) $s['id']];
                }
            }
        }
        return $this->renderForm($in, []);
    }

    public function review(Request $req): Response
    {
        $this->throttle('review:' . $req->ip(), 120, 3600);
        $in = array_intersect_key($req->post, array_flip(self::INPUT_KEYS));
        $formId = $req->str('form_id');
        if (!preg_match('/^[a-f0-9]{32}$/', $formId)) {
            $formId = bin2hex(random_bytes(16));
        }
        Session::set('booking_input', $in);
        Session::set('booking_form_id', $formId);
        try {
            $draft = Container::form()->validate($in);
        } catch (ValidationException $e) {
            return $this->renderForm($in, $e->errors, 422);
        }
        Session::set('booking_reviewed_total', $draft['quote']['total_paise']);
        return $this->renderReview($draft);
    }

    /** Refreshing or returning to the review page re-validates the saved input. */
    public function reviewAgain(Request $req): Response
    {
        $in = Session::get('booking_input');
        if (!$in) {
            return Response::redirect('/book');
        }
        try {
            $draft = Container::form()->validate($in);
        } catch (ValidationException $e) {
            return $this->renderForm($in, $e->errors, 422);
        }
        Session::set('booking_reviewed_total', $draft['quote']['total_paise']);
        return $this->renderReview($draft);
    }

    public function confirm(Request $req): Response
    {
        $in = Session::get('booking_input');
        $formId = (string) Session::get('booking_form_id', '');
        if (!$in || $formId === '') {
            // Already submitted (e.g. back button + resubmit): show the booking if this browser made it.
            $done = Session::get('booking_done', []);
            if ($req->str('form_id') !== '' && isset($done[$req->str('form_id')])) {
                return Response::redirect('/b/' . $done[$req->str('form_id')]);
            }
            flash('info', 'Your booking session has expired. Please fill in the form again.');
            return Response::redirect('/book');
        }
        try {
            $draft = Container::form()->validate($in);
        } catch (ValidationException $e) {
            return $this->renderForm($in, $e->errors, 422);
        }
        if ($draft['quote']['total_paise'] !== (int) Session::get('booking_reviewed_total', -1)) {
            Session::set('booking_reviewed_total', $draft['quote']['total_paise']);
            return $this->renderReview($draft, 'Prices were updated by the temple while you were booking. Please check the new total before submitting.');
        }
        if ($req->str('agree') !== '1') {
            return $this->renderReview($draft, null, ['agree' => 'Please tick the box to confirm the details are correct.']);
        }
        // Generous enough for the temple office booking on behalf of devotees; tune in .env.
        $perHour = max(1, (int) \SJT\Core\Env::get('BOOKING_RATE_LIMIT_PER_HOUR', '40'));
        $this->throttle('create:' . $req->ip(), $perHour, 3600);
        $this->throttle('create-mobile:' . $draft['mobile'], max(10, intdiv($perHour, 2)), 3600);

        try {
            [$b] = Container::service()->create($draft, $formId, $req->ip());
        } catch (ValidationException $e) {
            return $this->renderForm($in, $e->errors, 409);
        }
        self::grant($b);
        $done = Session::get('booking_done', []);
        $done[$formId] = $b['booking_no'];
        Session::set('booking_done', array_slice($done, -10, null, true));
        Session::forget('booking_input');
        Session::forget('booking_form_id');
        Session::forget('booking_reviewed_total');
        Session::set('just_booked', $b['booking_no']);

        return Response::redirect('/b/' . $b['booking_no'] . ((int) $b['total_paise'] > 0 && $b['payment_status'] === 'pending' ? '/pay' : ''));
    }

    public function availability(Request $req): Response
    {
        $this->throttle('avail:' . $req->ip(), 600, 600);
        $date = Validator::date($req->str('date'));
        if (!$date) {
            return Response::json(['error' => 'Invalid date'], 400);
        }
        return Response::json(['date' => $date, 'services' => Container::availability()->forDate($date)]);
    }

    // ------------------------------------------------------------------

    private function renderForm(array $in, array $errors, int $status = 200): Response
    {
        $catalog = Container::catalog();
        $services = $catalog->services();
        $avail = Container::availability();
        $slots = [];
        foreach ($catalog->slots() as $s) {
            $slots[$s['service_id']][] = $s;
        }
        $formId = (string) Session::get('booking_form_id', '');
        if (!preg_match('/^[a-f0-9]{32}$/', $formId)) {
            $formId = bin2hex(random_bytes(16));
            Session::set('booking_form_id', $formId);
        }
        $coupons = $catalog->coupons();
        $pricing = [
            'services' => array_map(fn ($s) => [
                'id' => $s['id'], 'name' => $s['name'], 'price' => $s['price_paise'], 'foodOnly' => $s['is_food_only'],
                'combinable' => $s['is_combinable'], 'allowFood' => $s['allow_food'], 'approval' => $s['requires_approval'],
            ], $services),
            'coupons' => array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'price' => $c['price_paise']], $coupons),
            'extra' => ['label' => $this->cfg()->get('additional_charge_label') ?: 'Additional charges', 'amount' => $this->cfg()->int('additional_charge_paise')],
            'availabilityUrl' => url('/api/availability'),
        ];
        return $this->view('public/form', [
            'title' => 'Book a service',
            'services' => $services,
            'coupons' => $coupons,
            'slots' => $slots,
            'in' => $in,
            'errors' => $errors,
            'formId' => $formId,
            'minDate' => $avail->minDate(),
            'maxDate' => $avail->maxDate(),
            'pricingJson' => json_encode($pricing, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ], $status);
    }

    private function renderReview(array $draft, ?string $notice = null, array $errors = []): Response
    {
        return $this->view('public/review', [
            'title' => 'Review your booking',
            'd' => $draft,
            'q' => $draft['quote'],
            'formId' => (string) Session::get('booking_form_id'),
            'notice' => $notice,
            'errors' => $errors,
            'terms' => App::settings()->get('terms_text', ''),
        ]);
    }
}
