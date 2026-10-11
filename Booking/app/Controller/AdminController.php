<?php
declare(strict_types=1);

namespace SJT\Controller;

use SJT\Core\App;
use SJT\Core\Container;
use SJT\Core\Request;
use SJT\Core\Response;
use SJT\Core\Session;
use SJT\Service\Auth;
use SJT\Service\Reports;

final class AdminController extends Controller
{
    public function loginForm(Request $req): Response
    {
        if (Container::auth()->user()) {
            return Response::redirect('/admin');
        }
        return $this->view('admin/login', ['title' => 'Administrator sign in', 'username' => ''], 200, 'admin/layout');
    }

    public function login(Request $req): Response
    {
        $username = $req->str('username');
        $res = Container::auth()->attempt($username, (string) ($req->post['password'] ?? ''), $req->ip());
        if (!$res['ok']) {
            return $this->view('admin/login', ['title' => 'Administrator sign in', 'username' => $username, 'error' => $res['error']], 401, 'admin/layout');
        }
        $to = (string) Session::get('admin_intended', '/admin');
        Session::forget('admin_intended');
        return Response::redirect(str_starts_with($to, '/admin') ? $to : '/admin');
    }

    public function logout(Request $req): Response
    {
        $actor = Container::auth()->actor();
        Container::audit()->log($actor, 'admin', $actor['id'], null, 'auth.logout', []);
        Container::auth()->logout();
        flash('info', 'You have been signed out.');
        return Response::redirect('/admin/login');
    }

    public function dashboard(Request $req): Response
    {
        $db = App::db();
        $s = App::settings();
        $counts = [
            'verify' => (int) $db->value("SELECT COUNT(*) FROM bookings WHERE payment_status = 'awaiting_verification' AND booking_status IN ('pending','confirmed')"),
            'approve' => (int) $db->value("SELECT COUNT(*) FROM bookings WHERE approval_status = 'pending' AND booking_status = 'pending'"),
            'today' => (int) $db->value("SELECT COUNT(*) FROM bookings WHERE service_date = ? AND booking_status IN ('pending','confirmed')", [today()]),
            'new_today' => (int) $db->value('SELECT COUNT(*) FROM bookings WHERE created_at >= ?', [today() . ' 00:00:00']),
            'failed_notifications' => (int) $db->value("SELECT COUNT(*) FROM notifications WHERE status = 'failed'"),
        ];
        $setup = [];
        if (!Container::upi()->available()) {
            $setup[] = ['UPI payment details are not verified yet, so devotees are asked to pay at the counter.', '/admin/settings#payment'];
        }
        if (!Container::whatsapp()->configured() || !$s->bool('whatsapp_enabled')) {
            $setup[] = ['WhatsApp notifications are not active. Messages are being logged as failed for follow-up.', '/admin/settings#whatsapp'];
        }
        if (!$s->adminNumbers()) {
            $setup[] = ['No administrator WhatsApp number is set for new-booking alerts.', '/admin/settings#whatsapp'];
        }
        if (!$s->bool('prices_reviewed')) {
            $setup[] = ['Service prices are still the installation placeholders. Review and confirm them.', '/admin/services'];
        }
        if (!$s->get('temple_phone')) {
            $setup[] = ['The temple phone number is missing from invoices and messages.', '/admin/settings#temple'];
        }
        $reports = new Reports($db);
        return $this->adminView('dashboard', [
            'title' => 'Dashboard',
            'counts' => $counts,
            'setup' => $setup,
            'summary' => $reports->summary(),
            'recent' => Container::bookings()->search([], 1, 8)['rows'],
            'upcoming' => $db->all(
                "SELECT * FROM bookings WHERE service_date BETWEEN ? AND ? AND booking_status IN ('pending','confirmed') ORDER BY service_date, id LIMIT 30",
                [today(), date('Y-m-d', strtotime('+7 days'))]
            ),
        ]);
    }

    public function passwordForm(Request $req): Response
    {
        return $this->adminView('password', ['title' => 'Change password']);
    }

    public function password(Request $req): Response
    {
        $u = Container::auth()->user();
        $row = App::db()->one('SELECT password_hash FROM admins WHERE id = ?', [$u['id']]);
        $errors = [];
        if (!password_verify((string) ($req->post['current'] ?? ''), $row['password_hash'])) {
            $errors['current'] = 'Current password is incorrect.';
        }
        $new = (string) ($req->post['new'] ?? '');
        if ($p = Auth::passwordProblem($new)) {
            $errors['new'] = $p;
        } elseif ($new !== (string) ($req->post['confirm'] ?? '')) {
            $errors['confirm'] = 'The two new passwords do not match.';
        }
        if ($errors) {
            return $this->adminView('password', ['title' => 'Change password', 'errors' => $errors], 422);
        }
        App::db()->update('admins', ['password_hash' => Auth::hash($new), 'updated_at' => now()], 'id = :id', ['id' => $u['id']]);
        Container::audit()->log(Container::auth()->actor(), 'admin', $u['id'], null, 'admin.password_changed', []);
        Session::regenerate();
        flash('success', 'Your password has been changed.');
        return Response::redirect('/admin');
    }
}
