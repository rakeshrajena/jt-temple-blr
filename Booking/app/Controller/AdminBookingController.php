<?php
declare(strict_types=1);

namespace SJT\Controller;

use SJT\Core\App;
use SJT\Core\Container;
use SJT\Core\HttpException;
use SJT\Core\Request;
use SJT\Core\Response;
use SJT\Core\ValidationException;
use SJT\Service\Notifier;
use SJT\Service\Reports;
use SJT\Service\Validator;

final class AdminBookingController extends Controller
{
    private const FILTER_KEYS = ['q', 'service_id', 'payment_status', 'booking_status', 'approval_status', 'date_from', 'date_to', 'created_from', 'created_to', 'has_food'];

    public function index(Request $req): Response
    {
        $f = $this->filters($req);
        $page = max(1, $req->int('page', 1));
        return $this->adminView('bookings', [
            'title' => 'Bookings',
            'f' => $f,
            'result' => Container::bookings()->search($f, $page, 25),
            'services' => Container::catalog()->services(false),
        ]);
    }

    public function export(Request $req): Response
    {
        $f = $this->filters($req);
        $repo = Container::bookings();
        $rows = [];
        foreach ($repo->export($f) as $b) {
            $id = (int) $b['id'];
            $rows[] = [
                $b['booking_no'], $b['invoice_no'], $b['created_at'], $b['service_date'], $repo->slotSummary($id),
                $b['devotee_name'], $b['mobile'], (string) $b['email'], $repo->serviceSummary($id), $repo->foodSummary($id),
                Reports::rupees((int) $b['subtotal_paise']), Reports::rupees((int) $b['additional_paise']), Reports::rupees((int) $b['total_paise']),
                $b['booking_status'], $b['payment_status'], $b['approval_status'], (string) $b['payment_ref'], (string) $b['paid_at'],
            ];
        }
        Container::audit()->log(Container::auth()->actor(), 'export', 'bookings', null, 'export.bookings', ['filters' => array_filter($f), 'rows' => count($rows)]);
        $csv = Reports::csv(['Booking no', 'Invoice no', 'Booked at', 'Service date', 'Time slots', 'Devotee', 'Mobile', 'Email', 'Services', 'Food coupons',
            'Subtotal (INR)', 'Additional (INR)', 'Total (INR)', 'Booking status', 'Payment status', 'Approval status', 'Payment reference', 'Paid at'], $rows);
        return Response::file($csv, 'text/csv; charset=UTF-8', 'bookings-' . date('Ymd-His') . '.csv');
    }

    public function show(Request $req): Response
    {
        $b = $this->booking($req);
        $id = (int) $b['id'];
        $repo = Container::bookings();
        $items = $repo->items($id);
        $slotOptions = [];
        foreach ($items as $it) {
            if ($it['item_type'] === 'service' && $it['slot_id'] !== null) {
                $slotOptions[$it['id']] = Container::catalog()->slots((int) $it['service_id']);
            }
        }
        return $this->adminView('booking', [
            'title' => 'Booking ' . $b['booking_no'],
            'b' => $b,
            'items' => $items,
            'payments' => $repo->payments($id),
            'notifications' => $repo->notifications($id),
            'audit' => $repo->audit($id),
            'slotOptions' => $slotOptions,
            'devoteeLink' => absolute_url('/b/' . $b['booking_no'], ['t' => $b['access_token']]),
            'errors' => \SJT\Core\Session::get('action_errors', []),
            'openAction' => \SJT\Core\Session::get('action_open'),
        ] + $this->clearActionState());
    }

    private function clearActionState(): array
    {
        \SJT\Core\Session::forget('action_errors');
        \SJT\Core\Session::forget('action_open');
        return [];
    }

    public function invoicePdf(Request $req): Response
    {
        $b = $this->booking($req);
        return Response::file(Container::invoices()->pdf($b), 'application/pdf', 'Invoice-' . $b['booking_no'] . '.pdf', true);
    }

    public function action(Request $req): Response
    {
        $b = $this->booking($req);
        $svc = Container::service();
        $actor = Container::auth()->actor();
        $action = $req->str('action');
        $done = 'Saved.';
        try {
            switch ($action) {
                case 'approve':
                    $svc->approve($b, $actor, $req->str('note'));
                    $done = 'Booking approved.';
                    break;
                case 'reject':
                    $svc->reject($b, $actor, $req->str('reason'));
                    $done = 'Booking marked as not approved.';
                    break;
                case 'cancel':
                    $svc->cancel($b, $actor, $req->str('reason'));
                    $done = 'Booking cancelled.';
                    break;
                case 'complete':
                    $svc->complete($b, $actor);
                    $done = 'Booking marked as completed.';
                    break;
                case 'verify_payment':
                    $pid = $req->int('payment_id');
                    $svc->verifyPayment($b, $actor, $pid ?: null, $req->str('reference'), $req->str('amount'), $req->str('note'));
                    $done = 'Payment verified and recorded.';
                    break;
                case 'reject_payment':
                    $svc->rejectPayment($b, $actor, $req->str('reason'));
                    $done = 'Payment marked as not verified. The devotee has been asked to resubmit.';
                    break;
                case 'refund':
                    $svc->refund($b, $actor, $req->str('reference'), $req->str('note'));
                    $done = 'Refund recorded.';
                    break;
                case 'adjust':
                    $svc->adjustCharges($b, $actor, $req->str('label'), $req->str('amount'), $req->str('note'));
                    $done = 'Charges updated.';
                    break;
                case 'reschedule':
                    $slots = array_map('intval', array_filter($req->arr('slots'), 'is_scalar'));
                    $svc->reschedule($b, $actor, $req->str('date'), $slots, $req->str('override') === '1', $req->str('note'));
                    $done = 'Booking rescheduled.';
                    break;
                case 'note':
                    $svc->addNote($b, $actor, $req->str('note'));
                    $done = 'Note added.';
                    break;
                case 'resend':
                    $type = $req->str('type');
                    $fresh = Container::bookings()->find((int) $b['id']);
                    if ($type === 'ack') {
                        Container::notifier()->bookingCreated($fresh);
                    } else {
                        Container::notifier()->statusUpdate($fresh, \SJT\Service\Status::headline($fresh)[1] . '.');
                    }
                    Container::audit()->log($actor, 'booking', $b['booking_no'], (int) $b['id'], 'notification.resent', ['type' => $type]);
                    $done = 'Notification queued. Check the notification log below for the result.';
                    break;
                default:
                    throw new HttpException(400, 'Unknown action');
            }
        } catch (ValidationException $e) {
            \SJT\Core\Session::set('action_errors', $e->errors);
            \SJT\Core\Session::set('action_open', $action);
            flash('error', implode(' ', $e->errors));
            return Response::redirect('/admin/bookings/' . $b['booking_no'] . '#act-' . $action);
        }
        flash('success', $done);
        return Response::redirect('/admin/bookings/' . $b['booking_no']);
    }

    // ---------------------------------------------------------------- notifications

    public function notifications(Request $req): Response
    {
        $status = $req->str('status', 'failed');
        $allowed = ['failed', 'queued', 'sent', 'delivered', 'read', 'resolved', 'all'];
        $status = in_array($status, $allowed, true) ? $status : 'failed';
        $rows = App::db()->all(
            'SELECT n.*, b.booking_no, b.devotee_name FROM notifications n LEFT JOIN bookings b ON b.id = n.booking_id'
            . ($status === 'all' ? '' : ' WHERE n.status = ?') . ' ORDER BY n.id DESC LIMIT 300',
            $status === 'all' ? [] : [$status]
        );
        $counts = [];
        foreach (App::db()->all('SELECT status, COUNT(*) c FROM notifications GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['c'];
        }
        return $this->adminView('notifications', ['title' => 'Notifications', 'rows' => $rows, 'status' => $status, 'counts' => $counts]);
    }

    public function retry(Request $req): Response
    {
        $id = (int) $req->param('id');
        $ok = Container::notifier()->dispatch($id);
        Container::audit()->log(Container::auth()->actor(), 'notification', $id, null, 'notification.retry', ['sent' => $ok]);
        flash($ok ? 'success' : 'error', $ok ? 'Message sent to WhatsApp.' : 'Sending failed again. See the error shown for the message.');
        return Response::redirect('/admin/notifications');
    }

    public function retryAll(Request $req): Response
    {
        $rows = App::db()->all("SELECT id FROM notifications WHERE status IN ('failed','queued') AND template IS NOT NULL ORDER BY id LIMIT 100");
        $sent = 0;
        foreach ($rows as $r) {
            $sent += Container::notifier()->dispatch((int) $r['id']) ? 1 : 0;
        }
        Container::audit()->log(Container::auth()->actor(), 'notification', null, null, 'notification.retry_all', ['tried' => count($rows), 'sent' => $sent]);
        flash($sent ? 'success' : 'info', 'Retried ' . count($rows) . ' message(s); ' . $sent . ' sent.');
        return Response::redirect('/admin/notifications');
    }

    public function resolve(Request $req): Response
    {
        $id = (int) $req->param('id');
        $note = Validator::text($req->str('note'), 200);
        App::db()->update('notifications', ['status' => 'resolved', 'next_attempt_at' => null, 'error' => trim('Handled manually' . ($note ? ': ' . $note : '')), 'updated_at' => now()], "id = :id AND status IN ('failed','queued')", ['id' => $id]);
        Container::audit()->log(Container::auth()->actor(), 'notification', $id, null, 'notification.resolved', ['note' => $note]);
        flash('success', 'Marked as handled.');
        return Response::redirect('/admin/notifications');
    }

    // ---------------------------------------------------------------- reports & audit

    public function reports(Request $req): Response
    {
        [$from, $to, $group] = $this->reportRange($req);
        return $this->adminView('reports', [
            'title' => 'Reports',
            'from' => $from, 'to' => $to, 'group' => $group,
            'report' => (new Reports(App::db()))->rows($from, $to, $group),
        ]);
    }

    public function reportExport(Request $req): Response
    {
        [$from, $to, $group] = $this->reportRange($req);
        $type = $req->str('type', 'summary');
        $db = App::db();
        if ($type === 'payments') {
            $rows = $db->all(
                "SELECT b.booking_no, b.invoice_no, b.devotee_name, b.mobile, p.method, p.reference, p.amount_paise, p.status, p.submitted_at, p.reviewed_at, p.reviewed_by, p.review_note
                 FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE p.submitted_at >= ? AND p.submitted_at <= ? ORDER BY p.id",
                [$from . ' 00:00:00', $to . ' 23:59:59']
            );
            $rows = array_map(function ($r) {
                $r['amount_paise'] = Reports::rupees((int) $r['amount_paise']);
                return $r;
            }, $rows);
            $csv = Reports::csv(['Booking no', 'Invoice no', 'Devotee', 'Mobile', 'Method', 'Reference', 'Amount (INR)', 'Status', 'Submitted at', 'Reviewed at', 'Reviewed by', 'Note'], $rows);
        } elseif ($type === 'items') {
            $rows = $db->all(
                "SELECT b.booking_no, b.invoice_no, b.created_at, b.service_date, b.devotee_name, i.item_type, i.name, i.slot_label, i.unit_price_paise, i.quantity, i.amount_paise, b.booking_status, b.payment_status
                 FROM booking_items i JOIN bookings b ON b.id = i.booking_id WHERE b.created_at >= ? AND b.created_at <= ? ORDER BY b.id, i.sort_order",
                [$from . ' 00:00:00', $to . ' 23:59:59']
            );
            $rows = array_map(function ($r) {
                $r['unit_price_paise'] = Reports::rupees((int) $r['unit_price_paise']);
                $r['amount_paise'] = Reports::rupees((int) $r['amount_paise']);
                return $r;
            }, $rows);
            $csv = Reports::csv(['Booking no', 'Invoice no', 'Booked at', 'Service date', 'Devotee', 'Item type', 'Item', 'Time slot', 'Unit price (INR)', 'Quantity', 'Amount (INR)', 'Booking status', 'Payment status'], $rows);
        } else {
            $data = (new Reports($db))->rows($from, $to, $group);
            $rows = [];
            foreach ($data['rows'] as $period => $r) {
                $rows[] = [$period, $r['bookings'], Reports::rupees($r['value']), Reports::rupees($r['pending_value']), Reports::rupees($r['revenue']), Reports::rupees($r['refunded']), $r['food_persons']];
            }
            $csv = Reports::csv(['Period', 'Bookings', 'Booked value (INR)', 'Awaiting payment (INR)', 'Verified revenue (INR)', 'Refunded (INR)', 'Food coupon persons'], $rows);
        }
        Container::audit()->log(Container::auth()->actor(), 'export', $type, null, 'export.report', ['from' => $from, 'to' => $to, 'type' => $type]);
        return Response::file($csv, 'text/csv; charset=UTF-8', "report-$type-$from-to-$to.csv");
    }

    public function audit(Request $req): Response
    {
        $q = $req->str('q');
        $p = [];
        $sql = 'SELECT a.*, b.booking_no FROM audit_log a LEFT JOIN bookings b ON b.id = a.booking_id';
        if ($q !== '') {
            $sql .= " WHERE a.actor LIKE ? ESCAPE '!' OR a.action LIKE ? ESCAPE '!' OR b.booking_no LIKE ? ESCAPE '!'";
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $p = [$like, $like, $like];
        }
        return $this->adminView('audit', ['title' => 'Audit log', 'rows' => App::db()->all($sql . ' ORDER BY a.id DESC LIMIT 500', $p), 'q' => $q]);
    }

    // ----------------------------------------------------------------

    private function booking(Request $req): array
    {
        $b = Container::bookings()->findByNo($req->param('no'));
        if (!$b) {
            throw new HttpException(404, 'Booking not found.');
        }
        return $b;
    }

    private function filters(Request $req): array
    {
        $f = [];
        foreach (self::FILTER_KEYS as $k) {
            $f[$k] = mb_substr($req->str($k), 0, 100);
        }
        return $f;
    }

    private function reportRange(Request $req): array
    {
        $from = Validator::date($req->str('from')) ?? date('Y-m-01');
        $to = Validator::date($req->str('to')) ?? today();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ((strtotime($to) - strtotime($from)) > 3 * 366 * 86400) {
            $from = date('Y-m-d', strtotime($to . ' -3 years'));
        }
        $group = in_array($req->str('group'), ['day', 'week', 'month'], true) ? $req->str('group') : 'day';
        return [$from, $to, $group];
    }
}
