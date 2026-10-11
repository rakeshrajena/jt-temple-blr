<?php
declare(strict_types=1);

namespace SJT\Controller;

use SJT\Core\App;
use SJT\Core\Container;
use SJT\Core\HttpException;
use SJT\Core\Request;
use SJT\Core\Response;
use SJT\Core\Session;
use SJT\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200, string $layout = 'layout'): Response
    {
        $data += ['title' => null, 'errors' => [], 'flashes' => Session::pullFlash()];
        return Response::html(View::render($template, $data, $layout), $status);
    }

    protected function adminView(string $template, array $data = [], int $status = 200): Response
    {
        $data += ['admin' => Container::auth()->user()];
        return $this->view('admin/' . $template, $data, $status, 'admin/layout');
    }

    protected function throttle(string $key, int $max, int $window): void
    {
        if (!Container::limiter()->hit($key, $max, $window)) {
            throw new HttpException(429);
        }
    }

    /**
     * Load a booking the visitor may see: via the private link token (?t=) or a
     * previous successful lookup in this browser session.
     */
    protected function accessibleBooking(Request $req): array
    {
        $b = Container::bookings()->findByNo($req->param('no'));
        $granted = Session::get('bk', []);
        $token = $req->str('t');
        if ($b && $token !== '' && hash_equals($b['access_token'], $token)) {
            $granted[$b['booking_no']] = true;
            Session::set('bk', $granted);
            return $b;
        }
        if ($b && !empty($granted[$b['booking_no']])) {
            return $b;
        }
        throw new HttpException(404, 'Booking not found. Please use "Find my booking" with your booking number and mobile number.');
    }

    protected static function grant(array $b): void
    {
        $g = Session::get('bk', []);
        $g[$b['booking_no']] = true;
        Session::set('bk', $g);
    }

    protected function cfg(): \SJT\Repo\Settings
    {
        return App::settings();
    }
}
